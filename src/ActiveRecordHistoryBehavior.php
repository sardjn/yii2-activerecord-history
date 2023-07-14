<?php
/**
 * @link http://mikhailmikhalev.ru
 * @author Mikhail Mikhalev
 */

namespace nhkey\arh;

use nhkey\arh\managers\BaseManager;
use Yii;
use yii\base\InvalidArgumentException;
use yii\db\ActiveRecord;
use yii\db\BaseActiveRecord;
use \yii\base\Behavior;
use yii\helpers\ArrayHelper;

/**
 * @property ActiveRecord $owner
 */
class ActiveRecordHistoryBehavior extends Behavior
{

    /**
     * @var BaseManager This is manager for save history in some storage
     * Default value: DBManager.
     */
    public $manager ='nhkey\arh\managers\DBManager';

    /**
     * @var array This fields not to be saved in the history.
     */
    public $ignoreFields = [];

    /**
     * @var array Events List than saved in storage
     */
    public $eventsList = [BaseManager::AR_INSERT, BaseManager::AR_UPDATE, BaseManager::AR_DELETE, BaseManager::AR_UPDATE_PK];

    /**
     * @var array options for manager
     */
    public $managerOptions;

    /**
     * Other configurations to display the fields values in the changes history, possible keys are:
     * - format
     * - value, expects a Closure which has only one parameter populated with old
     * Example:
     *
     * 'historyDisplayConfig' => [
     *      'idsupplier' => [
     *          'value' => function($value) {
     *              return User::findOne($value)->username;
     *          },
     *      ],
     *      'data_deliverynote' => [
     *          'format' => 'date',
     *      ],
     *  ],
     *
     * @var array
     */
    public $historyDisplayConfig = [];

    /**
     * @var string The key in which informations are stored in session.
     */
    public $sessionKey = 'ar_history_session';

    /**
     * If the ids of the record inserted and updated will be stored in session.
     * It is used in hasBeenModified get method.
     * The ids will be stored in the key set in $sessionKey of this behavior under their formName().
     * @see getHasBeenModified()
     * @var bool
     */
    public $saveUpdatesInSession = false;

    /**
     * @var array Get Yii2 event name from behavior event name
     * @return array|string
     */
    public function getEventName($event){
        $eventNames = [
            BaseManager::AR_INSERT => BaseActiveRecord::EVENT_AFTER_INSERT,
            BaseManager::AR_UPDATE => BaseActiveRecord::EVENT_AFTER_UPDATE,
            BaseManager::AR_DELETE => BaseActiveRecord::EVENT_AFTER_DELETE,
            BaseManager::AR_UPDATE_PK => BaseActiveRecord::EVENT_AFTER_UPDATE,

        ];
        return isset($eventNames[$event]) ? $eventNames[$event] : $eventNames;

    }

    public function events()
    {
        $events = [];
        foreach ($this->eventsList as $event){
            $events[$this->getEventName($event)] = 'saveHistory';
        }
        return $events;
    }

    /**
     * @param Event $event
     * @throws \Exception
     */
    public function saveHistory($event)
    {
        $manager = new $this->manager;
        $manager->setOptions($this->managerOptions);

        switch ($event->name){
            case BaseActiveRecord::EVENT_AFTER_INSERT:
                $type = $manager::AR_INSERT;
                $changedAttributes = $event->changedAttributes;
                foreach ($this->ignoreFields as $ignoreField) {
                    if (array_key_exists($ignoreField, $changedAttributes))
                        unset($changedAttributes[$ignoreField]);
                }
                $manager->setUpdatedFields($changedAttributes);
                if($this->saveUpdatesInSession) {
                    $this->storeInSession($type);
                }
                break;

            case BaseActiveRecord::EVENT_AFTER_UPDATE:

                if (in_array(BaseManager::AR_UPDATE_PK, $this->eventsList) && ($this->owner->getOldPrimaryKey() != $this->owner->getPrimaryKey()))
                    $type = $manager::AR_UPDATE_PK;
                elseif (in_array(BaseManager::AR_UPDATE, $this->eventsList))
                    $type = $manager::AR_UPDATE;
                else
                    return true;

                $changedAttributes = $event->changedAttributes;
                foreach ($this->ignoreFields as $ignoreField)
                    if (array_key_exists($ignoreField, $changedAttributes))
                        unset($changedAttributes[$ignoreField]);

                $manager->setUpdatedFields($changedAttributes);
                if(!empty($changedAttributes) && $this->saveUpdatesInSession) {
                    $this->storeInSession($type);
                }
                break;

            case BaseActiveRecord::EVENT_AFTER_DELETE:
                $type = $manager::AR_DELETE;
                $changedAttributes = $this->owner->attributes;
                foreach ($this->ignoreFields as $ignoreField) {
                    if (array_key_exists($ignoreField, $changedAttributes))
                        unset($changedAttributes[$ignoreField]);
                }
                $manager->setUpdatedFields($changedAttributes);
                break;

            default:
                throw new \Exception('Not found event!');
        }
        $manager->run($type, $this->owner);
    }

