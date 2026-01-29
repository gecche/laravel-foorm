<?php

namespace Gecche\Foorm;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait FoormSingleTrait
{
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
            return;
        }

        foreach ($this->relationsAsOptions as $relation => $field) {
            if (is_array($this->formData[$relation])) {
                $this->formData[$relation] = array_values($this->formData[$relation]);
            }
        }

    }


    public function setModelRelationsData()
    {
        $relationsKeys = array_keys($this->getRelations());
        foreach ($relationsKeys as $relationKey) {

            $method = 'setModelRelationData' . Str::studly($relationKey);
            if (method_exists($this, $method)) {
                $this->$method($relationKey);
            } else {
                $this->setModelRelationData($relationKey);
            }

        }

    }

    public function setModelRelationData($relationKey)
    {
        $this->model->load($relationKey);
    }

    public function setModelData()
    {

        $configData = $this->getAllFieldsAndDefaultsFromConfig();


        $this->setModelRelationsData();

        foreach (Arr::get($this->config, 'appends', []) as $appendField) {
            $this->model->append($appendField);
        };

        $modelData = $this->model->toArray();


        $this->formData = $this->removeAndSetDefaultFromConfig($modelData, $configData, 1, Arr::get($this->config, 'relations', []));
    }

    protected function removeAndSetDefaultFromConfig($modelData, $configData, $level = 1, $configRelations = [])
    {
        //SE LIVELLO > 1 E CHIAVE DI MODELDATA E' NUMERICA DEVO ENTRARE DENTRO
        if ($level > 1 && count($modelData) > 0 && count(array_filter(array_keys($modelData), 'is_string')) <= 0) {
            foreach ($modelData as $numericKey => $value) {
                $modelData[$numericKey] = $this->removeAndSetDefaultFromConfig($modelData[$numericKey], $configData,
                    ($level + 1), $configRelations);
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
                //RElations as options gestite al livello 1 al momento
                $configRelation = Arr::get($configRelations, $configKey, []);

                if (array_key_exists('as_options', $configRelation)) {

                    $fieldOptions = Arr::get($configRelation['as_options'], 'field', 'id');
                    $modelData[$configKey] = collect($modelData[$configKey])->pluck($fieldOptions, $fieldOptions)->all();


                } else {
                    $modelData[$configKey] = $this->removeAndSetDefaultFromConfig($modelData[$configKey], $configField,
                        ($level + 1), Arr::get($configRelations, 'relations', []));

                }

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
                : $this->guessDefaultForField($fieldKey, $this->config['fields'][$fieldKey]);
        }

        $configRelations = array_keys(Arr::get($this->config, 'relations', []));

        foreach ($configRelations as $relationKey) {


            $relationConfig = Arr::get($this->config['relations'], $relationKey, []);

            $modelFields[$relationKey] = $this->_getAllFieldsAndDefaultsFromConfig($relationConfig,
                Arr::get($extraDefaults, $relationKey, []));

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
                : $this->guessDefaultForField($fieldKey, $relationConfig['fields'][$fieldKey]);
        }

        $configRelations = array_keys(Arr::get($relationConfig, 'relations', []));

        foreach ($configRelations as $relationKey) {


            $subRelationConfig = Arr::get($relationConfig['relations'], $relationKey, []);

            $modelFields[$relationKey] = $this->_getAllFieldsAndDefaultsFromConfig($subRelationConfig,
                Arr::get($extraDefaults, $relationKey, []));

        }

        return $modelFields;


    }

    protected function guessDefaultForField($fieldKey, $fieldConfig)
    {

        $defaultValue = Arr::get($fieldConfig, 'default');

        if (is_null($defaultValue)) {
            if (Arr::get($fieldConfig, 'options')) {
                $defaultValue = $this->config['null-value'];
            }
            if (Arr::get($fieldConfig, 'referred_data')) {
                $defaultValue = $this->config['null-value'];
            }
        }

        return $defaultValue;

    }

    //SOLO AL LIVELLO DEL MODELLO PRINCIPALE
    protected function setFixedConstraintsToData($data)
    {

        foreach ($this->fixedConstraints as $fixedConstraintKey => $fixedCostraintValue) {

            $data[$fixedConstraintKey] = $fixedCostraintValue;
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

        $this->formData = $this->setFixedConstraintsToData($this->formData);

        $this->finalizeData();

        return $this->formData;

    }


    public function setFormMetadata()
    {


        $this->setFormMetadataFields();

        $this->setFormMetadataRelations();

    }


    protected function fillFormMetadataFields($fields = [], $relationName = null, $relationMetadata = [])
    {
        $fields = parent::fillFormMetadataFields($fields, $relationName,
            $relationMetadata); // TODO: Change the autogenerated stub

        $fields = $this->manageMetadataFieldsReferredData($fields, $relationName, $relationMetadata);

        return $fields;
    }

    protected function manageMetadataFieldsReferredData($fields = [], $relationName = null, $relationMetadata = [])
    {

        foreach ($fields as $fieldKey => $fieldValue) {

            if (Arr::get($fieldValue, 'referred_data')) {
                $referredData = $this->createReferredData($fieldKey, $fieldValue, $relationName);


                $fieldValue['referred_data'] = $referredData;

            }

            $fields[$fieldKey] = $fieldValue;

        }

        return $fields;

    }

    protected function createReferredData($fieldKey, $fieldValue, $relationName = null)
    {

        $referredData = $fieldValue['referred_data'];
        //SE E' un array metto le referred_data così come sono;
        if (is_array($referredData)) {
            return $referredData;
        }

        $referredDataArray = explode(':', $referredData);
        $referredDataType = array_shift($referredDataArray);


        switch ($referredDataType) {
            case 'method':
                $methodName = 'createReferredData' . Str::studly($fieldKey);
                $methodClassType = Arr::get($referredDataArray, 0, 'foorm');
                switch ($methodClassType) {
                    case 'foorm' :
                        return $this->$methodName($fieldValue);
                    case 'model' :
                        return $this->model->$methodName($fieldValue);
                    default:
                        return $methodClassType::$methodName($fieldValue);

                }
                break;
            case 'relation':

                /*
                 * Prendo tutte le relazioni del modello anche quelle non in configurazione
                 */
                $relations = ($this->getModelName())::getRelationsData();
                $referredRelationName = Arr::get($referredDataArray, 0);
                if (is_null($relationName)) {


                    if (!array_key_exists($referredRelationName, $relations)) {
                        throw new \Exception("Relation " . $referredRelationName . " not found.");
                    }

                    $relationResult = $this->model->$referredRelationName;

                    if (is_null($relationResult)) {
                        return [];
                    }
                    if (is_array($relationResult)) {
                        throw new \Exception("Referred data only for belongsto macrotypes");
                    }

                    if (!array_key_exists(1, $referredDataArray)) {
                        $fieldsToFilter = $relationResult->getColumnsForSelectList();
                    } else {
                        $fieldsToFilter = explode('|', Arr::get($referredDataArray, 1));
                    }

                    $relationResult = $relationResult->toArray();
                    $fieldsToFilter = array_combine($fieldsToFilter, $fieldsToFilter);
                    return array_intersect_key($relationResult, $fieldsToFilter);
                } else {
                    if (!array_key_exists($relationName, $relations)) {
                        throw new \Exception("Relation " . $relationName . " not found.");
                    }

                    $relatedData = $relations[$relationName];
                    $relatedModel = Arr::get($relatedData,'related');
                    $nestedRelationsData = $relatedModel::getRelationsData();

                    if (!array_key_exists($referredRelationName, $nestedRelationsData)) {
                        throw new \Exception("Nested relation " . $relationName.'.'.$referredRelationName . " not found.");
                    }

                    $relationResult = $this->model->$relationName;
                    if (is_null($relationResult)) {
                        return [];
                    }


                    if (is_object($relationResult) && is_a($relationResult,Model::class)) {


                        $nestedRelationResult = $relationResult->$referredRelationName;
                        if (!array_key_exists(1, $referredDataArray)) {
                            $fieldsToFilter = $nestedRelationResult->getColumnsForSelectList();
                        } else {
                            $fieldsToFilter = explode('|', Arr::get($referredDataArray, 1));
                        }

                        $nestedRelationResult = $nestedRelationResult->toArray();
                        $fieldsToFilter = array_combine($fieldsToFilter, $fieldsToFilter);
                        return array_intersect_key($nestedRelationResult, $fieldsToFilter);

                    } else {
                        $referredDataResult = [];
                        foreach ($relationResult as $relationResultElement) {
                            $nestedRelationResult = $relationResultElement->$referredRelationName;

                            if (!array_key_exists(1, $referredDataArray)) {
                                $fieldsToFilter = $nestedRelationResult->getColumnsForSelectList();
                            } else {
                                $fieldsToFilter = explode('|', Arr::get($referredDataArray, 1));
                            }

                            $nestedRelationResult = $nestedRelationResult->toArray();
                            $fieldsToFilter = array_combine($fieldsToFilter, $fieldsToFilter);
                            $referredDataResult[] = array_intersect_key($nestedRelationResult, $fieldsToFilter);
                        }
                        return $referredDataResult;
                    }
                }

            default:
                return [];
        }

    }


}
