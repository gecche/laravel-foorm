<?php namespace Gecche\Foorm\Breeze;

use Gecche\Breeze\Breeze as GeccheBreeze;
use Gecche\Breeze\Contracts\HasFoormInterface;
use Gecche\Foorm\Breeze\Contracts\FoormBreezeInterface;

/**
 * Breeze - Eloquent model base class with some pluses!
 *
 */
abstract class Breeze extends GeccheBreeze implements FoormBreezeInterface {


    use Concerns\HasFoormHelpers;

}
