<?php

namespace nhkey\arh\controllers\api\v1;

use Yii;
use yii\rest\Controller;
use nhkey\arh\managers\BaseManager;
use nhkey\arh\managers\ActiveRecordHistoryInterface;
use yetopen\helpers\ArrayHelper;
use yii\web\NotFoundHttpException;
use yii\db\ActiveRecord;
use nhkey\arh\Module;
use yii\filters\Cors;

class ActiveRecordHistoryController extends Controller 
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        return array_merge(
            [
                'corsFilter' => [
                    'class' => Cors::class,
                ],
            ],
            $behaviors,
        );
    }

    public function actionModelChanges($model, $pk)
    {
        /** @var Module $module */
        $module = Yii::$app->getModule('arh');
        $class = ArrayHelper::getValue($module->restClassMappings, $model);
        $models = $this->findModels($class, explode(',', $pk));
        $changes = [];
        foreach ($models as $model) {
            $changes = array_merge($changes, $model->changes());
        }
        usort($changes, function ($a, $b) {
            return $a['id'] < $b['id'] ? -1 : 1;
        });

        $userClass = $module->userClass;
        $instance = $class::instantiate([]);
        $fieldsConfig = $instance->getBehavior('history')->historyDisplayConfig;

        $valuesFormatting = [

            'field_name' => [
                'value' => function ($model) use ($instance) {
                    return $instance->getAttributeLabel($model['field_name']);
                },
            ],
            'old_value' => [
                'value' => function ($model) use ($fieldsConfig) {
                    return BaseManager::applyFormat($model, $fieldsConfig, true);
                },
                'format' => 'html',
            ],
            'new_value' => [
                'value' => function ($model) use ($fieldsConfig) {
                    return BaseManager::applyFormat($model, $fieldsConfig);
                },
                'format' => 'html',
            ],
            'type' => [
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
            ],
            'user_id' => [
                'value' => function($model) use ($userClass) {
                    return $userClass::findOne($model['user_id']);
                },
            ],
        ];

        return array_map(function($change) use ($valuesFormatting, $fieldsConfig, $class) {
            foreach ($change as $attribute => &$value) {
                if ($attribute === "field_id") {
                    $change["description"] = (string)$class::findOne($change['field_id']);
                }
                if (!isset($valuesFormatting[$attribute])) {
                    continue;
                }
                $formatter = $valuesFormatting[$attribute];
                if (isset($formatter['value'])) {
                    $value = $formatter['value']($change);
                }
                if (isset($formatter['format'])) {
                    $value = Yii::$app->formatter->format($value, $formatter['format']);
                }
            }

            if (isset($fieldsConfig['model'])) {
                $change["model"] = $fieldsConfig['model'];
            } else {
                $change["model"] = $class;
            }
            return $change;
        }, $changes);
    }

    /**
     * @return ActiveRecord
     * @throws NotFoundHttpException
     */
    private function findModels($class, $field_id)
    {
        return $class::findAll($field_id);
    }
}
