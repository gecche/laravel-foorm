<?php
/**
 * Created by PhpStorm.
 * User: gecche
 * Date: 01/02/19
 * Time: 22:07
 */

namespace Gecche\Foorm\FormList\ListBuilder;


use Gecche\Foorm\Contracts\ListBuilder;
use Gecche\Breeze\Breeze;
use Illuminate\Database\Eloquent\Builder;

class StandardListBuilder implements ListBuilder
{


    /**
     * @var Builder
     */
    protected $builder;


    public function createBuilder(Breeze $model)
    {
        $modelClass = get_class($model);
        $this->builder = $modelClass::query();
        // TODO: Implement createBuilder() method.
    }

    public function applyFixedConstraints($constraints = [])
    {
        // TODO: Implement applyFixedConstraints() method.
    }

    public function applyRelations()
    {
        // TODO: Implement applyRelations() method.
    }


}