<?php

use nhkey\arh\managers\BaseManager;
use yii\grid\GridView;
use nhkey\arh\managers\ActiveRecordHistoryInterface;

$module = Yii::$app->getModule('arh');
$userClass = $module->userClass;
$instance = $modelClass::instantiate([]);
$fieldsConfig = $instance->getBehavior('history')->historyDisplayConfig;
$columns = [

    'id' => [
        'attribute' => 'id',
        'label' => Yii::t('arh', 'Id'),
        'visible' => YII_DEBUG,
    ],
    'date' => [
        'attribute' => 'date',
        'format' => 'datetime',
        'label' => Yii::t('arh', 'Date'),
    ],
    'field_id' => [
        'attribute' => 'field_id',
        'value' => function ($model) use ($modelClass) {
            return $modelClass::findOne($model['field_id']);
        },
        'label' => Yii::t('arh', 'Description'),
    ],
    'field_name' => [
        'attribute' => 'field_name',
        'value' => function ($model) use ($instance) {
            return $instance->getAttributeLabel($model['field_name']);
        },
        'label' => Yii::t('arh', 'Field Name'),
    ],
    'old_value' => [
        'attribute' => 'old_value',
        'value' => function ($model) use ($fieldsConfig) {
            return BaseManager::applyFormat($model, $fieldsConfig, true);
        },
        'format' => 'html',
        'label' => Yii::t('arh', 'Old Value'),
    ],
    'new_value' => [
        'attribute' => 'new_value',
        'value' => function ($model) use ($fieldsConfig) {
            return BaseManager::applyFormat($model, $fieldsConfig);
        },
        'format' => 'html',
        'label' => Yii::t('arh', 'New Value'),
    ],
    'type' => [
        'attribute' => 'type',
        'value' => function($model) {
            switch ($model['type']) {
                case ActiveRecordHistoryInterface::AR_INSERT:
                    return Yii::t('arh', 'Insert');
                case ActiveRecordHistoryInterface::AR_UPDATE:
                    return Yii::t('arh', 'Update');
                case ActiveRecordHistoryInterface::AR_DELETE:
                    return Yii::t('arh', 'Delete');
                case ActiveRecordHistoryInterface::AR_UPDATE_PK:
                    return Yii::t('arh', 'Update ID');
            }
        },
        'label' => Yii::t('arh', 'Type'),
    ],
    'user_id' => [
        'attribute' => 'user_id',
        'value' => function($model) use ($userClass) {
            return $userClass::findOne($model['user_id']);
        },
        'label' => Yii::t('arh', 'User'),
    ],
    'action' => [
        'attribute' => 'action',
        'label' => Yii::t('arh', 'Action'),
    ],
];

if($module->changesHistoryColumns) {
    $columns = \yetopen\helpers\ArrayHelper::filter($columns, $module->changesHistoryColumns);
}

echo GridView::widget([
    'dataProvider' => $dataProvider,
    'options' => [
        'id' => 'model-changes'
    ],
    'columns' => $columns,
]);