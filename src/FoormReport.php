<?php

namespace Gecche\Foorm;


use Gecche\Cupparis\App\Breeze\Breeze;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FoormReport extends Foorm
{

    use FoormCollectionTrait;
    use ConstraintBuilderTrait;
    use OrderBuilderTrait;

    /**
     * @var Builder|null
     */
    protected $formBuilder;


    protected $listOrder;

    /**
     * @var Builder|null
     */
    protected $formAggregatesBuilder;

    /*
     * @var Array|null;
     */
    protected $paginateSelect;

    protected $customFuncs = [];


    /**
     *
     */
    protected function generateReportBuilder()
    {


        $this->reportBuilder();

        $this->applyFixedConstraints();

    }


    protected function reportBuilder()
    {

        $this->formBuilder = DB::table($this->model->getTable());

    }


    public function getDataFromBuilder($params = [])
    {

        $this->getReportData();


        $this->finalizeData();
    }

    public function getReportData()
    {

        $this->formBuilder = $this->formBuilder->get();

    }

    public function finalizeData()
    {

        $this->formData = $this->formBuilder->toArray();

    }

    public function initFormBuilder()
    {
        $this->formBuilder = null;
    }



    /**
     * Generates and returns the data list
     *
     * - Defines the relations of the model involved in the form
     * - Generates an initial builder
     * - Apply search filters if any
     * - Apply the desired list order
     * - Paginate results
     * - Apply last transformations to the list
     * - Format the result
     *
     */
    public function getFormData()
    {

        $this->getFormBuilder();

        $this->getDataFromBuilder();


        /*
         * REINIZIALIZZO IL FORM BUILDER
         */
        $this->initFormBuilder();

        return $this->formData;

    }



    public function setFormBuilder()
    {

        $this->generateReportBuilder();

        $this->applySearchFilters();

        $this->applyListOrder();

    }



}
