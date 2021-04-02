<?php
/**
 * @link http://mikhailmikhalev.ru
 * @author Mikhail Mikhalev
 */

namespace nhkey\arh;

use nhkey\arh\managers\BaseManager;
use Yii;
use yii\db\BaseActiveRecord;
use \yii\base\Behavior;


class ActiveRecordHistoryBehavior extends Behavior
{

    /**
     * @var BaseManager This is manager for save history in some storage
     * Default value: DBManager.
     */
    public $manager ='nhkey\arh\managers\DBManager';

    /**
     * @var array This fields don't save in your storage
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
                break;

            case BaseActiveRecord::EVENT_AFTER_DELETE:
                $type = $manager::AR_DELETE;
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
}
