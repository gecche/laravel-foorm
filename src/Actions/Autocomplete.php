<?php

namespace Gecche\Foorm\Actions;


use Gecche\Foorm\FoormAction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class Autocomplete extends FoormAction
{

    protected $fieldToAutocomplete;

    protected $modelToAutocomplete;

    protected $value;


    protected function init()
    {
        parent::init();

        $this->fieldToAutocomplete = Arr::get($this->input, 'field');
        $this->value = Arr::get($this->input, 'value');
    }

    public function performAction()
    {

        $methodName = 'autocomplete' . Str::studly($this->fieldToAutocomplete);
        if (method_exists($this,$methodName)) {
            $autocompleteResult = $this->$methodName();
        } else {
            $autocompleteResult = $this->autocomplete();
        }

//        $this->actionResult = [
//            'autocomplete' => $autocompleteResult,
//            'field' => $this->fieldToAutocomplete,
//            'value' => $this->value,
//        ];

        $this->actionResult = $autocompleteResult;

        return $this->actionResult;

    }

    protected function autocomplete() {
        $nItems = Arr::get($this->input,'n_items');
        if (!$nItems) {
            $nItems = Arr::get($this->config,'n_items');
        }

        $searchFields = Arr::get($this->config,'search_fields');
        $resultFields = Arr::get($this->config,'result_fields');



        $modelMethodName = 'autocomplete' . Str::studly(Arr::get($this->config,'autocomplete_type'));

        return ($this->modelToAutocomplete)::$modelMethodName($this->value,$searchFields,$resultFields,$nItems,null);

    }


    public function validateAction()
    {

        $this->validateField();

    }


    protected function validateField()
    {

        $field = $this->fieldToAutocomplete;
        if (!$field) {
            throw new \Exception("The autocomplete action needs a field to be autocompleted");
        }

        $notAllowedFields = Arr::get($this->config, 'banned_fields', []);
        if (in_array($field, $notAllowedFields)) {
            throw new \Exception("The autocomplete action does not allow the field " . $field . " for this foorm");
        }

        if (!$this->foorm->hasFlatField($field)) {
            throw new \Exception("The field " . $field . " to be autocompleted is not configured in this foorm");
        }

        $fieldConfig = Arr::get(Arr::get($this->config, 'fields', []), $field, []);

        $this->modelToAutocomplete = Arr::get($fieldConfig, 'model', $this->guessModelToAutocomplete($field));

        if (!Str::startsWith($this->modelToAutocomplete,$this->getModelsNamespace())) {
            $this->modelToAutocomplete =
                $this->getModelsNamespace() . $this->modelToAutocomplete;
        }


        if (!$this->modelToAutocomplete || !class_exists($this->modelToAutocomplete)) {
            throw new \Exception("No model has been provided for the field " . $field . " to be autocompleted");
        }


    }


    protected function guessModelToAutocomplete($field) {

        $chunks = explode('|', $field);
        if ($chunks == 1) {
            if (Str::endsWith($field,'_id')) {
                return Str::studly(substr($field,0,-3));
            }
        }

        if (Str::endsWith($field,'_id')) {
            return Str::studly(substr($field,0,-3));
        }

        $relation = $chunks[0];
        $relationModel = $this->foorm->getRelationConfig($relation,'related');

        return $relationModel ? $relationModel : false;

    }


}
