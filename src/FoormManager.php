<?php

namespace Gecche\Foorm;

use Gecche\Breeze\Breeze;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FoormManager extends FoormManagerBase
{

    /**
     * @var Request
     */
    protected $request;


    /**
     * @return mixed
     */
    public function getFoorm($formName, Request $request, $params = [])
    {
        $this->setFoormBasicData($formName);

        $this->request = $request;
        $this->buildParams($params);
        $this->getConfig();
        $this->setModel();

        return $this->setFoorm();
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }


    public function checkIfIsApi()
    {

        if ($this->request->wantsJson()
            || $this->request->is('*api/*')
            || $this->request->routeIs('api.*')
        ) {
            return true;
        }

        return false;
    }

    protected function setFixedConstraintsToParams($params)
    {
        if (is_array(Arr::get($params, 'fixed_constraints'))) {
            return $params;
        }

        $requestFixedConstraints = $this->request->input('fixed_constraints');
        if (is_array($requestFixedConstraints)) {
            $params['fixed_constraints'] = $requestFixedConstraints;
        }

        return $params;
    }


    protected function buildInput() {
        return $this->request->input() + $this->request->allFiles();
    }




    /**
     * @return mixed
     */
    public function getFoormAction($action, $formName, Request $request, $params = [])
    {
        $params['from_action'] = $action;
        $foorm = $this->getFoorm($formName, $request, $params);
        return $this->setFoormAction($action, $foorm);
    }


}
