<?php

namespace Gecche\Foorm\Old;

use Cupparis\Acl\Facades\Acl;
use Cupparis\Ardent\Ardent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;

class ModelFormListPdf extends ModelFormList {

    protected $paginateNumber = null;
    protected $paginateSelect = array('*');
    protected $permissionPrefix = null;

    protected $summaryParams = [];
    protected $summaryResult = null;

//    protected $totalCount = null;
//

    public function __construct(Ardent $model, $permissionPrefix = 'LIST', $params = array()) {

        ModelForm::__construct($model, $permissionPrefix, $params);
        $modelRelativeName = snake_case($this->getModelRelativeName());
        $formKey = 'forms.'.$modelRelativeName;
        // valore default del paginate number
        $values = Config::get($formKey,[]);

        $this->permissionPrefix = $permissionPrefix;

        $this->setHasManies();
        $this->setBelongsTos();

        $this->setListResult();
        $this->setSearchFilters(Arr::get($params,'input',null));
        $this->setOrderResult();

//        $this->totalCount = $this->result->count();

    }

//    public function getCount() {
//        return $this->totalCount;
//    }

    public function getChunkResult($page,$perPage = 1000) {
        $skip = ($page - 1) * $perPage;
        $tempResult = $this->result;

        $this->result = $this->result->take($perPage)->skip($skip)->get();
        /*
         * Performs custom model data
         * per esemèpio usare la funzione "each" sulla eloquent collection
         */
        $this->customizeResult();

        $modelName = $this->getModelName();
        $pdfType = Arr::get($this->params,'pdfType','default');
        $params = [
            'headers' => $page == 1 ? true : false,
        ];
        $pdf = $modelName::getPdfExport($this->result, $this->resultParams, $pdfType, $params);

        $this->result = $tempResult;
        return $pdf;

    }

    public function setResult() {


        $this->result = $this->result->get();


        /*
         * Performs custom model data
         * per esemèpio usare la funzione "each" sulla eloquent collection
         */
        $this->customizeResult();

        $this->setResultParams();

        $modelName = $this->getModelName();
        $pdfType = Arr::get($this->params,'pdfType','default');
        $pdf = $modelName::getPdfExport($this->result, $this->resultParams, $pdfType);

        $this->result = $pdf;
    }

    public function setResultParams() {

        $this->resultParams = $this->db_methods->listColumnsDefault($this->model->getTable());

        $this->setResultParamsAppendsDefaults();
        $this->setResultParamsDefaults();

        foreach (array_keys($this->resultParams) as $resultField) {
            $this->createResultParamItem($resultField);
        }

        $fieldParamsFromModel = $this->model->getFieldParams();
        $modelFieldsParamsFromModel = array_intersect_key($fieldParamsFromModel,$this->resultParams);

        $this->resultParams = array_replace_recursive($this->resultParams,$modelFieldsParamsFromModel);

        foreach ($this->hasManies as $key => $value) {
            $modelName = $value['modelName'];
            $model = new $modelName;
            $value["fields"] = $this->db_methods->listColumnsDefault($model->getTable());

            $this->resultParams[$key] = $value;
        }

        foreach ($this->belongsTos as $key => $value) {
            $modelName = $value['modelName'];
            $options = $modelName::getForSelectList();
            $value['options'] = $options;
            $value['options_order'] = array_keys($options);
            $this->resultParams[$key] = $value;
        }
    }


}
