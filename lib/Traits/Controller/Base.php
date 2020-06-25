<?php
namespace Limbonia\Traits\Controller;

trait Base
{
  use \Limbonia\Traits\DriverList;
  use \Limbonia\Traits\HasApp;

  /**
   * Has this controller been initialized
   *
   * @var boolean
   */
  protected $bInit = false;

  /**
   * List of controllers this controller depends on to function correctly
   *
   * @var array
   */
  protected static $aControllerDependencies = [];

  /**
   * The API object for this class to use
   *
   * @var \Limbonia\Router
   */
  protected $oRouter = null;

  /**
   * A list of components the current user is allowed to use
   *
   * @var array
   */
  protected $hAllow = [];

  /**
   * Lists of columns to ignore when filling view data
   *
   * @var array
   */
  protected $aIgnore =
  [
    'edit' => [],
    'create' => [],
    'search' => [],
    'view' => [],
    'boolean' => []
  ];

  /**
   * Instantiate a controller
   *
   * @param \Limbonia\App $oApp
   */
  protected function baseConstruct(\Limbonia\App $oApp, \Limbonia\Router $oRouter = null)
  {
    $this->oApp = $oApp;
    $this->oRouter = is_null($this->oRouter) ? $this->oApp->getRouter() : $oRouter;
    $this->getType();
    $this->init();
  }

  /**
   * Activate this controller and any required dependencies then return a list of types that were activated
   *
   * @param array $hActiveController - the active controller list
   * @return array
   * @throws Exception on failure
   */
  public function activate(array $hActiveController)
  {
    $aNewActiveController = [$this->getType()];

    if (!empty(static::$aControllerDependencies))
    {
      foreach (static::$aControllerDependencies as $sController)
      {
        if (!isset($hActiveController[$sController]))
        {
          $this->oApp->activateController($sController);
          $aNewActiveController = array_merge($aNewActiveController, [$sController]);
        }
      }
    }

    $this->setup();
    return $aNewActiveController;
  }

  /**
   * Do whatever setup is needed to make this controller work...
   *
   * @throws Exception on failure
   */
  public function setup()
  {
  }

  /**
   * Deactivate this controller then return a list of types that were deactivated
   *
   * @param array $hActiveController - the active controller list
   * @return array
   * @throws Exception on failure
   */
  public function deactivate(array $hActiveController)
  {
    return [$this->getType()];
  }

  /**
   * Initialize this controller's custom data, if there is any
   */
  protected function init()
  {
  }

  /**
   * Return a valid component name from the specified menu item
   *
   * @param string $sMenuModel
   * @return string
   */
  protected function getComponent($sMenuModel)
  {
    if ($sMenuModel == 'list')
    {
      return 'search';
    }

    if ($sMenuModel == 'editcolumn')
    {
      return 'edit';
    }

    return $sMenuModel;
  }

  /**
   * Should the specified component type be allowed to be used by the current user of this controller?
   *
   * @param string $sComponent
   * @return boolean
   */
  public function allow($sComponent)
  {
    if (!isset($this->hAllow[$sComponent]))
    {
      $this->hAllow[$sComponent] = $this->oApp->user()->hasResource($this->getType(), $this->getComponent($sComponent));
    }

    return $this->hAllow[$sComponent];
  }

  /**
   * Generate and return the URI for the specified parameters
   *
   * @param string ...$aParam (optional) - List of parameters to place in the URI
   * @return string
   */
  public function generateUri(string ...$aParam): string
  {
    array_unshift($aParam, $this->sType);
    return $this->oApp->generateUri(...$aParam);
  }
}