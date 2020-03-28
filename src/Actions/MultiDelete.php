<?php

namespace Gecche\Foorm\Actions;


use Gecche\Foorm\FoormAction;


use Illuminate\Support\Arr;

class MultiDelete extends FoormAction
{
    protected $modelKeys = [];


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

        $countModels = ($this->modelName)::whereIn($this->model->getKey(),$this->modelKeys)
            ->count();

        if ($countModels <= 0 || $countModels != count($this->modelKeys)) {
            throw new \Exception("Some or all models not found, keys: " . implode(',',$this->modelKeys));
        }

    }

    public function validateAction() {
       return true;
    }


    protected function deleteRelations() {

        foreach ($this->modelKeys as $modelKey) {

            $model = ($this->modelName)::find($modelKey);

            foreach ($this->foorm->getRelations() as $relationName => $relationConfig) {
                $model->$relationName()->delete();
            }

        }

    }

    protected function deleteModels() {

        $this->model->destroy($this->modelKeys);

    }

}
