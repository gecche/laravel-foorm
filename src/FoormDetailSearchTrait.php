<?php

namespace Gecche\Foorm;


use Illuminate\Support\Arr;

trait FoormDetailSearchTrait
{

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

        $relationsKeys = array_keys($this->getRelations());

        foreach ($relationsKeys as $relationKey) {

            $this->model->load($relationKey);

        }

        $modelData = $this->model->toArray();

        $configData = $this->getAllFieldsAndDefaultsFromConfig();


        $this->formData = $this->removeAndSetDefaultFromConfig($modelData, $configData);
    }

    protected function removeAndSetDefaultFromConfig($modelData, $configData, $level = 1)
    {
        //SE LIVELLO > 1 E CHIAVE DI MODELDATA E' NUMERICA DEVO ENTRARE DENTRO
        if ($level > 1 && count($modelData) > 0 && count(array_filter(array_keys($modelData), 'is_string')) <= 0) {
            foreach ($modelData as $numericKey => $value) {
                $modelData[$numericKey] = $this->removeAndSetDefaultFromConfig($modelData[$numericKey], $configData, ($level + 1));
            }
            return $modelData;
        }
        $keyNotInConfig = array_keys(array_diff_key($modelData, $configData));

        foreach ($keyNotInConfig as $keyToEliminate) {
            unset($modelData[$keyToEliminate]);
        }

        foreach ($configData as $configKey => $configField) {

            if (!array_key_exists($configKey, $modelData)) {
                if ($level == 1) {
                    $modelData[$configKey] = $configField;
                } else {
                    continue;
                }
            } elseif (is_null($modelData[$configKey]) && $configField == $this->config['null-value']) {
                $modelData[$configKey] = $configField;
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

        $modelFieldsKeys = array_keys(Arr::get($this->config, 'fields', []));

        $modelFields = [];
        foreach ($modelFieldsKeys as $fieldKey) {
            $modelFields[$fieldKey] = array_key_exists($fieldKey, $extraDefaults)
                ? $extraDefaults[$fieldKey]
                : $this->guessDefaultForField($fieldKey,$this->config['fields'][$fieldKey]);
        }

        $configRelations = array_keys(Arr::get($this->config, 'relations', []));

        foreach ($configRelations as $relationKey) {


            $relationConfig = Arr::get($this->config['relations'], $relationKey, []);

            $modelFields[$relationKey] = $this->_getAllFieldsAndDefaultsFromConfig($relationConfig, Arr::get($extraDefaults, $relationKey, []));

        }

        return $modelFields;


    }

    protected function _getAllFieldsAndDefaultsFromConfig($relationConfig, $extraDefaults)
    {
        $modelFieldsKeys = array_keys(Arr::get($relationConfig, 'fields', []));

        $modelFields = [];
        foreach ($modelFieldsKeys as $fieldKey) {
            $modelFields[$fieldKey] = array_key_exists($fieldKey, $extraDefaults)
                ? $extraDefaults[$fieldKey]
                : $this->guessDefaultForField($fieldKey,$relationConfig['fields'][$fieldKey]);
        }

        $configRelations = array_keys(Arr::get($relationConfig, 'relations', []));

        foreach ($configRelations as $relationKey) {


            $subRelationConfig = Arr::get($relationConfig['relations'], $relationKey, []);

            $modelFields[$relationKey] = $this->_getAllFieldsAndDefaultsFromConfig($subRelationConfig, Arr::get($extraDefaults, $relationKey, []));

        }

        return $modelFields;


    }

    protected function guessDefaultForField($fieldKey,$fieldConfig) {

        $defaultValue = Arr::get($fieldConfig, 'default');

        if (is_null($defaultValue) && Arr::get($fieldConfig,'options')) {
            $defaultValue = $this->config['null-value'];
        }

        return $defaultValue;

    }

    //SOLO AL LIVELLO DEL MODELLO PRINCIPALE
    protected function setFixedConstraints($data)
    {
        $fixedConstraints = Arr::get($this->params, 'fixed_constraints', []);

        foreach ($fixedConstraints as $fixedConstraint) {


            $field = Arr::get($fixedConstraint, 'field', null);

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


    protected function getRelationFieldsFromConfig($relation)
    {
        return $this->getRelationConfig($relation,'fields', []);
    }

    protected function getRelationConfig($relation,$key = null, $defaultValue = null)
    {
        $relationsConfig = Arr::get($this->config, 'relations', []);
        $relationConfig = Arr::get($relationsConfig, $relation, []);
        if (is_null($key)) {
            return $relationConfig;
        }
        return Arr::get($relationConfig, $key, $defaultValue);
    }


}
