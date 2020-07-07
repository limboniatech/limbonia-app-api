<?php
namespace Limbonia\Traits\Controller\Api;

trait HasSettings
{
  /**
   * Instantiate a controller
   */
  protected function apiSettingsConstruct()
  {
  }

  /**
   * Generate and return the list of settings or settings fields
   *
   * @return array
   * @throws \Limbonia\Exception
   */
  protected function processApiGetSettings()
  {
    if ($this->oRouter->action != 'settings')
    {
      throw new \Limbonia\Exception("Invalid settings action: {$this->oRouter->action}");
    }

    if ($this->oRouter->subaction == 'fields')
    {
      return $this->getSettingsFields();
    }

    return $this->getSetting($this->oRouter->subaction);
  }

  /**
   * Create the API specified model with the API specified data then return the created model
   *
   * @return array
   * @throws \Limbonia\Exception
   */
  protected function processApiPostSettings()
  {
    foreach ($this->oRouter->data as $sSettingName => $xSettingValue)
    {
      $this->setSetting($sSettingName, $xSettingValue);
    }

    $this->saveSettings();
    return $this->getSetting();
  }
}