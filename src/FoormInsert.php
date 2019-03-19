<?php

namespace Gecche\Foorm;


use Gecche\DBHelper\Facades\DBHelper;
use Gecche\ModelPlus\ModelPlus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;

class FoormInsert extends Foorm
{

    use ConstraintBuilderTrait;

    protected $extraDefaults = [];

    protected $inputForSave = null;

    protected $validator = null;
    protected $validationSettings = null; //rules, customMessages, customAttributes
    /**
     * @return array
     */
    public function getExtraDefaults()
    {
        return $this->extraDefaults;
    }

    /**
     * @param array $extraDefaults
     */
    public function setExtraDefaults($extraDefaults)
    {
        $this->extraDefaults = $extraDefaults;
    }


    public function finalizeData($finalizationFunc = null)
    {

        if ($finalizationFunc instanceof \Closure) {
            $this->formData = $finalizationFunc($this->formData);
            return $this->formData;
        }

        return $this->formData;

    }


    public function setModelData()
    {

        $relations = $this->getRelations();

        foreach (array_keys($relations) as $relationKey) {

            $this->model->load($relationKey);

        }

        $modelData = $this->model->toArray();

        $configData = $this->getAllFieldsAndDefaultsFromConfig();


        $this->formData = $this->removeAndSetDefaultFromConfig($modelData, $configData);
    }

    protected function removeAndSetDefaultFromConfig($modelData, $configData, $level = 1)
    {
        $keyNotInConfig = array_keys(array_diff_key($modelData, $configData));

        foreach ($keyNotInConfig as $keyToEliminate) {
            unset($modelData[$keyToEliminate]);
        }

        foreach ($configData as $configKey => $configField) {

            if (!array_key_exists($configKey, $modelData)) {
                if ($level == 1) {
                    $modelData[$configKey] = $configData[$configKey];
                } else {
                    continue;
                }
            }

            if (is_array($configField)) {

                if (!is_array($modelData[$configKey])) {
                    continue;
//                    $modelData[$configKey] = [];
                }
                $modelData[$configKey] = $this->removeAndSetDefaultFromConfig($modelData[$configKey], $configField, ($level + 1));

            }


        }

        return $modelData;

    }


    public function getAllFieldsAndDefaultsFromConfig()
    {

        $extraDefaults = $this->getExtraDefaults();

        $modelFieldsKeys = array_keys(array_get($this->config, 'fields', []));

        $modelFields = [];
        foreach ($modelFieldsKeys as $fieldKey) {
            $modelFields[$fieldKey] = array_key_exists($fieldKey, $extraDefaults)
                ? $extraDefaults[$fieldKey]
                : array_get($this->config['fields'][$fieldKey], 'default');
        }

        $configRelations = array_keys(array_get($this->config, 'relations', []));

        foreach ($configRelations as $relationKey) {


            $relationConfig = array_get($this->config['relations'], $relationKey, []);

            $modelFields[$relationKey] = $this->_getAllFieldsAndDefaultsFromConfig($relationConfig, array_get($extraDefaults, $relationKey, []));

        }

        return $modelFields;


    }

    protected function _getAllFieldsAndDefaultsFromConfig($relationConfig, $extraDefaults)
    {
        $modelFieldsKeys = array_keys(array_get($relationConfig, 'fields', []));

        $modelFields = [];
        foreach ($modelFieldsKeys as $fieldKey) {
            $modelFields[$fieldKey] = array_key_exists($fieldKey, $extraDefaults)
                ? $extraDefaults[$fieldKey]
                : array_get($relationConfig['fields'][$fieldKey], 'default');
        }

        $configRelations = array_keys(array_get($relationConfig, 'relations', []));

        foreach ($configRelations as $relationKey) {


            $subRelationConfig = array_get($relationConfig['relations'], $relationKey, []);

            $modelFields[$relationKey] = $this->_getAllFieldsAndDefaultsFromConfig($subRelationConfig, array_get($extraDefaults, $relationKey, []));

        }

        return $modelFields;


    }


    //SOLO AL LIVELLO DEL MODELLO PRINCIPALE
    protected function setFixedConstraints($data)
    {
        $fixedConstraints = array_get($this->params, 'fixed_constraints', []);

        foreach ($fixedConstraints as $fixedConstraint) {


            $field = array_get($fixedConstraint, 'field', null);

            if (!$field || !is_string($field) || !array_key_exists('value', $fixedConstraint)) {
                continue;
            };

            $data[$field] = $fixedConstraint['value'];
        }

        return $data;
    }