    /**
     * Get the record corresponding to last change of an attribute
     * @param $attribute
     * @param bool $format if true tries to format old_value and new_value according to format specified
     * in 'historyDisplayConfig' parameter
     * @return mixed
     */
    public function lastChanged($attribute, $format = false)
    {
        $manager = new $this->manager;
        $manager->setOptions($this->managerOptions);

        $field = $manager->getData($attribute, $this->owner);
        if ($format) {
            $field['old_value'] = BaseManager::applyFormat($field, $this->historyDisplayConfig, true);
            $field['new_value'] = BaseManager::applyFormat($field, $this->historyDisplayConfig);
        }
        return $field;
    }

    /**
     * Get the records corresponding to all changes
     * @param bool $format if true tries to format old_value and new_value according to format specified
     * in 'historyDisplayConfig' parameter
     * @return mixed
     */
    public function changes($format = false)
    {
        $manager = new $this->manager;
        $manager->setOptions($this->managerOptions);

        $fields = $manager->getAllData($this->owner);
        if ($format) {
            foreach ($fields AS $k => $field) {
                $fields[$k]['old_value'] = BaseManager::applyFormat($field, $this->historyDisplayConfig, true);
                $fields[$k]['new_value'] = BaseManager::applyFormat($field, $this->historyDisplayConfig);
            }
        }
        return $fields;
    }
    
    /**
    * Returns the dates in which an attribute is changed, if from_to is set it
    * reports only the related dates in which the attribute is hanged from
    * a specified value to another
    * for example $model->changesDatesAttribute('active", ["1","0"] reports a
    * string with the dates in which the model->active is passed from "1" to "0"
    * @param string $attribute
    * @param array $from_to if set must contain 2 values [old_value, new_value]
    * @param string $sep default <br />
    * @param string $format default "date" (but also datetime can be used)
    * @return string
    */
   public function changesDatesAttribute($attribute, $from_to=[], $sep="<br />", $format="date" )
   {
       $manager = new $this->manager;
       $manager->setOptions($this->managerOptions);
       $fields = $manager->getAllData($this->owner);
       $dates = [];
       foreach ($fields AS $k => $field) {
           if ($field['field_name']===$attribute){
               $data = Yii::$app->formatter->asDate($field['date']);
               if ($format=="datetime"){
                   $data = Yii::$app->formatter->asDatetime($field['date']);
               }
               $add = [$data];
               // if from_to is specified the different changes are ignored
               if (count($from_to)==2 && ($field['old_value']!=$from_to[0] ||  $field['new_value']!=$from_to[1])){
                   $add = [];
               }
              $dates = array_merge($dates, $add);
           }
       }
       return implode($sep, $dates);
   }

    /**
     * @return array|null the infos stored in session.
     */
   protected function getSession()
   {
       return Yii::$app->session->get($this->sessionKey);
   }

    /**
     * @return array The infos stored in session for the model class of the owner.
     * @throws \Exception
     */
   protected function getModelSession()
   {
       return ArrayHelper::getValue($this->getSession(), $this->owner->formName(), []);
   }

    /**
     * Stores the id of the model in session as key and the type of operation as value.
     * @param mixed $type Event types of history to the AR object, as declared in the manager
     * @return void
     */
   protected function storeInSession($type)
   {
       $modelSession = $this->getSession();
       $modelSession[$this->owner->formName()][$this->owner->getPrimaryKey()] = $type;
       Yii::$app->session->set($this->sessionKey, $modelSession);
   }

    /**
     * @return bool|null If the model has been inserted or modified in the current session. If saving in session is
     * disabled it will return `null`.
     * @throws \Exception
     */
   public function getHasBeenModified()
   {
       if(!$this->saveUpdatesInSession) {
           return null;
       }

       $manager = new $this->manager;
       $sessionVal = ArrayHelper::getValue($this->getModelSession(), $this->owner->getPrimaryKey());
       return in_array($sessionVal, [$manager::AR_UPDATE, $manager::AR_UPDATE_PK]);
   }

    /**
     * @return bool|null If the model has been inserted or modified in the current session. If saving in session is
     * disabled it will return `null`.
     * @throws \Exception
     */
    public function getHasBeenInserted()
    {
        if(!$this->saveUpdatesInSession) {
            return null;
        }

        $manager = new $this->manager;
        $type = $manager::AR_INSERT;
        return ArrayHelper::getValue($this->getModelSession(), $this->owner->getPrimaryKey()) == $type;
    }

    /**
     * @param $attribute
     * @param $date string
     * @return string|null
     * @throws \yii\base\ErrorException
     */
    public function valueWas($attribute, $date)
    {
        if(!\DateTime::createFromFormat('Y-m-d H:i:s', $date)) {
            throw new InvalidArgumentException('The date must be passed with format "Y-m-d H:i:s"');
        }
        /** @var BaseManager $manager */
        $manager = new $this->manager;
        return $manager->getRecordValueAt($this->owner, $attribute, $date);
    }
}
