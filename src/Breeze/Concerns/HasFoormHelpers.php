<?php

namespace Gecche\Foorm\Breeze\Concerns;

use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Arr;

trait HasFoormHelpers
{
    public $defaultOrderColumns = ['id' => 'ASC'];
    public $columnsForSelectList = ['id'];
    public $columnsSearchAutoComplete = ['id'];
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

        $key = Arr::get($params,'key',$this->getKeyName());

        if (is_null($columns)) {
            $columns = $this->getColumnsForSelectList();
        }
        $columns = Arr::wrap($columns);

        $columns = [-1 => $key . ' as '.$this->keynameInList] + $columns;

        $separator = Arr::get($params,'separator',$this->getSeparatorForSelectList());
        $maxItems = Arr::get($params,'max_items',$this->getMaxItemsForSelectList());
        $distinct = Arr::get($params,'distinct',false);
        $orderColumns = Arr::get($params,'order',$this->getDefaultOrderColumns());


        //IMPOSTO ORDINAMENTO
        foreach ($orderColumns as $orderColumn => $orderType) {
            $builder->orderBy($orderColumn, $orderType);
        }

        //IMPOSTO LIMITE ELEMENTI
        if ($maxItems) {
            $builder->take($maxItems);
        }

//        Log::info($listBuilder->toSql());



        $list = $builder->select($columns)->get();

        $ids = $list->pluck($this->keynameInList)->all();

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




    public function getNItemsAutoComplete()
    {
        return $this->nItemsAutoComplete;
    }

    public function getColumnsSearchAutoComplete($lang = true)
    {
        return $this->columnsSearchAutoComplete ? $this->columnsSearchAutoComplete : $this->getKeyName();
//        return $this->setCurrentLangFields($this->columnsSearchAutoComplete);
    }


    public static function autoComplete($value, $fields = null, $labelColumns = null, $n_items = null, $builder = null)
    {
        $model = new static();

        if ($fields === null) {
            $fields = $model->getColumnsSearchAutoComplete();
        }
        $fields = Arr::wrap($fields);

        if ($labelColumns === null) {
            $labelColumns = $model->getColumnsForSelectList();
        }
        $labelColumns = Arr::wrap($labelColumns);

//        if ($separator === null) {
//            $separator = $model->getFieldsSeparator();
//        }

        $startBuilder = $builder ? $builder : $model;
        $modelBuilder = clone $startBuilder;
        $modelBuilder = $modelBuilder->where(function ($query) use ($fields, $value) {
            foreach ($fields as $field) {
                $query->orWhere($field, 'LIKE', '%' . $value . '%');
            }
        });
        $standardResultCount = $modelBuilder->limit(1)->count();

        $completionItems = [];

        if ($standardResultCount > 0) {

            $idsToExclude = [];
            if ($n_items === null) {
                $n_items = $model->getNItemsAutoComplete();
            }

            //MATCHING ESATTO
            $modelBuilder = clone $startBuilder;
            $modelBuilder = $modelBuilder->where(function ($query) use ($fields, $value) {
                foreach ($fields as $field) {
                    $query->orWhere($field, $value);
                }
            });
            $exactCompletionResult = $modelBuilder->limit($n_items)->get();

            list($exactCompletionItems, $n_items_matched, $ids_matched) =
                $model->setCompletionItem($exactCompletionResult,$labelColumns);

            $idsToExclude = $ids_matched;
            $idsToExclude[] = -1;
            $n_items = $n_items - $n_items_matched;

            $completionItems = $exactCompletionItems->toArray();

            if ($n_items > 0) {
                //MATCHING PARZIALE START
                $modelBuilder = clone $startBuilder;
                $modelBuilder = $modelBuilder->whereNotIn($model->getKeyName(), $idsToExclude);

                $modelBuilder = $modelBuilder->where(function ($query) use ($value, $fields) {
                    foreach ($fields as $field) {
                        $query->orWhere($field, 'LIKE', $value . '%');
                    }
                    return $query;
                });
                $startCompletionResult = $modelBuilder->limit($n_items)->get();

                list($startCompletionItems, $n_items_matched, $ids_matched) =
                    $model->setCompletionItem($startCompletionResult,$labelColumns);

                $idsToExclude = array_merge($idsToExclude, $ids_matched);
                $n_items = $n_items - $n_items_matched;

                $completionItems = array_merge($completionItems, $startCompletionItems->toArray());

                if ($n_items > 0) {
                    //MATCHING PARZIALE
                    $modelBuilder = clone $startBuilder;
                    $modelBuilder = $modelBuilder->whereNotIn($model->getKeyName(), $idsToExclude);
                    $modelBuilder = $modelBuilder->where(function ($query) use ($value, $fields) {
                        foreach ($fields as $field) {
                            $query->orWhere($field, 'LIKE', '%' . $value . '%');
                        }
                        return $query;
                    });

                    $standardCompletionResult = $modelBuilder->limit($n_items)->get();

                    list($standardCompletionItems, $n_items_matched, $ids_matched) =
                        $model->setCompletionItem($standardCompletionResult,$labelColumns);

                    $idsToExclude = $ids_matched;
                    $n_items = $n_items - $n_items_matched;

                    $completionItems = array_merge($completionItems, $standardCompletionItems->toArray());
                }
            }
        }


        return $completionItems;
    }

    public function setCompletionItemOK($result, $labelColumns, $separator)
    {
        $n_items = $result->count();

        $ids = $result->pluck($this->getKeyName())->all();

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
            return $item->toArray();
//            return [
//                'id' => $idValue,
//                'label' => $labelValue,
//                'data' => $item->toArray(),
//                'morph_id' => $idValue,
//                'morph_type' => ltrim(get_class($item), "\\"),
//            ];
        });

        return [$items, $n_items, $ids];
    }

    public function setCompletionItem($result, $labelColumns)
    {

        $ids = $result->pluck($this->getKeyName())->all();

        $items = $result->toArray();

        $n_items = count($items);



        $items = $result->map(function ($item) use ($labelColumns) {
            $filteredItem = [];

            foreach ($labelColumns as $column) {
                $chunks = explode('|', $column);
                if (count($chunks) > 1) {
                    $relation = $chunks[0];
                    $columnField = $chunks[1];
                    if (!array_key_exists($relation,$item) ||
                        !array_key_exists($columnField,$item[$relation])) {
                        continue;
                    }
                    $columnValue = $item[$relation][$columnField];
                } else {
                    $columnValue = Arr::get($item,$column);
                }
                $filteredItem[$column] = $columnValue;
            }

            return $filteredItem;
        });


        return [$items, $n_items, $ids];
    }




    public function setDefaultOrderColumns($columns = [])
    {
        $this->defaultOrderColumns = $columns;
    }

    public function getDefaultOrderColumns($lang = true)
    {
        if (!$lang) {
            return $this->defaultOrderColumns;
        }

        return $this->defaultOrderColumns;
        $order_lang = $this->setCurrentLangFields(array_keys($this->defaultOrderColumns));
        return array_combine($order_lang, array_values($this->defaultOrderColumns));

    }

    public function getFieldsSeparator() {
        return $this->fieldsSeparator;
    }




}
