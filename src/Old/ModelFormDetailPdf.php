<?php

namespace Gecche\Foorm\Old;

use Cupparis\Acl\Facades\Acl;
use Cupparis\Ardent\Ardent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;

class ModelFormDetailPdf extends ModelFormDetail {


    public function __construct(Ardent $model, $permissionPrefix = null, $params = array()) {

        ModelForm::__construct($model, $permissionPrefix, $params);

        $this->setHasManies();
        $this->setBelongsTos();
        $this->setResult();

    }

    public function setResult() {


        $this->setModelResult();
        /*
         * Performs custom model data
         */
        $this->customizeResult();

        $modelName = $this->getModelName();
        $pdfType = Arr::get($this->params,'pdfType','default');
        $pdf = $modelName::getPdfExport($this->result, $this->resultParams, $pdfType, ['model' => $this->model]);

        $this->result = $pdf;
    }

}
