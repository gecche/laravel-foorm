<?php

namespace Gecche\Foorm;

use Carbon\Carbon;
use Gecche\Breeze\Breeze;
use Illuminate\Support\Arr;
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

        $methodName = 'buildSearchFilter' . $studly_op;

        if (method_exists($this, $methodName)) {
            return $this->$methodName($builder, $field, $value, $params);
        }

        switch ($studly_op) {
            case 'Like':
                $value = $this->guessInputValue($value);
                if (is_null($value)) {
                    return $builder;
                }
                return $builder->where($field, 'LIKE', '%' . $value . '%');
            case 'Date':
                $value = $this->guessInputValue($value);
                if (is_null($value)) {
                    return $builder;
                }
                return $builder->where($field, '>=', $value . ' 00:00:00')
                    ->where($field, '<=', $value . ' 23:59:59');
            case 'IsNull':
                return $builder->whereNull($field);
            case 'IsNotNull':
                return $builder->whereNotNull($field);
            case 'In':
            case 'NotIn':
                $value = $this->guessInputValue($value,'array');
                if (empty($value)) {
                    $value = [$this->config['null-value']];
                }
                if ($studly_op == 'In') {
                    return $builder->whereIn($field, $value);
                } else {
                    return $builder->whereNotIn($field, $value);
                }
            case 'Between':
                $value = $this->guessInputValue($value,'array');
                $value1 = Arr::get($value, 0);
                $value2 = Arr::get($value, 1);
                if (!$value2) {
                    return $builder->where($field, '>=', $value1);
                }
                if (!$value1) {
                    return $builder->where($field, '<=', $value2);
                }
                return $builder->whereBetween($field, [$value1, $value2]);
            default:
                $value = $this->guessInputValue($value);
                if (is_null($value)) {
                    return $builder;
                }
                if ($value == $this->config['no-value']) {
                    return $builder->whereNull($field);
                }
                return $builder->where($field, $op, $value);
        }
    }


    /*
     * Constraint method for handling a field value which should between two dates passed in
     */
    public function buildConstraintDateIn($builder, $field, $value, $params = [])
    {

        $value = $this->guessInputValue($value,'array');

        $firstValue = Arr::get($value, 0, false);
        $secondValue = Arr::get($value, 1, false);
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
        $value = $this->guessInputValue($value,'array');

        if (!is_array($value)) {
            throw new \InvalidArgumentException("The value for a date_intersection constraint should be an array of two dates");
        }

        $firstValue = Arr::get($value, 0, false);
        $secondValue = Arr::get($value, 1, false);
        if (!$firstValue || !$secondValue) {
            throw new \InvalidArgumentException("The value for a date_intersection constraint should be an array of two dates");
        }


        $firstDate = $this->checkDateArg($firstValue);

        $secondDate = $this->checkDateArg($secondValue, true);

        if (!$firstDate || !$secondDate) {
            throw new \InvalidArgumentException("The value for a date_intersection constraint should be an array of two dates");
        }

        $endField = Arr::get($params, 'end_date_field', false);
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

        $searchFields = Arr::get($params, 'datatable_fields', []);
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

        $methodName = 'buildSearchFilterRelation' . $studly_op;

        if (method_exists($this, $methodName)) {
            return $this->$methodName($builder, $field, $value, $params);
        }

        switch ($studly_op) {
            case 'Like':
                $value = $this->guessInputValue($value);
                return $builder->whereHas($relation, function ($q) use ($field, $value) {
                    $q->where($field, 'LIKE', '%' . $value . '%');
                });
            case 'Date':
                $value = $this->guessInputValue($value);
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
                $value = $this->guessInputValue($value,'array');
                if (empty($value)) {
                    $value = [$this->config['null-value']];
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
                $value = $this->guessInputValue($value,'array');
                $value1 = Arr::get($value, 0, false);
                $value2 = Arr::get($value, 1, false);
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

                $value = $this->guessInputValue($value);
                if ($value == $this->config['no-value']) {
                    return $builder->whereHas($relation, function ($q) use ($field) {
                        $q->whereNotNull($field);
                    });
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

        $value = $this->guessInputValue($value,'array');

        $firstValue = Arr::get($value, 0, false);
        $secondValue = Arr::get($value, 1, false);
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

        $value = $this->guessInputValue($value,'array');

        $firstValue = Arr::get($value, 0, false);
        $secondValue = Arr::get($value, 1, false);
        if (!$firstValue || !$secondValue) {
            throw new \InvalidArgumentException("The value for a date_intersection constraint should be an array of two dates");
        }


        $firstDate = $this->checkDateArg($firstValue);

        $secondDate = $this->checkDateArg($secondValue, true);

        if (!$firstDate || !$secondDate) {
            throw new \InvalidArgumentException("The value for a date_intersection constraint should be an array of two dates");
        }

        $endField = Arr::get($params, 'end_date_field', false);
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

}
