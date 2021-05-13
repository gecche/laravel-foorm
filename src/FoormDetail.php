<?php

namespace Gecche\Foorm;


use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FoormDetail extends Foorm
{

    use ConstraintBuilderTrait;
    use FoormSingleTrait;

    protected $inputForSave = null;

    protected $validator = null;
    protected $validationSettings = null; //rules, customMessages, customAttributes


    public
    function isValid($input = null, $settings = null)
    {

        $input = is_array($input) ? $input : $this->input;

        $finalSettings = $this->getValidationSettings($input, $settings);
        $rules = Arr::get($finalSettings, 'rules', []);
        $customMessages = Arr::get($finalSettings, 'customMessages', []);
        $customAttributes = Arr::get($finalSettings, 'customAttributes', []);

        $this->validator = Validator::make($input, $rules, $customMessages, $customAttributes);

        if (!$this->validator->passes()) {
            $errors = $this->validator->errors()->getMessages();
            $errors = Arr::flatten($errors);
            throw ValidationException::withMessages($errors);
        }

        return true;
    }

    public
    function getValidationSettings($input, $rules)
    {
        $this->setValidationSettings($input, $rules);
        return $this->validationSettings;
    }

    protected
    function buildValidationSettings()
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

            $hasManyRules = Arr::get($hasManyValidationSettings, 'rules', []);
            $hasManyRules = array_key_append($hasManyRules, $key . '-', false);
            $hasManyRules = array_key_append($hasManyRules, '.*', true);
//            $newHasManyRules = [];
//            foreach ($hasManyRules as $hasManyRuleKey => $hasManyRuleValue) {
//                $newHasManyRules[$key.'*'.$hasManyRuleKey] => $hasManyRuleValue;
//            }
//            foreach ($hasManyRules as $hasManyRuleKey => $hasManyRuleValue) {
//                $valueRules = explode('|', $hasManyRuleValue);
//
//                //TODO: per ora le regole che sono già array le elvo perché troppo icnasinato e probabilmente sono casi stralimite.
//                //$nestedLevelRules = array();
//                foreach ($valueRules as $keyRule => $rule) {
//                    if (substr($rule, -5) === 'Array') {
//                        //$nestedLevelRules[$keyRule] = $rule;
//                        //Array nested per il momento non supportati! :) e quindi eliminati!!!
//                        unset($valueRules[$keyRule]);
//                        continue;
//                    }
//                    $valueRules[$keyRule] = $rule . 'Array';
//                }
//                $hasManyRules[$hasManyRuleKey] = implode('|', $valueRules);
//            }
//            $hasManyRules = array_key_append($hasManyRules, $key . '-', false);

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


    public
    function setValidationSettings($input, $rules = null)
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


    public
    function getValidationRulePrefix($prefix, $remove = true, $separator = '-')
    {

        $full_prefix = $prefix . $separator;
        $rules = preg_grep_keys('/^' . $full_prefix . '/', $this->validationSettings);
        if ($remove) {
            $rules = trim_keys($full_prefix, $rules);
        }
        return $rules;
    }


    public
    function save($input = null, $validate = true)
    {

        $this->inputForSave = is_array($input) ? $input : $this->setInputForSave();

        $this->setFixedConstraints($this->inputForSave);


        if ($validate) {
            $this->isValid($this->inputForSave);
        }

        $this->setFieldsToModel($this->model, Arr::get($this->config, 'fields', []), $this->inputForSave);

        //EVENTUALMENTE QUI AGGIUNGERE UN MODO PER SALVARE I BELONGS TO AGGIUNTI A RUN-TIME (MA BOH)

        $saved = $this->saveModel($this->inputForSave);

        //DA FARE
        $this->saveRelated($this->inputForSave);

        return $saved;
    }

    protected function setInputForSave()
    {

        $inputForSave = $this->input;

        $inputForSave = $this->transformRelationsAsOptions($inputForSave);

        return $inputForSave;

    }

    protected function transformRelationsAsOptions($input)
    {
        foreach ($this->relationsAsOptions as $key => $field) {
            $relationField = $key . '-' . $field;
            $values = Arr::get($input, $key, []);
            $input[$relationField] = $values;
//            $relationFieldStatus = $key .'-status';
//            $input[$relationFieldStatus] = array_fill(0,count($values),'new');
            unset($input[$key]);
        }
        return $input;
    }

    protected
    function setFieldsToModel($model, $configFields, $input)
    {

        //SE SONO IN EDIT non considero la primarykey dell'input
        if (Arr::get($this->params, 'id')) {
            unset($input[$this->primary_key_field]);
        }
        foreach (array_keys($configFields) as $fieldName) {
            /*
             * Filtro i campi in base alla configurazione.
             * Se nell'input non sono presenti alcuni campi non imposto niente
             */
            if (!array_key_exists($fieldName, $input)) {
                continue;
            }
            $model->$fieldName = Arr::get($input, $fieldName);
        }

    }


    protected
    function saveModel($input)
    {
        $saved = $this->model->save();
        if (!$saved) {
            throw new \Exception("Problemi nel salvataggio");
        }

        $this->model = $this->model->fresh();
        if (is_null($this->model)) {
            $modelName = $this->getModelName();
            $this->model = new $modelName;
        }
    }


    protected
    function saveRelated($input)
    {

        foreach ($this->belongsTos as $belongsToKey => $belongsToValue) {
            $saveRelatedName = 'saveRelated' . Str::studly($belongsToKey);
            $saveParams = $this->getRelationConfig($belongsToKey, 'saveParams', []);
            $this->$saveRelatedName('BelongsTo', $belongsToKey, $belongsToValue, $input, $saveParams);
        }


        foreach ($this->hasManies as $hasManyKey => $hasManyValue) {
            $saveRelatedName = 'saveRelated' . Str::studly($hasManyKey);
            $hasManyType = $hasManyValue['relationType'];
            $saveType = $this->getRelationConfig($hasManyKey, 'saveType');
            $saveParams = $this->getRelationConfig($hasManyKey, 'saveParams', []);

            if ($saveType) {
                $hasManyType = $hasManyType . Str::studly($saveType);
            }

            $hasManyInputs = $this->getHasManyInputs($hasManyKey,$input);
            $this->$saveRelatedName($hasManyType, $hasManyKey, $hasManyValue, $hasManyInputs, $saveParams);
        }
    }

    protected function getHasManyInputs($hasManyKey,$input) {
        $hasManyInputs = preg_grep_keys('/^' . $hasManyKey . '-/', $input);
        $hasManyInputs = trim_keys($hasManyKey . '-', $hasManyInputs);
        return $hasManyInputs;
    }

    /*
     * Salvataggio classico di belogns to many con aggancio/sgancio dei modelli dal modello principale e gestione dei
     * campi aggiuntivi della tabella pivot se presenti
     * Aggiunta anche la possibilità di agigungere direttamente nuovi elementi della tabella di destinazione se presenti
     */
//CREDO SIA OK
    public
    function saveRelatedBelongsToMany($hasManyKey, $hasManyValue, $hasManyInputs, $params = array())
    {

        $hasManyModelName = $hasManyValue['modelName'];
        $hasManyModel = new $hasManyModelName();
        $pkName = $hasManyModel->getKeyName();

        //Faccio il sync con vuoto: cancello tutte le associazioni presenti
        $this->model->$hasManyKey()->sync([]);

        //Se c'è un cmapo di ordinamento nella pivot
        $orderKey = $this->getRelationConfig($hasManyKey, 'orderKey');
        $pivotFields = $this->getRelationConfig($hasManyKey, 'pivotFields', []);
        $statusKey = $this->getRelationConfig($hasManyKey, 'statusKey', 'status');

        foreach (Arr::get($hasManyInputs, $pkName, []) as $i => $pk) {

            $pivotValues = [];

            foreach ($pivotFields as $pivotField) {

                //Il campo di ordinamento lo imposto io con l'ordine del form di interfaccia
                if ($pivotField == $orderKey) {
                    $pivotValues[$pivotField] = $i;
                    continue;
                }

                $pivotValues[$pivotField] = Arr::get(Arr::get($hasManyInputs, $pivotField, []), $i);

            }

            //In caso di possibile aggiunta salvo il modello
            $status = null;
            if (array_key_exists($statusKey, $hasManyInputs)) {
                $status = Arr::get($hasManyInputs[$statusKey], $i);
            }

            switch ($status) {
                case 'new':

                    $inputArray = [];
                    foreach ($this->getRelationFieldsFromConfig($hasManyKey) as $key => $value) {
                        if (array_key_exists($key, $hasManyInputs) && array_key_exists($i, $hasManyInputs[$key])) {
                            $inputArray[$key] = $hasManyInputs[$key][$i];
                        }
                    }

                    $hasManyModel = new $hasManyModelName($inputArray);
                    $this->performCallbacksSaveRelatedOperation($hasManyKey, 'beforeNewCallbackMethods', $hasManyModel, $inputArray);
                    $hasManyModel->save();
                    $this->performCallbacksSaveRelatedOperation($hasManyKey, 'afterNewCallbackMethods', $hasManyModel, $inputArray);
                    $pk = $hasManyModel->getKey();
                    break;
                default:
                    break;
            }


            //ESEGUO L'ATTACH CON I PIVOT VALUES
            $this->model->$hasManyKey()->attach($pk, $pivotValues);

        }

        $this->model->load($hasManyKey);

    }

    /*
     * Salvataggio classico di has many con aggiunta/rimozione degli has many collegati al modello principale
     */
    public
    function saveRelatedHasMany($hasManyKey, $hasManyValue, $hasManyInputs, $params = array())
    {

        $hasManyModelName = $hasManyValue['modelName'];
        $hasManyModel = new $hasManyModelName();
        $pkName = $hasManyModel->getKeyName();

        $statusKey = $this->getRelationConfig($hasManyKey, 'statusKey', 'status');
        $orderKey = $this->getRelationConfig($hasManyKey, 'orderKey');

        $currentPks = $this->model->$hasManyKey
            ->pluck($hasManyModel->getKeyName(), $hasManyModel->getKeyName())->all();

        $foundPks = [];

        foreach (Arr::get($hasManyInputs, $pkName, []) as $i => $pk) {

//            $status = $hasManyInputs[$statusKey][$i];

            if (in_array($pk, $currentPks)) {
                $status = 'updated';
                $foundPks[$pk] = $pk;
            } else {
                $status = 'new';
            }

            $inputArray = [];
            foreach (array_keys($this->getRelationFieldsFromConfig($hasManyKey)) as $key) {
                if (array_key_exists($key, $hasManyInputs) && array_key_exists($i, $hasManyInputs[$key])) {
                    $inputArray[$key] = $hasManyInputs[$key][$i];
                }
            }

            unset($inputArray[$statusKey]);
            if ($orderKey) {
                $inputArray[$orderKey] = $i;
            }

            //SALVARE
            switch ($status) {
                case 'new':
                    $hasManyModel = new $hasManyModelName($inputArray);
                    $this->performCallbacksSaveRelatedOperation($hasManyKey, 'beforeNewCallbackMethods', $hasManyModel, $inputArray);
                    $this->model->$hasManyKey()->save($hasManyModel);
                    $this->performCallbacksSaveRelatedOperation($hasManyKey, 'afterNewCallbackMethods', $hasManyModel, $inputArray);
                    break;
                case 'updated':
                    $hasManyModel = $hasManyModelName::find($pk);
                    $this->performCallbacksSaveRelatedOperation($hasManyKey, 'beforeUpdateCallbackMethods', $hasManyModel, $inputArray);
                    $hasManyModel->update($inputArray);
                    $this->performCallbacksSaveRelatedOperation($hasManyKey, 'afterUpdateCallbackMethods', $hasManyModel, $inputArray);
                    break;
                default:
                    throw new \Exception("Invalid status " . $status);
                    break;
            }

        }

        $notFoundPks = array_diff($currentPks, $foundPks);

        foreach ($notFoundPks as $pkToDelete) {
            $this->performCallbacksSaveRelatedOperation($hasManyKey, 'beforeDeleteCallbackMethods', $hasManyModel);
            $hasManyModelName::destroy($pkToDelete);
            //Questo non so se ha senso.
            $this->performCallbacksSaveRelatedOperation($hasManyKey, 'afterDeleteCallbackMethods', $hasManyModel);
        }


        $this->model->load($hasManyKey);

    }


//DA SISTEMARE E CAPIRE CHE CAVOLO ERA :)

//HO CAPITO: IN PRATICA QUESTO TIPO DI SALVATAGGIO E' UN PO' PARTICOLARE PERCHE'
//GESTISCE UN MODELLO HAS MANY IN CUI NON CREO/CANCELLO GLI ELEMENTI MA SEMPLICEMENTE
//GLI CAMBIO LA FOREIGN KEY.
//AD ESEMPIO SONO DENTRO UNA CATEGORIA "GESTIONE": GLI HAS MANY SONO DELLE PRATICHE:
//E CON L'HAS MANY VEDO TUTTE LE PARICHE DI QUESTA CATEGORIA
//SE LE "CANCELLO" DA QUI NON E' CHE CANCELLO LA PRATICA MA SEMPLICEMENTE GLI TOLGO LA CATEGORIA "GESTIONE"
//METTENDO A NULL LA FAOREIGN KEY.
//NON FREQUENTE MA HA SENSO: E' UN TIPO DI GESTIONE INVERSA DI UN BELONGSTO.
//SECONDO ME FUNZIONA ANCHE AL MOMENTO
    public
    function saveRelatedHasManyAssociation($hasManyKey, $hasManyValue, $hasManyInputs, $params = array())
    {

        $hasManyModelName = $hasManyValue['modelName'];
        $hasManyModel = new $hasManyModelName();
        $pkName = $hasManyModel->getKeyName();

        $hasManyModelForeignKey = Arr::get($hasManyValue, 'foreignKey');
        $hasManyModelForeignKey = $hasManyModelForeignKey ?: $hasManyModel->getForeignKey();


        foreach ($this->model->$hasManyKey as $hasManyModel) {
            $hasManyModel->$hasManyModelForeignKey = null;
            $hasManyModel->save();
        }

        foreach (Arr::get($hasManyInputs, $pkName, []) as $i => $pk) {

            $hasManyModel = $hasManyModelName::find($pk);
            $hasManyModel->$hasManyModelForeignKey = $this->model->getKey();
            $hasManyModel->save();
        }
    }


    public
    function saveRelatedMorphMany($hasManyKey, $hasManyValue, $hasManyInputs, $params = [])
    {

        return $this->saveRelatedHasMany($hasManyKey, $hasManyValue, $hasManyInputs, $params);

    }

    public
    function saveRelatedHasOne($hasManyKey, $hasManyValue, $hasManyInputs, $params = [])
    {

        return $this->saveRelatedHasMany($hasManyKey, $hasManyValue, $hasManyInputs, $params);

    }

    protected
    function performCallbacksSaveRelatedOperation($relationKey, $callbacksType, $relationModel, $inputArray = [])
    {

        $callbacks = $this->getRelationConfig($relationKey, $callbacksType, []);
        foreach ($callbacks as $callback) {
            $relationModel->$callback($inputArray);
        }

    }


    public
    function __call($name, $arguments)
    {

        $hasManyPrefix = 'saveRelated';
        if (Str::startsWith($name, $hasManyPrefix) && is_array($arguments)) {

            $suffix = Str::studly($arguments[0]);
            if (in_array($suffix, ['BelongsTo', 'MorphManyAdd'])) {
                return;
            }

            $newMethod = $hasManyPrefix . $suffix;
            unset($arguments[0]);
            return call_user_func_array(array($this, $newMethod), $arguments);
        }

        $prefixes = ['ajaxListing'];

        foreach ($prefixes as $prefix) {
            if (Str::startsWith($name, $prefix)) {
                return call_user_func_array(array($this, $prefix), $arguments);
            }
        }
        throw new \BadMethodCallException("Method [$name] does not exist.");

    }
}
