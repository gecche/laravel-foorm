<?php

namespace Gecche\Foorm\Old;

use Cupparis\Acl\Facades\Acl;
use Cupparis\Ardent\Ardent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;

class ModelFormListCsv extends ModelFormList {

    protected $paginateNumber = null;
    protected $paginateSelect = array('*');
    protected $permissionPrefix = null;

    protected $summaryParams = [];
    protected $summaryResult = null;

//    protected $totalCount = null;
//

    public function __construct(Ardent $model, $permissionPrefix = 'LIST', $params = array()) {

        ModelForm::__construct($model, $permissionPrefix, $params);
        $modelRelativeName = snake_case($this->getModelRelativeName());
        $formKey = 'forms.'.$modelRelativeName;
        // valore default del paginate number
        $values = Config::get($formKey,[]);

        $this->permissionPrefix = $permissionPrefix;

        $this->setHasManies();
        $this->setBelongsTos();

        $this->setListResult();
        $this->setSearchFilters(Arr::get($params,'input',null));
        $this->setOrderResult();

//        $this->totalCount = $this->result->count();

    }

//    public function getCount() {
//        return $this->totalCount;
//    }

    public function getChunkResult($page,$perPage = 1000) {
        $skip = ($page - 1) * $perPage;
        $tempResult = $this->result;

        $this->result = $this->result->take($perPage)->skip($skip)->get();

//        if ($page == 1 && $this->result->count() == 0) {
//            throw new \Exception("Nessun risultato");
//        }
        /*
         * Performs custom model data
         * per esemèpio usare la funzione "each" sulla eloquent collection
         */
        $this->customizeResult();

//        Log::info("Mah2");
        $modelName = $this->getModelName();
        $csvType = Arr::get($this->params,'csvType','default');
        $params = [
            'headers' => $page == 1 ? true : false,
        ];
        $csv = $modelName::getCsvExport($this->result, $this->resultParams, $csvType, $params);
//        Log::info("Mah3");

        $this->result = $tempResult;
        return $csv;

    }

    public function setResult() {


        $this->result = $this->result->get();

        /*
         * Performs custom model data
         * per esemèpio usare la funzione "each" sulla eloquent collection
         */
        $this->customizeResult();

        $modelName = $this->getModelName();
        $csvType = Arr::get($this->params,'csvType','default');
        $csv = $modelName::getCsvExport($this->result, $csvType);

        $this->result = $csv;
    }



}
