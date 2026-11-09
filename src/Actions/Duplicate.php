<?php

namespace Gecche\Foorm\Actions;

use Gecche\Foorm\FoormAction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class Duplicate extends FoormAction
{

    protected $modelToDuplicate;

    protected $duplicatedModel;

    protected $relationsToDuplicate = [];

    protected $fieldsToExclude = [];

    protected $fieldsToMakeUnique = [];

    protected function init()
    {
        if ($this->model->getKey()) {
            $this->modelToDuplicate = $this->model;
        } else {
            $this->modelToDuplicate = $this->model->find(Arr::get($this->input, 'id'));
        }

        $this->relationsToDuplicate = Arr::get($this->config, 'relations_to_duplicate', []);
        $this->fieldsToExclude = Arr::get($this->config, 'fields_to_exclude', []);
        $this->fieldsToMakeUnique = Arr::get($this->config, 'fields_to_make_unique', []);
    }

    public function performAction()
    {
        $this->duplicateModel();
        $this->duplicateRelations();

        $this->actionResult = [
            'duplicate' => 'ok',
            'source_id' => $this->modelToDuplicate->getKey(),
            'id' => $this->duplicatedModel->getKey(),
            'ids' => [$this->duplicatedModel->getKey()],
        ];

        return $this->actionResult;
    }

    public function validateAction()
    {
        if (!$this->modelToDuplicate || !$this->modelToDuplicate->getKey()) {
            throw new \Exception("The duplicate action needs a saved model");
        }

        return true;
    }

    protected function duplicateModel()
    {
        $this->duplicatedModel = $this->modelToDuplicate->replicate($this->fieldsToExclude);
        foreach (Arr::except($this->input,['id']) as $field) {
            $this->duplicatedModel->{$field} = $this->input[$field];
        }
        foreach ($this->fieldsToMakeUnique as $field) {
            if (!array_key_exists($field, $this->input)) {
                $newValue = $this->makeUnique($field);
                $this->duplicatedModel->{$field} = $newValue;
            }


        }
        $this->duplicatedModel->save();
    }

    protected function duplicateRelations()
    {
        $hasManies = $this->getFoorm()->getHasManies();
        $belongsTos = $this->getFoorm()->getBelongsTos();

        foreach (Arr::get($this->getFoorm()->getConfig(), 'relations', []) as $relationName => $relationValues) {

            if (!in_array($relationName, $this->relationsToDuplicate)) {
                continue;
            }

            if (in_array($relationName, array_keys($hasManies))) {
                foreach ($this->modelToDuplicate->$relationName as $relationModel) {
                    $this->duplicatedModel->$relationName()->save(
                        $relationModel->replicate($this->fieldsToExclude)
                    );
                }
            }

            if (in_array($relationName, array_keys($belongsTos))) {
                $related = $this->modelToDuplicate->$relationName;
                if ($related) {
                    $duplicateRelated = $related->replicate($this->fieldsToExclude);
                    $duplicateRelated->save();
                    $this->duplicatedModel->$relationName()->associate($duplicateRelated);
                    $this->duplicatedModel->save();
                }
            }
        }
    }

    protected function makeUnique($field)
    {
        $value = $this->duplicatedModel->{$field};

        if ($value === null || $value === '') {
            return $value;
        }

        $value = (string) $value;
        $baseValue = preg_replace('/\s\(\d+\)$/', '', $value) ?: $value;

        if (!$this->fieldValueExists($field, $value)) {
            return $value;
        }

        $suffix = 2;
        do {
            $candidate = $baseValue . ' (' . $suffix . ')';
            $suffix++;
        } while ($this->fieldValueExists($field, $candidate));

        return $candidate;
    }

    protected function fieldValueExists($field, $value): bool
    {
        return $this->getModel()->newQuery()
            ->where($field, $value)
            ->exists();
    }

}
