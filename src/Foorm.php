<?php

namespace Gecche\Foorm;

use Gecche\DBHelper\Facades\DBHelper;
use Gecche\Foorm\Contracts\ListBuilder;
use Gecche\Breeze\Breeze;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

abstract class Foorm
{

    /**
     * @var array
     */
    protected $input;

    /**
     * @var Breeze
     */
    protected $model;

    /**
     * @var array
     */
    protected $params;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $modelName;

    /**
     * @var string
     */
    protected $modelRelativeName;

    /**
     * @var string
     */
    protected $primary_key_field;


    protected $relations;

    protected $hasManies;
    protected $belongsTos;


    /**
     * @var mixed
     */
    protected $formData;

    /**
     * @var mixed
     */
    protected $formMetadata = null;

    /**
     * @var DBHelper
     */
    protected $dbHelper;

    protected $dependentForms = null;

    /**
     * FormList constructor.
     * @param array $input
     * @param Breeze $model
     * @param array $params
     */
    public function __construct(array $config, Breeze $model, array $input, $params = [])
    {

        $this->input = $input;
        $this->model = $model;
        $this->params = $params;
        $this->config = $config;

        $this->input = $this->filterPredefinedValuesFromInput($this->input);

        $this->dbHelper = DBHelper::helper($this->model->getConnectionName());


        $this->prepareRelationsData();

        $this->init();

    }

