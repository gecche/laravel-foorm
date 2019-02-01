<?php

namespace Gecche\Foorm;

use Cupparis\Ardent\Ardent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;

class ModelForm extends Form
{

    protected $db_methods;
    protected $model = null;
    protected $modelName = null;
    protected $modelRelativeName = null; //Senza namespace
    protected $modelNamePermission = null;
    protected $primary_key_field = null;
    protected $validationRulesJs = array();
    protected $hasManies = array();
    protected $belongsTos = array();

    protected $relations = array();

    protected $hasManiesSaveTypes = array();
    protected $relationsOrderKey = array();
    protected $belongsTosSaveTypes = array();
    protected $belongsToManiesSaveTypes = array();

    protected $inactiveRelations = array();

    protected $forSelectListConfig = [
        'default' => [
            'columns' => null,
            'separator' => null,
            'params' => [
                'filter_all' => true,
                'item_none' => false,
            ],
            'onlyValueSelectedDetail' => true,
        ]
    ];

    protected $enumsConfig = [
        'default' => [
            'filter_all' => true,
        ]
    ];

    public function __construct(Ardent $model, $permissionPrefix = null, $params = array())
    {

        parent::__construct($params);

        $this->model = $model;
        $this->modelName = get_class($this->model);
        $this->db_methods = new ModelDBMethods($this->model->getConnection());
        $this->modelRelativeName = trim_namespace($this->models_namespace, $this->modelName);

        $this->modelRelativeName = trim_namespace($this->datafilemodels_namespace, $this->modelRelativeName);
        $this->modelNamePermission = strtoupper(snake_case($this->modelRelativeName));
        $this->primary_key_field = $this->model->getTable() . '.' . $this->model->getKeyName();

        $this->setRelations();

    }

    public function setRelations($inactiveRelations = null)
    {
        if (is_null($inactiveRelations)) {
            $inactiveRelations = $this->inactiveRelations;
        }

        $relations = $this->model->getRelationData();

        foreach ($relations as $key => $relation) {
            if (in_array($key, $inactiveRelations)) {
                unset($relations[$key]);
            }
        }

        $this->relations = $relations;
        $this->belongsToManiesSaveTypes = array_merge(Config::get('forms.belongs_to_manies_save_types'),
            $this->belongsToManiesSaveTypes);
        $this->hasManiesSaveTypes = array_merge(Config::get('forms.has_manies_save_types'), $this->hasManiesSaveTypes);
        $this->belongsTosSaveTypes = array_merge(Config::get('forms.belongs_tos_save_types'),
            $this->belongsTosSaveTypes);

    }

    public function getModel()
    {
        return $this->model;
    }

    public function getModelName()
    {
        return $this->modelName;
    }

    public function getModelRelativeName()
    {
        return $this->modelRelativeName;
    }

    public function customizeResult()
    {
        return true;
    }

    protected function getFilterRealvalue($value)
    {
        $realvalue = null;
        if (is_string($value)) {
            $realvalue = $value;
        }
        if (is_array($value)) {
            $firstValue = array_get($value, 0, false);
            if ($firstValue !== false && $firstValue !== '') {
                $realvalue = $value[0];
            }
        }

        return $realvalue;
    }

    public function buildSearchFilter($key, $value, $op = '=', $builder = null, $searchInputs = [])
    {
        $searchParams = array_get($this->searchParams, $key, array());
        $searchDB = array_get($searchParams, 'db', $this->model->getTable() . '.' . $key);
        $realvalue = $this->getFilterRealvalue($value);

        if ($realvalue === null) {
            return $builder;
        }

        $studly_op = studly_case($op);
        switch ($studly_op) {
            case 'Like':
                return $builder->where($searchDB, 'LIKE', '%' . $realvalue . '%');
            case 'Date':
                return $builder->where($searchDB, '>=', $realvalue . ' 00:00:00')
                    ->where($searchDB, '<=', $realvalue . ' 23:59:59');
            case 'Is':
                return $builder->where($searchDB, 'IS', $realvalue);
            case 'IsNot':
                return $builder->where($searchDB, 'IS NOT', $realvalue);
            case 'IsNull':
                if ($realvalue == 'not') {
                    return $builder->whereNotNull($searchDB);
                } else {
                    return $builder->whereNull($searchDB);
                }
            case 'In':
            case 'NotIn':
                if (empty($value)) {
                    $value = array(-1);
                }
                if (!is_array($value)) {
                    $value = array($value);
                }
                if ($studly_op == 'In') {
                    return $builder->whereIn($searchDB, $value);
                } else {
                    return $builder->whereNotIn($searchDB, $value);
                }
            case 'Between':
                if (!is_array($value)) {
                    return $builder;
                }
                $value1 = array_get($value, 0);
                $value2 = array_get($value, 1);
                if (!$value2) {
                    return $builder->where($searchDB, '>=', $value1);
                }
                if (!$value1) {
                    return $builder->where($searchDB, '<=', $value2);
                }
                return $builder->whereBetween($searchDB, [$value1, $value2]);
            default:
                return $builder->where($searchDB, $op, $value);
        }
    }

