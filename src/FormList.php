<?php

namespace Gecche\Foorm;

use Cupparis\Acl\Facades\Acl;
use Cupparis\Ardent\Ardent;
use Gecche\ModelPlus\ModelPlus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;

class FormList {


    /**
     * @var array
     */
    protected $input;

    /**
     * @var ModelPlus
     */
    protected $model;

    /**
     * @var array
     */
    protected $params;

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
    protected $inactiveRelations;


    protected $formData;

    protected $formMetadata;

    public function __construct($input = [], ModelPlus $model, $params = [])
    {


        $this->input = $input;
        $this->model = $model;
        $this->params = $params;

        $this->modelName = get_class($this->model);
        $this->modelRelativeName = trim_namespace($this->getModelsNamespace(), $this->modelName);

        $this->primary_key_field = $this->model->getTable() . '.' . $this->model->getKeyName();

    }


    public function getModelsNamespace() {
        return config('foorm.models_namespace','App') . "\\";
    }

    /**
     * @return array
     */
    public function getInput()
    {
        return $this->input;
    }

    /**
     * @return ModelPlus
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
        return $this->modelName;
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



    public function getFormData() {
        $this->getRelations();
    }


    public function getRelations() {
        if (is_null($this->relations)) {
            return $this->buildRelations();
        }
        return $this->relations;
    }

    public function setRelations(array $relations) {
        $this->relations = $relations;
    }

    /**
     * @return mixed
     */
    public function getInactiveRelations()
    {
        return $this->inactiveRelations;
    }

    /**
     * @param mixed $inactiveRelations
     */
    public function setInactiveRelations($inactiveRelations)
    {
        $this->inactiveRelations = $inactiveRelations;
    }


    /**
     * Costruisce l'array delle relazioni del modello principale del form.
     * Si basa sull'array relationsData del modelplus, esclude le relazioni dichiarate inattive
     * da $inactiveRelations
     *
     */
    public function buildRelations()
    {

        $modelName = $this->modelName;

        $relations = $modelName::getRelationsData();

        foreach ($relations as $key => $relation) {
            if (in_array($key, $this->inactiveRelations)) {
                unset($relations[$key]);
            }
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

        foreach ($relations as $relationName => $relation) {

            $relations[$relationName]['max_items'] = 0;
            $relations[$relationName]['min_items'] = 0;
            switch ($relation[0]) {
                case ModelPlus::BELONGS_TO_MANY:

                    break;
                case ModelPlus::MORPH_MANY:

                    break;
                case ModelPlus::HAS_MANY:
                    break;
                case ModelPlus::HAS_ONE:
                    $relations[$relationName]['max_items'] = 1;
                    break;
                default:
                    unset($relations[$relationName]);
                    continue 2;  // per dire di continuare il ciclo for e non lo switch
                    break;
            }
            $relations[$relationName]['hasManyType'] = $relations[$relationName][0];
            unset($relations[$relationName][0]);
            $modelRelatedName = $relations[$relationName]['related'];
            unset($relations[$relationName][1]);
            $relations[$relationName]['modelName'] = $modelRelatedName;
            $relations[$relationName]['modelRelativeName'] = trim_namespace($this->getModelsNamespace(), $modelRelatedName);
            $relations[$relationName]['relationName'] = snake_case( $relations[$relationName]['modelRelativeName']);
        }

        $this->hasManies = $relations;

        return $relations;
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

        foreach ($relations as $relationName => $relation) {

            switch ($relation[0]) {
                case Ardent::BELONGS_TO:
                    $foreignKey = array_get($relations[$relationName], 'foreignKey', snake_case($relationName) . '_id');
                    $relations[$relationName]['relationName'] = $relationName;
                    break;
                default:
                    unset($relations[$relationName]);
                    continue 2;  // per dire di continuare il ciclo for e non lo switch
                    break;
            }
            unset($relations[$relationName][0]);
            $relations[$relationName]['modelName'] = $relations[$relationName]['related'];
            $relations[$relationName]['modelRelativeName'] = trim_namespace($this->getModelsNamespace(), $relations[$relationName]['related']);
            unset($relations[$relationName]['related']);
//QUI CAMBIAVA IL NOME DELLAR ELAZIONE CON IL NOME DELLA FOREIGNKEY, DA MIGLIORARE
//            if ($foreignKey !== $relationName) {
//                $relations[$foreignKey] = $relations[$relationName];
//                unset($relations[$relationName]);
//            }
        }

        $this->belongsTos = $relations;

        return $relations;
    }

    public function getBelongsTos()
    {
        if (is_null($this->belongsTos)) {
            return $this->setBelongsTos();
        }
        return $this->belongsTos;
    }




    public function getFormMetadata() {

    }


}
