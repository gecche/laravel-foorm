<?php

namespace Gecche\Foorm\Actions;


use Gecche\Foorm\FoormAction;


use Illuminate\Support\Arr;

class MultiDelete extends FoormAction
{
    protected $modelKeys = [];

    protected $relationsToDelete = [];


    protected function init() {
        $this->modelKeys = Arr::wrap(Arr::get($this->input,'ids',[]));
    }

    public function performAction()
    {

        $this->checkModels();
        $this->deleteRelations();
        $this->deleteModels();

        $this->actionResult = [
            'delete' => "ok",
            'ids' => $this->modelKeys,
        ];

        return $this->actionResult ;

    }


    protected function checkModels() {

        $modelName = $this->foorm->getModelName();
        $countModels = $modelName::whereIn($this->model->getKeyName(),$this->modelKeys)
            ->count();

        if ($countModels <= 0 || $countModels != count($this->modelKeys)) {
            throw new \Exception("Some or all " . $modelName . " models to be deleted not found, keys: " . implode(',',$this->modelKeys));
        }

    }

    public function validateAction() {
       return true;
    }


    protected function deleteRelations() {

        if (count($this->relationsToDelete) == 0) {
            return;
        }

        $modelName = $this->foorm->getModelName();
        foreach ($this->modelKeys as $modelKey) {

            $model = $modelName::find($modelKey);

            foreach ($this->foorm->getRelations() as $relationName => $relationConfig) {
                if (in_array($relationName,$this->relationsToDelete)) {
                $model->$relationName()->delete();
            }
            }

        }

    }

    protected function deleteModels() {

        $this->model->destroy($this->modelKeys);

    }

}
