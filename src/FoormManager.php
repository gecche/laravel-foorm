<?php

namespace Gecche\Foorm;

use Gecche\Breeze\Breeze;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FoormManager
{

    protected $baseConfig;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var string
     */
    protected $foormName;

    /**
     * @var string
     */
    protected $foormModel;

    /**
     * @var string
     */
    protected $foormType;

    protected $normalizedFoormType;

    /**
     * @var array
     */

    protected $defaultConfig;

    protected $config;

    protected $model;


    protected $params;

    protected $inputManipulationFunction;

    protected $actionConfig;


    /**
     * @return mixed
     */
    public function getFoorm($formName, Request $request, $params = [])
    {
        $formNameParts = explode('.', $formName);
        if (count($formNameParts) != 2) {
            throw new \InvalidArgumentException('A foorm name should be of type "<FORMNAME>.<FORMTYPE>".');
        }


        $this->foormName = $formName;
        $this->foormModel = $formNameParts[0];
        $this->foormType = $formNameParts[1];

        $this->request = $request;
        $this->buildParams($params);
        $this->getConfig();
        $this->setModel();

        return $this->setFoorm();
    }

    /**
     * FormList constructor.
     * @param array $input
     * @param Breeze $model
     * @param array $params
     */
    public function __construct($baseConfig)
    {

        $this->baseConfig = $baseConfig;


    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return string
     */
    public function getFoormName()
    {
        return $this->foormName;
    }

    public function getNormalizedFoormType() {
        return $this->normalizedFoormType;
    }

    public function getFoormType() {
        return $this->foormType;
    }


    public function buildParams($params)
    {
//        $this->setFixedConstraintsToParams($params);
        $this->params = $params;
    }

    protected function setFixedConstraintsToParams($params)
    {
        if (is_array(Arr::get($params, 'fixed_constraints'))) {
            return $params;
        }

        $requestFixedConstraints = $this->request->input('fixed_constraints');
        if (is_array($requestFixedConstraints)) {
            $params['fixed_constraints'] = $requestFixedConstraints;
        }

        return $params;
    }

    protected function fallbackFormName($formName)
    {
        $formNameParts = explode('.', $this->foormName);


        $formTypeFallbacks = config('foorm.types_fallbacks', []);
        if (!Arr::get($formTypeFallbacks, $this->foormType)) {
            return $formName;
        }

        return $formNameParts[0] . '.' . $formTypeFallbacks[$this->foormType];

    }


    public function getConfig()
    {

        $defaultConfig = $this->baseConfig;

        $typeDefaults = Arr::get(Arr::get($defaultConfig, 'types_defaults', []), $this->foormType, []);

        unset($defaultConfig['types_defaults']);

        $defaultConfig = array_merge($defaultConfig, $typeDefaults);

        $formConfig = $this->getFormTypeConfig($this->foormName);

        $finalConfig = array_replace_recursive($defaultConfig, $formConfig);

        $snakeModelName = Arr::get($formConfig, 'model', $this->foormModel);
        $relativeModelName = Str::studly($snakeModelName);
        $fullModelName = $finalConfig['models_namespace'] . $relativeModelName;

        if (!class_exists($fullModelName))
            throw new \InvalidArgumentException("Model class $fullModelName does not exists");

        $finalConfig = array_merge($finalConfig, $this->getRealFoormClass($formConfig, $relativeModelName, $this->foormType));
        $this->normalizedFoormType = Arr::get($finalConfig,'form_type',$this->foormType);

        $finalConfig['model'] = $snakeModelName;
        $finalConfig['relative_model_name'] = $relativeModelName;
        $finalConfig['full_model_name'] = $fullModelName;

        foreach (Arr::get($formConfig, 'dependencies', []) as $dependencyKey => $dependencyFormType) {
            $dependencyConfig = $this->getFormTypeConfig($this->foormModel . '.' . $dependencyFormType);


            $dependencyConfig = array_replace_recursive($defaultConfig, $dependencyConfig);
            $dependencyConfig = array_merge($dependencyConfig, $this->getRealFoormClass($formConfig, $relativeModelName, $dependencyFormType));

            $dependencyConfig['model'] = $snakeModelName;
            $dependencyConfig['relative_model_name'] = $relativeModelName;
            $dependencyConfig['full_model_name'] = $fullModelName;


            $finalConfig['dependencies'][$dependencyKey] = $dependencyConfig;
        }

        $this->config = $finalConfig;

        return $finalConfig;

    }

    public function getRelativeModelName() {
        return Arr::get($this->config,'relative_model_name');
    }

    public function getFullModelName() {
        return Arr::get($this->config,'full_model_name');
    }

    protected function getFormTypeConfig($formName)
    {
        $formConfig = config('foorms.' . $formName, false);

        if (!is_array($formConfig)) {
            $formConfig = config('foorms.' . $this->fallbackFormName($formName), false);
            if (!is_array($formConfig)) {
                throw new \InvalidArgumentException('Configuration of foorm ' . $formName . ' not found');
            }
        }

        return $formConfig;
    }

    protected function getRealFoormClass($formConfig, $relativeModelName, $formNameToCheck)
    {
        $snakeFormName = Arr::get($formConfig, 'form_type', $formNameToCheck);
        $relativeFormName = Str::studly($snakeFormName);
        $fullFormName = $this->baseConfig['foorms_namespace'] . $relativeModelName . "\\Foorm" . $relativeFormName;


        if (!class_exists($fullFormName)) {//Example: exists App\Foorm\User\List class?

            $fullFormName = $this->baseConfig['foorms_namespace'] . $relativeFormName;
            if (!class_exists($fullFormName)) {//Example: exists App\Foorm\List class?
                $fullFormName = $this->baseConfig['foorms_defaults_namespace'] . 'Foorm' . $relativeFormName;

                if (!class_exists($fullFormName)) {//Example: exists Gecche\Foorm\List class?
                    throw new \InvalidArgumentException("Foorm class not found");
                }

            }

        }

        return [
            'form_type' => $snakeFormName,
            'relative_form_name' => $relativeFormName,
            'full_form_name' => $fullFormName,
        ];

    }


    protected function getRealFoormActionClass($action)
    {


        $relativeFormName = Arr::get($this->config, 'relative_form_name');
        $relativeModelName = Arr::get($this->config, 'relative_model_name');
        $fullFormActionName = $this->baseConfig['foorms_namespace'] . $relativeModelName
            . "\\Actions\\" . Str::studly($action);


        if (!class_exists($fullFormActionName)) {//Example: exists App\Foorm\User\List class?

            $fullFormActionName = $this->baseConfig['foorms_namespace'] . "Actions\\" . Str::studly($action);
            if (!class_exists($fullFormActionName)) {//Example: exists App\Foorm\List class?
                $fullFormActionName = $this->baseConfig['foorms_defaults_namespace']
                    . "Actions\\" . Str::studly($action);

                if (!class_exists($fullFormActionName)) {//Example: exists Gecche\Foorm\List class?
                    throw new \InvalidArgumentException("Foorm Action class not found");
                }

            }

        }

        return [
            'full_form_action_name' => $fullFormActionName,
        ];

    }

    /**
     * @param array $config
     */
    public function setConfig($config)
    {
        $this->config = array_merge($this->getConfig(), $config);
    }


    protected function setModel()
    {

        $fullModelName = Arr::get($this->config, 'full_model_name');

        $id = Arr::get($this->params, 'id');
        if ($id) {
            $model = $fullModelName::find($id);
            if (!$model || !$model->getKey()) {
                throw new \InvalidArgumentException("Model $fullModelName with id $id not found.");
            }
        } else {
            $model = new $fullModelName;
        }

        $this->model = $model;
    }

    protected function setFoorm()
    {

        $totalRequestInput = $this->request->input() + $this->request->allFiles();

        $input = $this->setInputForForm($totalRequestInput);

        $fullFormName = Arr::get($this->config, 'full_form_name');


        $foorm = new $fullFormName($this->config, $this->model, $input, $this->params);

        $dependentForms = [];

        foreach (Arr::get($this->config, 'dependencies', []) as $dependencyKey => $dependencyConfig) {
            $dependentFormName = Arr::get($dependencyConfig, 'full_form_name');
            $dependentForms[$dependencyKey] = new $dependentFormName($dependencyConfig, $this->model, $input, $this->params);
        }

        $foorm->setDependentForms($dependentForms);
        return $foorm;

    }


    public function setInputManipulationFunction(\Closure $closure)
    {
        $this->inputManipulationFunction = $closure;
    }

    protected function setInputForForm($input)
    {


        $inputManipulationFunction = $this->inputManipulationFunction;

        if ($inputManipulationFunction instanceof \Closure) {
            return $inputManipulationFunction($input);
        }


        return $input;
    }


    protected function setFoormAction($action,$foorm)
    {

        $this->checkActionAllowed($action);

        $this->setActionConfig($action);


        $fullFormActionName = Arr::get($this->actionConfig, 'full_form_action_name');

        $totalRequestInput = $this->request->input() + $this->request->allFiles();


        Log::info(print_r($totalRequestInput,true));
        return new $fullFormActionName($this->actionConfig, $foorm, $this->model,
            $totalRequestInput, $this->params);

        //CHECK ACTION
        //BUILD FORM ACTION
    }

    protected function checkActionAllowed($action)
    {

        if (!Arr::get(Arr::get($this->config, 'allowed_actions', []), $action)) {
            throw new \Exception("Action " . $action . " not allowed in form " . $this->foormName);
        }

    }


    public function setActionConfig($action)
    {

        $actionConfig = Arr::get(Arr::get($this->config, 'actions', []), $action, []);

        $this->actionConfig = array_merge($actionConfig, $this->getRealFoormActionClass($action));

        return $this->actionConfig;

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


    /**
     * @return mixed
     */
    public function getFoormAction($action, $formName, Request $request, $params = [])
    {
        $foorm = $this->getFoorm($formName, $request, $params);
        return $this->setFoormAction($action,$foorm);
    }


}
