<?php

namespace Gecche\Foorm;

use Cupparis\Acl\Facades\Acl;
use Cupparis\Ardent\Ardent;

use Gecche\DBHelper\Facades\DBHelper;
use Gecche\Foorm\Contracts\ListBuilder;
use Gecche\Breeze\Breeze;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;

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


        $this->dbHelper = DBHelper::helper($this->model->getConnectionName());

        $this->prepareRelationsData();


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


    public function getModelsNamespace()
    {
        return array_get($this->config,'models_namespace');
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
        return array_get($this->config,'full_model_name');
    }

    /**
     * @return string
     */
    public function getModelRelativeName()
    {
        return $this->modelRelativeName;
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

//        foreach ($relations as $key => $relation) {
//            if (in_array($key, $this->inactiveRelations)) {
//                unset($relations[$key]);
//            }
//        }

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

        $configRelations = array_get($this->config,'relations',[]);

        $this->hasManies = [];



        foreach ($relations as $relationName => $relationFromModel) {

            if (!array_key_exists($relationName,$configRelations)) {
                continue;
            }

            $configRelationMetadata = array_get($configRelations,$relationName,[]);

            $relationConfig = [];

            $relationConfig['max_items'] = 0;
            $relationConfig['min_items'] = 0;
            switch ($relationFromModel[0]) {
                case Breeze::BELONGS_TO_MANY:

                    break;
                case Breeze::MORPH_MANY:

                    break;
                case Breeze::HAS_MANY:
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

        $configRelations = array_get($this->config,'relations',[]);

        $this->belongsTos = [];

        foreach ($relations as $relationName => $relationFromModel) {

            if (!array_key_exists($relationName,$configRelations)) {
                continue;
            }

            $configRelationMetadata = array_get($configRelations,$relationName,[]);

            $relationConfig = [];

            switch ($relationFromModel[0]) {
                case Breeze::BELONGS_TO:
//                    $foreignKey = array_get($relations[$relationName], 'foreignKey', snake_case($relationName) . '_id');
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

        foreach (array_get($metadata,'relations',[]) as $key => $relation) {

            $relation['modelName'] = $relation['modelRelativeName'];
            unset($relation['modelRelativeName']);
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
                array_get($fieldValue, 'bool-false-value', $defaultOptionsValues['bool-false-value'])
                => array_get($fieldValue, 'bool-false-label', $defaultOptionsValues['bool-false-label']),
                array_get($fieldValue, 'bool-true-value', $defaultOptionsValues['bool-true-value'])
                => array_get($fieldValue, 'bool-true-label', $defaultOptionsValues['bool-true-label']),
            ];
        }

        if ($options == 'dboptions') {
            return $this->dbHelper->listEnumValues($fieldKey);
        }

        if (starts_with($options, 'belongsto:')) {

            $relationValue = explode(':',$options);
            $relationModelName = $relationValue[1];

            $fullRelationModelName = $this->getModelsNamespace().$relationModelName;

            $relationModel = new $fullRelationModelName;
            $options = $relationModel->getForSelectList();

            return $options;
        }

        return [];

    }

    protected function setNullOption($fieldValue, $options, $hasNullOption, $defaultOptionsValues)
    {

        if ($hasNullOption == 'onchoice' && count($options) <= 1) {
            return $options;
        }

        $nullLabel = array_get($fieldValue, 'null-label', $defaultOptionsValues['null-label']);

        $nullOption = [$defaultOptionsValues['null-value'] =>
            ucfirst(trans($nullLabel))];

        return $nullOption + $options;

    }


    protected function setFormMetadataFields() {
        $fields = array_get($this->config, 'fields');
        $this->formMetadata['fields'] = $this->_setFormMetadataFields($fields);

        return $fields;
    }

    protected function _setFormMetadataFields($fields = []) {


        $defaultOptionsValues = [
            'null-value' => $this->config['null-value'],
            'null-label' => $this->config['null-label'],
            'bool-false-value' => $this->config['bool-false-value'],
            'bool-false-label' => $this->config['bool-false-label'],
            'bool-true-value' => $this->config['bool-true-value'],
            'bool-true-label' => $this->config['bool-true-label'],
        ];
        foreach ($fields as $fieldKey => $fieldValue) {

            if (array_get($fieldValue, 'options')) {
                $options = $this->createOptions($fieldKey, $fieldValue, $defaultOptionsValues);

                $hasNullOption = array_get($fieldValue, 'nulloption', true);

                if ($hasNullOption) {
                    $options = $this->setNullOption($fieldValue, $options, $hasNullOption, $defaultOptionsValues);
                }

                $fieldValue['options'] = $options;
                unset($fieldValue['nulloption']);
            }

            $fields[$fieldKey] = $fieldValue;

        }

        return $fields;
    }

    protected function setFormMetadataRelations() {

        $relations = [];


        foreach ($this->hasManies as $key => $relationMetadata) {
            $relationMetadata['fields'] = $this->_setFormMetadataFields(array_get($relationMetadata,'fields',[]));
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
