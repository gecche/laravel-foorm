<?php

namespace Gecche\Foorm\Actions;


use Gecche\Foorm\FoormAction;

class Delete extends FoormAction
{

    public function performAction()
    {

        $this->deleteRelations();
        $this->deleteModel();

        $this->actionResult = [
            'delete' => "ok",
            'ids' => [$this->model->getKey()],
        ];

        return $this->actionResult;

    }


    public function validateAction() {

        return true;

    }


    protected function deleteRelations() {

        foreach ($this->foorm->getRelations() as $relationName => $relationConfig) {
            $this->model->$relationName()->delete();
        }

    }

    protected function deleteModel() {

        $this->model->destroy($this->model->getKey());

    }


}
