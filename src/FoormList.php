<?php

namespace Gecche\Foorm;


use Gecche\DBHelper\Facades\DBHelper;
use Gecche\Breeze\Breeze;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FoormList extends Foorm
{

    use ConstraintBuilderTrait;
    use OrderBuilderTrait;
    use AggregateBuilderTrait;


    /**
     * @var array
     */
    protected $formAggregatesData;

    /**
     * @var Builder|null
     */
    protected $formBuilder;


    protected $listOrder;

    /**
     * @var Builder|null
     */
    protected $formAggregatesBuilder;


    protected $customFuncs = [];



    public function setCustomFunc($type, \Closure $func) {
        $this->customFuncs[$type] = $func;
    }

    /**
     *
     */
    protected function generateListBuilder()
    {


        $this->applylistBuilder();

        $this->applyRelations();

        $this->applyFixedConstraints();

    }


    protected function applyListBuilder()
    {


        if (Arr::get($this->customFuncs,'listBuilder') instanceof \Closure) {
            $builder = $this->customFuncs['listBuilder'];
            $this->formBuilder = $builder($this->model);
            return;
        }

        $modelClass = get_class($this->model);
        $this->formBuilder = $modelClass::query();

    }

    protected function applyRelations()
    {
        foreach (array_keys($this->relations) as $relationName) {
            $this->formBuilder = $this->formBuilder->with($relationName);
        }
    }

    protected function applyFixedConstraints()
    {
        $fixedConstraints = Arr::get($this->params, 'fixed_constraints', []);

        foreach ($fixedConstraints as $fixedConstraint) {
            $this->applyConstraint($fixedConstraint);
        }
    }

    protected function applyConstraint(array $constraintArray)
    {


        $field = Arr::get($constraintArray, 'field', null);
        if (!$field || !is_string($field) || !array_key_exists('value', $constraintArray)) {
            return $this->formBuilder;
        };

        $value = $constraintArray['value'];
        $op = Arr::get($constraintArray, 'op', '=');
        $params = Arr::get($constraintArray, 'params', []);

        $studlyField = Str::studly($field);

        $methodName = 'buildSearchFilterField' . $studlyField;

        //Se esiste il metodo specifico SUL CAMPO lo chiamo
        if (method_exists($this, $methodName)) {
            return $this->$methodName($this->formBuilder, $op, $value, $params);
        }


        $modelRelations = $this->model->getRelationsData();

        $relation = null;
        $isRelation = false;
        $fieldExploded = explode('|', $field);
        $db = null;
        $table = null;


        if (array_key_exists($field,$modelRelations)) {
            return $this->formBuilder = $this->buildConstraint($this->formBuilder, $field, $value, $op, $params);
        }

        if (count($fieldExploded) > 1) {
            $isRelation = true;
            $relation = $fieldExploded[0];
            unset($fieldExploded[0]);
            $field = implode('.', $fieldExploded);

            $relationData = Arr::get($modelRelations, $relation, []);
            if (!array_key_exists('related', $relationData)) {
                return $this->formBuilder;
            }

            $relationModelName = $relationData['related'];
            $relationModel = new $relationModelName;
            $table = Arr::get($constraintArray, 'table', $relationModel->getTable());
            $db = Arr::get($constraintArray, 'db',config('database.connections.' . $relationModel->getConnectionName() . '.database'));

        } else {
            $table = Arr::get($constraintArray, 'table', $this->model->getTable());
            $db = Arr::get($constraintArray, 'db');
        }


        $dbField = $db ? $db . '.' : '';
        $dbField .= $table ? $table . '.' : '';
        $dbField .= $field;

        if ($isRelation) {
            return $this->formBuilder = $this->buildConstraintRelation($relation, $this->formBuilder, $dbField, $value, $op, $params);

        }

        return $this->formBuilder = $this->buildConstraint($this->formBuilder, $dbField, $value, $op, $params);

    }




    protected function applySearchFilters()
    {
        $searchFilters = $this->buildSearchFilters();

        foreach ($searchFilters as $searchFilter) {
            $this->applyConstraint($searchFilter);
        }
    }

    protected function buildSearchFilters() {

        $inputSearchFilters = Arr::get($this->input, 'search_filters', []);

        $dependentSearchForm = Arr::get($this->dependentForms,'search');

        if (!$dependentSearchForm) {
            return $inputSearchFilters;
        }

        return $this->buildSearchFiltersFromDependencies($inputSearchFilters,$dependentSearchForm);

    }

    protected function buildSearchFiltersFromDependencies($inputSearchFilters,$searchForm) {

        $searchFilters = [];

        $searchConfig = $searchForm->getConfig();

        foreach (Arr::get($searchConfig,'fields',[]) as $searchFieldName => $searchFieldConfig) {
            if (array_key_exists($searchFieldName,$inputSearchFilters)) {
                $searchFilters[] = [
                    'field' => $searchFieldName,
                    'op' => Arr::get($searchFieldConfig, 'operator', '='),
                    'value' => $inputSearchFilters[$searchFieldName]['value']
                ];
            }
        }


        return $searchFilters;

    }

    /**
     * @param \Closure|string $builder
     */
    public function setListOrder($builder)
    {
        $this->listOrder = $builder;
    }


    protected function applyListOrder()
    {
        $orderParams = Arr::get($this->input, 'order_params', []);

        $field = Arr::get($orderParams, 'field', null);

        if (!$field || !is_string($field)) {

            return $this->applyListOrderDefault();
        };
        return $this->applyListOrderFromInput($field, $orderParams);


    }

    protected function applyListOrderFromInput($field, $orderParams)
    {
        $direction = Arr::get($orderParams, 'direction', 'ASC');
        $params = Arr::get($orderParams, 'params', []);

        return $this->formBuilder = $this->buildOrder($this->formBuilder, $field, $direction, $params);

    }

    protected function applyListOrderDefault()
    {
        if ($this->listOrder instanceof \Closure) {
            $builder = $this->listOrder;
            $this->formBuilder = $builder($this->formBuilder);
        }

        return $this->formBuilder;

    }


    protected function paginateList()
    {


        $paginationInput = Arr::get($this->input, 'pagination', []);

        $paginationConfig = Arr::get($this->config, 'pagination', []);

        $perPage = Arr::get($paginationInput, 'per_page') ?: Arr::get($paginationConfig, 'per_page', 10);

        $page = Arr::get($paginationInput, 'page', 1);

        $paginateSelect = is_array(Arr::get($this->config,'paginate_select',false))
            ? $this->paginateSelect
            : ["*"];


        if ($perPage < 0) {
            //No pagination: set a fixed big value in order to have the same output structure
            $perPage = Arr::get($this->config, 'no_paginate_value', 1000000);
        }

//        Log::info("QUERYLIST::: ".$this->formBuilder->toSql());

        $this->formBuilder = $this->formBuilder->paginate($perPage, $paginateSelect, 'page', $page);


        return $this->formBuilder;

    }

    public function getDataFromBuilder($params = []) {


        if (Arr::get($this->customFuncs,'transformToData') instanceof \Closure) {
            $transformationFunc = $this->customFuncs['transformToData'];
            return call_user_func_array($transformationFunc,$params);
        }

        $this->paginateList();
        $this->finalizeData();
    }

    public function finalizeData()
    {

        if (Arr::get($this->customFuncs,'finalizeData') instanceof \Closure) {
            $finalizationFunc = $this->customFuncs['finalizeData'];
            $this->formData = $finalizationFunc($this->formBuilder);
            return;
        }

        $this->formData = $this->finalizeDataStandard();

    }

    public function initFormBuilder() {
        $this->formBuilder = null;
    }

    protected function finalizeDataStandard()
    {

        $this->filterFieldsFromConfig();

        $arrayData = $this->formBuilder->toArray();

        $arrayData['pagination_steps'] = Arr::get(Arr::get($this->config, 'pagination', []),'pagination_steps',[]);

        return $arrayData;
    }

    protected function filterFieldsFromConfig() {
        $configFields = array_keys(Arr::get($this->config,'fields',[]));

        $hasManies = array_keys($this->getHasManies());
        $belongsTos = array_keys($this->getBelongsTos());
        $relations = Arr::get($this->config,'relations',[]);

        $relationsConfigFields = [];
        foreach ($relations as $relationName => $relationValue) {

            $configFields[] = $relationName;
            $relationsConfigFields[$relationName] = Arr::get($relationValue,'fields',[]);
        }

        $collection = $this->formBuilder->getCollection();


//        echo "<pre>";
//        print_r($collection->toArray());
//        echo "</pre>";

// build your second collection with a subset of attributes. this new
// collection will be a collection of plain arrays, not Users models.
        $newCollection = $collection->map(function ($model) use ($configFields,$hasManies,$belongsTos,$relationsConfigFields) {



            $arrayFiltered = array_intersect_key($model->toArray(),array_flip($configFields));

            foreach ($belongsTos as $relation) {
                $relationArray = is_array($arrayFiltered[$relation]) ? array_intersect_key($arrayFiltered[$relation],$relationsConfigFields[$relation]) : [];
                $arrayFiltered[$relation] = $relationArray;
            }

            foreach ($hasManies as $relation) {

                foreach (Arr::get($arrayFiltered,$relation,[]) as $hasManyArrayKey => $hasManyArray) {
                    $relationArray = array_intersect_key($hasManyArray,$relationsConfigFields[$relation]);
                    $arrayFiltered[$relation][$hasManyArrayKey] = $relationArray;

                }
            }



            return $arrayFiltered;
        });
        $this->formBuilder->setCollection($newCollection);
    }

    /**
     * Generates and returns the data list
     *
     * - Defines the relations of the model involved in the form
     * - Generates an initial builder
     * - Apply search filters if any
     * - Apply the desired list order
     * - Paginate results
     * - Apply last transformations to the list
     * - Format the result
     *
     */
    public function getFormData()
    {

        $this->getFormBuilder();

        $this->setAggregatesBuilder();

        $this->getDataFromBuilder();


        /*
         * REINIZIALIZZO IL FORM BUILDER
         */
        $this->initFormBuilder();

        return $this->formData;

    }

    protected function cloneFormBuilder()
    {
        $builder = $this->getFormBuilder();

        return clone($builder);

    }



    public function getFormBuilder()
    {
        if (is_null($this->formBuilder)) {
            $this->setFormBuilder();
        }

        return $this->formBuilder;
    }


    protected function setAggregatesBuilder() {
        $this->formAggregatesBuilder = $this->cloneFormBuilder();
    }

    public function getFormAggregatesData()
    {


//        count, max, min,  avg, and sum

        $aggregatesArray = Arr::get($this->config, 'aggregates', []);


        $this->formAggregatesData = $this->applyAggregates($this->formAggregatesBuilder, $aggregatesArray);

        return $this->formAggregatesData;

    }



    public function setFormBuilder()
    {

        $this->generateListBuilder();

        $this->applySearchFilters();

        $this->applyListOrder();

    }

    protected function setFormMetadataOrder() {
        $orderParams = Arr::get($this->input, 'order_params', []);

        $orderField = Arr::get($orderParams,'field', false);
        if ($orderField !== false) {
            $this->formMetadata['order']['field'] = $orderField;
            $this->formMetadata['order']['direction'] = Arr::get($orderParams,'direction', 'ASC');
        } else {
            $order = $this->model->getDefaultOrderColumns();
            $orderColumns = array_keys($order);
            $orderDirections = array_values($order);
            $this->formMetadata['order']['field'] = Arr::get($orderColumns, 0, 'id');
            $this->formMetadata['order']['direction'] = Arr::get($orderDirections, 0, 'ASC');
        }

    }

    public function setFormMetadata()
    {


        $this->setFormMetadataFields();

        $this->setFormMetadataRelations();

        $this->setFormMetadataOrder();


    }


}
