<?php namespace Gecche\Foorm\Old\Http;

/*
 * This file is part of the Ardent package.
 *
 * (c) Max Ehsan <contact@laravelbook.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Ardent - Self-validating Eloquent model base class
 *
 */
class Request extends \Illuminate\Http\Request {

        /**
	 * Retrieve an input item from the request.
         * Perform also some filtering functions by default.
	 *
	 * @param  string  $key
	 * @param  mixed   $default
	 * @return string
	 */
	public function input($key = null, $default = null)
	{
		$input = $this->getInputSource()->all() + $this->query->all();

                
                foreach ($input as $inputKey => $inputValue) {
                
                    if (is_string($inputValue)) {
                        switch($inputValue) {
                            case env('FORM_FILTER_ALL',-99):
                                $input[$inputKey] = null;
                                break;
                            case env('FORM_ITEM_NONE',-99):
                                $input[$inputKey] = null;
                                break;                        
                            default:
                                break;
                        }
                    } elseif (is_array($inputValue)) {

                        foreach ($inputValue as $inputValueKey => $inputValueValue) {

                            switch($inputValueValue) {
                                case env('FORM_FILTER_ALL',-99):
                                    $input[$inputKey][$inputValueKey] = null;
                                    break;
                                case env('FORM_ITEM_NONE',-99):
                                    $input[$inputKey][$inputValueKey] = null;
                                    break;
                                default:
                                    break;
                            }

                        }

                    }                
                }
                
		return Arr::get($input, $key, $default);
	}

}
