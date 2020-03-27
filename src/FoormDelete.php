<?php

namespace Gecche\Foorm;


class FoormDelete extends Foorm
{

    public function getFormData()
    {

        $this->validateDelete();
        $this->deleteRelations();
        $this->deleteModel();

        $this->formData = [
            'delete' => "ok",
            'ids' => [$this->model->getKey()],
        ];

        return $this->formData;

    }


    protected function validateDelete() {

        return true;

    }


    protected function deleteRelations() {

        foreach ($this->getRelations() as $relationName => $relationConfig) {
            $this->model->$relationName()->delete();
        }

    }

    protected function deleteModel() {

        $this->model->destroy($this->model->getKey());

    }


}
