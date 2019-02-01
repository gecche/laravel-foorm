<?php

namespace Cupparis\Form;

use Illuminate\Support\MessageBag;

interface FormInterface {

    public function isValid($input = null);

    public function save($input = null, $validate = true);

    public function setDynamicValidationRules();

    public function getErrors();

    public function setErrors(MessageBag $errors);

    public function hasErrors();

    public function getError($key);

    public function isPosted();
    
    public function setValidationRules();

    public function setCustomValidationRules();

    public function getValidationRules();

    public function getValidationRule($key);

    public function getValidationRulePrefix($prefix, $remove = true, $separator = '-');

    public function getResult();

    public function getResultParams();

    public function getTranslations();
    
    public function getSearchParams();
    
    public function setSearchParams();

    public function buildSearchFilter($key,$value,$builder = null);


}