    public function buildSearchFilterDatatable($key, $value, $op = '=', $builder = null, $searchInputs = [])
    {
        $studly_op = studly_case($op);
        if ($studly_op != 'Datatable' || $key != 'datatable') {
            return $builder;
        }

        $searchFields = Input::get('datatable_fields',[]);
        if (!is_array($searchFields)) {
            return $builder;
        }


        $value = is_array($value) ? $value[0] : $value;

        $searchParams = array_get($this->searchParams, $key, array());


        $builder->where(function ($query) use ($value,$searchFields,$searchParams) {
            foreach ($searchFields as $searchField) {
                $searchDB = array_get($searchParams, 'db', $this->model->getTable() . '.' . $searchField);
                $query->orWhere($searchDB, 'LIKE', '%' . $value . '%');
            }
        });

        return $builder;

    }

    public function buildSearchFilterDateIn($key, $value, $op = '=', $builder = null, $searchInputs = [])
    {
        $studly_op = studly_case($op);
        if ($studly_op != 'DateIn') {
            return $builder;
        }
        $searchParams = array_get($this->searchParams, $key, array());
        $searchDB = array_get($searchParams, 'db', $this->model->getTable() . '.' . $key);

        //DA SISTEMARE
        if (is_array($value)) {
            $firstValue = array_get($value, 0, false);
            $secondValue = array_get($value, 1, false);
            if (!$firstValue && !$secondValue) {
                return $builder;
            }
            if (!$firstValue) {
                $firstValue = '1900-01-01';
            }
            if (!$secondValue) {
                $secondValue = '2100-12-31';
            }

            if (strlen($firstValue) == 10) {
                $firstValue .= ' 00:00:00';
            }

            if (strlen($secondValue) == 10) {
                $secondValue .= ' 23:59:59';
            }

        }

        $endField = array_get($searchParams, 'end_date_in_field', false);
        if ($endField) {

            $searchDBEnd = array_get($searchParams, 'db', $this->model->getTable() . '.' . $endField);

            $resultQuery = $builder->where(function ($query) use ($searchDB, $searchDBEnd, $firstValue, $secondValue) {
                $query->orWhere(function ($query) use ($searchDB, $searchDBEnd, $firstValue, $secondValue) {
                    $query->where($searchDB, '>=', $firstValue)
                        ->where($searchDB, '<=', $secondValue);
                })->orWhere(function ($query) use ($searchDB, $searchDBEnd, $firstValue, $secondValue) {
                    $query->where($searchDBEnd, '>=', $firstValue)
                        ->where($searchDBEnd, '<=', $secondValue);
                })->orWhere(function ($query) use ($searchDB, $searchDBEnd, $firstValue, $secondValue) {
                    $query->where($searchDBEnd, '>', $secondValue)
                        ->where($searchDB, '<=', $firstValue);
                });
            });

            return $resultQuery;

        }


        return $builder->where($searchDB, '>=', $firstValue)
            ->where($searchDB, '<=', $secondValue);
    }

