<?php

namespace nhkey\arh\controllers\api\v1;

use nhkey\arh\managers\ActiveRecordHistoryInterface;
use nhkey\arh\managers\BaseManager;
use nhkey\arh\Module;
use yetopen\helpers\ArrayHelper;
use Yii;
use yii\db\ActiveRecord;
use yii\filters\Cors;
use yii\rest\Controller;
use yii\web\NotFoundHttpException;

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
        ];

        $changesWithJson = [];
        foreach ($changes as $record) {
            $oldJson = json_decode($record['old_value'], true);
            $newJson = json_decode($record['new_value'], true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($newJson)) {
                $changesWithJson[] = $record;
                continue;
            }
            if (!is_array($oldJson)) {
                $oldJson = [];
            }
            foreach ($newJson as $key => $newValue) {
                $oldValue = $oldJson[$key] ?? null;
                if ($oldValue != $newValue) {
                    $datum = [
                        "id" => $record['id'],
                        "date" => $record['date'],
                        "table" => $record['table'],
                        "field_name" => $key,
                        "field_id" => $record['field_id'],
                        "old_value" => $oldValue ?: null,
                        "new_value" => $newValue ?: null,
                        "type" => $record['type'],
                        "user_id" => $record['user_id'],
                        "action" => $record['action'],
                    ];
                    $changesWithJson[] = $datum;
                }
            }
        }

        return array_map(function($change) use ($valuesFormatting, $fieldsConfig, $class, $userClass) {
            foreach ($change as $attribute => &$value) {
                if ($attribute === "field_id") {
                    $change["description"] = (string)$class::findOne($change['field_id']);
                }
                if ($attribute === "user_id") {
                    $change["user"] = (string)$userClass::findOne($change['user_id']);
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
        }, $changesWithJson);
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
