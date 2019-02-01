<?php

namespace Gecche\Foorm\Old;

use Cupparis\Acl\Facades\Acl;
use Cupparis\Ardent\Ardent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;

class ModelFormList extends ModelForm {

    protected $paginateNumber = null;
    protected $paginateSelect = array('*');
    protected $permissionPrefix = null;

    protected $summaryParams = [];
    protected $summaryResult = null;


    public function __construct(Ardent $model, $permissionPrefix = 'LIST', $params = array()) {

        parent::__construct($model, $permissionPrefix, $params);


        //PAGINAZIONE
        $modelRelativeName = snake_case($this->getModelRelativeName());
        $formKey = 'forms.'.$modelRelativeName;
        // valore default del paginate number
        $values = Config::get($formKey,[]);

        $per_page = array_get($values,'per_page',false);
        $pagination_steps = array_get($values,'pagination_steps',[]);
        if ($per_page !== false) {
            $this->paginateNumber = $per_page;
        } else {
            if (count($pagination_steps) > 0) {
                $this->paginateNumber = current($pagination_steps);
            } else {
                $this->paginateNumber = Config::get('forms.paginateNumber',5);
            }
        }

        //TOTALI
        $summary = array_get($values,'summary',[]);

        foreach ($summary as $summaryFieldKey => $summaryFieldValue) {

            if (!is_array($summaryFieldValue)) {
                $summaryFieldValue = [$summaryFieldValue];
            }

            $this->summaryParams[$summaryFieldKey] = $summaryFieldValue;
        }

        //PERMESSI
        $this->permissionPrefix = $permissionPrefix;

        $this->setHasManies();
        $this->setBelongsTos();
        if (array_get($params,'buildList',true)) {
            $this->setResult();
            $this->setResultParams();
            $this->setSummaryParams();
        }
        $this->setMetadata();
        $this->setValidationRules();
        if (Config::get('app.translations_mode') != 'file') {
            $this->setTranslations();
        }
    }

    public function setResult() {

        $this->setListResult();

        $this->setSearchFilters();

        $this->setOrderResult();

//        Log::info($this->result->toSql());
//        Log::info($this->result->getBindings());
        $this->paginateResult();

        /*
         * Performs custom model data
         * per esemèpio usare la funzione "each" sulla eloquent collection
         */
        $this->customizeResult();
       
        $this->result = $this->result->toArray();

        $key = snake_case($this->getModelRelativeName()) . '.pagination_steps';
        //echo $key;
        $values = Config::get('forms.'.$key,[5 => 5,10 => 10,20 => 20,25 => 25]);
        $this->result['pagination_steps'] = $values;
        // in caso di csv setto la variabile error se ci sono errori nel load
        if ($this->permissionPrefix == 'CSV') {
            $countE = DB::table('csv_error')
                ->select(DB::raw('count(*) as total'))
                ->where('csv_id',$this->params['csv_id'])
                ->groupBy('csv_id')
                ->count();
            //echo($countE);
            if ($countE > 0) {
                $this->result['has_errors'] = true;
            } else {
                $this->result['has_errors'] = false;
            }
        }
        if ($this->permissionPrefix == 'DATAFILE') {
            $countE = DB::table('datafile_error')
                ->select(DB::raw('count(*) as total'))
                ->where('datafile_id',$this->params['datafile_id'])
                ->groupBy('datafile_id')
                ->count();
            //echo($countE);
            if ($countE > 0) {
                $this->result['has_errors'] = true;
            } else {
                $this->result['has_errors'] = false;
            }
        }
        return $this->result;
    }

    public function setListResult() {

        switch ($this->permissionPrefix) {

            case 'ARCHIVIO':
               
                $modelClass = $this->modelName;
                $this->result = $modelClass::where('id', '>', 0);
                $this->setContextConstraints();
                break;
            case 'CSV':
                $modelClass = $this->modelName;
                $this->result = $modelClass::where($this->model->getTable().".".$this->model->getCsvIdField(), '=', $this->params['csv_id']);
                if ($this->params['only_errors']) {
                    $this->paginateSelect = array($this->model->getTable().".*");
                    $this->model->setDefaultOrderColumns(array("id" => "ASC"));
                    $this->result = $this->result->join('csv_error',"csv_error.csv_table_id", "=",$this->model->getTable().".id");//->orderBy($this->model->getTable().".id");
                    $this->result = $this->result->groupBy("csv_error.csv_table_id");
                    
                }
                //$user = $user->leftJoin('acl_users_roles','users.id', '=', 'acl_users_roles.user_id');
                break;
            case 'DATAFILE':
                $modelClass = $this->modelName;
                $this->result = $modelClass::where($this->model->getTable().".".$this->model->getDatafileIdField(), '=', $this->params['datafile_id']);
                if ($this->params['only_errors']) {
                    $this->paginateSelect = array($this->model->getTable().".*");
                    $this->model->setDefaultOrderColumns(array("id" => "ASC"));
                    $this->result = $this->result->join('datafile_error',"datafile_error.datafile_table_id", "=",$this->model->getTable().".id");//->orderBy($this->model->getTable().".id");
                    $this->result = $this->result->groupBy("datafile_error.datafile_table_id");

                }
                //$user = $user->leftJoin('acl_users_roles','users.id', '=', 'acl_users_roles.user_id');
                break;
            default:
                $this->result = Acl::query($this->model->select($this->model->getTable().'.*'), $this->permissionPrefix . '_' . $this->modelNamePermission, $this->primary_key_field);
                $this->setContextConstraints();
                break;
        }

        $this->setRelationsListResult();
    }

