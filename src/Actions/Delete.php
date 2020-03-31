<?php

namespace Gecche\Foorm\Actions;


use Gecche\Foorm\FoormAction;

class Delete extends FoormAction
{

    protected $modelToDelete;

    protected function init() {


        if ($this->model->getKey()) {
            $this->modelToDelete = $this->model;
        } else {
            $this->modelToDelete = $this->model->find(Arr::get($this->input,'id'));
        }
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

        foreach ($this->foorm->getRelations() as $relationName => $relationConfig) {
            $this->modelToDelete->$relationName()->delete();
        }

    }

    protected function deleteModel() {

        $this->modelToDelete->destroy($this->modelToDelete->getKey());

    }


}
