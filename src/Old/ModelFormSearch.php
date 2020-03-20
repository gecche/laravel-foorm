<?php

namespace Gecche\Foorm\Old;

use Cupparis\Acl\Facades\Acl;
use Cupparis\Ardent\Ardent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;

class ModelFormSearch extends ModelForm {

    protected $paginateNumber = 5;
    protected $paginateSelect = array('*');
    protected $permissionPrefix = null;

    public function __construct(Ardent $model, $permissionPrefix = 'LIST', $params = array()) {

        parent::__construct($model, $permissionPrefix, $params);

        $this->permissionPrefix = $permissionPrefix;

        $this->setHasManies();
        $this->setBelongsTos();
        $this->setResult();
        $this->setResultParams();
        $this->setMetadata();
        $this->setValidationRules();

        if (Config::get('app.translations_mode') != 'file') {
            $this->setTranslations();
        }
    }

    protected function setContextConstraints() {
        if (Arr::get($this->params,'constraintKey',false)) {
            $this->result = $this->result->where($this->params['constraintKey'],$this->params['constraintValue']);
        }
    }


    protected function setFilterAllForEnums() {
        foreach ($this->resultParams as $key => $value) {
            if (array_key_exists('options',$value) && Arr::get($this->enumsConfig,$key,$this->enumsConfig['default'])) {
                $options = [env('FORM_FILTER_ALL',-99) => trans_uc('app.any')] + $this->resultParams[$key]['options'];
                $this->resultParams[$key]['options'] = $options;
                $this->resultParams[$key]['options_order'] = array_keys($options);
            }
        }
    }

     public function setResultParams() {

         $cacheKey = 'resultParams.search.'.$this->modelRelativeName;
//        echo "CacheKey::$cacheKey::\n";

         if (!app()->environment('local') && Config::get('app.cacheform') && Cache::has($cacheKey)) {
             $this->resultParams = Cache::get($cacheKey);
         } else {


             $this->resultParams = $this->db_methods->listColumnsDefault($this->model->getTable());
             $this->setFilterAllForEnums();

             $this->setResultParamsAppendsDefaults();
             $this->setResultParamsDefaults();

             foreach (array_keys($this->result) as $resultField) {

                 if ($resultField == 'data') {
                     $data = $this->result[$resultField];
                     if (is_array($data) && !empty($data)) {
                         $firstData = current($data);
                         foreach (array_keys($firstData) as $resultDataField) {
                             $this->createResultParamItem($resultDataField);
                         }
                     }
                     continue;
                 }

                 $this->createResultParamItem($resultField);
             }


             $fieldParamsFromModel = $this->model->getFieldParams();
             $modelFieldsParamsFromModel = array_intersect_key($fieldParamsFromModel, $this->resultParams);

             $this->resultParams = array_replace_recursive($this->resultParams, $modelFieldsParamsFromModel);

             $this->setResultParamsSearchRelation();


             foreach ($this->hasManies as $key => $value) {
                 $modelName = $value['modelName'];
                 $model = new $modelName;
                 $value["fields"] = $this->db_methods->listColumnsDefault($model->getTable());

                 $this->resultParams[$key] = $value;
             }

             foreach ($this->belongsTos as $key => $value) {
                 $modelName = $value['modelName'];
                 $forSelectListConfig = Arr::get($this->forSelectListConfig, $key,
                     $this->forSelectListConfig['default']);
                 if (!Arr::get($forSelectListConfig['params'],'permissionPrefix')) {
                     $forSelectListConfig['params']['permissionPrefix'] = $this->permissionPrefix;
                 }

                 $options = $modelName::getForSelectList($forSelectListConfig['columns'],
                     $forSelectListConfig['separator'], $forSelectListConfig['params']);
                 $value['options'] = $options;
                 $value['options_order'] = array_keys($options);
                 $this->resultParams[$key] = $value;
             }
         }

         Cache::forever($cacheKey,$this->resultParams);

         $order_input = Input::get('order_field', false);
         if ($order_input) {
             $this->resultParams['order_field'] = $order_input;
             $this->resultParams['order_direction'] = Input::get('order_direction', 'ASC');
         } else {
             $order = $this->model->getDefaultOrderColumns();
             $orderColumns = array_keys($order);
             $orderDirections = array_values($order);
             $this->resultParams['order_field'] = Arr::get($orderColumns, 0, 'id');
             $this->resultParams['order_direction'] = Arr::get($orderDirections, 0, 'ASC');
         }


     }

    public function setMetadata()
    {
        $resultParamsKeys = array_keys($this->resultParams);
        $resultKeys = array_keys($this->result);

        $this->metadata = $this->resultParams;


        foreach ($resultKeys as $key) {
           unset($this->metadata[$key]);
        }
        unset($this->metadata['order_field']);
        unset($this->metadata['order_direction']);

    }

    protected function setResultParamsSearchRelation() {

        foreach ($this->model->getFieldParams() as $key => $param) {

            $searchKeyParts = explode('|',$key);
            switch (count($searchKeyParts)) {
                case 2:
                    $searchField = $searchKeyParts[1];
                    $relationModelData = $this->getRelationDataFromModel($searchKeyParts[0]);

                    if (count($relationModelData) < 1) {
                        continue;
                    }

                    $relationModelName = $relationModelData['modelName'];
                    $relationModel = new $relationModelName;

                    if (!isset($this->resultParams[$key])) {
                       $this->resultParams[$key] = $this->db_methods->listColumnsDefault($relationModel->getTable(), null, true, false, $searchField);
                    }

                    if ($relationModel->getKeyName() == $searchField) {

                        $forSelectListConfig = Arr::get($this->forSelectListConfig,$key,$this->forSelectListConfig['default']);
                        $options = $relationModelName::getForSelectList($forSelectListConfig['columns'],$forSelectListConfig['separator'],$forSelectListConfig['params']);
                        $this->resultParams[$key]['options'] = $options;
                        $this->resultParams[$key]['options_order'] = array_keys($options);

                    }

                    break;
                default:
                    continue;
            }

        }

    }



}
