<?php

namespace Gecche\Foorm;

use Gecche\Foorm\Contracts\ListBuilder;
use Gecche\Breeze\Breeze;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

abstract class FoormAction
{

    /**
     * @var array
     */
    protected $input;

    /**
     * @var Breeze
     */
    protected $model;

    /**
     * @var array
     */
    protected $params;

    /**
     * @var array
     */
    protected $config;

    protected $foorm;

    protected $actionResult;

    /**
     * FormList constructor.
     * @param array $input
     * @param Breeze $model
     * @param array $params
     */
    public function __construct(array $config, Foorm $foorm, Breeze $model, array $input, $params = [])
    {

        $this->input = $input;
        $this->model = $model;
        $this->params = $params;
        $this->config = $config;
        $this->foorm = $foorm;

        $this->input = $this->filterPredefinedValuesFromInput($this->input);


        $this->init();

    }

    protected function init() {
        return;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param array $config
     */
    public function setConfig($config)
    {
        $this->config = array_merge($this->config, $config);
    }


    /**
     * @return array
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * @return Breeze
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }


    public function filterPredefinedValuesFromInput($value) {

        $foormConfig = $this->foorm->getConfig();
        $nullValue = $foormConfig['null-value'];
        $anyValue = $foormConfig['null-value'];
        $noValue = $foormConfig['no-value'];

        if (is_string($value)) {
          if (in_array($value,[$nullValue,$anyValue])) {
            return null;
          } elseif (in_array($value,[$noValue])) {
              return 'no-value';
          }
          return $value;
        }

        if (is_array($value)) {
            foreach ($value as $valuePartKey => $valuePart) {
                $value[$valuePartKey] = $this->filterPredefinedValuesFromInput($valuePart);
            }
        }

        return $value;
    }

    abstract public function performAction();

    /**
     * @return Foorm
     */
    public function getFoorm(): Foorm
    {
        return $this->foorm;
    }

    /**
     * @param Foorm $foorm
     */
    public function setFoorm(Foorm $foorm): void
    {
        $this->foorm = $foorm;
    }

    /**
     * @return mixed
     */
    public function getActionResult()
    {
        return $this->actionResult;
    }

    /**
     * @param mixed $actionResult
     */
    public function setActionResult($actionResult): void
    {
        $this->actionResult = $actionResult;
    }


    abstract public function validateAction();

    public function getModelsNamespace()
    {
        return Arr::get($this->config,'models_namespace');
    }


}
