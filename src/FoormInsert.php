<?php

namespace Gecche\Foorm;


use Gecche\DBHelper\Facades\DBHelper;
use Gecche\ModelPlus\ModelPlus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Input;

class FoormInsert extends Foorm
{

    use ConstraintBuilderTrait;




    /**
     * @var \Closure|null
     */
    protected $listBuilder;


    





    protected function applyFixedConstraints()
    {
        $fixedConstraints = array_get($this->params, 'fixed_constraints', []);

        foreach ($fixedConstraints as $fixedConstraint) {
            $this->applyConstraint($fixedConstraint);
        }
    }



    public function finalizeData($finalizationFunc = null)
    {

        if ($finalizationFunc instanceof \Closure) {
            $this->formData = $finalizationFunc($this->formBuilder);
        } else {

            $this->formData = $this->finalizeDataStandard();
        }

        return $this->formData;

    }

    protected function finalizeDataStandard()
    {
        $arrayData = $this->formBuilder->toArray();


        return $arrayData;
    }


    public function setModelData()
    {

        $relations = $this->getRelations();

        foreach (array_keys($relations) as $relationKey) {

            $this->model->load($relationKey);

        }

        $modelData = $this->model->toArray();

        $configFields = $this->getAllFieldsAndDefaultsFromConfig();


        $modelDataDot = array_dot($modelData);
        $configFieldsDot = array_dot($configFields);

        $keyNotInConfig = array_diff_key($modelDataDot,$configFieldsDot);

        foreach ($keyNotInConfig as $keyToEliminate) {
            unset($modelDataDot[$keyToEliminate]);
        }

        foreach ($configFieldsDot as $configKey => $configField) {
            if (!array_key_exists($configKey,$modelDataDot)) {
                $modelDataDot[$configKey] = $configFieldsDot[$configKey];
            }
        }

        $this->formData = array_undot($modelDataDot);

    }

    public function getAllFieldsAndDefaultsFromConfig() {

        $modelFieldsKeys = array_keys(array_get($this->config,'fields',[]));

        $modelFields = [];
        foreach ($modelFieldsKeys as $fieldKey) {
            $modelFields[$fieldKey] = array_get($this->config['fields'][$fieldKey],'default');
        }

        $configRelations = array_keys(array_get($this->config,'relations',[]));

        foreach ($configRelations as $relationKey) {

            $relationFields = [];

            $relationConfigFields = array_keys(array_get($this->config['relations'][$relationKey],'fields',[]));

            foreach ($relationConfigFields as $relationFieldKey) {
                $relationFields[$relationFieldKey] = array_get($this->config['relations'][$relationKey]['fields'][$relationFieldKey],'default');
            }

            $modelFields[$relationKey] = $relationFields;

        }

        return $modelFields;






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

//        $this->finalizeData();

        return $this->formData;

    }




    public function setFormMetadata()
    {


        $this->setFormMetadataFields();

        $this->setFormMetadataRelations();


    }


}
