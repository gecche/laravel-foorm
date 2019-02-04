<?php

namespace Gecche\Foorm;

use Carbon\Carbon;
use Gecche\ModelPlus\ModelPlus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;

trait AggregateBuilderTrait
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
    public function buildAggregate($builder, $op, $field = null)
    {

        switch ($op) {
            case 'count':
                return $builder->$op();
            case 'sum':
            case 'avg':
            case 'min':
            case 'max':
                return $builder->$op($field);
            default:
                throw new \InvalidArgumentException("Invalid aggregate function: only count, avg, min, max and sum are allowed.");
        }

    }


    protected function applyAggregates(Builder $builder, array $aggregatesArray)
    {

        $aggregatesData = [];
        foreach ($aggregatesArray as $aggregateField => $aggregateOperators) {

            if (!is_array($aggregateOperators)) {
                $aggregateOperators = [$aggregateOperators];
            }

            foreach ($aggregateOperators as $aggregateOperator) {
                $aggregatesData[$aggregateField] = $this->buildAggregate($builder,$aggregateOperator,$aggregateField);
            }

        }

        return $aggregatesData;

    }

}