    public function buildSearchFilterRelation($relation, $key, $value, $op = '=', $builder = null, $searchInputs = [])
    {
        $searchParams = array_get($this->searchParams, $key, array());
        $relationModelData = $this->getRelationDataFromModel($relation);
        //Da controllare: in questo non ho la relatzione nel modello
        if (count($relationModelData) < 1) {
            return $builder;
        }

        $relationModelName = $relationModelData['modelName'];
        $relationModel = new $relationModelName;

        $dbName = Config::get('database.connections.' . $relationModel->getConnectionName() . '.database');

        $searchDB = array_get($searchParams, 'db', $dbName . '.' . $relationModel->getTable() . '.' . $key);
        $realvalue = $this->getFilterRealvalue($value);

        if ($realvalue === null) {
            return $builder;
        }

        $studly_op = studly_case($op);


        switch ($studly_op) {
            case 'Like':
                return $builder->whereHas($relation, function ($q) use ($searchDB, $realvalue) {
                    $q->where($searchDB, 'LIKE', '%' . $realvalue . '%');
                });
            case 'Date':
                return $builder->whereHas($relation, function ($q) use ($searchDB, $realvalue) {
                    $q->where($searchDB, '>=', $realvalue . ' 00:00:00')
                        ->where($searchDB, '<=', $realvalue . ' 23:59:59');
                });
            case 'Is':
                return $builder->whereHas($relation, function ($q) use ($searchDB, $realvalue) {
                    $q->where($searchDB, 'IS', $realvalue);
                });
            case 'IsNot':
                return $builder->whereHas($relation, function ($q) use ($searchDB, $realvalue) {
                    $q->where($searchDB, 'IS NOT', $realvalue);
                });
            case 'IsNull':
                if ($realvalue == 'not') {
                    return $builder->whereHas($relation, function ($q) use ($searchDB) {
                        $q->whereNotNull($searchDB);
                    });
                } else {
                    return $builder->whereHas($relation, function ($q) use ($searchDB) {
                        $q->whereNull($searchDB);
                    });
                }
            case 'In':
            case 'NotIn':
                if (empty($value)) {
                    $value = array(-1);
                }
                if (!is_array($value)) {
                    $value = array($value);
                }
                if ($studly_op == 'In') {
                    return $builder->whereHas($relation, function ($q) use ($searchDB, $value) {
                        $q->whereIn($searchDB, $value);
                    });
                } else {
                    return $builder->whereHas($relation, function ($q) use ($searchDB, $value) {
                        $q->whereNotIn($searchDB, $value);
                    });
                }
            case 'Between':
                if (!is_array($value)) {
                    return $builder;
                }
                $value1 = array_get($value, 0, false);
                $value2 = array_get($value, 1, false);
                if (!$value2) {
                    return $builder->whereHas($relation, function ($q) use ($searchDB, $value1) {
                        $q->where($searchDB, '>=', $value1);
                    });
                }
                if (!$value1) {
                    return $builder->whereHas($relation, function ($q) use ($searchDB, $value2) {
                        $q->where($searchDB, '<=', $value2);
                    });
                }
                return $builder->whereHas($relation, function ($q) use ($searchDB, $value1, $value2) {
                    $q->whereBetween($searchDB, [$value1, $value2]);
                });
            default:
                return $builder->whereHas($relation, function ($q) use ($searchDB, $value, $op) {
                    $q->where($searchDB, $op, $value);
                });
        }
    }

