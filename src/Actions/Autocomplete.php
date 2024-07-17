<?php

namespace Gecche\Foorm\Actions;


use Gecche\Foorm\FoormAction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class Autocomplete extends FoormAction
{

    protected $fieldToAutocomplete;

    protected $modelToAutocomplete;

    protected $value;

    protected $fieldConfig;

    protected $params = [];

    protected function init()
    {
        parent::init();

        $this->fieldToAutocomplete = Arr::get($this->input, 'field');
        $this->value = Arr::get($this->input, 'value');
        $this->params = Arr::get($this->input, 'params', []);
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
            $nItems = Arr::get($this->fieldConfig,'n_items');
        }

        $searchFields = Arr::get($this->fieldConfig,'search_fields');
        $resultFields = Arr::get($this->fieldConfig,'result_fields');


        $builder = $this->getAutocompleteBuilder();

        $modelMethodName = 'autocomplete' . Str::studly(Arr::get($this->fieldConfig,'autocomplete_type'));

        $autocompleteResult = ($this->modelToAutocomplete)::$modelMethodName($this->value,$searchFields,$resultFields,$nItems,$builder);

        return $this->finalizeData($autocompleteResult);

    }

    protected function getAutocompleteBuilder() {
        return null;
    }

    public function validateAction()
    {

        $this->validateField();

    }

    protected function finalizeData($autocompleteResult) {

        return $autocompleteResult;

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

        $this->fieldConfig = Arr::get(Arr::get($this->config, 'fields', []), $field, []);


        $this->modelToAutocomplete = Arr::get($this->fieldConfig, 'model', $this->guessModelToAutocomplete($field));

        if (!Str::startsWith($this->modelToAutocomplete,$this->foorm->getModelsNamespace())) {
            $this->modelToAutocomplete =
                $this->foorm->getModelsNamespace() . $this->modelToAutocomplete;
        }

        \Log::info($this->modelToAutocomplete);

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
        $relationModel = $this->foorm->getRelationConfig($relation,'modelName');

        return $relationModel ? $relationModel : false;

    }


}
