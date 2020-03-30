<?php

namespace Gecche\Foorm\Actions;


use Gecche\Foorm\FoormAction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class Autocomplete extends FoormAction
{

    protected $fieldToAutocomplete;


    protected function init()
    {
        parent::init();

        $this->fieldToAutocomplete = Arr::get($this->input, 'field');
        $this->valueToSet = Arr::get($this->input, 'value');
    }

    public function performAction()
    {

        $methodName = 'setValue' . Str::studly($this->fieldToAutocomplete);
        if (method_exists($this,$methodName)) {
            $setResult = $this->$methodName();
        } else {
            $setResult = $this->setValue();
        }

        $this->actionResult = [
            'set' => $setResult,
            'field' => $this->fieldToAutocomplete,
            'value' => $this->valueToSet,
        ];

        return $this->actionResult;

    }


    public function validateAction()
    {

        if (!$this->model->getKey()) {
            throw new \Exception("The set action needs a saved model");
        }

        $this->validateField();
        $this->validateValue();

    }


    protected function setValue() {

        $this->model->{$this->fieldToAutocomplete} = $this->valueToSet;
        return $this->model->save();

    }

    protected function validateField()
    {

        $field = $this->fieldToAutocomplete;
        if (!$field) {
            throw new \Exception("The set action needs a field to be autocompleted");
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

        $validator = Validator::make([$this->fieldToAutocomplete => $this->valueToSet], $settings['rules'], $settings['customMessages'], $settings['customAttributes']);

        if (!$validator->passes()) {
            $errors = Arr::flatten($validator->errors()->getMessages());
            throw new \Exception(json_encode($errors));
        }
    }

    protected function getValidationSettings()
    {

        $field = $this->fieldToAutocomplete;

        $settings = is_array($this->validationSettings) ? $this->validationSettings
            : $this->model->getModelValidationSettings();

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
