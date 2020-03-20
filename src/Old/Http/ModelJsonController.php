<?php namespace Cupparis\App\Http\Controllers;

use App\Http\Controllers\Controller as AppController;
use Cupparis\Acl\Facades\Acl;
use Cupparis\Auth\Contracts\VerificationBroker;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Response;
use Exception;
use Illuminate\Support\Facades\DB;

class ModelJsonController extends AppController {

    protected $modelName;
    protected $modelRelativeName;
    protected $modelNamePermission;
    protected $modelFormName;
    protected $modelFormNamePrefix = '';
    
    protected $permissionPrefix = null;
    protected $modelFormType = 'Detail';

    protected $model;
    protected $modelForm;

    protected $params = array();

    protected $request;

    protected $externalModelName = null;

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param array $params
     */
    public function setParams($params)
    {
        $this->params = $params;
    }

    public function setContextConstraints($constraintKey,$constraintValue) {
        if ($constraintKey) {
            $this->params['constraintKey'] = $constraintKey;
            $this->params['constraintValue'] = $constraintValue;
        }
    }

    public function __construct(Request $request,$externalModelName = null) {

        $this->request = $request;

        $this->forms_namespace = Config::get('app.forms_namespace','App\Forms') . "\\";

        $this->externalModelName = $externalModelName;


        parent::__construct($request);

        $this->result["backParams"] = array(
                "bp-formClassName" => null,
                "bp-modelClassName" => null,
        );
    }

    protected function initCheck($csv = false,$datafile = false) {

        $this->setModel($csv,$datafile);

        if (!$this->checkPermissions())
            return false;

        $this->setModelForm();

        return true;
        
    }

    protected function initCheckHasMany() {

        $this->setModel();

        $this->setModelForm();

        return true;

    }

    protected function setModel($csv = false,$datafile = false) {


        $this->setModelName($csv,$datafile);

        if ($this->model === null) {
            $this->model = new $this->modelName;
        }
        $this->result['backParams']['bp-modelClassName'] = $this->modelRelativeName;
        $this->result['backParams']['bp-modelPk'] = $this->model->getKey() ? $this->model->getKey() : null;
        return true;

    }

    protected function checkPermissions() {


        //Check permission with possibly the model id
        if ($this->permissionPrefix !== null) {
            if ($this->model->exists()) {
                $id = $this->model->getKey();
            } else {
                $id = null;
            }

            if (!Acl::check($this->permissionPrefix . '_'.  $this->modelNamePermission,$id)) {
                $this->result = array(
                    "msg" => Lang::get('app.unauthorized'),
                    "error" => 1,
                );
                return false;
            }
        }
        return true;
    }

    protected function setModelForm() {

        $this->setModelFormName();

        $tmp = $this->modelFormName;
        $this->modelForm = new $tmp($this->model,$this->permissionPrefix,$this->params);
        $this->result['backParams']['bp-formClassName'] = $this->modelFormName;
        return true;

    }

    protected function setResultGet() {
        $this->result["result"] = $this->modelForm->getResult();
        $this->result["validationRules"] = $this->modelForm->getValidationRulesJs();
        $this->result['resultParams'] = $this->modelForm->getResultParams();
        $this->result['summary'] = $this->modelForm->getSummary();
        if (Config::get('app.translations_mode') != 'file') {
            $translations = array_merge($this->result['translations'],$this->modelForm->getTranslations());
        } else {
            $translations = [];
        }
        if (config("app.translations_notation",'') === 'dot')
            $this->result['translations'] = array_dot($translations);
        else
            $this->result['translations'] = $translations;
        $this->result['metadata'] = $this->modelForm->getMetadata();
        $this->result['data_header'] = $this->modelForm->getDataHeader();
    }

    public function callAction($method, $parameters)
    {
        call_user_func_array(array($this, $method), $parameters);
        return Response::json($this->result);
    }

