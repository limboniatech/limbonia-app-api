<?php

namespace Limbonia\Traits\Controller;

trait Api
{
  /**
   * Translate HTTP method to internal component
   *
   * @var array
   */
  protected static $hHttpMethodToComponent =
  [
    'head' => 'search',
    'get' => 'search',
    'post' => 'create',
    'put' => 'edit',
    'delete' => 'delete',
    'options' => ''
  ];

  /**
   * List of valid HTTP methods
   *
   * @var array
   */
  protected static function getHttpMethods()
  {
    $aMethods = [];

    foreach (['head', 'get', 'post', 'put', 'delete'] as $sMethod)
    {
      if (\method_exists(static::class, 'processApi' . \ucfirst($sMethod)))
      {
        $aMethods[] = $sMethod;
      }
    }

    $aMethods[] = 'options';
    return $aMethods;
  }

  /**
   * Return list of valid http methods
   *
   * @return array
   */
  public function getAllowedHttpMethods()
  {
    $aAllowedMethods = [];

    foreach (static::getHttpMethods() as $sMethod)
    {
      if (empty(static::$hHttpMethodToComponent[$sMethod]) || $this->allow(static::$hHttpMethodToComponent[$sMethod]))
      {
        $aAllowedMethods[] = $sMethod;
      }
    }

    return $aAllowedMethods;
  }

  /**
   * Is the current user valid?
   *
   * @return boolean
   */
  public function validUser()
  {
    $oUser = $this->oApp->user();

    if ($oUser instanceof \Limbonia\Model\User)
    {
      return $oUser->id > 0;
    }

    return false;
  }

  /**
   * Emit the Allow header containing only methods the current user can access
   */
  public function headerAllowedHttpMethods()
  {
    header('Allow: ' . strtoupper(implode(',', $this->getAllowedHttpMethods())));
  }

  /**
   * Process the current API call and return the appropriate data
   *
   * @return mixed
   * @throws \Limbonia\Exception\Web
   */
  public function processApi()
  {
    http_response_code(200);

    if (!$this->validUser())
    {
      throw new \Limbonia\Exception\Web('Authentication required', null, 401);
    }

    if (!in_array($this->oRouter->method, static::getHttpMethods()))
    {
      throw new \Limbonia\Exception\Web("HTTP method ({$this->oRouter->method}) not allowed", null, 405);
    }

    if (!empty(static::$hHttpMethodToComponent[$this->oRouter->method]) && !$this->allow(static::$hHttpMethodToComponent[$this->oRouter->method]))
    {
      throw new \Limbonia\Exception\Web("Action not allowed to user", null, 405);
    }

    switch ($this->oRouter->method)
    {
      case 'head':
        return $this->processApiHead();

      case 'get':
        if ($this->oRouter->action == 'components')
        {
          return $this->getComponents();
        }
    
        return $this->processApiGet();

      case 'put':
        return $this->processApiPut();

      case 'post':
        http_response_code(201);
        return $this->processApiPost();

      case 'delete':
        http_response_code(204);
        return $this->processApiDelete();

      case 'options':
        $this->headerAllowedHttpMethods();
        return null;
    }

    throw new \Limbonia\Exception\Web("HTTP method ({$this->oRouter->method}) not recognized", null, 405);
  }
}