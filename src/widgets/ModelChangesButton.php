<?php

namespace nhkey\arh\widgets;

use Yii;
use yii\base\InvalidConfigException;
use yii\bootstrap\Modal;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\HeadersAlreadySentException;
use yii\web\View;

class ModelChangesButton extends \yii\base\Widget
{
    /**
     * The class (included namespace) of which to display the changes
     * @var string
     */
    public $modelClass;
    /**
     * Primary key/keys of the class of which to display the changes
     * @var integer|array
     */
    public $primaryKey;

    public $buttonOptions = [];

    public $modalHeader;

    /**
     * @return string|void
     */
    public function run()
    {
        if(empty($this->modelClass)) {
            throw new InvalidConfigException("The modelClass property is mandatory");
        }
        if(empty($this->primaryKey)) {
            $this->primaryKey = -1;
        }
        if(is_null($this->modalHeader)) {
            $this->modalHeader = Yii::t('arh', 'Changes history');
        }
        $this->addModal();
        parent::run();
    }

    /**
     * Add a modal to the view and loads the content via Ajax on page load.
     */
    public function addModal()
    {
        $buttonDefaultOptions = [
            'class' => 'btn btn-primary',
            'label' => Yii::t('arh', 'Changes history')
        ];

        $modal = Modal::begin([
            'header' => $this->modalHeader,
            'headerOptions' => ['class' => 'modal-header'],
            'options' => [
                'id' => $this->id,
                'tabindex' => false // important for Select2 to work properly
            ],
            //keeps from closing modal with esc key
            'clientOptions' => ['keyboard' => FALSE],
            'size' => Modal::SIZE_LARGE,
            'toggleButton' => array_merge($buttonDefaultOptions, $this->buttonOptions),
        ]);
        Modal::end();

        $url = Url::to(['//arh/active-record-history/model-changes']);
        $modelClass = Json::encode($this->modelClass);
        $primaryKey = Json::encode($this->primaryKey);

        // Js to automatically load the content of the modal after loading the page
        $this->view->registerJs(<<<JS
            $('#$this->id .modal-body').load('$url', { modelClass: $modelClass, primaryKey: $primaryKey });
JS
        , View::POS_LOAD); // We launch the function after load so it won't slow down the actual loading of the page
    }
}