<?php

namespace Gecche\Foorm;


use Gecche\DBHelper\Facades\DBHelper;
use Gecche\ModelPlus\ModelPlus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Input;

class FoormInsert extends Foorm
{

    use ConstraintBuilderTrait;

    protected $extraDefaults = [];

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


        $this->formData = $this->removeAndSetDefaultFromConfig($modelData,$configData);
    }

    protected function removeAndSetDefaultFromConfig($modelData,$configData,$level = 1) {
        $keyNotInConfig = array_keys(array_diff_key($modelData,$configData));

        foreach ($keyNotInConfig as $keyToEliminate) {
            unset($modelData[$keyToEliminate]);
        }

        foreach ($configData as $configKey => $configField) {

            if (!array_key_exists($configKey,$modelData)) {
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
                $modelData[$configKey] = $this->removeAndSetDefaultFromConfig($modelData[$configKey],$configField,($level+1));

            }


        }

        return $modelData;

    }


    public function getAllFieldsAndDefaultsFromConfig() {

        $extraDefaults = $this->getExtraDefaults();

        $modelFieldsKeys = array_keys(array_get($this->config,'fields',[]));

        $modelFields = [];
        foreach ($modelFieldsKeys as $fieldKey) {
            $modelFields[$fieldKey] = array_key_exists($fieldKey,$extraDefaults)
                ? $extraDefaults[$fieldKey]
                : array_get($this->config['fields'][$fieldKey],'default');
        }

        $configRelations = array_keys(array_get($this->config,'relations',[]));

        foreach ($configRelations as $relationKey) {


            $relationConfig = array_get($this->config['relations'],$relationKey,[]);

            $modelFields[$relationKey] = $this->_getAllFieldsAndDefaultsFromConfig($relationConfig,array_get($extraDefaults,$relationKey,[]));

        }

        return $modelFields;


    }

    protected function _getAllFieldsAndDefaultsFromConfig($relationConfig,$extraDefaults) {
        $modelFieldsKeys = array_keys(array_get($relationConfig,'fields',[]));

        $modelFields = [];
        foreach ($modelFieldsKeys as $fieldKey) {
            $modelFields[$fieldKey] = array_key_exists($fieldKey,$extraDefaults)
                ? $extraDefaults[$fieldKey]
                : array_get($relationConfig['fields'][$fieldKey],'default');
        }

        $configRelations = array_keys(array_get($relationConfig,'relations',[]));

        foreach ($configRelations as $relationKey) {


            $subRelationConfig = array_get($relationConfig['relations'],$relationKey,[]);

            $modelFields[$relationKey] = $this->_getAllFieldsAndDefaultsFromConfig($subRelationConfig,array_get($extraDefaults,$relationKey,[]));

        }

        return $modelFields;


    }


    //SOLO AL LIVELLO DEL MODELLO PRINCIPALE
    protected function setFixedConstraints()
    {
        $fixedConstraints = array_get($this->params, 'fixed_constraints', []);

        foreach ($fixedConstraints as $fixedConstraint) {


            $field = array_get($fixedConstraint, 'field', null);

            if (!$field || !is_string($field) || !array_key_exists('value', $fixedConstraint)) {
                continue;
            };

            $this->formData[$field] = $fixedConstraint['value'];
        }
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

        $this->setFixedConstraints();

        $this->finalizeData();

        return $this->formData;

    }




    public function setFormMetadata()
    {


        $this->setFormMetadataFields();

        $this->setFormMetadataRelations();

    }


}
