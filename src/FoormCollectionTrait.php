<?php

namespace Gecche\Foorm;


use Gecche\Cupparis\App\Breeze\Breeze;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait FoormCollectionTrait
{


    protected function applyFixedConstraints()
    {
        $fixedConstraints = Arr::get($this->params, 'fixed_constraints', []);

        foreach ($fixedConstraints as $fixedConstraint) {
            $this->applyConstraint($fixedConstraint);
        }
    }

    public function getBasicQueryFieldName()
    {
        return Arr::get($this->config, 'basic_query_field_name', 'basic_query');
    }

    protected function buildSearchFilterBasicQuery($builder, $op, $value, $params)
    {
        $value = $this->guessInputValue($value,'string');
        if (is_null($value)) {
            return $builder;
        }

        $fieldsForBasicQuery = Arr::wrap(Arr::get($this->config,'basic_query_fields',[$this->model->getKeyName()]));

        $builder->where(function ($q) use ($fieldsForBasicQuery,$value) {
            foreach ($fieldsForBasicQuery as $field) {
                list($dbField,$relation) = $this->getFieldAndRelationForConstraint($field);
                if ($relation) {
                    $q->whereHas($relation, function ($q2) use ($dbField, $value) {
                        $q2->where($dbField, 'LIKE', '%' . $value . '%');
                    });
                } else {
                    $q->orWhere($dbField,'LIKE','%'.$value.'%');

                }
            }
        });

        return $builder;

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

        $fieldSanitized = str_replace('|', '_', $field);

        $basicQueryFieldName = $this->getBasicQueryFieldName();
        if ($fieldSanitized == $basicQueryFieldName) {
            return $this->buildSearchFilterBasicQuery($this->formBuilder, $op, $value, $params);
        }

        $studlyField = Str::studly($fieldSanitized);

        $methodName = 'buildSearchFilterField' . $studlyField;

        //Se esiste il metodo specifico SUL CAMPO lo chiamo
        if (method_exists($this, $methodName)) {
            return $this->$methodName($this->formBuilder, $op, $value, $params);
        }

        list($dbField,$relation) = $this->getFieldAndRelationForConstraint($field,$constraintArray);


        if ($relation) {
            return $this->formBuilder = $this->buildConstraintRelation($relation, $this->formBuilder, $dbField, $value,
                $op, $params);

        }

        return $this->formBuilder = $this->buildConstraint($this->formBuilder, $dbField, $value, $op, $params);

    }

    protected function getFieldAndRelationForConstraint($field,$constraintArray = []) {
        $modelRelations = $this->model->getRelationsData();

        $relation = null;
        $isRelation = false;
        $fieldExploded = explode('|', $field);
        $db = null;
        $table = null;


        if (array_key_exists($field, $modelRelations)) {
            return [$field,null];
        }

        if (count($fieldExploded) > 1) {
            $isRelation = true;
            $relation = $fieldExploded[0];
            unset($fieldExploded[0]);
            $field = implode('.', $fieldExploded);

            $relationData = Arr::get($modelRelations, $relation, []);
            $relationType = Arr::get($relationData, 0);
            if (!in_array($relationType, [Breeze::MORPH_TO]) &&
                !array_key_exists('related', $relationData)) {
                return $this->formBuilder;
            }

            $relationModelName = Arr::get($relationData, 'related');
            if ($relationModelName) {
                $relationModel = new $relationModelName;
                $table = Arr::get($constraintArray, 'table', $relationModel->getTable());
                $db = Arr::get($constraintArray, 'db',
                    config('database.connections.' . $relationModel->getConnectionName() . '.database'));
            }

        } else {
            $table = Arr::get($constraintArray, 'table', $this->model->getTable());
            $db = Arr::get($constraintArray, 'db');
        }


        $dbField = $db ? $db . '.' : '';
        $dbField .= $table ? $table . '.' : '';
        $dbField .= $field;

        return [$dbField,$relation];
    }

    protected function applySearchFilters()
    {
        $searchFilters = $this->buildSearchFilters();

        foreach ($searchFilters as $searchFilter) {
            $this->applyConstraint($searchFilter);
        }
    }

    protected function buildSearchFilters()
    {

        $inputSearchFilters = Arr::get($this->input, 'search_filters', []);

        $dependentSearchForm = Arr::get($this->dependentForms, 'search');

        if (!$dependentSearchForm) {
            return $inputSearchFilters;
        }

        return $this->buildSearchFiltersFromDependencies($inputSearchFilters, $dependentSearchForm);

    }

    protected function buildSearchFiltersFromDependencies($inputSearchFilters, $searchForm)
    {

        $searchFilters = [];

        $searchConfig = $searchForm->getConfig();

        $searchConfigFields = Arr::get($searchConfig, 'fields', []);
        $basicQueryFieldName = $this->getBasicQueryFieldName();
        if ($basicQueryFieldName) {
            $searchConfigFields[$basicQueryFieldName] = [];
        }

        foreach ($searchConfigFields as $searchFieldName => $searchFieldConfig) {
            if (array_key_exists($searchFieldName, $inputSearchFilters)) {
                $searchFilters[] = [
                    'field' => Arr::get($searchFieldConfig, 'field', $searchFieldName),
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

        $orderColumns = $this->model->getDefaultOrderColumns();

        if (!$orderColumns || !is_array($orderColumns)) {
            return $this->formBuilder;
        }

        foreach ($orderColumns as $field => $direction) {
            $this->formBuilder = $this->buildOrder($this->formBuilder, $field, $direction);
        }

        return $this->formBuilder;

    }


    public function getFormBuilder()
    {
        if (is_null($this->formBuilder)) {
            $this->setFormBuilder();
        }

        return $this->formBuilder;
    }


    protected function setFormMetadataOrder()
    {
        $orderParams = Arr::get($this->input, 'order_params', []);

        $orderField = Arr::get($orderParams, 'field', false);
        if ($orderField !== false) {
            $this->formMetadata['order']['field'] = $orderField;
            $this->formMetadata['order']['direction'] = Arr::get($orderParams, 'direction', 'ASC');
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
