<?php
/**
 * @link http://mikhailmikhalev.ru
 * @author Mikhail Mikhalev
 */

namespace nhkey\arh\managers;

use Yii;
use yii\base\ErrorException;
use yii\db\Expression;
use yii\helpers\Json;


abstract class BaseManager implements ActiveRecordHistoryInterface
{

    /**
     * @var array list of updated fields
     */
    public $updatedFields;

    /**
     * @var boolean Flag for save current user_id in history
     */
    public $saveUserId = true;

    /**
     * @var boolean Flag for save the value of all the fields when new record is insert
     */
    public $saveAllFieldsOnInsert = false;

    /**
     * @inheritdoc
     */
    public function setOptions($options)
    {
        if (is_array($options)) {
            foreach ($options as $optionKey => $optionValue)
                $this->{$optionKey} = $optionValue;
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setUpdatedFields($attributes)
    {
        $this->updatedFields = $attributes;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function run($type, $object)
    {
        $pk = $object->primaryKey();
        $pk = $pk[0];

        $data = [
            'table' => $object->tableName(),
            'field_id' => $object->getPrimaryKey(),
            'type' => $type,
            'date' => date('Y-m-d H:i:s', time()),
            'action' => Yii::$app->requestedRoute,
        ];

        if ($this->saveUserId)
            $data['user_id'] = isset(Yii::$app->user->id) ?  Yii::$app->user->id : '';

        switch ($type) {
            case self::AR_INSERT:
                if ($this->saveAllFieldsOnInsert) {

                } else {
                    $data['field_name'] = $pk;
                    $this->saveField($data);
                    break;
                }
            case self::AR_UPDATE:
                foreach ($this->updatedFields as $updatedFieldKey => $updatedFieldValue) {
                    $data['field_name'] = $updatedFieldKey;
                    $data['old_value'] = $this->encodeValue($updatedFieldValue);
                    $data['new_value'] = $this->encodeValue($object->$updatedFieldKey);
                    if($data['old_value'] != $data['new_value']){
                        $this->saveField($data);
                    }
                }
                break;
            case self::AR_DELETE:
                $data['field_name'] = $pk;
                $this->saveField($data);
                break;
            case self::AR_UPDATE_PK:
                $data['field_name'] = $pk;
                $data['old_value'] = $object->getOldPrimaryKey();
                $data['new_value'] = $object->{$pk};
                $this->saveField($data);
                break;
        }
    }

    /**
     * Find the last data record corresponding to the changes of a specific attribute
     * @param $attribute
     * @param $object
     * @return array
     * @throws ErrorException
     */
    public function getData($attribute, $object)
    {
        $filter = [
            'table' => $object->tableName(),
            'field_id' => new Expression("'{$object->getPrimaryKey()}'"),
            'field_name' => $attribute,
        ];
        $order = ['id' => SORT_DESC];
        return $this->getField($filter, $order);
    }

    /**
     * Find the data records corresponding to the changes
     * @param $object
     * @return array
     * @throws ErrorException
     */
    public function getAllData($object)
    {
        $filter = [
            'table' => $object->tableName(),
            'field_id' => new Expression("'{$object->getPrimaryKey()}'"),
        ];
        $order = ['id' => SORT_DESC, 'type' => SORT_DESC];
        return $this->getFields($filter, $order);
    }

    /**
     *
     * @param $model
     * @param $fieldsConfig format configuration according to 
     * @param false $old_value
     * @return false|mixed|string
     */
    static function applyFormat($model, $fieldsConfig, $old_value = false) {
        $field_name = $model['field_name'];
        $value = $old_value ? $model['old_value'] : $model['new_value'];
        if(isset($fieldsConfig[$field_name]['value'])) {
            $value = call_user_func($fieldsConfig[$field_name]['value'], $value);
        }
        if(isset($fieldsConfig[$field_name]['format'])) {
            return Yii::$app->formatter->format($value, $fieldsConfig[$field_name]['format']);
        }
        return $value;
    }

    /**
     * By default is not able to obtain the data record, the implementation is demanded to each specialization
     * of the manager
     * @param array $filter
     * @param array $order
     * @return array
     * @throws ErrorException
     */
    protected function getField(array $filter, array $order)
    {
       throw new ErrorException("Method not implemented");
    }

    /**
     * By default is not able to obtain the data records, the implementation is demanded to each specialization
     * of the manager
     * @param array $filter
     * @param array $order
     * @return array
     * @throws ErrorException
     */
    protected function getFields(array $filter, array $order)
    {
        throw new ErrorException("Method not implemented");
    }

    /**
     * Encode the value to be written in database
     * @param $value
     * @return string
     */
    protected function encodeValue($value)
    {
        if(!is_array($value)) {
            return $value;
        }
        return Json::encode($value);
    }

}
