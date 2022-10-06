<?php

namespace Gecche\Foorm;


use Gecche\Cupparis\App\Breeze\Breeze;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class FoormList extends Foorm
{

    use FoormCollectionTrait;
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

    /*
     * @var Array|null;
     */
    protected $paginateSelect;

    protected $customFuncs = [];


    public function setCustomFunc($type, \Closure $func)
    {
        $this->customFuncs[$type] = $func;
    }

    /**
     *
     */
    protected function generateListBuilder()
    {


        $this->applyListBuilder();

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


    protected function paginateList()
    {


        $paginationInput = Arr::get($this->input, 'pagination', []);

        $paginationConfig = Arr::get($this->config, 'pagination', []);

        $perPage = Arr::get($paginationInput, 'per_page') ?: Arr::get($paginationConfig, 'per_page', 10);

        $page = Arr::get($paginationInput, 'page', 1);

        $paginateSelect = Arr::get($this->config,'paginate_select',false);
        if (!is_array($paginateSelect)) {
            $paginateSelect = $this->paginateSelect;
        }
        if (!is_array($paginateSelect)) {
            $paginateSelect = ["*"];
        }

        if ($perPage < 0) {
            //No pagination: set a fixed big value in order to have the same output structure
            $perPage = Arr::get($this->config, 'no_paginate_value', 1000000);
        }

//        Log::info("QUERYLIST::: ".$this->formBuilder->toSql());

        $this->formBuilder = $this->formBuilder->paginate($perPage, $paginateSelect, 'page', $page);


        return $this->formBuilder;

    }

    public function getDataFromBuilder($params = [])
    {


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

    public function initFormBuilder()
    {
        $this->formBuilder = null;
    }

    protected function finalizeDataStandard()
    {

        $this->filterFieldsFromConfig();

        $arrayData = $this->formBuilder->toArray();

        $paginationSteps = Arr::get(Arr::get($this->config, 'pagination', []),'pagination_steps',[]);

        $arrayData['pagination_steps'] = array_combine($paginationSteps,$paginationSteps);
        return $arrayData;
    }

    protected function filterFieldsFromConfig()
    {
        $configFields = array_keys(Arr::get($this->config, 'fields', []));
        $configAppends = Arr::get($this->config, 'appends', []);

        $hasManies = array_keys($this->getHasManies());
        $belongsTos = array_keys($this->getBelongsTos());
        $relations = Arr::get($this->config, 'relations', []);

        $relationsConfigFields = [];
//        $relationsConfigAppends = [];
        foreach ($relations as $relationName => $relationValue) {

            $configFields[] = $relationName;
            $relationsConfigFields[$relationName] = Arr::get($relationValue, 'fields', []);
//            $relationsConfigAppends[$relationName] = Arr::get($relationValue, 'appends', []);
        }

        $collection = $this->formBuilder->getCollection();


        $newCollection = $this->filterCollectionStandard($collection, $configFields, $configAppends,
            $hasManies, $belongsTos,
            $relationsConfigFields, $this->relations);
//        echo "<pre>";
//        print_r($collection->toArray());
//        echo "</pre>";

// build your second collection with a subset of attributes. this new
// collection will be a collection of plain arrays, not Users models.
        $this->formBuilder->setCollection($newCollection);
    }


    protected function filterCollectionStandard(
        $collection,
        $configFields,
        $configAppends,
        $hasManies,
        $belongsTos,
        $relationsConfigFields,
        $relationsMetadata = []
    )
    {
        return $collection->map(function ($model) use (
            $configFields,
            $configAppends,
            $hasManies,
            $belongsTos,
            $relationsConfigFields,
            $relationsMetadata
        ) {


            foreach ($configAppends as $appendField) {
                $model->append($appendField);
            };


            $arrayFiltered = array_intersect_key($model->toArray(), array_flip($configFields));

            foreach ($belongsTos as $relation) {
                $relationArray = is_array($arrayFiltered[$relation]) ? array_intersect_key($arrayFiltered[$relation],
                    $relationsConfigFields[$relation]) : [];
                $arrayFiltered[$relation] = $relationArray;
            }

            foreach ($hasManies as $relation) {

                $relationType = Arr::get(Arr::get($relationsMetadata, $relation, []), 0);

                switch ($relationType) {

                    case Breeze::HAS_ONE:
                        $relationArray = is_array($arrayFiltered[$relation]) ? array_intersect_key($arrayFiltered[$relation],
                            $relationsConfigFields[$relation]) : [];

                        $arrayFiltered[$relation] = $relationArray;
                        break;

                    default:

                        foreach (Arr::get($arrayFiltered, $relation, []) as $hasManyArrayKey => $hasManyArray) {
                            $relationArray = array_intersect_key($hasManyArray, $relationsConfigFields[$relation]);
                            $arrayFiltered[$relation][$hasManyArrayKey] = $relationArray;

                        }
                        break;
                }

            }


            return $arrayFiltered;
        });
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


    protected function setAggregatesBuilder()
    {
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


}
