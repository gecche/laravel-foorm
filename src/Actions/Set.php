<?php

namespace Gecche\Foorm\Actions;


use Gecche\Foorm\FoormAction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class Set extends FoormAction
{

    protected $modelToSet;

    protected $fieldToSet;
    protected $valueToSet;

    protected $validationSettings;

    protected function init()
    {
        if ($this->model->getKey()) {
            $this->modelToSet = $this->model;
        } else {
            $this->modelToSet = $this->model->find(Arr::get($this->input,'id'));
        }

        parent::init();

        $this->fieldToSet = Arr::get($this->input, 'field');
        $this->valueToSet = Arr::get($this->input, 'value');
    }

    public function performAction()
    {

        $methodName = 'setValue' . Str::studly($this->fieldToSet);
        if (method_exists($this,$methodName)) {
            $setResult = $this->$methodName();
        } else {
            $setResult = $this->setValue();
        }

        $this->actionResult = [
            'set' => $setResult,
            'field' => $this->fieldToSet,
            'value' => $this->valueToSet,
        ];

        return $this->actionResult;

    }


    public function validateAction()
    {

        $this->validateField();
        $this->validateValue();

    }


    protected function setValue() {

        $this->modelToSet->{$this->fieldToSet} = $this->valueToSet;
        return $this->modelToSet->save();

    }

    protected function validateField()
    {

        $field = $this->fieldToSet;
        if (!$field) {
            throw new \Exception("The set action needs a field to be set");
        }

        $allowedFields = Arr::get($this->config, 'allowed_fields', []);
        if (!in_array($field, $allowedFields)) {
            throw new \Exception("The set action does not allow the field " . $field . " for this foorm");
        }

        $foormFields = Arr::get($this->foorm->getConfig(), 'fields', []);
        if (!array_key_exists($field,$foormFields)) {
            throw new \Exception("The field " . $field . " to be set is not configured in this foorm");
        }

    }

    protected function validateValue()
    {

        $settings = $this->getValidationSettings();

        $validator = Validator::make([$this->fieldToSet => $this->valueToSet], $settings['rules'], $settings['customMessages'], $settings['customAttributes']);

        if (!$validator->passes()) {
            $errors = Arr::flatten($validator->errors()->getMessages());
            throw new \Exception(json_encode($errors));
        }
    }

    protected function getValidationSettings()
    {

        $field = $this->fieldToSet;

        $settings = is_array($this->validationSettings) ? $this->validationSettings
            : $this->modelToSet->getModelValidationSettings();

        $rules = Arr::get($settings, 'rules', []);
        $customMessages = Arr::get($settings, 'customMessages', []);
        $customAttributes = Arr::get($settings, 'customAttributes', []);
        if (array_key_exists($field, $rules)) {
            return [
                'rules' => [$field => $rules[$field]],
                'customMessages' => array_key_exists($field, $customMessages)
                    ? [$field => Arr::get($customMessages, $field)]
                    : [],
                'customAttributes' => array_key_exists($field, $customAttributes)
                    ? [$field => Arr::get($customAttributes, $field)]
                    : [],
            ];
        }

        return [
            'rules' => [],
            'customMessages' => [],
            'customAttributes' => [],
        ];

    }

}
