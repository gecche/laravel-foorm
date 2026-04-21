<?php

namespace Gecche\Foorm;

use Gecche\Breeze\Breeze;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FoormQueueManager extends FoormManagerBase
{

    protected $queueInputData = [];

    protected $isQueue = true;

    /**
     * @return mixed
     */
    public function getFoorm($formName, array $queueInputData, $params = [])
    {
        $this->setFoormBasicData($formName);

        $this->queueInputData = $queueInputData;
        $this->buildParams($params);
        $this->getConfig();
        $this->setModel();

        return $this->setFoorm();
    }


    protected function buildInput() {
        return $this->queueInputData;
    }




    /**
     * @return mixed
     */
    public function getFoormAction($action, $formName, $queueInputData, $params = [])
    {
        $params['from_action'] = $action;
        $foorm = $this->getFoorm($formName, $queueInputData, $params);
        return $this->setFoormAction($action, $foorm);
    }


}
