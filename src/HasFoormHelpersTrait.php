<?php

namespace Gecche\Foorm;

use Illuminate\Support\Facades\Validator;

trait HasFoormHelpersTrait
{
    public $defaultOrderColumns = array('id' => 'ASC');
    public $columnsForSelectList = array('id');
    public $columnsSearchAutoComplete = array('id');
    public $nItemsAutoComplete = 20;
    public $nItemsForSelectList = 100;
    public $itemNoneForSelectList = false;
    public $fieldsSeparator = ' - ';

    protected $keynameInList = 'keyname';


    public function getForSelectList($builder = null, $columns = null, $params = [], $listValuesFunc = null, $postFormatFunc = 'sortASC')
    {


        if (is_null($builder)) {
            $builder = $this->newQuery();
        }

        $key = array_get($params,'key',$this->getKeyName());

        if (is_string($columns)) {
            $columns = [$columns];
        }
        if ($columns === null) {
            $columns = $this->getColumnsForSelectList();
        }
        $columns = [$key . ' as '.$this->keynameInList] + $columns;

        $separator = array_get($params,'separator',$this->getSeparatorForSelectList());
        $maxItems = array_get($params,'max_items',$this->getMaxItemsForSelectList());
        $distinct = array_get($params,'distinct',false);
        $orderColumns = array_get($params,'order',$this->getDefaultOrderColumns());


        //IMPOSTO ORDINAMENTO
        foreach ($orderColumns as $orderColumn => $orderType) {
            $listBuilder = $builder->orderBy($orderColumn, $orderType);
        }

        //IMPOSTO LIMITE ELEMENTI
        if ($maxItems) {
            $listBuilder = $listBuilder->take($maxItems);
        }

//        Log::info($listBuilder->toSql());



        $list = $builder->select($columns)->get();

        $ids = $list->lists($this->keynameInList)->all();

        if ($listValuesFunc instanceof \Closure) {
            $values = $listValuesFunc($list);
        } elseif (is_string($listValuesFunc)) {
            $listValuesMethod = 'getSelectListValues'.$listValuesFunc;
            $values = $this->$listValuesMethod($list,$columns,$separator);
        } else {
            $values = $this->getSelectListValuesStandard($list,$columns,$separator);
        }

        $selectList = array_combine($ids, $values->toArray());

        if ($distinct) {
            $selectList = array_unique($selectList);
        }

        if ($postFormatFunc instanceof \Closure) {
            $selectList = $postFormatFunc($selectList);
        } elseif (is_string($postFormatFunc)) {
            $postFormatMethod = 'postFormatSelectList'.$postFormatFunc;
            $selectList = $this->$postFormatMethod($selectList);
        }
        
        return $selectList;

    }

    public function postFormatSelectListSortASC($selectList) {
        asort($selectList);
        return $selectList;
    }

    public function postFormatSelectListSortDESC($selectList) {
        arsort($selectList);
        return $selectList;
    }


    public function getSelectListValuesStandard($list,$columns,$separator) {

        $values = $list->map(function ($item) use ($columns, $separator) {
            $value = '';
            foreach ($columns as $column) {
                $value .= $separator . $item->$column;
            }
            return trim($value, $separator);
        });

        return $values;
    }




    public function getColumnsForSelectList($lang = true)
    {

        return $this->columnsForSelectList ?: '*';

//DA VEDERE COME FUNZIOANVA IN CASI DI MULTILINGUA, MA NON SO SE E' DA METTERE QUI!
//        return $this->setCurrentLangFields($this->columnsForSelectList);

    }

    /**
     * @return int
     */
    public function getMaxItemsForSelectList()
    {
        return $this->nItemsForSelectList ?: 'all';
    }

    public function getSeparatorForSelectList()
    {
        return $this->fieldsSeparator;
    }


