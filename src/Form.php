<?php

namespace Gecche\Foorm;

use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Input;
use Exception;
use Illuminate\Support\Facades\Config;

class Form implements FormInterface {

    protected $validationRules = array();
    protected $validator = null;
    protected $errors = null;
    protected $passes = false;
    protected $result = array();
    protected $resultParams = array();
    protected $summary = array();

    protected $translations = array();
    protected $searchParams = array();
    protected $orderParams = array();
    protected $ajaxListingParams = array();

    protected $metadata = array();

    protected $params = array();
    
    protected $models_namespace;

    protected $datafilemodels_namespace;

    public function __construct($params = array()) {

        $this->params = $params;

        $this->errors = new MessageBag();
        try {
            if ($old = Input::old("errors")) {
                $this->errors = $old;
            }
        } catch (Exception $e) {
            $this->errors = new MessageBag();        
        }
        
        $this->models_namespace = Config::get('app.models_namespace','App') . "\\";

        $this->datafilemodels_namespace = Config::get('app.datafilemodels_namespace','App') . "\\";
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param array $params
     */
    public function setParams($params)
    {
        $this->params = $params;
    }

    /**
     * @return array
     */
    public function getSummary()
    {
        return $this->summary;
    }

    /**
     * @param array $summary
     */
    public function setSummary($summary)
    {
        $this->summary = $summary;
    }



    public function isValid($input = null) {
        
        if ($input === null) {
            $input = Input::all();
        }

        $rules = $this->getValidationRulesForSaving();
        $this->validator = Validator::make($input, $rules);

        $this->setDynamicValidationRules();

        $this->passes = $this->validator->passes();

        if (!$this->passes) {
            $errors = $this->validator->errors()->getMessages();
            $errors = array_flatten($errors);
            throw new Exception(json_encode($errors));
        }

        return true;
    }

    public function getValidationRulesForSaving() {
        return $this->getValidationRules();
    }

    public function save($input = null, $validate = true) {
        return true;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function setErrors(MessageBag $errors) {
        $this->errors = $errors;
        return $this;
    }

    public function hasErrors() {
        return $this->errors->any();
    }

    public function getError($key) {
        return $this->getErrors()->first($key);
    }

    public function isPosted() {
        return Input::server("REQUEST_METHOD") == "POST";
    }

    public function setDynamicValidationRules() {
        return true;
    }

    public function setValidationRules() {
        return true;
    }

    public function setCustomValidationRules()
    {
        return;
    }


    public function getValidationRules() {
        return $this->validationRules;
    }

    public function getValidationRule($key) {
        return $this->validationRules[$key];
    }

    public function getValidationRulePrefix($prefix, $remove = true, $separator = '-') {

        $full_prefix = $prefix . $separator;
        $rules = preg_grep_keys('/^' . $full_prefix . '/', $this->validationRules);
        if ($remove) {
            $rules = trim_keys($full_prefix, $rules);
        }
        return $rules;
    }

    public function getResult() {
        return $this->result;
    }

    public function setResult() {
        //$this->result = $this->model->toArray();
        return true;
    }

    public function getResultParams() {
        return $this->resultParams;
    }

    public function setResultParams() {
        return true;
    }

    /**
     * @return array
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @param array $metadata
     */
    public function setMetadata()
    {
        return true;
    }



    public function getTranslations() {
        return $this->translations;
    }

    public function getSearchParams() {
        return $this->searchParams;
    }

    public function setSearchParams() {
        return true;
    }

    public function buildSearchFilter($key, $value, $op = '=', $builder = null) {
        return $builder;
    }

    public function buildOrder($key, $direction, $builder = null) {
        return $builder;
    }

    public function ajaxListing($listModelName, $key, $pk = 0, $fieldValues = array(), $actionParams = array()) {

        $ajaxListingParams = array_get($this->ajaxListingParams, $key, array());
        
        $listModelName = array_get($ajaxListingParams, 'model', $listModelName);
        
        if (!$listModelName) {
            throw new Exception('List model name missing');
        }
        
        $labelColumns = array_get($actionParams, 'description', null);
        $separator = array_get($actionParams, 'separator', null);

        //$model = new static();

        $listModel = new $listModelName();

        if (is_string($fieldValues)) {
            $fieldValues = array($fieldValues => $fieldValues);
        }

        if ($labelColumns === null) {
            $labelColumns = array_get($ajaxListingParams, 'labels', $listModel->getColumnsForSelectList());
        }
        if (is_string($labelColumns)) {
            $labelColumns = array($labelColumns);
        }

        if ($separator === null) {
            $separator = array_get($ajaxListingParams, 'separator', $listModel->getFieldsSeparator());
        }


        $listModelBuilder = $listModel;
        $ajaxListingParamsDB = array_get($ajaxListingParams, 'db', array());
        foreach ($fieldValues as $field => $value) {
            $dbField = array_get($ajaxListingParamsDB, $field, array());
            $listModelBuilder = $listModelBuilder->where(array_get($dbField,'dbField',$field), array_get($dbField,'operator','='), $value);
        }



        $listingResult = $listModelBuilder->get();
        $listingItems = $this->setListingItem($listingResult, $listModel, $labelColumns, $separator, $actionParams);

        return $listingItems;
    }

    public function setListingItem($result, $listModel, $labelColumns, $separator, $actionParams) {

        $item_all = array_get($actionParams, env('FORM_FILTER_ALL',-99), false);
        $item_none = array_get($actionParams, env('FORM_ITEM_NONE',-99), false);


        $ids = $result->lists($listModel->getKeyName())->all();
        ////
        $labels = $result->map(function ($item) use ($labelColumns, $separator) {
            $labelValue = '';
            foreach ($labelColumns as $column) {
                $labelValue .= $separator . $item->$column;
            }
            $labelValue = trim($labelValue, $separator);

            return $labelValue;
        });

        $labelsArray = $labels->toArray();

        if ($item_all) {
            $labelsArray = array('All') + $labelsArray;
            $ids = array(env('FORM_FILTER_ALL',-99)) + $ids;
        } else {
            if ($item_none) {
                $labelsArray = array('None') + $labelsArray;
                $ids = array(env('FORM_ITEM_NONE',-99)) + $ids;
            }
        }

        $result = array();
        foreach ($ids as $key => $id) {
            $result[] = array("key" => $id, "value" => $labelsArray[$key]);
        }

        return $result;
    }

    public function __call($name, $arguments) {
        
        if (method_exists($this, $name)) {
            call_user_func_array(array($this, $name), $arguments);
            return;
        }

        $aliases = array(
            'saveRelatedHasOneStandard' => 'saveRelatedHasManyStandard',
        );

        if (in_array($name,array_keys($aliases))) {
            call_user_func_array(array($this, $aliases[$name]), $arguments);
            return;
        }
        
        $hasManyPrefix = 'saveRelated';
        if (starts_with($name, $hasManyPrefix) && is_array($arguments)) {

            $suffix = studly_case($arguments[0]);
            if (in_array($suffix,['MorphManyAdd'])) {
                return;
            }

            $newMethod = $hasManyPrefix . $suffix;
            unset($arguments[0]);
            return call_user_func_array(array($this, $newMethod), $arguments);
        }
        
        $prefixes = array('buildSearchFilterRelation','buildSearchFilter', 'buildOrder', 'ajaxListing');

        foreach ($prefixes as $prefix) {
            if (starts_with($name, $prefix)) {
                return call_user_func_array(array($this, $prefix), $arguments);
            }
        }
        throw new \BadMethodCallException("Method [$name] does not exist.");
    }
    
    
    

}
