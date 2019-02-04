<?php

namespace Gecche\Foorm;

use Carbon\Carbon;
use Gecche\ModelPlus\ModelPlus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;

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

        $methodName = 'buildOrder' . studly_case($field);

        if (method_exists($this, $methodName)) {
            return $this->$methodName($builder, $field, $direction, $params);
        }

        $table = array_get($params, 'table', $this->model->getTable());
        $db = array_get($params, 'db');

        $dbField = $db ? $db . '.' : '';
        $dbField .= $table ? $table . '.' : '';
        $dbField .= $field;

        return $builder->orderBy($dbField, $direction);

    }


}
