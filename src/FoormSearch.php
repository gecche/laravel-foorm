<?php

namespace Gecche\Foorm;


use Illuminate\Support\Arr;

class FoormSearch extends Foorm
{

    use FoormSingleTrait;

    protected $extraDefaults = [];


    public function buildRelations()
    {

        $modelName = $this->getModelName();

        $relations = $modelName::getRelationsData();

        $configRelations = array_keys(Arr::get($this->config,'relations',[]));

        $configFields = array_keys(Arr::get($this->config,'fields',[]));
        foreach ($configFields as $field) {
            $exploded = explode('|',$field);
            if (count($exploded) < 2) {
                continue;
            }
            $relationName = $exploded[0];
            if (!in_array($relationName,$configRelations)) {
                $configRelations[] = $relationName;
                $this->config['relations'][$relationName] = [];
            }
        }

        $relationsToUnset = array_diff(array_keys($relations),$configRelations);

        foreach ($relationsToUnset as $relationToUnset) {
            unset($relations[$relationToUnset]);
        }

        $this->relations = $relations;

        return $relations;
    }

    protected function setFormMetadataFields() {
        $fields = Arr::get($this->config, 'fields', []);

        $simpleFields = [];
        $fieldsWithRelations = [];
        foreach ($fields as $field => $fieldData) {
            $exploded = explode('|',$field);
            if (count($exploded) < 2) {
                $simpleFields[$field] = $fieldData;
                continue;
            }
            $fieldsWithRelations[$exploded[0]][$field] = $fieldData;
        }


        $this->formMetadata['fields'] = $this->fillFormMetadataFields($simpleFields);

        foreach ($fieldsWithRelations as $relation => $relationFields) {
            $relationMetadata = Arr::get($this->hasManies,$relation,Arr::get($this->belongsTos,$relation));
            if (is_null($relationMetadata)) {
                throw new \Exception("Relation " . $relation . ' not found');
            }

            $this->formMetadata['fields'] = array_merge($this->formMetadata['fields'],
                $this->fillFormMetadataFields($relationFields,$relation,$relationMetadata));
        }


        return $fields;
    }

}
