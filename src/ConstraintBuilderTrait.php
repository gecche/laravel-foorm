<?php

namespace Gecche\Foorm;

use Carbon\Carbon;
use Gecche\ModelPlus\ModelPlus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;

trait ConstraintBuilderTrait
{


    /**
     * The main method for building a constraint
     *
     * @param $builder
     * @param $field
     * @param $value
     * @param string $op
     * @param array $params
     * @return mixed
     */
    public function buildConstraint($builder, $field, $value, $op = '=', $params = [])
    {

        $studly_op = studly_case($op);
        switch ($studly_op) {
            case 'Like':
                return $builder->where($field, 'LIKE', '%' . $value . '%');
            case 'Date':
                return $builder->where($field, '>=', $value . ' 00:00:00')
                    ->where($field, '<=', $value . ' 23:59:59');
            case 'IsNull':
                return $builder->whereNull($field);
            case 'IsNotNull':
                return $builder->whereNotNull($field);
            case 'In':
            case 'NotIn':
                if (empty($value)) {
                    $value = array(-1);
                }
                if (!is_array($value)) {
                    $value = array($value);
                }
                if ($studly_op == 'In') {
                    return $builder->whereIn($field, $value);
                } else {
                    return $builder->whereNotIn($field, $value);
                }
            case 'Between':
                if (!is_array($value)) {
                    return $builder;
                }
                $value1 = array_get($value, 0);
                $value2 = array_get($value, 1);
                if (!$value2) {
                    return $builder->where($field, '>=', $value1);
                }
                if (!$value1) {
                    return $builder->where($field, '<=', $value2);
                }
                return $builder->whereBetween($field, [$value1, $value2]);
            default:
                $methodName = 'buildSearchFilter' . $studly_op;

                if (method_exists($this, $methodName)) {
                    return $this->$methodName($builder, $field, $value, $params);
                }

                return $builder->where($field, $op, $value);
        }
    }


    /*
     * Constraint method for handling a field value which should between two dates passed in
     */
    public function buildConstraintDateIn($builder, $field, $value, $params = [])
    {

        if (!is_array($value)) {
            throw new \InvalidArgumentException("The value for a date_in constraint should be an array of dates");
        }

        $firstValue = array_get($value, 0, false);
        $secondValue = array_get($value, 1, false);
        $invalidInterval = false;
        if (!$firstValue && !$secondValue) {
            $invalidInterval = true;
        }

        $firstDate = null;
        $secondDate = null;

        if (!$invalidInterval) {

            $firstDate = $this->checkDateArg($firstValue);

            $secondDate = $this->checkDateArg($secondValue, true);

            if (!$firstDate && !$secondDate) {
                $invalidInterval = true;
            }

        }

        if ($invalidInterval) {
            throw new \InvalidArgumentException("The value for a date_in constraint should be an array of dates");
        }

        if (!$firstDate) {
            return $builder->where($field, '<=', $secondValue);
        }

        if (!$secondDate) {
            return $builder->where($field, '>=', $firstValue);
        }

        return $builder->where($field, '>=', $firstValue)
            ->where($field, '<=', $secondValue);


    }


