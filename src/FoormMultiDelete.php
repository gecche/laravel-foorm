<?php

namespace Gecche\Foorm;


use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Request;

class FoormMultiDelete extends Foorm
{

    protected $modelKeys = [];


    protected function init() {
        $this->modelKeys = Arr::wrap(Request::get('ids',[]));
    }


    public function getFormData()
    {

        $this->checkModels();
        $this->validateDelete();
        $this->deleteRelations();
        $this->deleteModels();

        $this->formData = [
            'delete' => "ok",
            'ids' => $this->modelKeys,
        ];

        return $this->formData ;

    }


    protected function checkModels() {

        $countModels = ($this->modelName)::whereIn($this->model->getKey(),$this->modelKeys)
            ->count();

        if ($countModels <= 0 || $countModels != count($this->modelKeys)) {
            throw new \Exception("Some or all models not found, keys: " . implode(',',$this->modelKeys));
        }

    }

    protected function validateDelete() {
        return true;
    }


    protected function deleteRelations() {

        foreach ($this->modelKeys as $modelKey) {

            $model = ($this->modelName)::find($modelKey);

            foreach ($this->getRelations() as $relationName => $relationConfig) {
                $model->$relationName()->delete();
            }

        }

    }

    protected function deleteModels() {

        $this->model->destroy($this->modelKeys);

    }

}