    public function buildSearchFilterRelationDateIn($relation, $key, $value, $op = '=', $builder = null, $searchInputs = [])
    {
        $searchParams = array_get($this->searchParams, $key, array());
        $relationModelData = $this->getRelationDataFromModel($relation);
        //Da controllare: in questo non ho la relatzione nel modello
        if (count($relationModelData) < 1) {
            return $builder;
        }

        $relationModelName = $relationModelData['modelName'];
        $relationModel = new $relationModelName;

        $dbName = Config::get('database.connections.' . $relationModel->getConnectionName() . '.database');

        $studly_op = studly_case($op);
        if ($studly_op != 'DateIn') {
            return $builder;
        }

        $searchParams = array_get($this->searchParams, $key, array());
        $searchDB = array_get($searchParams, 'db', $dbName . '.' . $relationModel->getTable() . '.' . $key);

        //DA SISTEMARE
        if (!is_array($value)) {
            return $builder;
        }
        $firstValue = array_get($value, 0, false);
        $secondValue = array_get($value, 1, false);
        if (!$firstValue && !$secondValue) {
            return $builder;
        }


        if (!$firstValue) {
            $firstValue = '1900-01-01';
        }
        if (!$secondValue) {
            $secondValue = '2100-12-31';
        }

        if (strlen($firstValue) == 10) {
            $firstValue .= ' 00:00:00';
        }

        if (strlen($secondValue) == 10) {
            $secondValue .= ' 23:59:59';
        }


        $endField = array_get($searchParams, 'end_date_in_field', false);
        if ($endField) {

            $searchDBEnd = array_get($searchParams, 'db', $dbName . '.' . $relationModel->getTable() . '.' . $endField);

            $resultQuery = $builder->whereHas($relation, function ($qRel) use ($searchDB, $searchDBEnd, $firstValue, $secondValue) {

                    $qRel->where(function ($query) use ($searchDB, $searchDBEnd, $firstValue, $secondValue) {
                        $query->orWhere(function ($query) use ($searchDB, $searchDBEnd, $firstValue, $secondValue) {
                            $query->where($searchDB, '>=', $firstValue)
                                ->where($searchDB, '<=', $secondValue);
                        })->orWhere(function ($query) use ($searchDB, $searchDBEnd, $firstValue, $secondValue) {
                            $query->where($searchDBEnd, '>=', $firstValue)
                                ->where($searchDBEnd, '<=', $secondValue);
                        })->orWhere(function ($query) use ($searchDB, $searchDBEnd, $firstValue, $secondValue) {
                            $query->where($searchDBEnd, '>', $secondValue)
                                ->where($searchDB, '<=', $firstValue);
                        });
                    });
            });

            return $resultQuery;

        }

        return $builder->whereHas($relation, function ($q) use ($searchDB, $firstValue, $secondValue) {
            $q->whereBetween($searchDB, [$firstValue,$secondValue]);
        });
    }


    public function buildOrder($key, $direction, $builder = null)
    {
        $orderParams = array_get($this->orderParams, $key, array());
        $orderDB = array_get($orderParams, 'db', $this->model->getTable() . '.' . $key);

        $return = $builder->orderBy($orderDB, $direction);
        return $return;
    }

    /*
      protected function modelFilter() {
      if (array_get($this->result, 'created_at'))
      unset($this->modelStandardResult['created_at']);
      return true;
      }
     *
     */

    protected function getSaveType($array, $key, $defaultSaveType)
    {
        $saveTypeValues = array_get($array, $key, $defaultSaveType);
        if (is_array($saveTypeValues)) {
            $saveType = array_get($saveTypeValues, 'type', false);
            $saveTypeParams = array_get($saveTypeValues, 'params', array());
        } else {
            $saveType = $saveTypeValues;
            $saveTypeParams = array();
        }

        return [
            'type' => $saveType,
            'params' => $saveTypeParams,
        ];
    }


