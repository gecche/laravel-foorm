<?php

namespace Gecche\Foorm;


use Gecche\DBHelper\Facades\DBHelper;
use Gecche\Breeze\Breeze;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;

class FoormDetail extends Foorm
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
            $hasManyType = $hasManyValue['relationType'];
            $saveType = $this->getRelationConfig($hasManyKey,'saveType', 'standard');
            $saveTypeParams = $this->getRelationConfig($hasManyKey,'saveTypeParams', []);
            if ($saveType) {
                $hasManyType = $hasManyType . studly_case($saveType);
            }
            $hasManyInputs = preg_grep_keys('/^' . $hasManyKey . '-/', $input);
            $hasManyInputs = trim_keys($hasManyKey . '-', $hasManyInputs);
            $this->$saveRelatedName($hasManyType, $hasManyKey, $hasManyValue, $hasManyInputs, $saveTypeParams);
        }
    }


    protected function getRelationFieldsFromConfig($relation)
    {
        return $this->getRelationConfig($relation,'fields', []);
    }

    protected function getRelationConfig($relation,$key = null, $defaultValue = null)
    {
        $relationsConfig = array_get($this->config, 'relations', []);
        $relationConfig = array_get($relationsConfig, $relation, []);
        if (is_null($key)) {
            return $relationConfig;
        }
        return array_get($relationConfig, $key, $defaultValue);
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
    public function saveRelatedBelongsToManyAdd($hasManyKey, $hasManyValue, $hasManyInputs, $params = array())
    {

        $hasManyModelName = $hasManyValue['modelName'];
        $hasManyModel = new $hasManyModelName();
        $pkName = $hasManyModel->getKeyName();

        $standardActions = [
            'update' => true,
            'remove' => true,
        ];
        $actionsToDo = array_get($params, 'actions', []);
        $actionsToDo = array_merge($standardActions, $actionsToDo);

        $statusKey = $this->getRelationConfig($hasManyKey,'status-key', 'status');

        foreach (array_get($hasManyInputs, $pkName, []) as $i => $pk) {

            $status = $hasManyInputs[$statusKey][$i];

            $inputArray = [];
            foreach ($this->getRelationFieldsFromConfig($hasManyKey) as $key => $value) {
                $inputArray[$key] = $hasManyInputs[$key][$i];
            }

            $pivotFields = $this->model->getPivotKeys($hasManyKey);
            $pivotValues = [];

            foreach ($pivotFields as $pivotField) {
                $pivotValues[$pivotField] = array_get($inputArray, $pivotField, null);
            }

            $orderKey = $this->getRelationConfig($hasManyKey,'orderKey');
            if ($orderKey) {
                $pivotValues[$orderKey] = $i;
            }


            switch ($status) {
                case 'old':
                    $hasManyModel = $hasManyModelName::find($pk);
                    $this->model->$hasManyKey()->detach($hasManyModel->getKey());
                    $this->model->$hasManyKey()->attach($hasManyModel->getKey(), $pivotValues);
                    break;
                case 'new':
                    $hasManyModel = $hasManyModelName::create($inputArray);
                    $callbacks = $this->getRelationConfig($hasManyKey,'afterNewCallbackMethods',[]);
                    foreach ($callbacks as $callback) {
                        $hasManyModel->$callback($inputArray);
                    }
                    $this->model->$hasManyKey()->attach($hasManyModel->getKey(), $pivotValues);
                    break;
                case 'updated':
                    $hasManyModel = $hasManyModelName::find($pk);
                    if ($actionsToDo['update']) {
                        $hasManyModel->update($inputArray);
                        $callbacks = $this->getRelationConfig($hasManyKey,'afterUpdateCallbackMethods',[]);
                        foreach ($callbacks as $callback) {
                            $hasManyModel->$callback($inputArray);
                        }
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

    public function saveRelatedBelongsToManyStandard($hasManyKey, $hasManyValue, $hasManyInputs, $params = array())
    {

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

    public function saveRelatedHasManyStandard($hasManyKey, $hasManyValue, $hasManyInputs, $params = array())
    {

        $hasManyModelName = $hasManyValue['modelName'];
        $hasManyModel = new $hasManyModelName();
        $pkName = $hasManyModel->getKeyName();

        $statusKey = array_get($this->config['relations'][$hasManyKey], 'status-key', 'status');

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
                    break;
                case 'updated':
                    $hasManyModel = $hasManyModelName::find($pk);
                    $hasManyModel->update($inputArray);
                    break;
                case 'deleted':
                    $hasManyModelName::destroy($pk);
                    break;
                default:
                    throw new \Exception("Invalid status " . $status);
                    break;
            }

        }
        $this->model->load($hasManyKey);
    }

    public function saveRelatedHasManyAssociation($hasManyKey, $hasManyValue, $hasManyInputs, $params = array())
    {

        $hasManyModelName = $hasManyValue['modelName'];
        $hasManyModel = new $hasManyModelName();
        $pkName = $hasManyModel->getKeyName();

        $hasManyModelForeignKey = array_get($hasManyValue, 'foreignKey');
        $hasManyModelForeignKey = $hasManyModelForeignKey ?: $hasManyModel->getForeignKey();


        foreach ($this->model->$hasManyKey as $hasManyModel) {
            $hasManyModel->$hasManyModelForeignKey = null;
            $hasManyModel->save();
        }

        foreach (array_get($hasManyInputs, $pkName, []) as $i => $pk) {

            $hasManyModel = $hasManyModelName::find($pk);
            $hasManyModel->$hasManyModelForeignKey = $this->model->getKey();
            $hasManyModel->save();
        }
    }

    public function saveRelatedHasManyMorph($hasManyKey, $hasManyValue, $hasManyInputs, $params = array())
    {

        $hasManyModelName = $hasManyValue['modelName'];
        $hasManyModel = new $hasManyModelName();
        $pkName = $hasManyModel->getKeyName();

        $hasManyModelForeignKey = array_get($hasManyValue, 'foreignKey');
        $hasManyModelForeignKey = $hasManyModelForeignKey ?: $hasManyModel->getForeignKey();


        foreach ($this->model->$hasManyKey as $hasManyModel) {
            $hasManyModel->delete();
        }

        $orderKey = array_get($hasManyValue, 'orderKey');
        $morphTypeKey = array_get($hasManyValue, 'morphType');
        $morphIdKey = array_get($hasManyValue, 'morphId');


        foreach (array_get($hasManyInputs, $pkName, []) as $i => $pk) {


            $inputArray = [];
            foreach ($this->getRelationFieldsFromConfig($hasManyKey) as $key) {
                $inputArray[$key] = $hasManyInputs[$key][$i];
            }

            if ($orderKey)
                $inputArray[$orderKey] = $i;

            $inputArray[$morphTypeKey] = $inputArray['morph_type'];

            $inputArray[$morphIdKey] = $inputArray['morph_id'];


            $inputArray[$hasManyModelForeignKey] = $this->model->getKey();


            $hasManyModel = $hasManyModelName::create($inputArray);
            $hasManyModel->save();

        }
    }


    public function __call($name, $arguments)
    {


        $aliases = array(
            'saveRelatedHasOneStandard' => 'saveRelatedHasManyStandard',
        );

        if (in_array($name, array_keys($aliases))) {
            return call_user_func_array(array($this, $aliases[$name]), $arguments);
        }

        $hasManyPrefix = 'saveRelated';
        if (starts_with($name, $hasManyPrefix) && is_array($arguments)) {

            $suffix = studly_case($arguments[0]);
            if (in_array($suffix, ['MorphManyAdd'])) {
                return;
            }

            $newMethod = $hasManyPrefix . $suffix;
            unset($arguments[0]);
            return call_user_func_array(array($this, $newMethod), $arguments);
        }

        $prefixes = ['ajaxListing'];

        foreach ($prefixes as $prefix) {
            if (starts_with($name, $prefix)) {
                return call_user_func_array(array($this, $prefix), $arguments);
            }
        }
        throw new \BadMethodCallException("Method [$name] does not exist.");

    }
}
