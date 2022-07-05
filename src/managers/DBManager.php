<?php
/**
 * @link http://mikhailmikhalev.ru
 * @author Mikhail Mikhalev
 */

namespace nhkey\arh\managers;

use yii\db\Expression;
use const SORT_DESC;
use Yii;
use yii\db\ActiveQuery;
use yii\db\Connection;
use yii\db\Query;
use yii\di\Instance;


/**
 * Class DBManager for save history in DB
 * @package nhkey\arh
 */
class DBManager extends BaseManager
{
    /**
     * @var string static default for migration
     */
    public static $defaultTableName = '{{%modelhistory}}';

    /**
     * @var string tableName
     */
    public $tableName;

    /**
     * @var string DB
     */
    public static $db = 'db';

    public function __construct()
    {
        $this->tableName = isset($this->tableName) ? $this->tableName : $this::$defaultTableName;
    }

    /**
     * @inheritdoc
     */
    public function saveField($data)
    {
        self::getDB()->createCommand()
            ->insert($this->tableName, $data)->execute();
    }

    /**
     * @inheritdoc
     */
    public function getRecordValueAt($object, $attribute, $date)
    {
        // We assume that for future dates the value will stay the same since we have no way to know it
        if($date >= date('Y-m-d H:i:s')) {
            return $object->getAttribute($attribute);
        }
        $query = $this->prepareBaseQuery();
        // Getting the date-time in which the record was created
        $insertDate = (clone $query)
            ->select('date')
            ->andWhere([
                'table' => $object->tableName(),
                'field_id' => new Expression("'{$object->getPrimaryKey()}'"),
                'type' => static::AR_INSERT,
            ])
            ->scalar();
        // The record was inserted after the specified date, we return `null`
        if($insertDate > $date) {
            return null;
        }
        // Searching for the first history record for the attribute and active record with date prior to the given one.
        // We order by descendent ID so that it will be returned the most recent.
        $query->select('new_value')
            ->andWhere([
                'table' => $object->tableName(),
                'field_id' => new Expression("'{$object->getPrimaryKey()}'"),
                'field_name' => $attribute,
            ])
            ->andWhere(['<=', 'date', $date])
            ->orderBy(['id' => SORT_DESC]);
        // If no record was found, it means that the value was never changed, in that case we return the current one.
        return $query->scalar() ?: $object->getAttribute($attribute);
    }

    /**
     * Query for data record according to parameters
     * @param array $filter
     * @param array $order
     * @return array|false
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     */
    protected function getField(array $filter, array $order)
    {
        return $this->prepareQuery($filter, $order)->queryOne();
    }

    /**
     * Query for data records according to parameters
     * @param array $filter
     * @param array $order
     * @return array|false
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     */
    protected function getFields(array $filter, array $order)
    {
        return $this->prepareQuery($filter, $order)->queryAll();
    }

    /**
     * @return Query
     */
    private function prepareBaseQuery()
    {
        return (new Query())->from($this->tableName);
    }

    /**
     * @param array $filter
     * @param array $order
     * @return \yii\db\Command
     * @throws \yii\base\InvalidConfigException
     */
    private function prepareQuery(array $filter, array $order)
    {
        $query = $this->prepareBaseQuery();
        $query->select('*')->andWhere($filter)->orderBy($order);
        return $query->createCommand(self::getDB());
    }

    /**
     * @return Connection Return database connection
     * @throws \yii\base\InvalidConfigException
     */
    private static function getDB()
    {
        return Instance::ensure(self::$db, Connection::className());
    }
}
