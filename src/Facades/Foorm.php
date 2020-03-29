<?php namespace Gecche\Foorm\Facades;

use Illuminate\Support\Facades\Facade;
/**
 * @see \Illuminate\Filesystem\Filesystem
 */
class Foorm extends Facade {

	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
            protected static function getFacadeAccessor() { return 'foorm'; }

}