    public function setHasManies()
    {

        $cacheKey = 'hasManies.' . $this->modelName;

        if (!app()->environment('local') && Cache::has($cacheKey)) {
            $this->hasManies = Cache::get($cacheKey);
            return;
        }

        $relations = $this->relations;

        foreach ($relations as $key => $relation) {

            $relations[$key]['max_items'] = 0;
            $relations[$key]['min_items'] = 0;
            switch ($relation[0]) {
                case Ardent::BELONGS_TO_MANY:
                    $saveTypeArray = $this->getSaveType($this->belongsToManiesSaveTypes, $key, 'add');
                    $saveType = $saveTypeArray['type'];
                    if (starts_with($saveType, 'standard_with_save')) {
                        list($saveType, $pivotModelName) = explode(':', $saveType);
                        $relations[$key]['pivotModelName'] = $pivotModelName;
                    }
                    $relations[$key]['saveType'] = $saveType;
                    $relations[$key]['saveTypeParams'] = $saveTypeArray['params'];

                    if (isset($this->relationsOrderKey[$key]) && !$this->relationsOrderKey[$key]) {
                        $relations[$key]['orderKey'] = false;
                    } else {

                        $orderKey = array_get($this->relationsOrderKey, $key, 'ordine');
                        $pivotFields = $this->model->getPivotKeys($key);
                        if (in_array($orderKey, $pivotFields)) {
                            $relations[$key]['orderKey'] = $orderKey;
                        } else {
                            $relations[$key]['orderKey'] = false;
                        }
                    }
                    break;
                case Ardent::MORPH_MANY:
                    $saveTypeArray = $this->getSaveType($this->belongsToManiesSaveTypes, $key, 'add');
                    $saveType = $saveTypeArray['type'];
                    $relations[$key]['saveType'] = $saveType;
                    $relations[$key]['saveTypeParams'] = $saveTypeArray['params'];

                    if (isset($this->relationsOrderKey[$key]) && !$this->relationsOrderKey[$key]) {
                        $relations[$key]['orderKey'] = false;
                    } else {
                        $orderKey = array_get($this->relationsOrderKey, $key, 'ordine');
                        $relations[$key]['orderKey'] = $orderKey;
                    }

                    break;
                case Ardent::HAS_MANY:
                    $saveTypeArray = $this->getSaveType($this->hasManiesSaveTypes, $key, 'standard');
                    $saveType = $saveTypeArray['type'];
                    $relations[$key]['saveType'] = $saveType;
                    $relations[$key]['saveTypeParams'] = $saveTypeArray['params'];

                    if (isset($this->relationsOrderKey[$key]) && !$this->relationsOrderKey[$key]) {
                        $relations[$key]['orderKey'] = false;
                    } else {
                        $orderKey = array_get($this->relationsOrderKey, $key, 'ordine');
                        $relations[$key]['orderKey'] = $orderKey;
                    }
                    break;
                case Ardent::HAS_ONE:
                    $saveTypeArray = $this->getSaveType($this->hasManiesSaveTypes, $key, 'standard');
                    $saveType = $saveTypeArray['type'];
                    $relations[$key]['saveType'] = $saveType;
                    $relations[$key]['saveTypeParams'] = $saveTypeArray['params'];
                    $relations[$key]['max_items'] = 1;
                    break;
                default:
                    unset($relations[$key]);
                    continue 2;  // per dire di continuare il ciclo for e non lo switch
                    break;
            }
            $relations[$key]['hasManyType'] = $relations[$key][0];
            unset($relations[$key][0]);
            $relations[$key]['modelName'] = $relations[$key][1];
            $relations[$key]['modelRelativeName'] = trim_namespace($this->models_namespace, $relations[$key][1]);
            $relations[$key]['relationName'] = snake_case( $relations[$key]['modelRelativeName']);
            unset($relations[$key][1]);
        }

        $fieldParamsFromModel = $this->model->getFieldParams();
        $hasManyParamsFromModel = array_intersect_key($fieldParamsFromModel, $relations);

        $hasManies = array_replace_recursive($relations, $hasManyParamsFromModel);

        Cache::forever($cacheKey, $hasManies);
        $this->hasManies = $hasManies;
    }

    public function getHasManies()
    {
        return $this->hasManies;
    }

    public function setBelongsTos()
    {

        $cacheKey = 'belongsTos.' . $this->modelName;

        if (!app()->environment('local') && Cache::has($cacheKey)) {
            $this->belongsTos = Cache::get($cacheKey);
            return;
        }

        $relations = $this->relations;

        foreach ($relations as $key => $relation) {

            switch ($relation[0]) {
                case Ardent::BELONGS_TO:
                    $foreignKey = array_get($relations[$key], 'foreignKey', snake_case($key) . '_id');

                    $saveTypeArray = $this->getSaveType($this->belongsTosSaveTypes, $key, 'standard');
                    $saveType = $saveTypeArray['type'];
                    $relations[$key]['saveType'] = $saveType;
                    $relations[$key]['saveTypeParams'] = $saveTypeArray['params'];

                    $relations[$key]['relationName'] = $key;
                    break;
                default:
                    unset($relations[$key]);
                    continue 2;  // per dire di continuare il ciclo for e non lo switch
                    break;
            }
            unset($relations[$key][0]);
            $relations[$key]['modelName'] = $relations[$key][1];
            $relations[$key]['modelRelativeName'] = trim_namespace($this->models_namespace, $relations[$key][1]);
            unset($relations[$key][1]);

            if ($foreignKey !== $key) {
                $relations[$foreignKey] = $relations[$key];
                unset($relations[$key]);
            }
        }

        $fieldParamsFromModel = $this->model->getFieldParams();
        $belongsTosParamsFromModel = array_intersect_key($fieldParamsFromModel, $relations);

        $belongsTos = array_replace_recursive($relations, $belongsTosParamsFromModel);

        Cache::forever($cacheKey, $belongsTos);
        $this->belongsTos = $belongsTos;
    }

