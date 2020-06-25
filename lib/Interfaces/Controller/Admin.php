<?php

namespace Limbonia\Interfaces\Controller;

interface Admin
{
  /**
   * Return this controller's admin group
   *
   * @return string
   */
  static function getGroup();

  /**
   * Generate and return an HTML field, in the default style, using the specified data
   *
   * @param string $sContent
   * @param string $sLabel (optional)
   * @param string $sFieldId (optional)
   * @return string
   */
  static function field($sContent, $sLabel = '', $sFieldId = '');

  /**
   * Generate and return an HTML field, in the default style, using the specified widget object to generate the content and field id
   *
   * @param \Limbonia\Widget $oWiget
   * @param string $sLabel (optional)
   * @return string
   */
  static function widgetField(\Limbonia\Widget $oWiget, $sLabel = '');

  /**
   * Is this controller currently performing a search?
   *
   * @return boolean
   */
  function isSearch();

  /**
   * Prepare the view for display based on the current action and current method
   */
  function prepareView();

  /**
   * Generate and return the path of the view to display
   *
   * @return boolean|string
   */
  function getView();

  /**
   * Return an array of data that is needed to display the controller's admin output
   *
   * @return array
   */
  function getAdminOutput();

  /**
   * Return an array of height and width for a popup based on the specified name, if there is one
   *
   * @param string $sName
   * @return array
   */
  function getPopupSize($sName);

  /**
   * Return this controller's list of menu items
   *
   * @return array
   */
  function getMenuItems();

  /**
   * Return this controller's list of quick search models
   *
   * @return array
   */
  function getQuickSearch();

  /**
   * Return this controller's list of sub-menu items
   *
   * @param boolean $bOnlyUserAllowed (optional) - Should the returned array only contain models that the current user has access to?
   * @return array
   */
  function getSubMenuItems($bOnlyUserAllowed = false);

  /**
   * Generate and return the title for this controller
   *
   * @return string
   */
  function getTitle();

  /**
   * Return the current action
   *
   * @return string
   */
  function getCurrentAction();

  /**
   * Generate the search results table headers in the specified grid object
   *
   * @param \Limbonia\Widget\Table $oSortGrid
   * @param string $sColumn
   */
  function processSearchGridHeader(\Limbonia\Widget\Table $oSortGrid, $sColumn);

  /**
   * Generate and return the HTML needed to control the row specified by the id
   *
   * @param string $sIDColumn
   * @param integer $iID
   * @return string
   */
  function processSearchGridRowControl($sIDColumn, $iID);

  /**
   * Generate and return the HTML for the specified form field based on the specified information
   *
   * @param string $sName
   * @param string $sValue
   * @param array $hData
   * @return string
   */
  function getFormField($sName, $sValue = null, $hData = []);

  /**
   * Generate and return the HTML for the specified form field based on the specified information
   *
   * @param string $sName
   * @param string $sValue
   * @param array $hData
   * @return string
   */
  function getField($sName, $sValue = null, $hData = []);

  /**
   * Generate and return the column title from the specified column name
   *
   * @param string $sColumn
   * @return string
   */
  function getColumnTitle($sColumn);

  /**
   * Generate and return the value of the specified column
   *
   * @param \Limbonia\Model $oModel
   * @param string $sColumn
   * @return mixed
   */
  function getColumnValue(\Limbonia\Model $oModel, $sColumn);

  /**
   * Generate and return the HTML for all the specified form fields
   *
   * @param array $hFields - List of the fields to generate HTML for
   * @param array $hValues (optional) - List of field data, if there is any
   * @return string
   */
  function getFormFields($hFields, $hValues = []);

  /**
   * Generate and return the HTML for all the specified form fields
   *
   * @param array $hFields - List of the fields to generate HTML for
   * @param array $hValues (optional) - List of field data, if there is any
   * @return string
   */
  function getFields($hFields, $hValues = []);

  /**
   * Echo the form generated by the specified data
   *
   * @param string $sType
   * @param array $hFields
   * @param array $hValues
   */
  function getForm($sType, $hFields, $hValues = []);
}