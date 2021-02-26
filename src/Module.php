<?php
/**
 * Created by PhpStorm.
 * User: liviucalin
 * Date: 2019-06-13
 * Time: 12:59
 */

namespace nhkey\arh;

use nhkey\arh\widgets\ModelChangesButton;
use yii\base\Module AS BaseModule;


class Module extends BaseModule
{
    public $controllerNamespace = 'nhkey\arh\controllers';

    public $allowedPermissions = [];

    public $userClass;

    /**
     * The columns to display in the model changes page displayed by @see ModelChangesButton widget, if empty defaults
     * to all.
     * @var array
     */
    public $changesHistoryColumns;

    public function init()
    {
        $class = get_class($this);
        $reflector = new \ReflectionClass($class);
        $dir = dirname($reflector->getFileName());
        \Yii::setAlias("@arh", $dir);

        if (empty(\Yii::$app->i18n->translations['arh'])) {
            \Yii::$app->i18n->translations['arh'] = [
                'class' => 'yii\i18n\PhpMessageSource',
                'basePath' => '@arh/messages',
                'fileMap' => [
                    'arh' => 'arh.php',
                ],
            ];
        }
        parent::init();
    }
}