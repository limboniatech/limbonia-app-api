<?php
namespace Limbonia\Controller;

/**
 * Limbonia Controller base class
 *
 * This defines all the basic parts of an Limbonia controller
 *
 * @author Lonnie Blansett <lonnie@limbonia.tech>
 * @package Limbonia
 */
class Api extends \Limbonia\Controller implements \Limbonia\Interfaces\Controller\Api
{
  use \Limbonia\Traits\Controller\Api;
  use \Limbonia\Traits\DriverList;

  /**
   * Controller Factory
   *
   * @param string $sType - The type of controller to create
   * @param \Limbonia\App\Api $oApp
   * @return \Limbonia\Interfaces\Controller\Api
   */
  public static function factory($sType, \Limbonia\App\Api $oApp)
  {
    return static::driverFactory($sType, $oApp);
  }

  /**
   * Instantiate an API controller
   *
   * @param \Limbonia\App\Api $oApp
   * @param \Limbonia\Router $oRouter (optional)
   */
  protected function __construct(\Limbonia\App\Api $oApp, \Limbonia\Router $oRouter = null)
  {
      parent::__construct($oApp, $oRouter);
  }
}