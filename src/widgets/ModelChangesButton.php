<?php

namespace nhkey\arh\widgets;

use yii\base\InvalidConfigException;
use yii\bootstrap\Modal;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\View;

class ModelChangesButton extends \yii\base\Widget
{
    public $models;

    public $fieldsConfig;

    public $buttonOptions = [];

    public $modalHeader;

    /**
     * @return string|void
     */
    public function run()
    {
        if(empty($this->models)) {
            throw new InvalidConfigException("The models property is mandatory");
        }
        if(empty($this->fieldsConfig)) {
            throw new InvalidConfigException("The fieldsConfig property is mandatory");
        }
        if(is_null($this->modalHeader)) {
            $this->modalHeader = Yii::t('arh', 'Model changes history');
        }
        parent::run();
        $this->addModal();
        $this->addButton();
    }

    /**
     * Add a modal to the view and loads the content via Ajax on page load.
     */
    public function addModal()
    {
        Modal::begin([
            'header' => $this->modalHeader,
            'headerOptions' => ['class' => 'modal-header'],
            'options' => [
                'id' => 'model-changes-modal',
                'tabindex' => false // important for Select2 to work properly
            ],
            //keeps from closing modal with esc key
            'clientOptions' => ['keyboard' => FALSE],
            'size' => Modal::SIZE_LARGE,
        ]);
        echo "<div class='modal-content'><i class=\"fa fa-refresh fa-spin\"></i></div>";
        Modal::end();

        $url = Url::to(['//arh/active-record-history/model-changes']);
        $jsonData = Json::encode($this->fieldsConfig);
        $this->view->registerJs(<<<JS
            // Cant use $.load() because uses GET 
            $('#model-changes-modal modal-content').load('$url', { fieldsConfig: $jsonData});
JS
        , View::POS_LOAD); // We launch the function after load so it won't slow down the actual loading of the page
    }

    /**
     * Renders the button that shows the modal
     */
    public function addButton()
    {
        $buttonDefaultOptions = [
            'class' => 'btn btn-primary',
            'data' => [
                'toggle' => "modal",
                'target' => "#modal-header",
            ],
        ];
        echo Html::button(Yii::t('arh', 'Changes history'), array_merge($buttonDefaultOptions, $this->buttonOptions));
    }
}