    public function getBelongsTos()
    {
        return $this->belongsTos;
    }

    protected function _setTranslations()
    {

        $result = array();
        foreach ($this->resultParams as $field => $value) {
            if (is_array($value) && array_get($value, 'fields', false)) {
                $subfields = array_keys($value['fields']);
                foreach ($subfields as $subfield) {
                    $subModelName = camel_case($value['modelRelativeName']);

                    //$subfieldKey = $subModelName . '-' . $subfield;
                    $subfieldKey = $field . '-' . $subfield;
                    $result[$subfieldKey] = Lang::getMFormField($subfield, $subModelName);
                    $result[$subfieldKey . '_label'] = Lang::getMFormLabel($subfield, $subModelName);
                    $result[$subfieldKey . '_msg'] = Lang::getMFormMsg($subfield, $subModelName);
                    $result[$subfieldKey . '_addedLabel'] = Lang::getMFormAddedLabel($subfield, $subModelName);
                }
            }
            $modelName = camel_case($this->modelRelativeName);
            $fieldKey = $modelName . '-' . $field;
            $result[$fieldKey] = Lang::getMFormField($field, $modelName);
            $result[$fieldKey . '_label'] = Lang::getMFormLabel($field, $modelName);
            $result[$fieldKey . '_msg'] = Lang::getMFormMsg($field, $modelName);
            $result[$fieldKey . '_addedLabel'] = Lang::getMFormAddedLabel($field, $modelName);
        }

        $searchfields = Lang::get('searchfields');
        if (!is_array($searchfields)) {
            $searchfields = array();
        }
        foreach ($searchfields as $key => $value) {
            $modelName = camel_case($this->modelRelativeName);
            $fieldKey = $modelName . '-' . $key;
            $result[$fieldKey] = Lang::getMFormField($key, $modelName, array(), null, 'ucfirst', '_', 'searchfields.');
            $result[$fieldKey . '_label'] = Lang::getMFormLabel($key, $modelName, array(), null, 'ucfirst', '_',
                'searchfields.');
            $result[$fieldKey . '_msg'] = Lang::getMFormMsg($key, $modelName, array(), null, 'ucfirst', '_',
                'searchfields.');
            $result[$fieldKey . '_addedLabel'] = Lang::getMFormAddedLabel($key, $modelName, array(), null, 'ucfirst',
                '_', 'searchfields.');
        }

        $customfields = Lang::get('customfields');
        if (!is_array($customfields)) {
            $customfields = array();
        }
        foreach ($customfields as $key => $value) {
            $modelName = camel_case($this->modelRelativeName);
            $fieldKey = $key;
            $result[$fieldKey] = Lang::getMFormField($key, $modelName, array(), null, 'ucfirst', '_', 'customfields.');
            $result[$fieldKey . '_label'] = Lang::getMFormLabel($key, $modelName, array(), null, 'ucfirst', '_',
                'customfields.');
            $result[$fieldKey . '_msg'] = Lang::getMFormMsg($key, $modelName, array(), null, 'ucfirst', '_',
                'customfields.');
            $result[$fieldKey . '_addedLabel'] = Lang::getMFormAddedLabel($key, $modelName, array(), null, 'ucfirst',
                '_', 'customfields.');
        }

        $result_pagination = array_key_append(Lang::get('pagination'), 'pagination-', false);

        $result = array_merge($this->translations, $result, $result_pagination);

        $result_validation = array_key_append(Lang::get('validation'), 'validation-', false);
        $result = array_merge($this->translations, $result, $result_validation);

        $this->translations = $result;
    }

