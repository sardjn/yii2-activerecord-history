<?php
/**
 * @link http://mikhailmikhalev.ru
 * @author Mikhail Mikhalev
 */

use nhkey\arh\managers\DBManager;
use yii\db\Migration;

class m250904_191840_add_referrer_column extends Migration
{
    public function up()
    {
        $this->addColumn(DBManager::$defaultTableName, 'referrer', $this->string());
    }

    public function down()
    {
        $this->dropColumn(DBManager::$defaultTableName, 'referrer');
    }
}