    /*
     * Constraint method for handling an intersection of two intervals of dates:
     * the first interval built from two field values
     * and the second interval passed in as the value
     */
    public function buildConstraintDateIntersection($builder, $field, $value, $params = [])
    {

        if (!is_array($value)) {
            throw new \InvalidArgumentException("The value for a date_intersection constraint should be an array of two dates");
        }

        $firstValue = array_get($value, 0, false);
        $secondValue = array_get($value, 1, false);
        if (!$firstValue || !$secondValue) {
            throw new \InvalidArgumentException("The value for a date_intersection constraint should be an array of two dates");
        }


        $firstDate = $this->checkDateArg($firstValue);

        $secondDate = $this->checkDateArg($secondValue, true);

        if (!$firstDate || !$secondDate) {
            throw new \InvalidArgumentException("The value for a date_intersection constraint should be an array of two dates");
        }

        $endField = array_get($params, 'end_date_field', false);
        if (!$endField) {
            throw new \InvalidArgumentException("The date_intersection constraint expect an 'end_date_field' as a param.");
        }

        $resultQuery = $builder->where(function ($query) use ($field, $endField, $firstValue, $secondValue) {
            $query->orWhere(function ($query) use ($field, $endField, $firstValue, $secondValue) {
                $query->where($field, '>=', $firstValue)
                    ->where($field, '<=', $secondValue);
            })->orWhere(function ($query) use ($field, $endField, $firstValue, $secondValue) {
                $query->where($endField, '>=', $firstValue)
                    ->where($endField, '<=', $secondValue);
            })->orWhere(function ($query) use ($field, $endField, $firstValue, $secondValue) {
                $query->where($endField, '>', $secondValue)
                    ->where($field, '<=', $firstValue);
            });
        });

        return $resultQuery;


    }

    /*
     * Constraint for handling the jquery datatable search standard format
     */
    public function buildSearchFilterDatatable($builder, $field, $value, $params = [])
    {

        $searchFields = array_get($params, 'datatable_fields', []);
        if (!is_array($searchFields)) {
            return $builder;
        }


        $value = is_array($value) ? $value[0] : $value;


        $builder->where(function ($query) use ($value, $searchFields) {
            foreach ($searchFields as $searchField) {
                $query->orWhere($searchField, 'LIKE', '%' . $value . '%');
            }
        });

        return $builder;

    }


    public function buildConstraintRelation($relation, $builder, $field, $value, $op = '=', $params = [])
    {

        $studly_op = studly_case($op);


        switch ($studly_op) {
            case 'Like':
                return $builder->whereHas($relation, function ($q) use ($field, $value) {
                    $q->where($field, 'LIKE', '%' . $value . '%');
                });
            case 'Date':
                return $builder->whereHas($relation, function ($q) use ($field, $value) {
                    $q->where($field, '>=', $value . ' 00:00:00')
                        ->where($field, '<=', $value . ' 23:59:59');
                });
            case 'IsNull':
                return $builder->whereHas($relation, function ($q) use ($field) {
                    $q->whereNotNull($field);
                });
            case 'IsNotNull':
                return $builder->whereHas($relation, function ($q) use ($field) {
                    $q->whereNull($field);
                });
            case 'In':
            case 'NotIn':
                if (empty($value)) {
                    $value = array(-1);
                }
                if (!is_array($value)) {
                    $value = array($value);
                }
                if ($studly_op == 'In') {
                    return $builder->whereHas($relation, function ($q) use ($field, $value) {
                        $q->whereIn($field, $value);
                    });
                } else {
                    return $builder->whereHas($relation, function ($q) use ($field, $value) {
                        $q->whereNotIn($field, $value);
                    });
                }
            case 'Between':
                if (!is_array($value)) {
                    return $builder;
                }
                $value1 = array_get($value, 0, false);
                $value2 = array_get($value, 1, false);
                if (!$value2) {
                    return $builder->whereHas($relation, function ($q) use ($field, $value1) {
                        $q->where($field, '>=', $value1);
                    });
                }
                if (!$value1) {
                    return $builder->whereHas($relation, function ($q) use ($field, $value2) {
                        $q->where($field, '<=', $value2);
                    });
                }
                return $builder->whereHas($relation, function ($q) use ($field, $value1, $value2) {
                    $q->whereBetween($field, [$value1, $value2]);
                });
            default:
                $methodName = 'buildSearchFilterRelation' . $studly_op;

                if (method_exists($this, $methodName)) {
                    return $this->$methodName($builder, $field, $value, $params);
                }

                return $builder->whereHas($relation, function ($q) use ($field, $value, $op) {
                    $q->where($field, $op, $value);
                });
        }
    }