    public static function getForSelectList51($columns = null, $separator = null, $params = array())
    {


        $model = new static();

        if ($columns === null) {
            $columns = $model->getColumnsForSelectList();
        }
        if ($separator === null) {
            $separator = $model->getFieldsSeparator();
        }

        if (array_get($params, 'nItems', false)) {
            $nItems = $params['nItems'];
        } else {
            $nItems = $model->getNItemsForSelectList();
        }

        $namespace = $model->models_namespace;
        $classed = get_called_class();

        $modelRelativeName = trim_namespace($model->models_namespace, get_called_class());
        $modelNameForPermission = strtoupper(snake_case($modelRelativeName));

        $permissionPrefix = array_get($params,'permissionPrefix','LIST');

        $permission = array_get($params, 'permission', $permissionPrefix.'_' . $modelNameForPermission);

        $listBuilder = Acl::query($model, $permission, $model->getTable() . '.' . $model->getKeyName());

        $filters = array_get($params, 'filters', array());

        foreach ($filters as $filter) {
            $filter_field = array_get($filter, 'field', 'id');
            $filter_operator = array_get($filter, 'operator', '=');
            $filter_value = array_get($filter, 'value', -1);
            switch (strtolower($filter_operator)) {
                case 'null':
                    $listBuilder = $listBuilder->whereNull($filter_field);
                    break;
                case 'not_null':
                    $listBuilder = $listBuilder->whereNotNull($filter_field);
                    break;
                case 'in':
                    $listBuilder = $listBuilder->whereIn($filter_field, $filter_value);
                    break;
                case 'not_in':
                    $listBuilder = $listBuilder->whereNotIn($filter_field, $filter_value);
                    break;
                default:
                    $listBuilder = $listBuilder->where($filter_field, $filter_operator, $filter_value);
                    break;
            }

        }

        $orderColumns = array_get($params,'order',$model->getDefaultOrderColumns());

        foreach ($orderColumns as $orderColumn => $orderType) {
            $listBuilder = $listBuilder->orderBy($orderColumn, $orderType);
        }

        //LIMITO A 100
        if (!array_get($params, 'all', false)) {
            $listBuilder = $listBuilder->take($nItems);
        }

//        Log::info($listBuilder->toSql());


        $list = $listBuilder->select($model->getTable() . '.*')->get();

        $ids = $list->lists($model->getKeyName())->all();

        $values = static::getForSelectListValues($list,$columns,$separator,$params);


        $return_array = array_combine($ids, $values->toArray());

        if (array_get($params, 'filter_all', false)) {
            $return_array = array(env('FORM_FILTER_ALL', -99) => trans_uc('app.filter_all')) + $return_array;
        }

        $itemNoneArray = array(env('FORM_ITEM_NONE', -99) => trans_uc('app.item_none'));
        $itemNoneParam = array_get($params, 'item_none', null);
        if ($itemNoneParam || (is_null($itemNoneParam) && $model->itemNoneForSelectList)) {
            $return_array = $itemNoneArray + $return_array;
        }

        return $return_array;

    }


    public function getForSelectListVola($records = null,$fieldValues = null,$fieldKey = null,$separator = null,$distinct = false, $postFormat = 'sortASC') {

        if (is_null($records)) {
            $records = $this->getRecords();
        }
        $separator = $separator ? $separator : $this->forSelectSeparator;
        $fieldValues = $fieldValues ? $fieldValues : $this->forSelectValuesFields;
        $fieldKey = $fieldKey ? $fieldKey : $this->getPkName();

        $obj_select = array();
        foreach ($records as $record) {
            $key = $record->get($fieldKey);
            if (array_key_exists($key,$obj_select)) {
                continue;
            }
            $values = '';
            foreach ($fieldValues as $fieldValue) {
                $values .= $record->get($fieldValue) . $separator;
            }
            if ($values) {
                $values = substr($values,0,-strlen($separator));
            }
            $obj_select[$key] = $values;
        }

        if ($distinct) {
            $obj_select = array_unique($obj_select);
        }

        $postformatMethodName = 'selectList'.studly_case($postFormat);
        if (method_exists($this,$postformatMethodName)) {
            $obj_select = $this->$postformatMethodName($obj_select);
        }

        return $obj_select;
    }






    public function getNItemsAutoComplete()
    {
        return $this->nItemsAutoComplete;
    }

    public function getColumnsSearchAutoComplete($lang = true)
    {
        if (!$lang) {
            return $this->columnsSearchAutoComplete;
        }

        return $this->setCurrentLangFields($this->columnsSearchAutoComplete);
    }


