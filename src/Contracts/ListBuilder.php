<?php namespace Gecche\Foorm\Contracts;

use Gecche\ModelPlus\ModelPlus;
use Illuminate\Database\Eloquent\Builder;

interface ListBuilder {


    /**
     * Create the main List Builder
     *
     * @return Builder
     */
    public function createBuilder(ModelPlus $model);

    /**
     * Apply constraints passed in to the form, if any
     *
     * @return Builder
     */
    public function applyFixedConstraints($constraints = []);



    /**
     * Adds to the builder the relations needed in the form
     *
     * @return Builder
     */
    public function applyRelations();
}
