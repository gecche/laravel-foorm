<?php

namespace Gecche\Foorm\Old\Contracts;

use Gecche\Foorm\Old\Form;
use Illuminate\Support\MessageBag;

interface FormInterface {

    /**
     * @return MessageBag|null
     */
    public function getErrors();

    /**
     * @param MessageBag $errors
     * @return $this
     */
    public function setErrors(MessageBag $errors);

    /**
     * @return bool
     */
    public function hasErrors();

    /**
     * @param $key
     * @return string
     */
    public function getError($key);

    /**
     * @return array
     */
    public function getResult();

    /**
     * @return bool
     */
    public function setResult();

    /**
     * @return array
     */
    public function getMetadata();

    /**
     * @param array $metadata
     */
    public function setMetadata();

}
