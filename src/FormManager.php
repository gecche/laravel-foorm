<?php

namespace Gecche\Foorm;

use Gecche\ModelPlus\ModelPlus;
use Illuminate\Http\Request;

class FormManager
{

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var string
     */
    protected $formName;

    /**
     * @var array
     */
    protected $config;

    protected $model;

    protected $form;

    protected $params;


    /**
     * FormList constructor.
     * @param array $input
     * @param ModelPlus $model
     * @param array $params
     */
    public function __construct($formName,Request $request,$params = [])
    {

        $this->formName = $formName;
        $this->request = $request;
        $this->params = $params;
        $this->getConfig();
        $this->setModel();
        $this->setForm();


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
    public function getFormName()
    {
        return $this->formName;
    }



    public function getConfig() {

        $defaultConfig = config('foorm',[]);

        $modelsNamespace = array_get($defaultConfig,'models_namespace',"App\\");
        $foormsNamespace = array_get($defaultConfig,'foorms_namespace',"App\\Foorm\\");
        $foormsDefaultsNamespace = array_get($defaultConfig,'foorms_defaults_namespace',"Gecche\\Foorm\\");

        $formNameParts = explode('.',$this->formName);
        if (count($formNameParts) != 2) {
            throw new \InvalidArgumentException('A foorm name should be of type "<FORMNAME>.<FORMTYPE>".');
        }

        $formConfig = config('foorms.'.$this->formName,false);

        if (!is_array($formConfig)) {
            throw new \InvalidArgumentException('Configuration of foorm '.$this->formName.' not found');
        }

        $finalConfig = array_replace_recursive($defaultConfig,$formConfig);

        $snakeModelName = array_get($formConfig,'model',$formNameParts[0]);


        $relativeModelName = studly_case($snakeModelName);
        $fullModelName = $modelsNamespace . $relativeModelName;

        if (!class_exists($fullModelName))
            throw new \InvalidArgumentException("Model class $fullModelName does not exists");


        $snakeFormName = array_get($formConfig,'form_type',$formNameParts[1]);
        $relativeFormName = studly_case($snakeFormName);
        $fullFormName = $foormsNamespace . $relativeModelName . "\\" . $relativeFormName;


        if (!class_exists($fullFormName)) {//Example: exists App\Foorm\User\List class?

            $fullFormName = $foormsNamespace . $relativeFormName;
            if (!class_exists($fullFormName)) {//Example: exists App\Foorm\List class?
                $fullFormName = $foormsDefaultsNamespace . $relativeFormName;

                if (!class_exists($fullFormName)) {//Example: exists Gecche\Foorm\List class?
                    throw new \InvalidArgumentException("Form class not found");
                }

            }

        }

        $finalConfig['model'] = $snakeModelName;
        $finalConfig['form_type'] = $snakeFormName;
        $finalConfig['models_namespace'] = $modelsNamespace;
        $finalConfig['foorms_namespace'] = $foormsNamespace;
        $finalConfig['foorms_default_namespace'] = $foormsDefaultsNamespace;
        $finalConfig['relative_form_name'] = $relativeFormName;
        $finalConfig['relative_model_name'] = $relativeModelName;
        $finalConfig['full_form_name'] = $fullFormName;
        $finalConfig['full_model_name'] = $fullModelName;

        $this->config = $finalConfig;

        return $finalConfig;

    }




    /**
     * @param array $config
     */
    public function setConfig($config)
    {
        $this->config = array_merge($this->getConfig(), $config);
    }


    protected function setModel() {

        $fullModelName = array_get($this->config,'full_model_name');

        $id = array_get($this->params,'id');
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

    protected function setForm() {


        $input = $this->request->input();

        $fullFormName = array_get($this->config,'full_form_name');

        $this->form = new $fullFormName($this->config,$this->model,$input,$this->params);

    }



    /**
     * @return ModelPlus
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


}