    protected function _setTranslationsTest()
    {

        $snakeModelName = snake_case($this->modelRelativeName);
        //echo "_setTRanslationsTest --- " . $this->modelRelativeName . "\n";
        $result = array();
        foreach ($this->resultParams as $field => $value) {
            echo "here --- ".$this->modelRelativeName."---".$field."\n";
            if (is_array($value) && array_get($value, 'fields', false)) {
                $subfields = array_keys($value['fields']);
                //$result[$field] = [];
                foreach ($subfields as $subfield) {
                    $subModelName = camel_case($value['modelRelativeName']);
                    $snakeSubModelName = snake_case($value['modelRelativeName']);
                    $result[$field][$subfield] = [
                        'label' =>   Lang::getMFormLabel($subfield, $subModelName),
                        'msg' => Lang::getMFormMsg($subfield, $subModelName),
                        'addedLabel' => Lang::getMFormAddedLabel($subfield, $subModelName)
                    ];
                    $result[$field]['modelMetadata']['singular'] = trans_choice('model.'.$snakeSubModelName,1);
                    $result[$field]['modelMetadata']['plural'] = trans_choice('model.'.$snakeSubModelName,2);
//                    $subfieldKey = $field . '-' . $subfield;
//                    $result[$subfieldKey] = Lang::getMFormField($subfield, $subModelName);
//                    $result[$subfieldKey . '_label'] = Lang::getMFormLabel($subfield, $subModelName);
//                    $result[$subfieldKey . '_msg'] = Lang::getMFormMsg($subfield, $subModelName);
//                    $result[$subfieldKey . '_addedLabel'] = Lang::getMFormAddedLabel($subfield, $subModelName);
                }
            }
            $modelName = snake_case($this->modelRelativeName);
            $result[$modelName][$field] = [
                'label' =>   Lang::getMFormLabel($field, $modelName),
                'msg' => Lang::getMFormMsg($field, $modelName),
                'addedLabel' => Lang::getMFormAddedLabel($field, $modelName)
            ];
//            $fieldKey = $modelName . '-' . $field;
//            $result[$fieldKey] = Lang::getMFormField($field, $modelName);
//            $result[$fieldKey . '_label'] = Lang::getMFormLabel($field, $modelName);
//            $result[$fieldKey . '_msg'] = Lang::getMFormMsg($field, $modelName);
//            $result[$fieldKey . '_addedLabel'] = Lang::getMFormAddedLabel($field, $modelName);
        }

        $searchfields = Lang::get('searchfields');
        if (!is_array($searchfields)) {
            $searchfields = array();
        }
        foreach ($searchfields as $key => $value) {
            $modelName = camel_case($this->modelRelativeName);
            $result[$modelName][$key] = [
                'label' =>   Lang::getMFormLabel($key, $modelName, array(), null, 'ucfirst', '_', 'searchfields.'),
                'msg' => Lang::getMFormMsg($key, $modelName, array(), null, 'ucfirst', '_', 'searchfields.'),
                'addedLabel' => Lang::getMFormAddedLabel($key, $modelName, array(), null, 'ucfirst', '_', 'searchfields.')
            ];
//            $fieldKey = $modelName . '-' . $key;
//            $result[$fieldKey] = Lang::getMFormField($key, $modelName, array(), null, 'ucfirst', '_', 'searchfields.');
//            $result[$fieldKey . '_label'] = Lang::getMFormLabel($key, $modelName, array(), null, 'ucfirst', '_',
//                'searchfields.');
//            $result[$fieldKey . '_msg'] = Lang::getMFormMsg($key, $modelName, array(), null, 'ucfirst', '_',
//                'searchfields.');
//            $result[$fieldKey . '_addedLabel'] = Lang::getMFormAddedLabel($key, $modelName, array(), null, 'ucfirst',
//                '_', 'searchfields.');
        }

        $customfields = Lang::get('customfields');
        if (!is_array($customfields)) {
            $customfields = array();
        }
        foreach ($customfields as $key => $value) {
            $modelName = camel_case($this->modelRelativeName);

            $keyPrefix = $modelName.'_';
            if (!starts_with($key,$keyPrefix)) {
                continue;
            }
            $key2 = str_replace($keyPrefix,'',$key);
            $result[$modelName][$key2] = [
                'label' =>   Lang::getMFormLabel($key, $modelName, array(), null, 'ucfirst', '_', 'customfields.'),
                'msg' => Lang::getMFormMsg($key, $modelName, array(), null, 'ucfirst', '_', 'customfields.'),
                'addedLabel' => Lang::getMFormAddedLabel($key, $modelName, array(), null, 'ucfirst', '_', 'customfields.')
            ];

//            $fieldKey = $key;
//            $result[$fieldKey] = Lang::getMFormField($key, $modelName, array(), null, 'ucfirst', '_', 'customfields.');
//            $result[$fieldKey . '_label'] = Lang::getMFormLabel($key, $modelName, array(), null, 'ucfirst', '_',
//                'customfields.');
//            $result[$fieldKey . '_msg'] = Lang::getMFormMsg($key, $modelName, array(), null, 'ucfirst', '_',
//                'customfields.');
//            $result[$fieldKey . '_addedLabel'] = Lang::getMFormAddedLabel($key, $modelName, array(), null, 'ucfirst',
//                '_', 'customfields.');
        }

        $result[$modelName]['modelMetadata']['singular'] = trans_choice('model.'.$snakeModelName,1);
        $result[$modelName]['modelMetadata']['plural'] = trans_choice('model.'.$snakeModelName,2);
//        $result['validation'] = Lang::get('validation');
//        $result['pagination'] = Lang::get('pagination');

        $this->translations = $result;
    }