    public static function autoComplete($fields = null, $labelColumns = null, $n_items = null, $separator = null, $builder = null)
    {
        $model = new static();

        if ($fields === null) {
            $fields = $model->getColumnsSearchAutoComplete();
        }
        if (is_string($fields)) {
            $fields = array($fields);
        }
        if ($labelColumns === null) {
            $labelColumns = $model->getColumnsForSelectList();
        }
        if (is_string($labelColumns)) {
            $labelColumns = array($labelColumns);
        }
        if ($separator === null) {
            $separator = $model->getFieldsSeparator();
        }
        $term = Input::get('term', '');

        $startBuilder = $builder ? $builder : $model;
        $modelBuilder = clone $startBuilder;
        $modelBuilder = $modelBuilder->where(function ($query) use ($fields, $term) {
            foreach ($fields as $field) {
                $query->orWhere($field, 'LIKE', '%' . $term . '%');
            }
        });
        $standardResultCount = $modelBuilder->limit(1)->count();

        $completionItems = array();

        if ($standardResultCount > 0) {

            $idsToExclude = array();
            if ($n_items === null) {
                $n_items = $model->getNItemsAutoComplete();
            }

            //MATCHING ESATTO
            $modelBuilder = clone $startBuilder;
            $modelBuilder = $modelBuilder->where(function ($query) use ($fields, $term) {
                foreach ($fields as $field) {
                    $query->orWhere($field, $term);
                }
            });
            $exactCompletionResult = $modelBuilder->limit($n_items)->get();

            list($exactCompletionItems, $n_items_matched, $ids_matched) = $model->setCompletionItem($exactCompletionResult,
                $labelColumns, $separator);

            $idsToExclude = $ids_matched;
            $idsToExclude[] = -1;
            $n_items = $n_items - $n_items_matched;

            $completionItems = $exactCompletionItems->toArray();

            if ($n_items > 0) {
                //MATCHING PARZIALE START
                $modelBuilder = clone $startBuilder;
                $modelBuilder = $modelBuilder->whereNotIn($model->getKeyName(), $idsToExclude);

                $modelBuilder = $modelBuilder->where(function ($query) use ($term, $fields) {
                    foreach ($fields as $field) {
                        $query->orWhere($field, 'LIKE', $term . '%');
                    }
                    return $query;
                });
                $startCompletionResult = $modelBuilder->limit($n_items)->get();

                list($startCompletionItems, $n_items_matched, $ids_matched) = $model->setCompletionItem($startCompletionResult,
                    $labelColumns, $separator);

                $idsToExclude = array_merge($idsToExclude, $ids_matched);
                $n_items = $n_items - $n_items_matched;

                $completionItems = array_merge($completionItems, $startCompletionItems->toArray());

                if ($n_items > 0) {
                    //MATCHING PARZIALE
                    $modelBuilder = clone $startBuilder;
                    $modelBuilder = $modelBuilder->whereNotIn($model->getKeyName(), $idsToExclude);
                    $modelBuilder = $modelBuilder->where(function ($query) use ($term, $fields) {
                        foreach ($fields as $field) {
                            $query->orWhere($field, 'LIKE', '%' . $term . '%');
                        }
                        return $query;
                    });

                    $standardCompletionResult = $modelBuilder->limit($n_items)->get();

                    list($standardCompletionItems, $n_items_matched, $ids_matched) = $model->setCompletionItem($standardCompletionResult,
                        $labelColumns, $separator);

                    $idsToExclude = $ids_matched;
                    $n_items = $n_items - $n_items_matched;

                    $completionItems = array_merge($completionItems, $standardCompletionItems->toArray());
                }
            }
        }


        return $completionItems;
    }

    public function setCompletionItem($result, $labelColumns, $separator)
    {
        $n_items = $result->count();

        $ids = $result->lists('id')->all();

        $items = $result->map(function ($item) use ($labelColumns, $separator) {
            $labelValue = '';
            foreach ($labelColumns as $column) {
                $chunks = explode('.', $column);
                if (count($chunks) > 1) {
                    $relation = $chunks[0];
                    $column = $chunks[1];
                    $labelValue .= $separator . $item->$relation->$column;
                } else {
                    $labelValue .= $separator . $item->$column;
                }
            }
            $labelValue = trim($labelValue, $separator);

            $idValue = $item->getKey();
            return array(
                'id' => $idValue,
                'label' => $labelValue,
                'data' => $item->toArray(),
                'morph_id' => $idValue,
                'morph_type' => ltrim(get_class($item), "\\"),
            );
        });

        return array($items, $n_items, $ids);
    }

    public function setDefaultOrderColumns($columns = array())
    {
        $this->defaultOrderColumns = $columns;
    }

    public function getDefaultOrderColumns($lang = true)
    {
        if (!$lang) {
            return $this->defaultOrderColumns;
        }

        $order_lang = $this->setCurrentLangFields(array_keys($this->defaultOrderColumns));
        return array_combine($order_lang, array_values($this->defaultOrderColumns));

    }



}
