<?php

namespace Gecche\Foorm\Old;

use Cupparis\Acl\Facades\Acl;
use Cupparis\Ardent\Ardent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;

class ModelFormDatafileListCsv extends ModelFormListCsv {


    public function __construct(Ardent $model, $permissionPrefix = 'LIST', $params = array()) {

        parent::__construct($model, $permissionPrefix, $params);

        $datafile_id = Arr::get($this->params,'datafile_id',-1);
        $this->result->where('datafile_id',$datafile_id);
        $this->result->has('errors');

    }



}
