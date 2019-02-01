<?php

namespace Gecche\Foorm\Old;

use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Input;
use Exception;
use Illuminate\Support\Facades\Config;

trait FormListTrait {

    protected $summary = [];

    protected $searchParams = [];

    protected $orderParams = [];



    /**
     * @return array
     */
    public function getSummary()
    {
        return $this->summary;
    }

    /**
     * @param array $summary
     */
    public function setSummary($summary)
    {
        $this->summary = $summary;
    }




    public function getSearchParams() {
        return $this->searchParams;
    }

    public function setSearchParams() {
        return true;
    }

    public function buildSearchFilter($key, $value, $op = '=', $builder = null) {
        return $builder;
    }

    public function buildOrder($key, $direction, $builder = null) {
        return $builder;
    }

}
