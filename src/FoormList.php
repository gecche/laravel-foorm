<?php

namespace Gecche\Foorm;


use Gecche\DBHelper\Facades\DBHelper;
use Gecche\Breeze\Breeze;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;

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


    /**
     * @var \Closure|null
     */
    protected $listBuilder;

    protected $listOrder;

    protected $paginateSelect;


    /**
     * @var Builder|null
     */
    protected $formAggregatesBuilder;





    /**
     * @param \Closure|string $builder
     */
    public function setListBuilder($builder)
    {
        $this->listBuilder = $builder;
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
        if ($this->listBuilder instanceof \Closure) {
            $builder = $this->listBuilder;
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
        $fixedConstraints = array_get($this->params, 'fixed_constraints', []);

        foreach ($fixedConstraints as $fixedConstraint) {
            $this->applyConstraint($fixedConstraint);
        }
    }

    protected function applyConstraint(array $constraintArray)
    {


        $field = array_get($constraintArray, 'field', null);

        if (!$field || !is_string($field) || !array_key_exists('value', $constraintArray)) {
            return $this->formBuilder;
        };

        $value = $constraintArray['value'];

        $relation = null;
        $isRelation = false;
        $fieldExploded = explode('.', $field);
        if (count($fieldExploded) > 1) {
            $isRelation = true;
            $relation = $fieldExploded[0];
            unset($fieldExploded[0]);
            $field = implode('.', $fieldExploded);

            $relationData = array_get($this->relations, $relation, []);
            if (!array_key_exists('modelName', $relationData)) {
                return $this->formBuilder;
            }

            $relationModelName = $relationData['modelName'];
            $relationModel = new $relationModelName;
            $table = array_get($constraintArray, 'table', $relationModel->getTable());
            $db = array_get($constraintArray, 'db',config('database.connections.' . $relationModel->getConnectionName() . '.database'));

        } else {
            $table = array_get($constraintArray, 'table', $this->model->getTable());
            $db = array_get($constraintArray, 'db');
        }


        $dbField = $db ? $db . '.' : '';
        $dbField .= $table ? $table . '.' : '';
        $dbField .= $field;

        $op = array_get($constraintArray, 'op', '=');
        $params = array_get($constraintArray, 'params', []);


        if ($isRelation) {
            return $this->formBuilder = $this->buildConstraintRelation($relation, $this->formBuilder, $dbField, $value, $op, $params);

        }

        return $this->formBuilder = $this->buildConstraint($this->formBuilder, $dbField, $value, $op, $params);

    }




    protected function applySearchFilters()
    {
        $searchFilters = array_get($this->input, 'search_filters', []);

        foreach ($searchFilters as $searchFilter) {
            $this->applyConstraint($searchFilter);
        }
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
        $orderParams = array_get($this->input, 'order_params', []);

        $field = array_get($orderParams, 'field', null);

        if (!$field || !is_string($field)) {

            return $this->applyListOrderDefault();
        };
        return $this->applyListOrderFromInput($field, $orderParams);


    }

    protected function applyListOrderFromInput($field, $orderParams)
    {
        $direction = array_get($orderParams, 'direction', 'ASC');
        $params = array_get($orderParams, 'params', []);

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


    public function setPaginateSelect(array $paginateSelect)
    {
        $this->paginateSelect = $paginateSelect;
    }

    protected function paginateList()
    {


        $paginationInput = array_get($this->input, 'pagination', []);

        $perPage =
            array_get($paginationInput, 'per_page',
                array_get($this->config, 'per_page', 10)
            );

        $page = array_get($paginationInput, 'page', 1);

        $paginateSelect = is_array($this->paginateSelect)
            ? $this->paginateSelect
            : [$this->model->getTable() . ".*"];

        $configFields = array_keys(Arr::get($this->config,'fields',[]));

        /*
         * QUESTO NON SO BENE COME FUNZIONI SULLE RELAZIONI
         */
//        $prefixedConfigFields = $this->array_key_append($configFields, $this->model->getTable() . ".", false);
//
//        $paginateSelect = is_array($this->paginateSelect)
//            ? $this->paginateSelect
//            : $prefixedConfigFields;

        if ($perPage < 0) {
            //No pagination: set a fixed big value in order to have the same output structure
            $perPage = array_get($this->config, 'no_paginate_value', 1000000);
        }

        Log::info("QUERYLIST::: ".$this->formBuilder->toSql());

//      $this->summaryResult = $this->formBuilder;
        $this->formBuilder = $this->formBuilder->paginate($perPage, $paginateSelect, 'page', $page);


        return $this->formBuilder;

    }


    public function finalizeData($finalizationFunc = null)
    {

        if ($finalizationFunc instanceof \Closure) {
            $this->formData = $finalizationFunc($this->formBuilder);
        } else {

            $this->formData = $this->finalizeDataStandard();
        }

        return $this->formData;

    }

    protected function finalizeDataStandard()
    {
        $arrayData = $this->formBuilder->toArray();


        return $arrayData;
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

        $this->paginateList();

        $this->finalizeData();

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

        $aggregatesArray = array_get($this->config, 'aggregates', []);


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
        $order_input = array_get($this->input,'order_field', false);
        if ($order_input !== false) {
            $this->formMetadata['order']['order_field'] = $order_input;
            $this->formMetadata['order']['order_direction'] = Input::get('order_direction', 'ASC');
        } else {
            $order = $this->model->getDefaultOrderColumns();
            $orderColumns = array_keys($order);
            $orderDirections = array_values($order);
            $this->formMetadata['order']['order_field'] = array_get($orderColumns, 0, 'id');
            $this->formMetadata['order']['order_direction'] = array_get($orderDirections, 0, 'ASC');
        }

    }

    public function setFormMetadata()
    {


        $this->setFormMetadataFields();

        $this->setFormMetadataRelations();

        $this->setFormMetadataOrder();


    }


}
