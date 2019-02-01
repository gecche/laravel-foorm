<?php

namespace Cupparis\Form;

use Cupparis\Acl\Facades\Acl;
use Cupparis\Ardent\Ardent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Lang;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class ModelFormDetail extends ModelForm
{


    protected $resultExcludedFields = array();

    protected $hasManiesInsertDefault = array(
        //key => array(
        //  subfield => value
        //)
    );

    protected $input = array();

    public function __construct(Ardent $model, $permissionPrefix = null, $params = array())
    {

        parent::__construct($model, $permissionPrefix, $params);

        $this->setHasManies();
        $this->setBelongsTos();
        $this->setResult();
        $this->setResultParams();
        if (!$this->model->getKey()) {
            $this->setResultDefaults();
        }
        $this->setMetadata();

        $this->setValidationRules();
        $this->setValidationRulesJs();

        if (Config::get('app.translations_mode') != 'file') {
            $this->setTranslations();
        }
    }

    public function setInput($input = null)
    {

        $this->input = is_array($input) ? $input : Input::all();

        $constraintKey = array_get($this->params, 'constraintKey', false);
        $constraintValue = array_get($this->params, 'constraintValue', false);
        if ($constraintKey) {
            $this->input[$constraintKey] = $constraintValue;
        }
    }

    public function save($input = null, $validate = true)
    {

        $this->setInput($input);

        if ($validate) {
            $this->isValid($this->input);
        }

        $backParams = Input::get('backParams', array());
        $this->model->setFrontEndParams($backParams);

        //Filtrare le entries delle relazioni

        $inputFiltered = $this->filterInputForGuarded($this->input);
        $this->model->fill($inputFiltered);

        //QUI POTREI FARE UNA FORCE SAVE PERCHE' LA VALIDAZIONE L'HO GIA' FATTA CON IL MODELFORM
        //COMUNUQE SE NON VOGLIO BYPASSARE LA VALIDAZIONE DEL MODELLO DEVO ALMENO DISTINGUERE TRA INSERT E UPDATE
        if ($this->model->getKey()) {
            $saved = $this->model->updateUniques();
        } else {
            $saved = $this->model->save();
        }

        if (!$saved) {
            throw new Exception($this->model->errors());
        }

        $this->model->fresh();

        $this->saveRelated($this->input);
        $this->setResult();

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
            if (Config::get('forms.use_field_exists',false) && !array_key_exists($hasManyKey."_exists",$input)) {
                continue;
            }
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
        $belongsToModel->fill($belongsToInputs);
        $belongsToModel->save();

        $this->model->$belongsToKey = $belongsToModel->getKey();
        $this->model->save();
    }

    public function saveRelatedBelongsToManyAdd($hasManyKey, $hasManyValue, $input, $params = array())
    {
        $hasManyInputs = preg_grep_keys('/^' . $hasManyKey . '-/', $input);
        $hasManyInputs = trim_keys($hasManyKey . '-', $hasManyInputs);
        $hasManyInputs = $this->filterInputForGuardedMain($hasManyInputs);
        $hasManyModelName = $hasManyValue['modelName'];

        $standardActions = [
            'update' => true,
            'remove' => true,
        ];
        $actionsToDo = array_get($params, 'actions', array());
        $actionsToDo = array_merge($standardActions, $actionsToDo);


        foreach (array_get($hasManyInputs, 'status', array()) as $i => $status) {

            $inputArray = array();
            foreach (array_keys($hasManyInputs) as $key) {
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
                    $hasManyModel = $hasManyModelName::find($inputArray['id']);
                    $this->model->$hasManyKey()->detach($hasManyModel->getKey());
                    $this->model->$hasManyKey()->attach($hasManyModel->getKey(), $pivotValues);
                    break;
                case 'new':
                    $hasManyModel = $hasManyModelName::create($inputArray);
                    $hasManyModel->filesOps($inputArray);
                    $this->model->$hasManyKey()->attach($hasManyModel->getKey(), $pivotValues);
                    break;
                case 'updated':
                    $hasManyModel = $hasManyModelName::find($inputArray['id']);
                    if ($actionsToDo['update']) {
                        $hasManyModel->update($inputArray);
                        $hasManyModel->filesOps($inputArray);
                    }
                    $this->model->$hasManyKey()->detach($hasManyModel->getKey());
                    $this->model->$hasManyKey()->attach($hasManyModel->getKey(), $pivotValues);
                    break;
                case 'deleted':
                    if ($actionsToDo['remove']) {
                        $hasManyModel = $hasManyModelName::destroy($inputArray['id']);
                    } else {
                        $hasManyModel = $hasManyModelName::find($inputArray['id']);
                        $this->model->$hasManyKey()->detach($hasManyModel->getKey());
                    }
                    break;
                default:
                    throw new Exception("Invalid status " . $status);
                    break;
            }

            $this->model->load($hasManyKey);
        }
    }

    public function saveRelatedBelongsToManyStandardWithSave($hasManyKey, $hasManyValue, $input, $params = array())
    {
        $hasManyInputs = preg_grep_keys('/^' . $hasManyKey . '-/', $input);
        $hasManyInputs = trim_keys($hasManyKey . '-', $hasManyInputs);
        $hasManyModelName = $hasManyValue['modelName'];
        $pivotModelName = $this->models_namespace . $hasManyValue['pivotModelName'];

        $this->model->$hasManyKey()->sync(array());
        foreach (array_get($hasManyInputs, 'status', array()) as $i => $status) {

            $inputArray = array();
            foreach (array_keys($hasManyInputs) as $key) {
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
                    $this->model->$hasManyKey()->attach($inputArray['id'], $pivotValues);
                    $pivotModelName::orderBy('id', 'DESC')->first()->save();
                    break;
                default:
                    throw new Exception("Invalid status " . $status);
                    break;
            }
        }
    }

    public function saveRelatedBelongsToManyStandard($hasManyKey, $hasManyValue, $input, $params = array())
    {
        $hasManyInputs = preg_grep_keys('/^' . $hasManyKey . '-/', $input);
        $hasManyInputs = trim_keys($hasManyKey . '-', $hasManyInputs);
        $hasManyModelName = $hasManyValue['modelName'];

        $this->model->$hasManyKey()->sync(array());
        foreach (array_get($hasManyInputs, 'id', array()) as $i => $status) {

            $inputArray = array();
            foreach (array_keys($hasManyInputs) as $key) {
                $inputArray[$key] = $hasManyInputs[$key][$i];
            }

            $pivotFields = $this->model->getPivotKeys($hasManyKey);
            $pivotValues = array();

            foreach ($pivotFields as $pivotField) {
                if ($pivotField == 'ordine') {
                    $pivotValues[$pivotField] = $i;
                    continue;
                }

                $pivotValues[$pivotField] = array_get($inputArray, $pivotField, null);

            }
            $this->model->$hasManyKey()->attach($inputArray['id'], $pivotValues);

//            switch ($status) {
//                case 'old':
//                case 'new':
//                case 'updated':
//                case 'deleted':
//                    $this->model->$hasManyKey()->attach($inputArray['id'], $pivotValues);
//                    break;
//                default:
//                    throw new Exception("Invalid status " . $status);
//                    break;
//            }
        }
        // vecchio codice,
//        foreach (array_get($hasManyInputs, 'status', array()) as $i => $status) {
//
//            $inputArray = array();
//            foreach (array_keys($hasManyInputs) as $key) {
//                $inputArray[$key] = $hasManyInputs[$key][$i];
//            }
//
//            $pivotFields = $this->model->getPivotKeys($hasManyKey);
//            $pivotValues = array();
//
//            foreach ($pivotFields as $pivotField) {
//                if ($pivotField == 'ordine') {
//                    $pivotValues[$pivotField] = $i;
//                    continue;
//                }
//
//                $pivotValues[$pivotField] = array_get($inputArray, $pivotField, null);
//
//            }
//
//            switch ($status) {
//                case 'old':
//                case 'new':
//                case 'updated':
//                case 'deleted':
//                    $this->model->$hasManyKey()->attach($inputArray['id'], $pivotValues);
//                    break;
//                default:
//                    throw new Exception("Invalid status " . $status);
//                    break;
//            }
//        }
    }

    public function saveRelatedAclBelongsToMany($hasManyKey, $hasManyValue, $input, $params = array())
    {
        $aclType = trim_namespace($this->models_namespace, get_class($this->model));
        if (!in_array($aclType, array('Role', 'User')))
            throw new Exception("Invalid acl type " . $aclType);
        $permissionId = strtoupper(snake_case($hasManyKey));

        $hasManyInputs = preg_grep_keys('/^' . $hasManyKey . '-/', $input);
        $hasManyInputs = trim_keys($hasManyKey . '-', $hasManyInputs);
        $hasManyModelName = $hasManyValue['modelName'];
        if ($aclType === 'Role')
            Acl::updateRolePermission($this->model->getKey(), $permissionId, false, $hasManyInputs['id']);
        else
            Acl::updateUserPermission($this->model->getKey(), $permissionId, false, $hasManyInputs['id']);

    }

    public function saveRelatedHasManyStandard($hasManyKey, $hasManyValue, $input, $params = array())
    {
        $hasManyInputs = preg_grep_keys('/^' . $hasManyKey . '-/', $input);
        $hasManyInputs = trim_keys($hasManyKey . '-', $hasManyInputs);

        $hasManyInputs = $this->filterInputForGuardedMain($hasManyInputs);
        $hasManyModelName = $hasManyValue['modelName'];

        foreach (array_get($hasManyInputs, 'status', array()) as $i => $status) {

            $inputArray = array();
            foreach (array_keys($hasManyInputs) as $key) {
                $inputArray[$key] = array_get($hasManyInputs[$key], $i);
            }


            if (array_get($hasManyValue, 'orderKey', false)) {
                $inputArray[$hasManyValue['orderKey']] = $i;
            }
            unset($inputArray['status']);

            //SALVARE
            switch ($status) {
                case 'new':
                    $hasManyModel = new $hasManyModelName($inputArray);
                    $this->model->$hasManyKey()->save($hasManyModel);
                    $hasManyModel->filesOps($inputArray);
                    break;
                case 'old':
                    $hasManyModel = $hasManyModelName::find($inputArray['id']);
                    $hasManyModel->update($inputArray);
                    break;
                case 'updated':
                    $hasManyModel = $hasManyModelName::find($inputArray['id']);
                    $hasManyModel->update($inputArray);
                    $hasManyModel->filesOps($inputArray);
                    break;
                case 'deleted':
                    $hasManyModel = $hasManyModelName::destroy($inputArray['id']);
                    break;
                default:
                    throw new Exception("Invalid status " . $status);
                    break;
            }
            $this->model->load($hasManyKey);

        }
    }

    public function saveRelatedHasManyAssociation($hasManyKey, $hasManyValue, $input, $params = array())
    {
        $hasManyInputs = preg_grep_keys('/^' . $hasManyKey . '-/', $input);
        $hasManyInputs = trim_keys($hasManyKey . '-', $hasManyInputs);
        $hasManyModelName = $hasManyValue['modelName'];
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


    public function setValidationRules()
    {
        $modelName = $this->modelName;
        $modelRules = $modelName::$rules;

        $this->validationRules = $modelRules;

        $validationHasMany = array();
        foreach ($this->hasManies as $key => $value) {
            $hasManyModelName = $value['modelName'];

            $hasManyRules = $hasManyModelName::$rules;

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
            //$this->validationRules[] = 
            $validationHasMany = array_merge($validationHasMany, $hasManyRules);
        }

        $this->validationRules = array_merge($this->validationRules, $validationHasMany);

        $this->setCustomValidationRules();

    }


    public function setValidationRulesJs()
    {

        $validationRulesJs = $this->getValidationRules();

        foreach ($validationRulesJs as $ruleKey => $rule) {
            $validationRulesJs[$ruleKey] = rtrim($rule, "Array");
        }
        $this->validationRulesJs = $validationRulesJs;


    }


    public function setResult()
    {

        $this->setModelResult();
        /*
         * Performs custom model data
         */
        $this->customizeResult();

        return $this->result;
    }

    public function setModelResult()
    {

        $this->result = $this->model->toArray();


        $relations = $this->relations;


        foreach ($relations as $key => $relation) {

            switch ($relation[0]) {
                case Ardent::BELONGS_TO:
                    $belongsToModel = $this->model->$key;
                    $this->result[$key] = $belongsToModel === null ? array() : $belongsToModel->toArray();

                    //Result ad hoc per i points, eventualmente da modellare meglio ma per ora ok così
                    if ($key == 'point' && $belongsToModel) {
                        $this->result['point-lat'] = $this->model->point->lat;
                        $this->result['point-lng'] = $this->model->point->lng;
                    }
                    break;
                case Ardent::BELONGS_TO_MANY:
                case Ardent::HAS_MANY:
                    $this->result[$key] = $this->model->$key;
                    break;
                case Ardent::HAS_ONE:
                    $this->result[$key] = $this->model->$key === null ? array() : $this->model->$key->toArray();
                    break;
                default:
                    break;
            }
        }

        /*
         * Remove excluded fields
         */
        foreach ($this->resultExcludedFields as $excludedField) {
            unset($this->result[$excludedField]);
        }
    }

    public function setResultDefaults()
    {
        $resultDefault = $this->db_methods->listColumnsDefault($this->model->getTable(), NULL, true, true);
        foreach ($this->model->getHidden() as $hiddenKey) {
            unset($resultDefault[$hiddenKey]);
        }
        $this->result = array_merge($resultDefault, $this->result);

        $formItemNone = env('FORM_ITEM_NONE', -99);
        foreach ($this->resultParams as $paramKey => $paramValue) {
            if (!is_array($paramValue) || !array_key_exists('options', $paramValue) || array_get($this->result, $paramKey)) {
                continue;
            }
            $options = array_get($paramValue, 'options', []);
            $forSelectListConfig = array_get($this->forSelectListConfig, $paramKey,
                $this->forSelectListConfig['default']);
            $onlyValueSelectedDetail = array_get($forSelectListConfig,'onlyValueSelectedDetail',true);

            if (array_key_exists($formItemNone, $options))
                unset($options[$formItemNone]);
            if (count($options) == 1 && $onlyValueSelectedDetail) {
                $this->result[$paramKey] = key($options);
            }
        }

        //PARTE AGGIUNTIVA PER PRENDERE DEI PARAMETRI DI DEFAULT DA INPUT
        //USO I CAMPI CHE INZIANO CON fd_
        $url = URL::previous();

        try {
            parse_str( parse_url( $url, PHP_URL_QUERY), $input );
            $defaultInputs = preg_grep_keys('/^fd_/', $input);
            $defaultInputs = searchform_trim_keys('fd_', $defaultInputs);
            foreach ($defaultInputs as $defaultKey => $defaultValue) {
                if (array_key_exists($defaultKey, $this->result)) {
                    $this->result[$defaultKey] = $defaultValue;
                }
            }
        } catch (Exception $e) {

        }

        $constraintKey = array_get($this->params, 'constraintKey', false);
        $constraintValue = array_get($this->params, 'constraintValue', false);
        if ($constraintKey && array_key_exists($constraintKey, $this->result)) {
            $this->result[$constraintKey] = $constraintValue;
        }

    }

    public function setResultExcludedFields($array = array())
    {
        $this->resultExcludedFields = $array;
        return true;
    }


    public function setResultParams()
    {

        $cacheKey = 'resultParams.detail.' . $this->modelRelativeName;
//        echo "CacheKey::$cacheKey::\n";

        if (!app()->environment('local') && Config::get('app.cacheform') && Cache::has($cacheKey)) {
            $this->resultParams = Cache::get($cacheKey);
            return;
        }

        $this->resultParams = $this->db_methods->listColumnsDefault($this->model->getTable());

        $this->setResultParamsAppendsDefaults();
        $this->setResultParamsDefaults();

        foreach (array_keys($this->result) as $resultField) {

            $this->createResultParamItem($resultField);

        }

        $fieldParamsFromModel = $this->model->getFieldParams();
        $modelFieldsParamsFromModel = array_intersect_key($fieldParamsFromModel, $this->resultParams);

        $this->resultParams = array_replace_recursive($this->resultParams, $modelFieldsParamsFromModel);

        foreach ($this->hasManies as $key => $value) {
            $modelName = $value['modelName'];
            $model = new $modelName;
            $value["fields"] = $this->db_methods->listColumnsDefault($model->getTable());

            /*
             * AGGIUNTE SELECT PER BELONGS TO DENTRO HAS MANIES: DA METTERE NELLA VARIABILE FIELD PARAMS DEL MODELLO
             */

            if (array_get($this->resultParams, $key, false)) {
                foreach (array_get($this->resultParams[$key], 'belongsTo', []) as $belongsToField => $belongsToRelativeModelName) {
                    $belongsToModelName = $this->models_namespace . $belongsToRelativeModelName;
                    $options = $belongsToModelName::getForSelectList();
                    $value['fields'][$belongsToField]['options'] = $options;
                    $value['fields'][$belongsToField]['options_order'] = array_keys($options);
                }
            }

            /*
             * OPZIONI INIZIALI PER HAS MANY THROUGH
             */

            //if ($value['saveType'] === 'standard')  {
            if (array_get($value, 'saveType', false) === 'standard') {
                $value['hasManyThroughData'] = $modelName::autoComplete();
            }

            $this->resultParams[$key] = $value;
        }

        foreach ($this->belongsTos as $key => $value) {
            $modelName = $value['modelName'];
            list($columns, $separator, $otherParams) = $this->calculateSelectListParams($key);
//            Log::info(print_r($columns,1));
//            Log::info($separator);
//            Log::info(print_r($otherParams,1));
            $options = $modelName::getForSelectList($columns, $separator, $otherParams);
//            Log::info(print_r($options,1));
//            Log::info('END BENLONGSTO');
            $value['options'] = $options;
            $value['options_order'] = array_keys($options);

            //AGGIUNGO DATI MODELLO PER AD ESEMPIO AUTOCOMPLETE
            $value['modelData'] = array_get($this->result,$value['relationName'],[]);
            $this->resultParams[$key] = $value;
        }
        Cache::forever($cacheKey, $this->resultParams);

    }

    protected function calculateSelectListParams($key)
    {
        $separator = null;
        $columns = null;
        $otherParams = [];
        $fieldParams = $this->model->getFieldParams();
        $keySelectListParams = false;
        $keyParams = array_get($fieldParams, $key, false);
        if (is_array($keyParams)) {
            $keySelectListParams = array_get($keyParams, 'formSelectList', false);
        }
        if (is_array($keySelectListParams)) {
            $separator = array_get($keySelectListParams, 'separator', null);
            $columns = array_get($keySelectListParams, 'columns', null);
            $otherParams = array_get($keySelectListParams, 'params', []);
        }
        return array($columns, $separator, $otherParams);
    }

    public function setResultParamsDefaults()
    {

        parent::setResultParamsDefaults();

        /*
         * non serve perché ho aggiunto le options automatiche sui booleani
        if (array_get($this->resultParams,'attivo',false)) {
            $this->resultParams['attivo']['options'] = array(
                0 => ucfirst(Lang::get("app.no")),
                1 => ucfirst(Lang::get("app.yes"))
            );
        }
        */

        if (array_get($this->resultParams, 'newsletter_registered', false)) {
            $this->resultParams['newsletter_registered']['options'] = array(
                0 => ucfirst(Lang::get("app.no")),
                1 => ucfirst(Lang::get("app.yes"))
            );
        }

        if (array_get($this->resultParams, 'riservato', false)) {
            $this->resultParams['riservato']['options'] = array(
                0 => ucfirst(Lang::get("app.no")),
                1 => ucfirst(Lang::get("app.yes"))
            );
        }

        if (array_get($this->resultParams, 'fotos', false)) {
            $this->resultParams['fotos']['managed_fields'] = array(
                'nome',
                'descrizione',
            );
        }

    }

    public function setMetadata()
    {
        //$resultParamsKeys = array_keys($this->resultParams);
        //$resultKeys = array_keys($this->result);

        $this->metadata = $this->resultParams;
//        foreach ($resultParamsKeys as $key) {
//            if (isset($resultKeys[$key])) {
//                unset($this->metadata[$key]);
//            }
//        }

    }

    protected function filterInputForGuarded($input = array())
    {
        $inputFiltered = $input;
        $hasManiesKeys = array_keys($this->hasManies);
        foreach ($hasManiesKeys as $hasManyKey) {
            $inputFiltered = array_key_remove($inputFiltered, $hasManyKey . '-', false);
            // a volte il vettore degli has many e' vuoto e viene spedito il nome della relation e basta... la rimuovo per esempio sends di newsletter
            $inputFiltered = array_key_remove($inputFiltered, $hasManyKey, false);
        }

        $belongsTosKeys = array_keys($this->belongsTos);
        foreach ($belongsTosKeys as $belongsTosKey) {
            $key = $this->belongsTos[$belongsTosKey]['relationName'];
            $inputFiltered = array_key_remove($inputFiltered, $key . '-', false);
        }

        return $this->filterInputForGuardedMain($inputFiltered);

    }


    protected function filterInputForGuardedMain($input = array())
    {
        $inputFiltered = $input;

        $others_suffixes = ['_picker', '_view', '_exists', 'permalink'];
        foreach ($others_suffixes as $suffix) {
            $inputFiltered = array_key_remove($inputFiltered, $suffix, true);
        }
        return $inputFiltered;

    }


}