    /**
     * Generates and returns the data model
     *
     * - Get data from model and its relations
     * - Apply last transformations to the result
     * - Format the result
     *
     */
    public function getFormData()
    {

        $this->setModelData();

        $this->formData = $this->setFixedConstraints($this->formData);

        $this->finalizeData();

        return $this->formData;

    }


    public function setFormMetadata()
    {


        $this->setFormMetadataFields();

        $this->setFormMetadataRelations();

    }


    public function isValid($input = null,$settings = null)
    {

        $input = is_array($input) ? $input : $this->input;

        $finalSettings = $this->getValidationSettings($settings);
        $rules = array_get($finalSettings,'rules',[]);
        $customMessages = array_get($finalSettings,'customMessages',[]);
        $customAttributes = array_get($finalSettings,'customAttributes',[]);
        $this->validator = Validator::make($input, $rules,$customMessages,$customAttributes);

        if (!$this->validator->passes()) {
            $errors = $this->validator->errors()->getMessages();
            $errors = array_flatten($errors);
            throw new \Exception(json_encode($errors));
        }

        return true;
    }


    public function getValidationSettings($rules)
    {
        $this->setValidationSettings($rules);
        return $this->validationSettings;
    }

    protected function buildValidationSettings()
    {

        $uniqueRules = $this->model->getKey() ? 1 : 0;
        $this->validationSettings = $this->model->getModelValidationSettings($uniqueRules);


        $validationHasMany = [];
        foreach ($this->hasManies as $key => $value) {
            $hasManyModelName = $value['modelName'];

            $hasManyModel = new $hasManyModelName();

            $hasManyValidationSettings = $hasManyModel->getModelValidationSettings();

            $hasManyRules = array_get($hasManyValidationSettings,'rules',[]);
            foreach ($hasManyRules as $hasManyRuleKey => $hasManyRuleValue) {
                $valueRules = explode('|', $hasManyRuleValue);

                //TODO: per ora le regole che sono già array le elvo perché troppo icnasinato e probabilmente sono casi stralimite.
                //$nestedLevelRules = array();
                foreach ($valueRules as $keyRule => $rule) {
                    if (substr($rule, -5) === 'Array') {
                        //$nestedLevelRules[$keyRule] = $rule;
                        //Array nested per il momento non supportati! :) e quindi eliminati!!!
                        unset($valueRules[$keyRule]);
                        continue;
                    }
                    $valueRules[$keyRule] = $rule . 'Array';
                }
                $hasManyRules[$hasManyRuleKey] = implode('|', $valueRules);
            }
            $hasManyRules = array_key_append($hasManyRules, $key . '-', false);

            $hasManyValidationSettings['rules'] = $hasManyRules;
            //$this->validationRules[] =
            $validationHasMany = array_merge($validationHasMany, $hasManyValidationSettings);
        }

        $this->validationSettings = array_merge($this->validationSettings, $validationHasMany);


    }


    public function setValidationSettings($rules = null)
    {

        if (is_array($rules)) {
            $this->validationSettings = $rules;
            return;
        }

        if (is_null($rules)) {
            if (is_null($this->validationSettings)) {
                $this->buildValidationSettings();
            }
            return;
        }


        throw new \InvalidArgumentException("Rules should be an array or null");


    }




    public function getValidationRulePrefix($prefix, $remove = true, $separator = '-')
    {

        $full_prefix = $prefix . $separator;
        $rules = preg_grep_keys('/^' . $full_prefix . '/', $this->validationSettings);
        if ($remove) {
            $rules = trim_keys($full_prefix, $rules);
        }
        return $rules;
    }


    protected function setFieldsToModel($model,$configFields,$input) {

        foreach (array_keys($configFields) as $fieldName) {
            $model->$fieldName = array_get($input,$fieldName);
        }

    }

    public function save($input = null, $validate = true)
    {

        $this->inputforSave = is_array($input) ? $input : $this->input;

        $this->setFixedConstraints($this->inputForSave);


        if ($validate) {
            $this->isValid($this->inputForSave);
        }

        $this->setFieldsToModel($this->model,array_get($this->config,'fields',[]),$this->inputForSave);


        $saved = $this->model->save();

        if (!$saved) {
            throw new \Exception($this->model->errors());
        }

        $this->model->fresh();

        //DA FARE
//        $this->saveRelated($this->input);
//        $this->setResult();

        return $saved;
    }


}
