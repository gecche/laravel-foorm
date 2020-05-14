<?php namespace Gecche\Foorm\Breeze\Contracts;

/**
 * Breeze - Eloquent model base class with some pluses!
 *
 */
interface  HasFoormInterface {


    public function getForSelectList($builder = null, $columns = null, $params = [], $listValuesFunc = null, $postFormatFunc = 'sortASC');
    public function postFormatSelectListSortASC($selectList);

    public function postFormatSelectListSortDESC($selectList);


    public function getSelectListValuesStandard($list,$columns,$separator);




    public function getColumnsForSelectList($lang = true);

    public function getMaxItemsForSelectList();

    public function getSeparatorForSelectList();




    public function getNItemsAutoComplete();

    public function getColumnsSearchAutoComplete($lang = true);

    public static function autoComplete($value, $fields = null, $labelColumns = null, $n_items = null, $builder = null);


    public function setCompletionItem($result, $labelColumns);




    public function setDefaultOrderColumns($columns = []);

    public function getDefaultOrderColumns($lang = true);

    public function getFieldsSeparator();


}
