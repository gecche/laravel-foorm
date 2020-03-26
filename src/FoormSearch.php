<?php

namespace Gecche\Foorm;


use Gecche\DBHelper\Facades\DBHelper;
use Gecche\Breeze\Breeze;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FoormSearch extends Foorm
{

    use ConstraintBuilderTrait;
    use FoormDetailSearchTrait;

    protected $extraDefaults = [];


    public function __call($name, $arguments)
    {

        $prefixes = ['ajaxListing'];

        foreach ($prefixes as $prefix) {
            if (Str::startsWith($name, $prefix)) {
                return call_user_func_array(array($this, $prefix), $arguments);
            }
        }
        throw new \BadMethodCallException("Method [$name] does not exist.");

    }
}