    protected function init() {
        return;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param array $config
     */
    public function setConfig($config)
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * @return null
     */
    public function getDependentForms()
    {
        return $this->dependentForms;
    }

    /**
     * @param null $dependentForms
     */
    public function setDependentForms($dependentForms)
    {
        $this->dependentForms = $dependentForms;
    }




    public function getModelsNamespace()
    {
        return Arr::get($this->config,'models_namespace');
    }

    /**
     * @return array
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * @return Breeze
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @return string
     */
    public function getModelName()
    {
        return Arr::get($this->config,'full_model_name');
    }

    /**
     * @return string
     */
    public function getModelRelativeName()
    {
        return Arr::get($this->config,'relative_model_name');
    }

    /**
     * @return string
     */
    public function getPrimaryKeyField()
    {
        return $this->primary_key_field;
    }




    public function getRelations()
    {
        if (is_null($this->relations)) {
            return $this->buildRelations();
        }
        return $this->relations;
    }

    public function setRelations(array $relations)
    {
        $this->relations = $relations;
    }


    /**
     * Costruisce l'array delle relazioni del modello principale del form.
     * Si basa sull'array relationsData del breeze, esclude le relazioni dichiarate inattive
     * da $inactiveRelations
     *
     */
    public function buildRelations()
    {

        $modelName = $this->getModelName();

        $relations = $modelName::getRelationsData();

        $configRelations = array_keys(Arr::get($this->config,'relations',[]));

        $relationsToUnset = array_diff(array_keys($relations),$configRelations);

        foreach ($relationsToUnset as $relationToUnset) {
            unset($relations[$relationToUnset]);
        }

        $this->relations = $relations;

        return $relations;
    }


    /**
     * Costruisce l'array interno degli has many
     * Dalle relazioni prende solo quelle di tipo:
     * hasMany, belongsToMany, hasOne, morphMany
     *
     *
     * @return array
     */
    public function setHasManies()
    {

        $relations = $this->getRelations();

        $configRelations = Arr::get($this->config,'relations',[]);

        $this->hasManies = [];



        foreach ($relations as $relationName => $relationFromModel) {

            if (!array_key_exists($relationName,$configRelations)) {
                continue;
            }

            $configRelationMetadata = Arr::get($configRelations,$relationName,[]);

            $relationConfig = [];

            $relationConfig['max_items'] = 0;
            $relationConfig['min_items'] = 0;
            switch ($relationFromModel[0]) {
                case Breeze::BELONGS_TO_MANY:
                case Breeze::MORPH_MANY:
                case Breeze::HAS_MANY:
                case Breeze::HAS_MANY_THROUGH:
                case Breeze::MORPH_TO_MANY:
                    break;
                case Breeze::HAS_ONE:
                    $relationConfig['max_items'] = 1;
                    break;
                default:
                    unset($relationConfig);
                    continue 2;  // per dire di continuare il ciclo for e non lo switch
                    break;
            }
            $relationConfig['relationType'] = $relationFromModel[0];
            $modelRelatedName = $relationFromModel['related'];
            $relationConfig['modelName'] = $modelRelatedName;
            $relationConfig['modelRelativeName'] = trim_namespace($this->getModelsNamespace(), $modelRelatedName);
            $relationConfig['relationName'] = $relationName;


            $relationConfig = array_merge($relationConfig,$configRelationMetadata);

            $this->hasManies[$relationName] = $relationConfig;

        }


        return $this->hasManies;
    }

    /**
     * @return array
     */
    public function getHasManies()
    {
        if (is_null($this->hasManies)) {
            return $this->setHasManies();
        }
        return $this->hasManies;
    }

    public function setBelongsTos()
    {

        $relations = $this->getRelations();

        $configRelations = Arr::get($this->config,'relations',[]);

        $this->belongsTos = [];

        foreach ($relations as $relationName => $relationFromModel) {

            if (!array_key_exists($relationName,$configRelations)) {
                continue;
            }

            $configRelationMetadata = Arr::get($configRelations,$relationName,[]);

            $relationConfig = [];

            switch ($relationFromModel[0]) {
                case Breeze::BELONGS_TO:
//                    $foreignKey = Arr::get($relations[$relationName], 'foreignKey', Str::snake($relationName) . '_id');
                    $relationConfig['relationName'] = $relationName;
                    break;
                default:
                    unset($relationConfig);
                    continue 2;  // per dire di continuare il ciclo for e non lo switch
                    break;
            }
            $relationConfig['relationType'] = $relationFromModel[0];
            $relationConfig['modelName'] = $relationFromModel['related'];
            $relationConfig['modelRelativeName'] = trim_namespace($this->getModelsNamespace(), $relationFromModel['related']);

            $relationConfig = array_merge($relationConfig,$configRelationMetadata);

            $this->belongsTos[$relationName] = $relationConfig;

        }


        return $this->belongsTos;
    }

    public function getBelongsTos()
    {
        if (is_null($this->belongsTos)) {
            return $this->setBelongsTos();
        }
        return $this->belongsTos;
    }


    protected function prepareRelationsData()
    {
        $this->getRelations();
        $this->getHasManies();
        $this->getBelongsTos();
    }

    /**
     * Generates and returns the data list
     *
     * - Defines the relations of the model involved in the form
     * - Generates an initial builder
     * - Apply search filters if any
     * - Apply the desired list order
     * - Paginate results
     * - Apply last transformations to the list
     * - Format the result
     *
     */
    abstract public function getFormData();



    public function getFormMetadata()
    {
        if (is_null($this->formMetadata)) {
            $this->setFormMetadata();
        }

        $metadata = $this->formMetadata;
        $metadata = $this->cleanMetadata($metadata);
        return $metadata;

    }

    public function cleanMetadata($metadata) {

        $relationFieldsToUnset = [
            //'modelRelativeName',
            'modelName',
            'relationName',
            'relationType',
            'saveType',
            'saveParams',
            'pivotFields',
            'beforeNewCallbackMethods',
            'beforeUpdateCallbackMethods',
            'beforeDeleteCallbackMethods',
            'afterNewCallbackMethods',
            'afterUpdateCallbackMethods',
            'afterDeleteCallbackMethods',
        ];

        foreach (Arr::get($metadata,'relations',[]) as $key => $relation) {

            foreach ($relationFieldsToUnset as $field) {
                unset($relation[$field]);
            }
            $metadata['relations'][$key] = $relation;
        }

        return $metadata;

    }

    protected function createOptions($fieldKey, $fieldValue, $defaultOptionsValues)
    {

        $options = $fieldValue['options'];

        //SE E' un array metto le options così come sono;
        if (is_array($options)) {
            return $options;
        }

        if ($options == 'boolean') {

            return [
                Arr::get($fieldValue, 'bool-false-value', $defaultOptionsValues['bool-false-value'])
                => Arr::get($fieldValue, 'bool-false-label', $defaultOptionsValues['bool-false-label']),
                Arr::get($fieldValue, 'bool-true-value', $defaultOptionsValues['bool-true-value'])
                => Arr::get($fieldValue, 'bool-true-label', $defaultOptionsValues['bool-true-label']),
            ];
        }

        if ($options == 'dboptions') {
            return $this->dbHelper->listEnumValues($fieldKey,$this->getModel()->getTable());
        }

        if (Str::startsWith($options, 'relation:')) {

            Log::info(print_r($this->getModelName(),true));

            /*
             * Prendo tutte le relazioni del modello anche quelle non in configurazione
             */
            $relations = ($this->getModelName())::getRelationsData();
            $relationValue = explode(':',$options);
            $relationName = $relationValue[1];

            $relationModelName = Arr::get(Arr::get($relations,$relationName,[]),'related');

            if (!$relationModelName) {
                throw new \Exception("Relation " . $relationName . " not found in compiling options.");
            }

            $relationModel = new $relationModelName;
            $options = $this->getForSelectList($relationName,$relationModel);

            return $options;
        }

        return [];

    }


    protected function getForSelectList($relationName,$relationModel) {
        return $relationModel->getForSelectList(null, null, [], null, null);
    }


    public function createOptionsOrder($fieldKey,$fieldValue) {
        return array_keys($fieldValue['options']);
    }

    protected function setPredefinedOption($type ,$fieldValue, $options, $hasPredefinedOption, $defaultOptionsValues)
    {

        if (!in_array($type,['null','any','no'])) {
            throw new \InvalidArgumentException("Predefined option not allowed");
        }

        if ($hasPredefinedOption == 'onchoice' && count($options) <= 1) {
            return $options;
        }

        $predefinedLabel = Arr::get($fieldValue, $type.'-label', $defaultOptionsValues[$type.'-label']);

        $predefinedOption = [$defaultOptionsValues[$type.'-value'] =>
            ucfirst(trans($predefinedLabel))];

        return $predefinedOption + $options;

    }

    //Metodo per provare a capire cosa abbia inserito l'utente in particolare per i campi non valorizzati
    //Mi posso aspettare string o array
    //Nel caso di string, se il campo è vuoto o è un array con il primo elemento vuoto ritorno null
    //Nel caso di array se è vuoto o se tutti gli elementi sono null ritorno null, se c'è un no-value ritorno no-value
    protected function guessInputValue($value,$expected = 'string') {
        if ($expected === 'array') {
            $value = Arr::wrap($value);
            foreach ($value as $valuePart) {
                if ($valuePart == $this->config['no-value']) {
                    return $this->config['no-value'];
                }
                if ($valuePart) {
                    return $value;
                }
            }
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_array($value) && count($value) > 0) {
            $value = current($value);
            if ($value) {
                return $value;
            };
        }

        return null;

    }


    public function filterPredefinedValuesFromInput($value) {

        $nullValue = $this->config['null-value'];
        $anyValue = $this->config['null-value'];
        $noValue = $this->config['no-value'];

        if (is_string($value)) {
          if (in_array($value,[$nullValue,$anyValue])) {
            return null;
          } elseif (in_array($value,[$noValue])) {
              return 'no-value';
          }
          return $value;
        }

        if (is_array($value)) {
            foreach ($value as $valuePartKey => $valuePart) {
                $value[$valuePartKey] = $this->filterPredefinedValuesFromInput($valuePart);
            }
        }

        return $value;
    }

    protected function setFormMetadataFields() {
        $fields = Arr::get($this->config, 'fields', []);
        $this->formMetadata['fields'] = $this->_setFormMetadataFields($fields);

        return $fields;
    }

    protected function _setFormMetadataFields($fields = []) {


        /*
         * AnyValue e NullValue convergono su NullValue
         */
        $defaultOptionsValues = [
            'any-value' => $this->config['null-value'],
            'any-label' => $this->config['any-label'],
            'no-value' => $this->config['no-value'],
            'no-label' => $this->config['no-label'],
            'null-value' => $this->config['null-value'],
            'null-label' => $this->config['null-label'],
            'bool-false-value' => $this->config['bool-false-value'],
            'bool-false-label' => $this->config['bool-false-label'],
            'bool-true-value' => $this->config['bool-true-value'],
            'bool-true-label' => $this->config['bool-true-label'],
        ];
        foreach ($fields as $fieldKey => $fieldValue) {

            if (Arr::get($fieldValue, 'options')) {
                $options = $this->createOptions($fieldKey, $fieldValue, $defaultOptionsValues);

                $hasNullOption = Arr::get($fieldValue, 'nulloption', true);
                if ($hasNullOption) {
                    $options = $this->setPredefinedOption('null',$fieldValue, $options, $hasNullOption, $defaultOptionsValues);
                }

                $hasAnyOption = Arr::get($fieldValue, 'anyoption', false);
                if ($hasAnyOption) {
                    $options = $this->setPredefinedOption('any', $fieldValue, $options, $hasNullOption, $defaultOptionsValues);
                }
                $hasNoOption = Arr::get($fieldValue, 'nooption', false);
                if ($hasNoOption) {
                    $options = $this->setPredefinedOption('no', $fieldValue, $options, $hasNullOption, $defaultOptionsValues);
                }

                $fieldValue['options'] = $options;

                unset($fieldValue['nulloption']);

                $fieldValue['options_order'] = $this->createOptionsOrder($fieldKey,$fieldValue);
            }

            $fields[$fieldKey] = $fieldValue;

        }

        return $fields;
    }

    protected function setFormMetadataRelations() {

        $relations = [];


        foreach ($this->hasManies as $key => $relationMetadata) {
            $relationMetadata['fields'] = $this->_setFormMetadataFields(Arr::get($relationMetadata,'fields',[]));
            $relations[$key] = $relationMetadata;
        }

        foreach ($this->belongsTos as $key => $relationMetadata) {
            $relations[$key] = $relationMetadata;
        }

        $this->formMetadata['relations'] = $relations;
        return $relations;
    }


    public function setFormMetadata()
    {


        $this->setFormMetadataFields();

        $this->setFormMetadataRelations();

    }


    /**
     * Append or prepend a string to each key of an array.
     *
     * @param  array $array
     * @param  string $prefix
     * @param  boolean $append (append or prepend the prefix)
     * @return string
     */
    protected function array_key_append($array, $prefix, $append = true) {
        $new_keys = array();
        foreach (array_keys($array) as $key) {
            if ($append)
                $new_keys[] = $key . $prefix;
            else
                $new_keys[] = $prefix . $key;
        }
        return array_combine($new_keys, array_values($array));
    }
}