    public function __call($method, $parameters)
    {

//        $actions = [
//            'archivio',
//            'create',
//            'csv',
//            'csvdata',
//            'csverror',
//            'csvrowupdate',
//            'destroy',
//            'destroyall',
//            'edit',
//            'index',
//            'save',
//            'search',
//            'show',
//            'store',
//            'tree',
//            'update',
//
//        ];
//
//        if (in_array($method,$actions)) {
//            call_user_func_array(array($this, $method), $parameters);
//            return Response::json($this->result);
//        }

        if (method_exists($this, $method)) {
            return call_user_func_array(array($this, $method), $parameters);
        }

        $prefix = '_json_';

        if (starts_with($method,$prefix)) {
            $methodName = substr($method,strlen($prefix));
            call_user_func_array(array($this, $methodName), $parameters);
            return json_encode($this->result,JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        }

        parent::__call($method, $parameters);
    }

    /*
     * GET ACTIONS
     */
    public function index($constraintKey = null,$constraintValue = null) {


        $this->permissionPrefix = 'LIST';
        $this->modelFormType ='List';

        $this->setContextConstraints($constraintKey,$constraintValue);

        if (!$this->initCheck()) {
            return;
        }        
        $this->setResultGet();
        return;
        
    }


    public function csv() {
        //$this->modelFormNamePrefix = 'Csv';
        $this->permissionPrefix = 'CREATE';
//        //$this->modelFormType ='List'//;
        //$this->params = array('csv_id' => 0,"only_errors" => false);
        if (!$this->initCheck(true)) {
            return;
        }
        $this->setResultGet();
    	return;
    }

    /**
     * esegue l'update di una riga caricata dal file csv, usata in caso di errori
     * per permettere la modifica del singolo valore e viene rifatta la rivalidazione
     * degli errori
     */
    public function csvrowupdate() {

        $this->permissionPrefix = 'CSV';

        $this->setModel(true);
        if (!$this->checkPermissions()) {
            return;
        }

        $providerName = Str::studly($this->modelRelativeName);
        $csvProviderName = $this->csvproviders_namespace . $providerName;
        $csvProvider = new $csvProviderName;

        $csvProvider->fixErrorCsvRow(Input::all());

        return;
        //return View::make("admin::user/login");
    }

    /**
     * esegue l'update di un campo su una lista di ids, usata in caso di errori
     * per permettere la modifica del singolo valore e viene rifatta la rivalidazione
     * degli errori
     */
    public function csvmassiveupdate() {
        try {
            $this->permissionPrefix = 'CSV';

            $this->setModel(true);
            if (!$this->checkPermissions()) {
                return;
            }

            $providerName = Str::studly($this->modelRelativeName);
            $csvProviderName = $this->csvproviders_namespace . $providerName;
            $csvProvider = new $csvProviderName;

            $csvProvider->massiveUpdate(Input::all());

            //$csvProvider->fixErrorCsvRow(Input::all());

        } catch (\Exception $e) {
            $this->result['error'] = 1;
            $this->result['msg'] = $e->getMessage();
        }
        return;
        //return View::make("admin::user/login");
    }

    /**
     * esegue la rivalidazione nel db di un file csv precedentemente
     * caricato
     */
    public function csvrevalidate($job_id) {
        $this->permissionPrefix = 'CSV';

        $this->setModel(true);
        if (!$this->checkPermissions()) {
            return;
        }

        $providerName = Str::studly($this->modelRelativeName);
        $csvProviderName = $this->csvproviders_namespace . $providerName;
        $csvProvider = new $csvProviderName;

        $csvProvider->revalidate($job_id);
    }
    public function csvdata($job_id) {
        $this->modelFormNamePrefix = 'Csv';
        $this->permissionPrefix = 'CSV';
        $this->modelFormType ='List';

        $this->params = array('csv_id' => $job_id,"only_errors" => false);
        if (!$this->initCheck(true)) {
            return;
        }
        $this->setResultGet();
        return;
    }

    public function csverror($job_id) {
        $this->modelFormNamePrefix = 'Csv';
        $this->permissionPrefix = 'CSV';
        $this->modelFormType ='List';
    
        $this->params = array('csv_id' => $job_id,"only_errors" => true);
        if (!$this->initCheck(true)) {
            return;
        }
        $this->setResultGet();
        return;
    }
    
    public function csvrecovery($model) {
        $this->result['result'] = array();//array('field1' => 'ciao');
        $this->result['resultParams'] = array();
        $this->result['modelName'] = Str::studly($model);
        $csvProviderName = $this->csvproviders_namespace . Str::studly($model);
        
        $csvProvider = new $csvProviderName;
        $csvModelName = $csvProvider->getModelCsvName();
        $tableName = with(new $csvModelName)->getTable();
        
        //$entries = $csvModelName::groupBy('csv_id')->get();
        $entries = DB::table($tableName)
        ->join('queues', $tableName . '.csv_id', '=', 'queues.job_id')
        ->groupBy('csv_id')
        ->get();
        
        $this->result['csvModelName'] = $csvModelName;
        $this->result['result'] = $entries;
        /*
        $this->modelFormNamePrefix = 'Csv';
        $this->permissionPrefix = 'CSV';
        $this->modelFormType ='List';
    
        $this->params = array('csv_id' => $job_id,"only_errors" => true);
        if (!$this->initCheck(true)) {
            return;
        }
        $this->setResultGet();
        */
        return;
    }
    
    
    /*
     * DATAFILE
     */


    public function datafile() {
        //$this->modelFormNamePrefix = 'Datafile';
        $this->permissionPrefix = 'CREATE';
//        //$this->modelFormType ='List'//;
        //$this->params = array('datafile_id' => 0,"only_errors" => false);
        if (!$this->initCheck(false,true)) {
            return;
        }
        $this->setResultGet();
        return;
    }

    /**
     * esegue l'update di una riga caricata dal file datafile, usata in caso di errori
     * per permettere la modifica del singolo valore e viene rifatta la rivalidazione
     * degli errori
     */
    public function datafilerowupdate() {

        $this->permissionPrefix = 'DATAFILE';

        $this->setModel(false,true);
        if (!$this->checkPermissions()) {
            return;
        }

        $providerName = Str::studly($this->modelRelativeName);
        $datafileProviderName = $this->datafileproviders_namespace . $providerName;
        $datafileProvider = new $datafileProviderName;

        $datafileProvider->fixErrorDatafileRow(Input::all());

        return;
        //return View::make("admin::user/login");
    }

    /**
     * esegue l'update di un campo su una lista di ids, usata in caso di errori
     * per permettere la modifica del singolo valore e viene rifatta la rivalidazione
     * degli errori
     */
    public function datafilemassiveupdate() {
        try {
            $this->permissionPrefix = 'DATAFILE';

            $this->setModel(false,true);
            if (!$this->checkPermissions()) {
                return;
            }

            $providerName = Str::studly($this->modelRelativeName);
            $datafileProviderName = $this->datafileproviders_namespace . $providerName;
            $datafileProvider = new $datafileProviderName;

            $datafileProvider->massiveUpdate(Input::all());

            //$datafileProvider->fixErrorDatafileRow(Input::all());

        } catch (\Exception $e) {
            $this->result['error'] = 1;
            $this->result['msg'] = $e->getMessage();
        }
        return;
        //return View::make("admin::user/login");
    }

    /**
     * esegue la rivalidazione nel db di un file datafile precedentemente
     * caricato
     */
    public function datafilerevalidate($job_id) {
        $this->permissionPrefix = 'DATAFILE';

        $this->setModel(false,true);
        if (!$this->checkPermissions()) {
            return;
        }

        $providerName = Str::studly($this->modelRelativeName);
        $datafileProviderName = $this->datafileproviders_namespace . $providerName;
        $datafileProvider = new $datafileProviderName;

        $datafileProvider->revalidate($job_id);
    }

    public function datafiledata($job_id) {
        $this->modelFormNamePrefix = 'Datafile';
        $this->permissionPrefix = 'DATAFILE';
        $this->modelFormType ='List';

        $this->params = array('datafile_id' => $job_id,"only_errors" => false);
        if (!$this->initCheck(false,true)) {
            return;
        }
        $this->setResultGet();
        return;
    }

    public function datafileerror($job_id) {
        $this->modelFormNamePrefix = 'Datafile';
        $this->permissionPrefix = 'DATAFILE';
        $this->modelFormType ='List';

        $this->params = array('datafile_id' => $job_id,"only_errors" => true);
        if (!$this->initCheck(false,true)) {
            return;
        }
        $this->setResultGet();
        return;
    }

    public function datafilerecovery($model) {
        $this->result['result'] = array();//array('field1' => 'ciao');
        $this->result['resultParams'] = array();
        $this->result['modelName'] = Str::studly($model);
        $datafileProviderName = $this->datafileproviders_namespace . Str::studly($model);

        $datafileProvider = new $datafileProviderName;
        $datafileModelName = $datafileProvider->getModelDatafileName();
        $tableName = with(new $datafileModelName)->getTable();

        //$entries = $datafileModelName::groupBy('datafile_id')->get();
        $entries = DB::table($tableName)
            ->join('queues', $tableName . '.datafile_id', '=', 'queues.job_id')
            ->groupBy('datafile_id')
            ->get();

        $this->result['datafileModelName'] = $datafileModelName;
        $this->result['result'] = $entries;
        /*
        $this->modelFormNamePrefix = 'Datafile';
        $this->permissionPrefix = 'DATAFILE';
        $this->modelFormType ='List';
    
        $this->params = array('datafile_id' => $job_id,"only_errors" => true);
        if (!$this->initCheck(true)) {
            return;
        }
        $this->setResultGet();
        */
        return;
    }

    /*
     * END DATAFILE
     */
    
    public function show($model,$constraintKey = null,$constraintValue = null) {
        $this->model = $model;
        $this->permissionPrefix = 'VIEW';
        if (!$this->initCheck()) {
            return;
        }        
        $this->setResultGet();
        return;
        //return View::make("admin::user/login");
    }


    public function create($constraintKey = null,$constraintValue = null) {
        
        $this->permissionPrefix = 'CREATE';
        $this->setContextConstraints($constraintKey,$constraintValue);
        if (!$this->initCheck()) {
            return;
        }        
        $this->setResultGet();
        return;
    }

    public function createHasMany($constraintKey = null,$constraintValue = null) {

        $this->permissionPrefix = 'CREATE';
        $this->setContextConstraints($constraintKey,$constraintValue);
        if (!$this->initCheckHasMany()) {
            return;
        }
        $this->setResultGet();
        return;
    }


    public function edit($model,$constraintKey = null,$constraintValue = null) {

        $this->model = $model;
        $this->permissionPrefix = 'EDIT';
        $this->setContextConstraints($constraintKey,$constraintValue);
        if (!$this->initCheck()) {
            return;
        }        
        $this->setResultGet();
        return;
        //return View::make("admin::user/login");
    }

    
    /*
     * POST/PUT actions
     */
    
    public function store($constraintKey = null,$constraintValue = null) {

        $this->permissionPrefix = 'CREATE';
        $this->setContextConstraints($constraintKey,$constraintValue);

        if (!$this->initCheck()) {
            return;
        }           

        $saved = $this->save();

        if ($saved) {
            $this->setModelForm();
        }

        $this->setResultGet();

        return;
        
        //return View::make("admin::user/login");
    }

    public function update($model,$constraintKey = null,$constraintValue = null) {

        $this->model = $model;
        $this->permissionPrefix = 'EDIT';
        $this->setContextConstraints($constraintKey,$constraintValue);
        if (!$this->initCheck()) {
            return;
        }        
        $this->save();
        $this->setResultGet();
        return;
        //return View::make("admin::user/login");
    }

    public function utenteassociato($model, Mailer $mailer, VerificationBroker $verifications) {

        $this->model = $model;
        $this->permissionPrefix = 'EDIT';
        if (!$this->initCheck()) {
            return;
        }

       
        if (Input::get('rimuovi',0)) {
            $this->model->deassociateUser();
            return ;
        }
        
        $email = Input::get('email');

        $resultAssociateUser = $this->model->associateUser($email,$mailer,$verifications);

        if (!$resultAssociateUser) {
            $this->result['error'] = 1;
            $this->result['msg'] = $this->model->getErrorUserAssociated();
            return;
        }

        return;
        //return View::make("admin::user/login");
    }

    public function tree() {
        /*
        $this->model = $model;
        $this->permissionPrefix = 'EDIT';
        if (!$this->initCheck()) {
            return;
        }
        $this->save();
    */
        if (!$this->initCheck()) {
            return;
        }
        $this->result['error'] = 0;
        $this->result['msg'] =" Salvataggio da fare";
        return;
        //return View::make("admin::user/login");
    }
    
    public function save() {

       $saved = false;
        try {        
            //TODO: transazione secondo me qui.
            //START TRANSACTION
//            $this->modelForm->isValid($input);
            $saved = $this->modelForm->save();
            $this->result['msg'] = array(Lang::get('app.saved'));
            //COMMIT
        } catch (Exception $e) {
            $this->result['msg'] = json_decode($e->getMessage())?json_decode($e->getMessage()):$e->getMessage();
            $this->result['error'] = true;
            //ROLLBACK
        }

        return $saved;
    }
    /*
     * DELETE ACTIONS
     */

    public function destroy($model,$constraintKey = null,$constraintValue = null) {

        $this->model = $model;
        $this->permissionPrefix = 'DELETE';

        try {

            if (!$this->initCheck()) {
                return;
            }
            $this->model->delete();
            $this->result['msg'] = array(Lang::get('app.saved'));
        } catch (\Exception $e) {

            $msg = $e->getMessage();
            if (strstr(strtolower($e->getMessage()),'integrity constraint violation')) {
                $msg = trans_uc('app.delete-integrity')."<br/><br/>Ulteriori dettagli tecnici: <br/>".$e->getMessage();
                //QUI FORSE POTREMMO METTERE UN CASE PER ALCUNI MODELLI PER FAR CAPIRE LE DIPENDENZE, MA NON LO SO
            }

            $this->result['msg'] = json_decode($msg) ? json_decode($msg) : $msg;
            $this->result['error'] = true;

        }

        //TODO: VEDERE COSA RITORNA LA CANCELLAZIONE
        return;
    }    

   
    public function destroyall($constraintKey = null,$constraintValue = null) {

        $this->permissionPrefix = 'LIST';
        if (!$this->initCheck()) {
            return;
        }


        $ids = Input::get('ids',array());
        if (count($ids) > 0) {

            foreach ($ids as $id) {
                if (!Acl::check('DELETE_' . $this->modelNamePermission, $id)) {
                    $this->result = array(
                        "msg" => Lang::get('app.unauthorized'),
                        "error" => 1,
                    );
                    return false;
                }
            }

            $this->model->destroy($ids);
        }
        //TODO: VEDERE COSA RITORNA LA CANCELLAZIONE
        return;
    }    
    
    /*
     * Further actions
     */
    public function capabilities() {
        // TODO eseguire i controlli delle azioni che si possono fare sul modello 
        $this->modelFormType = 'List';
        $this->initCheck();
        $ids = Input::get('ids', array());

        $result = $this->model->getAllowedActions($ids);

        return Response::json($result);
    }

    public function search($constraintKey = null,$constraintValue = null) {
        $this->permissionPrefix = 'ARCHIVIO';
        $this->modelFormType = 'Search';
        $this->setContextConstraints($constraintKey,$constraintValue);
        if (!$this->initCheck()) {
            return;
        }

        $this->setResultGet();
//        $this->addFilterAllOptions();
        return;
    }

    protected function addFilterAllOptions() {

        foreach ($this->result['resultParams'] as $field_key => $field_params) {
            if (isset($field_params['options'])) {
                $options = [env('FORM_FILTER_ALL',-99) => trans_uc('app.any')] + $this->result['resultParams'][$field_key]['options'];
                $this->result['resultParams'][$field_key]['options'] = $options;
                $this->result['resultParams'][$field_key]['options_order'] = array_keys($options);
                //$this->result['result'][$field_key] = 'FILTER_ALL';
            }
        }
    }
    /*
     * Helper methods
     */
    protected function setModelFormName() {

        $modelFormName = $this->forms_namespace . $this->modelFormNamePrefix . $this->modelRelativeName . "Form" . $this->modelFormType;
        if (!class_exists($modelFormName))
            $modelFormName = "\Cupparis\Form\ModelForm".$this->modelFormType;
        $this->modelFormName = $modelFormName;
    }

    protected function setModelName($csv = false,$datafile = false)
    {

        $currentModelName = $this->externalModelName;
        if (!$currentModelName) {
            $routename = $this->request->route()->getName();
            $routenameParts = explode('.', $routename);
            $currentModelName = Arr::get($routenameParts,0,null);
            if ($currentModelName == 'apijson') {
                $currentModelName = Arr::get($routenameParts,1,'model');
            }

        }

        $this->modelNamePermission = strtoupper(snake_case($currentModelName));
        $this->modelRelativeName = Str::studly($currentModelName);

        if ($datafile) {
            $this->modelName = $this->datafilemodels_namespace . Str::studly($currentModelName);
        } else {
            $this->modelName = $this->models_namespace . Str::studly($currentModelName);
        }
    }

    public function archivio($constraintKey = null,$constraintValue = null) {
        $this->modelFormNamePrefix = 'Archivio';
        $this->permissionPrefix = 'ARCHIVIO';
        $this->modelFormType ='List';

        $this->setContextConstraints($constraintKey,$constraintValue);
        if (!$this->initCheck()) {
            return;
        }        
        
        $this->setResultGet();
        $this->result['modelTemplateName'] = snake_case($this->modelName);
        
        return;
        

        //return View::make(app('appTheme') . '::archivio.'. $this->result['modelTemplateName'] , array('data' => $this->result));
    }

    
}
