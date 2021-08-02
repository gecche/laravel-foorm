<?php

namespace Gecche\Foorm;

use Carbon\Carbon;
use Gecche\Breeze\Breeze;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

trait OrderBuilderTrait
{

    /**
     * The main method for building a constraint
     *
     * @param $builder
     * @param $field
     * @param string $direction = ASC|DESC
     * @param array $params
     * @return mixed
     */
    public function buildOrder($builder, $field, $direction = 'ASC', $params = [])
    {

        if ($direction != 'DESC') {
            $direction = 'ASC';
        }

        $methodName = 'buildOrder' . Str::studly($field);

        if (method_exists($this, $methodName)) {
            return $this->$methodName($builder, $field, $direction, $params);
        }

        $table = Arr::get($params, 'table', $this->model->getTable());
        $db = Arr::get($params, 'db');

        $dbField = $db ? $db . '.' : '';
        $dbField .= $table ? $table . '.' : '';
        $dbField .= $field;

        return $builder->orderBy($dbField, $direction);

    }


}
