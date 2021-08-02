<?php

namespace Gecche\Foorm\Actions;


use Gecche\Foorm\FoormAction;
use Illuminate\Support\Arr;

class Delete extends FoormAction
{

    protected $modelToDelete;

    protected $relationsToDelete = [];

    protected function init() {


        if ($this->model->getKey()) {
            $this->modelToDelete = $this->model;
        } else {
            $this->modelToDelete = $this->model->find(Arr::get($this->input,'id'));
        }

        $this->relationsToDelete = Arr::get($this->config,'relations_to_delete',[]);

    }



    public function performAction()
    {

        $this->deleteRelations();
        $this->deleteModel();

        $this->actionResult = [
            'delete' => "ok",
            'ids' => [$this->modelToDelete->getKey()],
        ];

        return $this->actionResult;

    }


    public function validateAction() {

        if (!$this->modelToDelete->getKey()) {
            throw new \Exception("The delete action needs a saved model");
        }
        return true;

    }


    protected function deleteRelations() {

        $hasManies = $this->getFoorm()->getHasManies();
        $belongsTos = $this->getFoorm()->getBelongsTos();

        foreach (Arr::get($this->getFoorm()->getConfig(),'relations',[]) as $relationName => $relationValues) {

            if (!in_array($relationName,$this->relationsToDelete)) {
                continue;
            }

            if (in_array($relationName,array_keys($hasManies))) {
                foreach ($this->modelToDelete->$relationName as $relationModelToDelete) {
                    $relationModelToDelete->delete();
                }
            }

            if (in_array($relationName,array_keys($belongsTos))) {
                $this->modelToDelete->$relationName->delete();
            }

        }

    }

    protected function deleteModel() {
        $this->modelToDelete->destroy($this->modelToDelete->getKey());
    }


}