    public function setTranslations()
    {
        if (config("app.translations_notation",'') === 'dot') {
            return $this->_setTranslationsTest();
        }
        return $this->_setTranslations();

    }

    public function setValidationRulesJs()
    {

        return true;
    }

    public function getValidationRulesJs()
    {
        return $this->validationRulesJs;
    }

    public function ajaxListing(
        $listModelName,
        $key,
        $pk = 0,
        $fieldValues = array(),
        $labelColumns = null,
        $n_items = null,
        $separator = null
    ) {
        $result = parent::ajaxListing($listModelName, $key, $pk, $fieldValues, $labelColumns, $n_items, $separator);
//Nel caso sia lo stesso modello ricorsivo escludo se stesso :)

        if ($this->modelRelativeName === $listModelName) {
            foreach ($result as $resultKey => $item) {

                if ($item["key"] === $pk) {
                    unset($result[$resultKey]);
                    break;
                }
            }
        }

        return $result;
    }

    public function getAppendsDefaults($raw = false)
    {
        if (!$this->model) {
            return array();
        }
        $appends = $this->model->getAppends();
        $appends_defaults = array();

        foreach ($appends as $append) {
            if ($raw) {
                $appends_defaults[$append] = null;
            } else {
                $column = array(); //$this->dataType($type);
                $column['column_default'] = null;
                $appends_defaults[$append] = $column;
            }

        }

        return $appends_defaults;
    }

    public function setResultParamsAppendsDefaults()
    {
        $this->resultParams = array_merge($this->resultParams, $this->getAppendsDefaults());
    }

    public function setResultParamsDefaults()
    {
        return;
    }

    public function getValidationRulesForSaving()
    {
        $rules = $this->getValidationRules();

        if ($this->model && $this->model->getKey()) {
            $rules = $this->model->getBuildedRules($rules);
        }

        return $rules;
    }

    public function createResultParamItem($field, $params = null)
    {
        if (array_get($this->resultParams, $field, false)) {
            return;
        }
        if (!$params) {
            $params = array(
                'column_default' => null,
            );
        }
        $this->resultParams[$field] = $params;
    }

    protected function getRelationDataFromModel($relation)
    {

        if (array_get($this->belongsTos, $relation, false)) {
            return $this->belongsTos[$relation];
        }

        if (array_get($this->hasManies, $relation, false)) {
            return $this->hasManies[$relation];
        }

        foreach ($this->belongsTos as $foreignKey => $belongsToRelation) {
            if ($belongsToRelation['relationName'] == $relation) {
                return $this->belongsTos[$foreignKey];
            }
        }

        return array();

    }


    public function getDataHeader()
    {
        return null;
    }

    public function addItemNoneToSelect($selectArray)
    {

        $itemNoneArray = [
            env('FORM_ITEM_NONE', -99) => trans_uc('app.item_none'),
        ];

        return $itemNoneArray + $selectArray;


    }

    public function addFilterAllToSelect($selectArray)
    {

        $itemNoneArray = [
            env('FORM_FILTER_ALL', -99) => trans_uc('app.filter_all'),
        ];

        return $itemNoneArray + $selectArray;


    }


}