    /*
     * Constraint method for handling a relation field value which should between two dates passed in
     */
    public function buildConstraintRelationDateIn($relation, $builder, $field, $value, $params = [])
    {

        if (!is_array($value)) {
            throw new \InvalidArgumentException("The value for a date_in constraint should be an array of dates");
        }

        $firstValue = array_get($value, 0, false);
        $secondValue = array_get($value, 1, false);
        $invalidInterval = false;
        if (!$firstValue && !$secondValue) {
            $invalidInterval = true;
        }

        $firstDate = null;
        $secondDate = null;

        if (!$invalidInterval) {

            $firstDate = $this->checkDateArg($firstValue);

            $secondDate = $this->checkDateArg($secondValue, true);

            if (!$firstDate && !$secondDate) {
                $invalidInterval = true;
            }

        }

        if ($invalidInterval) {
            throw new \InvalidArgumentException("The value for a date_in constraint should be an array of dates");
        }

        if (!$firstDate) {
            return $builder->whereHas($relation, function ($q) use ($field, $secondValue) {
                $q->where($field, '<=', $secondValue);
            });

        }

        if (!$secondDate) {
            return $builder->whereHas($relation, function ($q) use ($field, $firstValue) {
                $q->where($field, '>=', $firstValue);
            });

        }

        return $builder->whereHas($relation, function ($q) use ($field, $firstValue, $secondValue) {
            $q->where($field, '>=', $firstValue)
                ->where($field, '<=', $secondValue);
        });


    }


    /*
     * Constraint method for handling an intersection of two intervals of dates:
     * the first interval built from two field values
     * and the second interval passed in as the value
     */
    public function buildConstraintRelationDateIntersection($relation, $builder, $field, $value, $params = [])
    {

        if (!is_array($value)) {
            throw new \InvalidArgumentException("The value for a date_intersection constraint should be an array of two dates");
        }

        $firstValue = array_get($value, 0, false);
        $secondValue = array_get($value, 1, false);
        if (!$firstValue || !$secondValue) {
            throw new \InvalidArgumentException("The value for a date_intersection constraint should be an array of two dates");
        }


        $firstDate = $this->checkDateArg($firstValue);

        $secondDate = $this->checkDateArg($secondValue, true);

        if (!$firstDate || !$secondDate) {
            throw new \InvalidArgumentException("The value for a date_intersection constraint should be an array of two dates");
        }

        $endField = array_get($params, 'end_date_field', false);
        if (!$endField) {
            throw new \InvalidArgumentException("The date_intersection constraint expect an 'end_date_field' as a param.");
        }

        $resultQuery = $builder->whereHas($relation, function ($qRel) use ($field, $endField, $firstValue, $secondValue) {

            $qRel->where(function ($query) use ($field, $endField, $firstValue, $secondValue) {
                $query->orWhere(function ($query) use ($field, $endField, $firstValue, $secondValue) {
                    $query->where($field, '>=', $firstValue)
                        ->where($field, '<=', $secondValue);
                })->orWhere(function ($query) use ($field, $endField, $firstValue, $secondValue) {
                    $query->where($endField, '>=', $firstValue)
                        ->where($endField, '<=', $secondValue);
                })->orWhere(function ($query) use ($field, $endField, $firstValue, $secondValue) {
                    $query->where($endField, '>', $secondValue)
                        ->where($field, '<=', $firstValue);
                });
            });
        });

        return $resultQuery;

    }

    protected function checkDateArg($date, $endDate = false)
    {
        if (strlen($date) == 10) {
            $timeSuffix = $endDate ? ' 23:59:59' : ' 00:00:00';
            $date .= $timeSuffix;
        }
        try {
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $date);
        } catch (\Exception $e) {
            $date = null;
        }
        return $date;

    }

    protected function applyConstraint(array $constraintArray)
    {


        $field = array_get($constraintArray, 'field', null);

        if (!$field || !is_string($field) || !array_key_exists('value', $constraintArray)) {
            return $this->formBuilder;
        };

        $value = $constraintArray['value'];

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

}
