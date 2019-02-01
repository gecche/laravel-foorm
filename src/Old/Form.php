<?php

namespace Gecche\Foorm\Old;

use Gecche\Foorm\Old\Contracts\FormInterface;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Input;
use Exception;

/**
 * Class Form
 * @package Gecche\Foorm\Old
 */
class Form implements FormInterface
{


    /**
     * @var MessageBag|null
     */
    protected $errors = null;

    /**
     * @var array
     */
    protected $result = array();

    /**
     * @var array
     */
    protected $metadata = array();

    /**
     * Parametri vari per la costruzione del form
     *
     * @var array
     */
    protected $params = [];

    /**
     * Form constructor.
     *
     * @param array $params
     */
    public function __construct($params = [])
    {

        $this->params = $params;

        // Gestione errori da vedere come funzionava
        $this->errors = new MessageBag();
        try {
            if ($old = Input::old("errors")) {
                $this->errors = $old;
            }
        } catch (Exception $e) {
            $this->errors = new MessageBag();
        }
    }

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


    /**
     * @return MessageBag|null
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param MessageBag $errors
     * @return $this
     */
    public function setErrors(MessageBag $errors)
    {
        $this->errors = $errors;
        return $this;
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return $this->errors->any();
    }

    /**
     * @param $key
     * @return string
     */
    public function getError($key)
    {
        return $this->getErrors()->first($key);
    }


    /**
     * @return array
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @return bool
     */
    public function setResult()
    {
        //$this->result = $this->model->toArray();
        return true;
    }


    /**
     * @return array
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @param array $metadata
     */
    public function setMetadata()
    {
        return true;
    }


}
