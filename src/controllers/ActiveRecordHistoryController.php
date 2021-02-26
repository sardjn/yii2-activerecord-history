<?php

namespace nhkey\arh\controllers;

use Yii;
use yii\data\ArrayDataProvider;
use yii\db\ActiveRecord;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

class ActiveRecordHistoryController extends Controller
{
    public $module;
    public $enableCsrfValidation = false;

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'roles' => $this->module->allowedPermissions,
                        'allow' => true,
                    ],
                ],
            ],
        ];
    }

    public function init()
    {
        $this->module = Yii::$app->getModule("arh");
        parent::init();
    }

    public function actionModelChanges()
    {
        $modelClass = Yii::$app->request->post('modelClass');
        $primaryKey = Yii::$app->request->post('primaryKey');
        $models = $this->findModels($modelClass, $primaryKey);
        $changes = [];
        foreach ($models as $model) {
            $changes += $model->changes();
        }
        $dataProvider = new ArrayDataProvider([
            'models' => $changes,
        ]);
        return $this->renderAjax('model-changes', [
            'dataProvider' => $dataProvider,
            'modelClass' => $modelClass,
        ]);
    }

    /**
     * @param $class
     * @param $field_id
     * @return ActiveRecord
     * @throws NotFoundHttpException
     */
    protected function findModels($class, $field_id)
    {
        return $class::findAll($field_id);
    }
}