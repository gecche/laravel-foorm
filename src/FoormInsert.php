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


    public function isValid($input = null, $settings = null)
    {

        $input = is_array($input) ? $input : $this->input;

        $finalSettings = $this->getValidationSettings($settings);
        $rules = array_get($finalSettings, 'rules', []);
        $customMessages = array_get($finalSettings, 'customMessages', []);
        $customAttributes = array_get($finalSettings, 'customAttributes', []);
        $this->validator = Validator::make($input, $rules, $customMessages, $customAttributes);

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


        $validationHasMany = [
            'rules' => [],
            'customMessages' => [],
            'customAttributes' => [],
        ];
        foreach ($this->hasManies as $key => $value) {
            $hasManyModelName = $value['modelName'];

            $hasManyModel = new $hasManyModelName();

            $hasManyValidationSettings = $hasManyModel->getModelValidationSettings();

            $hasManyRules = array_get($hasManyValidationSettings, 'rules', []);
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
            $validationHasMany['rules'] = array_merge($validationHasMany['rules'], $hasManyValidationSettings['rules']);
            $validationHasMany['customMessages'] = array_merge($validationHasMany['customMessages'], $hasManyValidationSettings['customMessages']);
            $validationHasMany['customAttributes'] = array_merge($validationHasMany['customAttributes'], $hasManyValidationSettings['customAttributes']);

        }

        $this->validationSettings['rules'] = array_merge($this->validationSettings['rules'], $validationHasMany['rules']);
        $this->validationSettings['customMessages'] = array_merge($this->validationSettings['customMessages'], $validationHasMany['customMessages']);
        $this->validationSettings['customAttributes'] = array_merge($this->validationSettings['customAttributes'], $validationHasMany['customAttributes']);


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


    protected function setFieldsToModel($model, $configFields, $input)
    {

        foreach (array_keys($configFields) as $fieldName) {
            $model->$fieldName = array_get($input, $fieldName);
        }

    }

    public function save($input = null, $validate = true)
    {

        $this->inputForSave = is_array($input) ? $input : $this->input;

        $this->setFixedConstraints($this->inputForSave);


        if ($validate) {
            $this->isValid($this->inputForSave);
        }

        $this->setFieldsToModel($this->model, array_get($this->config, 'fields', []), $this->inputForSave);


        $saved = $this->model->save();

        if (!$saved) {
            throw new \Exception($this->model->errors());
        }

        $this->model->fresh();

        //DA FARE
        $this->saveRelated($this->inputForSave);
//        $this->setResult();

        return $saved;
    }


    protected function saveRelated($input)
    {

        foreach ($this->belongsTos as $belongsToKey => $belongsToValue) {
            $saveRelatedName = 'saveRelated' . studly_case($belongsToKey);
            $saveType = array_get($belongsToValue, 'saveType', 'standard');
            $saveTypeParams = array_get($belongsToValue, 'saveTypeParams', array());

            if ($saveType == 'standard') {
                continue;
            }

            $this->$saveRelatedName('BelongsTo', $belongsToKey, $belongsToValue, $input, $saveTypeParams);
        }


        foreach ($this->hasManies as $hasManyKey => $hasManyValue) {
            $saveRelatedName = 'saveRelated' . studly_case($hasManyKey);
            $hasManyType = $hasManyValue['hasManyType'];
            $saveType = array_get($hasManyValue, 'saveType', false);
            $saveTypeParams = array_get($hasManyValue, 'saveTypeParams', array());
            if ($saveType) {
                $hasManyType = $hasManyType . studly_case($saveType);
            }
            $this->$saveRelatedName($hasManyType, $hasManyKey, $hasManyValue, $input, $saveTypeParams);
        }
    }


    protected function getRelationFieldsFromConfig($relation)
    {
        $relationsConfig = array_get($this->config, 'relations', []);
        $relationConfig = array_get($relationsConfig, $relation, []);
        return array_get($relationConfig, 'fields', []);
    }

    /*
     * BelongsTo - Non Standard: add a new belongs to model while saving the main model
     */
    public function saveRelatedBelongsTo($belongsToKey, $belongsToValue, $input, $params = array())
    {

        $belongsToModelName = $belongsToValue['modelName'];
        $belongsToRelationName = $belongsToValue['relationName'];
        $belongsToModel = $this->model->$belongsToRelationName;

        if (!$belongsToModel)
            $belongsToModel = new $belongsToModelName;

        $belongsToInputs = preg_grep_keys('/^' . $belongsToRelationName . '-/', $input);
        $belongsToInputs = trim_keys($belongsToRelationName . '-', $belongsToInputs);

        if ($belongsToValue['saveType'] == 'morphed') {
            $morph_type = $belongsToRelationName . 'able_type';
            $morph_id = $belongsToRelationName . 'able_id';
            $belongsToInputs[$morph_id] = $this->model->getKey();
            $belongsToInputs[$morph_type] = $this->modelName;
        }

        $this->setFieldsToModel($belongsToModel, $this->getRelationFieldsFromConfig($belongsToKey), $this->inputForSave);

        $belongsToModel->save();

        $this->model->$belongsToKey = $belongsToModel->getKey();
        $this->model->save();
    }

    /*
     * BelongsToMany - SaveTYPE: ADD
     */
    public function saveRelatedBelongsToManyAdd($hasManyKey, $hasManyValue, $input, $params = array())
    {
        $hasManyInputs = preg_grep_keys('/^' . $hasManyKey . '-/', $input);
        $hasManyInputs = trim_keys($hasManyKey . '-', $hasManyInputs);

        $hasManyModelName = $hasManyValue['modelName'];
        $hasManyModel = new $hasManyModelName();
        $pkName = $hasManyModel->getKeyName();

        $standardActions = [
            'update' => true,
            'remove' => true,
        ];
        $actionsToDo = array_get($params, 'actions', []);
        $actionsToDo = array_merge($standardActions, $actionsToDo);

        $statusKey = array_get($this->config['relations'][$hasManyKey],'status-key','status');

        foreach (array_get($hasManyInputs, $pkName, []) as $i => $pk) {

            $status = $hasManyInputs[$statusKey][$i];

            $inputArray = [];
            foreach ($this->getRelationFieldsFromConfig($hasManyKey) as $key) {
                $inputArray[$key] = $hasManyInputs[$key][$i];
            }

            $pivotFields = $this->model->getPivotKeys($hasManyKey);
            $pivotValues = [];

            foreach ($pivotFields as $pivotField) {
                $pivotValues[$pivotField] = array_get($inputArray, $pivotField, null);
            }

            if (array_get($hasManyValue, 'orderKey')) {
                $pivotValues[$hasManyValue['orderKey']] = $i;
            }


            switch ($status) {
                case 'old':
                    $hasManyModel = $hasManyModelName::find($pk);
                    $this->model->$hasManyKey()->detach($hasManyModel->getKey());
                    $this->model->$hasManyKey()->attach($hasManyModel->getKey(), $pivotValues);
                    break;
                case 'new':
                    $hasManyModel = $hasManyModelName::create($inputArray);
                    $this->model->$hasManyKey()->attach($hasManyModel->getKey(), $pivotValues);
                    break;
                case 'updated':
                    $hasManyModel = $hasManyModelName::find($pk);
                    if ($actionsToDo['update']) {
                        $hasManyModel->update($inputArray);
                    }
                    $this->model->$hasManyKey()->detach($hasManyModel->getKey());
                    $this->model->$hasManyKey()->attach($hasManyModel->getKey(), $pivotValues);
                    break;
                case 'deleted':
                    if ($actionsToDo['remove']) {
                        $hasManyModel = $hasManyModelName::destroy($pk);
                    } else {
                        $hasManyModel = $hasManyModelName::find($pk);
                        $this->model->$hasManyKey()->detach($hasManyModel->getKey());
                    }
                    break;
                default:
                    throw new \Exception("Invalid status " . $status);
                    break;
            }

        }
        $this->model->load($hasManyKey);
    }

    public function saveRelatedBelongsToManyStandardWithSave($hasManyKey, $hasManyValue, $input, $params = array())
    {
        $hasManyInputs = preg_grep_keys('/^' . $hasManyKey . '-/', $input);
        $hasManyInputs = trim_keys($hasManyKey . '-', $hasManyInputs);

        $hasManyModelName = $hasManyValue['modelName'];
        $hasManyModel = new $hasManyModelName();
        $pkName = $hasManyModel->getKeyName();

        $pivotModelName = $this->getModelsNamespace() . $hasManyValue['pivotModelName'];

        $this->model->$hasManyKey()->sync(array());

        $statusKey = array_get($this->config['relations'][$hasManyKey],'status-key','status');
        foreach (array_get($hasManyInputs, $pkName, []) as $i => $pk) {

            $status = $hasManyInputs[$statusKey][$i];

            $inputArray = [];
            foreach ($this->getRelationFieldsFromConfig($hasManyKey) as $key) {
                $inputArray[$key] = $hasManyInputs[$key][$i];
            }

            $pivotFields = $this->model->getPivotKeys($hasManyKey);
            $pivotValues = array();

            foreach ($pivotFields as $pivotField) {
                $pivotValues[$pivotField] = array_get($inputArray, $pivotField, null);
            }

            if (array_get($hasManyValue, 'orderKey', false)) {
                $pivotValues[$hasManyValue['orderKey']] = $i;
            }


            switch ($status) {
                case 'old':
                case 'new':
                case 'updated':
                case 'deleted':
                    $this->model->$hasManyKey()->attach($pk, $pivotValues);
                    $pivotModelName::orderBy('id', 'DESC')->first()->save();
                    break;
                default:
                    throw new \Exception("Invalid status " . $status);
                    break;
            }
        }
        $this->model->load($hasManyKey);
    }

    public function saveRelatedBelongsToManyStandard($hasManyKey, $hasManyValue, $input, $params = array())
    {
        $hasManyInputs = preg_grep_keys('/^' . $hasManyKey . '-/', $input);
        $hasManyInputs = trim_keys($hasManyKey . '-', $hasManyInputs);

        $hasManyModelName = $hasManyValue['modelName'];
        $hasManyModel = new $hasManyModelName();
        $pkName = $hasManyModel->getKeyName();

        $this->model->$hasManyKey()->sync(array());

        $orderKey = array_get($hasManyValue, 'orderKey');


        foreach (array_get($hasManyInputs, $pkName, []) as $i => $pk) {

            $inputArray = [];
            foreach ($this->getRelationFieldsFromConfig($hasManyKey) as $key) {
                $inputArray[$key] = $hasManyInputs[$key][$i];
            }

            $pivotFields = $this->model->getPivotKeys($hasManyKey);
            $pivotValues = [];


            foreach ($pivotFields as $pivotField) {

                if ($pivotField == $orderKey) {
                    $pivotValues[$pivotField] = $i;
                    continue;
                }

                $pivotValues[$pivotField] = array_get($inputArray, $pivotField, null);

            }
            $this->model->$hasManyKey()->attach($pk, $pivotValues);

        }

        $this->model->load($hasManyKey);

    }

    public function saveRelatedHasManyStandard($hasManyKey, $hasManyValue, $input, $params = array())
    {
        $hasManyInputs = preg_grep_keys('/^' . $hasManyKey . '-/', $input);
        $hasManyInputs = trim_keys($hasManyKey . '-', $hasManyInputs);

        $hasManyModelName = $hasManyValue['modelName'];
        $hasManyModel = new $hasManyModelName();
        $pkName = $hasManyModel->getKeyName();

        $statusKey = array_get($this->config['relations'][$hasManyKey],'status-key','status');

        foreach (array_get($hasManyInputs, $pkName, []) as $i => $pk) {

            $status = $hasManyInputs[$statusKey][$i];

            $inputArray = [];
            foreach ($this->getRelationFieldsFromConfig($hasManyKey) as $key) {
                $inputArray[$key] = $hasManyInputs[$key][$i];
            }

            if (array_get($hasManyValue, 'orderKey')) {
                $pivotValues[$hasManyValue['orderKey']] = $i;
            }
            unset($inputArray[$statusKey]);

            //SALVARE
            switch ($status) {
                case 'new':
                    $hasManyModel = new $hasManyModelName($inputArray);
                    $this->model->$hasManyKey()->save($hasManyModel);
                    break;
                case 'old':
                    $hasManyModel = $hasManyModelName::find($inputArray['id']);
                    $hasManyModel->update($inputArray);
                    break;
                case 'updated':
                    $hasManyModel = $hasManyModelName::find($inputArray['id']);
                    $hasManyModel->update($inputArray);
                    break;
                case 'deleted':
                    $hasManyModel = $hasManyModelName::destroy($inputArray['id']);
                    break;
                default:
                    throw new \Exception("Invalid status " . $status);
                    break;
            }

        }
        $this->model->load($hasManyKey);
    }

    public function saveRelatedHasManyAssociation($hasManyKey, $hasManyValue, $input, $params = array())
    {
        $hasManyInputs = preg_grep_keys('/^' . $hasManyKey . '-/', $input);
        $hasManyInputs = trim_keys($hasManyKey . '-', $hasManyInputs);

        $hasManyModelName = $hasManyValue['modelName'];
        $hasManyModel = new $hasManyModelName();
        $pkName = $hasManyModel->getKeyName();

        $modelField = strtolower(snake_case($this->modelRelativeName)) . '_id';


        foreach ($this->model->$hasManyKey as $hasManyModel) {
            $hasManyModel->$modelField = null;
            $hasManyModel->save();
        }

        foreach (array_get($hasManyInputs, 'status', array()) as $i => $status) {

            $inputArray = array();
            foreach (array_keys($hasManyInputs) as $key) {
                $inputArray[$key] = $hasManyInputs[$key][$i];
            }
            //unset($inputArray['status']);
            //$modelField = strtolower(snake_case($this->modelName)) . '_id';
            //$inputArray[$modelField] = $this->model->getKey();

            //SALVARE
            switch ($status) {
                case 'new':
                case 'old':
                case 'deleted':
                    $hasManyModel = $hasManyModelName::find($inputArray['id']);
                    $hasManyModel->$modelField = $this->model->getKey();
                    $hasManyModel->save();
                    break;
                default:
                    throw new Exception("Invalid status " . $status);
                    break;
            }
        }
    }

    public function saveRelatedHasManyMorph($hasManyKey, $hasManyValue, $input, $params = array())
    {
        $hasManyInputs = preg_grep_keys('/^' . $hasManyKey . '-/', $input);
        $hasManyInputs = trim_keys($hasManyKey . '-', $hasManyInputs);
        $hasManyModelName = $hasManyValue['modelName'];
        $modelField = strtolower(snake_case($this->modelRelativeName)) . '_id';


        foreach ($this->model->$hasManyKey as $hasManyModel) {
            $hasManyModel->delete();
        }

        $hasManyModel = new $hasManyModelName;

        $hasManyAttributes = array_keys($hasManyModel->getDefaultFromDB());

        $morphIdAttribute = "";
        $morphTypeAttribute = "";
        $ordine = false;
        foreach ($hasManyAttributes as $hasManyAttribute) {
            if ($hasManyAttribute == 'ordine') {
                $ordine = true;
                continue;
            }
            if (ends_with($hasManyAttribute, "able_type")) {
                $morphTypeAttribute = $hasManyAttribute;
            }
            if (ends_with($hasManyAttribute, "able_id")) {
                $morphIdAttribute = $hasManyAttribute;
            }
        }
        foreach (array_get($hasManyInputs, 'status', array()) as $i => $status) {

            $inputArray = array();
            foreach (array_keys($hasManyInputs) as $key) {
                $inputArray[$key] = $hasManyInputs[$key][$i];
            }

            if ($ordine)
                $inputArray['ordine'] = $i;

            $inputArray[$morphTypeAttribute] = $inputArray['morph_type'];
            unset($inputArray['morph_type']);
            $inputArray[$morphIdAttribute] = $inputArray['morph_id'];
            unset($inputArray['morph_id']);
            unset($inputArray['id']);

            $inputArray[$modelField] = $this->model->getKey();

            switch ($status) {
                case 'old':
                case 'new':
                case 'updated':
                case 'deleted':
                    $hasManyModel = $hasManyModelName::create($inputArray);
                    $hasManyModel->save();


                    break;
                default:
                    throw new Exception("Invalid status " . $status);
                    break;
            }
        }
    }


}