    protected function setContextConstraints() {
        $constraintKey = array_get($this->params,'constraintKey',false);
        $constraintValue = array_get($this->params,'constraintValue',false);
        if ($constraintKey) {

            $constraintKeyParts = explode('.',$constraintKey);
            if (count($constraintKeyParts) == 1) {
                $this->result = $this->result->where($constraintKey,$constraintValue);
            } else {
                $this->result = $this->result->whereHas($constraintKeyParts[0],function ($query)
                    use ($constraintKeyParts,$constraintValue) {
                        return $query->where($constraintKeyParts[1],$constraintValue);
                });
            }

        }
    }

    public function setRelationsListResult() {
        $relations = $this->relations;

        foreach ($relations as $key => $relation) {

            switch ($relation[0]) {
                case Ardent::BELONGS_TO_MANY:
                case Ardent::HAS_ONE:
                case Ardent::HAS_MANY:
                case Ardent::BELONGS_TO:
                case Ardent::MORPH_MANY:
                    $this->result = $this->result->with($key);
                    break;
                default:
                    break;
            }
        }
        
    }

    public function setOrderResult() {
        if (array_key_exists('sort',Input::all())) {
            $sort = Input::get('sort',[]);
            $order_input = array_get($sort,'field', false);
            $order_direction = array_get($sort,'sort', 'ASC');
        } else {
            $order_input = Input::get('order_field', false);
            $order_direction = Input::get('order_direction', 'ASC');

        }


        if ($order_input) {

            $orderMethod = 'buildOrder' . studly_case($order_input);

            $this->result = $this->$orderMethod($order_input, $order_direction, $this->result);
        } elseif (method_exists($this->model,'defaultOrderMethod')) {
            $this->result = $this->model->defaultOrderMethod($this->result);
        } else {
            foreach ($this->model->getDefaultOrderColumns() as $orderColumn => $orderDirection) {
                $orderColumn = $this->model->getTable() . '.' . $orderColumn;
                $this->result = $this->result->orderBy($orderColumn, $orderDirection);
            }
        }
    }

    public function setSearchFilters($input = null) {

        if (is_null($input)) {
            $input = Input::all();
        }

//        Log::info("INPUT::");
//        Log::info($input);

        //$this->result deve essereun builder!!!
        $searchInputs = preg_grep_keys('/^s_/', $input);
        //dd($searchInputs);
        // ltrim bug in caso di key che inizia con s_s
        $searchInputs = searchform_trim_keys('s_', $searchInputs);
        //dd($searchInputs);
        foreach ($searchInputs as $searchKey => $searchValues) {
            
            if (ends_with($searchKey, '_operator')) {
                continue;
            }
            $searchOp = array_get($input,'s_' . $searchKey . '_operator','=');

            $searchKeyParts = explode('|',$searchKey);
            switch (count($searchKeyParts)) {
                case 1:
                    $searchMethod = 'buildSearchFilter' . studly_case($searchOp);
//                    echo "search Method $searchMethod\n";
                    $this->result = $this->$searchMethod($searchKey, $searchValues, $searchOp, $this->result, $searchInputs);
                    break;
                case 2:
                    $searchMethod = 'buildSearchFilterRelation' . studly_case($searchOp);

                    $this->result = $this->$searchMethod($searchKeyParts[0],$searchKeyParts[1], $searchValues, $searchOp, $this->result, $searchInputs);
                    break;
                default:
                    continue;
            }
            /*
            $searchParams = Input::get('s_' . $searchKey . '_params');

            if (empty($searchParams))
                $searchParams = [];
            */
        }
    }

    public function paginateResult() {

        $paginateNumber = Input::get('paginateNumber') ? Input::get('paginateNumber') : $this->paginateNumber;

        $paginationArray = Input::get('pagination',[]);
        $pageNumber = array_get($paginationArray,'page');
        $paginateNumber = array_get($paginationArray,'perpage',$paginateNumber);
        if ($paginateNumber < 0) {
            $this->summaryResult = $this->result;
            $this->result = $this->result->paginate(100000, $this->paginateSelect,'page',$pageNumber);
        } else {

            $this->summaryResult = $this->result;
            $this->result = $this->result->paginate($paginateNumber, $this->paginateSelect,'page',$pageNumber);

        }
    }

    public function setResultParams() {

        $cacheKey = 'resultParams.list.'.$this->modelRelativeName;
//        echo "CacheKey::$cacheKey::\n";

        if (!app()->environment('local') && Config::get('app.cacheform') && Cache::has($cacheKey)) {
            $this->resultParams = Cache::get($cacheKey);
            return;
        }

        $this->resultParams = $this->db_methods->listColumnsDefault($this->model->getTable());

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
        
        $order_input = Input::get('order_field', false);
        if ($order_input) {
            $this->resultParams['order_field'] = $order_input;
				$this->resultParams['order_direction'] = Input::get('order_direction', 'ASC');
        } else {
            $order = $this->model->getDefaultOrderColumns();
            $orderColumns = array_keys($order);
            $orderDirections = array_values($order);
            $this->resultParams['order_field'] = array_get($orderColumns, 0, 'id');
            $this->resultParams['order_direction'] = array_get($orderDirections, 0, 'ASC');
        }

        Cache::forever($cacheKey,$this->resultParams);


    }

    protected function setSummaryParams() {

        foreach ($this->summaryParams as $fieldKey => $fieldOperators) {

            $this->summary[$fieldKey] = [];
//            $this->resultParams[$fieldKey]['summaryVideo'] = [];
            foreach ($fieldOperators as $operator) {

                $value = $this->summaryResult->$operator($this->model->getTable().'.'.$fieldKey);
//                $valueVideo = $this->result->$operator($this->model->getTable().'.'.$fieldKey);

//                $this->resultParams[$fieldKey]['summaryVideo'][$operator] = $valueVideo;
                $this->summary[$fieldKey][$operator] = $value;
            }
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


}
