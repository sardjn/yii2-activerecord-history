<?php

use yii\grid\GridView;

$userClass = Yii::$app->getModule('arh')->userClass;

echo GridView::widget([
    'dataProvider' => $dataProvider,
    'options' => [
        'id' => 'model-changes'
    ],
    'columns' => [
        'date:datetime',
        'field_name',
        'old_value',
        'new_value',
        'type',
        [
            'attribute' => 'user_id',
            'value' => function($model) use ($userClass) {
                return $userClass::findOne($model['user_id']);
            },
        ],
        'action',
    ],
]);