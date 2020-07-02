<?php
namespace Limbonia\App;

/**
 * Limbonia API App Class
 *
 * This allows the basic app retrieve data base on the API URL and return
 * that data in JSON format
 *
 * @author Lonnie Blansett <lonnie@limbonia.tech>
 * @package Limbonia
 */
class Api extends \Limbonia\App
{
  protected static $hJsonData = null;

  public static function jsonData()
  {
    if (is_null(self::$hJsonData))
    {
      self::$hJsonData = json_decode(file_get_contents("php://input"), true);
    }

    return self::$hJsonData;
  }

  /**
   * The app constructor
   *
   * NOTE: This constructor should only be used by the factory and *never* directly
   *
   * @param array $hConfig - A hash of configuration data
   */
  protected function __construct(array $hConfig = [])
  {
    \Limbonia\App::__construct($hConfig);
    $oServer = \Limbonia\Input::singleton('server');

    if (empty($this->oDomain))
    {
      if (!empty($oServer['context_prefix']) && !empty($oServer['context_document_root']))
      {
         $this->oDomain = new \Limbonia\Domain($oServer['server_name'] . $oServer['context_prefix'], $oServer['context_document_root']);
      }
      else
      {
        $this->oDomain = \Limbonia\Domain::getByDirectory($oServer['document_root']);
      }

      $this->hConfig['baseuri'] = $this->oDomain->uri;
    }

    if (empty($this->oDomain->uri))
    {
      $this->oRouter = \Limbonia\Router::singleton();
    }
    //if the request is coming from a URI
    else
    {
      //then override the default Router object
      $this->oRouter = \Limbonia\Router::fromArray
      ([
        'uri' => $oServer['request_uri'],
        'baseurl' => $this->oDomain->uri,
        'method' => strtolower($oServer['request_method'])
      ]);
    }
  }

  /**
   * Generate and return the controller of the specified type
   *
   * @param string $sType
   * @return \Limbonia\Interfaces\Controller\Api
   */
  public function controllerFactory($sType)
  {
    $sDriver = \Limbonia\Controller\Api::driver($sType) ?? \Limbonia\Controller::driver($sType);

    if (!isset(self::$hControllerList[$sDriver]))
    {
      try
      {
        self::$hControllerList[$sDriver] = \Limbonia\Controller\Api::factory($sType, $this);
      }
      catch (\Throwable $th)
      {
        $oController = \Limbonia\Controller::factory($sType, $this);

        if (!($oController instanceof \Limbonia\Interfaces\Controller\Api))
        {
          throw new \Limbonia\Exception("Controller does not implement \Limbonia\Interfaces\Controller\Api");
        }
    
        self::$hControllerList[$sDriver] = $oController;
      }
    }

    return self::$hControllerList[$sDriver];
  }

  /**
   * Return the controller specified by the router
   * 
   * @return \Limbonia\Interfaces\Controller\Api
   * @throws \Limbonia\Exception
   */
  protected function getController()
  {
    if (empty($this->oRouter->controller))
    {
      throw new \Limbonia\Exception('No controller specified');
    }

    return $this->controllerFactory($this->oRouter->controller);
  }

  /**
   * Get the user associated with the specified auth_token and return it
   *
   * @param type $sAuthToken
   * @return type
   */
  public function userByAuthToken($sAuthToken)
  {
    $oUser = \Limbonia\Model\User::getByAuthToken($sAuthToken, $this->getDB());
    $oUser->setApp($this);
    return $oUser;
  }

  /**
   * Get the user associated with the specified API key and return it
   *
   * @param string $sApiKey
   * @return \Limbonia\Model\User
   */
  public function userByApiKey($sApiKey)
  {
    $oUser = \Limbonia\Model\User::getByApiKey($sApiKey, $this->getDB());
    $oUser->setApp($this);
    return $oUser;
  }

    /**
   * Generate and return the current user
   *
   * @return \Limbonia\Model\User
   * @throws \Exception
   */
  protected function generateUser()
  {
    $oServer = \Limbonia\Input::singleton('server');

    if (!empty($oServer['http_auth_token']))
    {
      return $this->userByAuthToken($oServer['http_auth_token']);
    }

    if (!empty($oServer['http_api_key']))
    {
      return $this->userByApiKey($oServer['http_api_key']);
    }

    return $this->modelFactory('user');
  }

  /**
   * Render this app instance for output and return that data
   *
   * @return string
   */
  protected function render()
  {
    try
    {
      $oController = $this->getController();
    }
    catch (\Limbonia\Exception $e)
    {
      throw new \Limbonia\Exception\Web('Valid controller not found: ' . $e->getMessage(), null, 400, $e);
    }
    
    $xResult = $oController->processApi();

    if ($xResult instanceof \Limbonia\ModelList || $xResult instanceof \Limbonia\Model || $xResult instanceof \Limbonia\Interfaces\Result)
    {
      return $xResult->getAll();
    }

    return $xResult;
  }

  /**
   * Run everything needed to react to input and display data in the way this app is intended
   */
  public function run()
  {
    try
    {
      ob_start();
      $this->oUser = $this->generateUser();
      $xOutput = $this->render();
    }
    catch (\Throwable $e)
    {
      $xOutput =
      [
        'error_code' => $e->getCode(),
        'message' => $e->getMessage()
      ];
  
      if (empty($xOutput['error_code']))
      {
        unset($xOutput['error_code']);
      }
  
      if ($e instanceof \Limbonia\Exception\Auth)
      {
        http_response_code(401);
      }
      elseif ($e instanceof \Limbonia\Exception\Web)
      {
        http_response_code($e->getResponseCode());
      }
      else
      {
        http_response_code(400);
      }
    }
    finally
    {
      if (http_response_code() == 405)
      {
        $this->getController()->headerAllowedHttpMethods();
      }

      ob_end_clean();
      die(parent::outputJson($xOutput));
    }
  }
}
