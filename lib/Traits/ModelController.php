<?php
namespace Limbonia\Traits;

/**
 * Limbonia ModelController Trait
 *
 * This trait allows an inheriting controller to use an model
 *
 * @author Lonnie Blansett <lonnie@limbonia.tech>
 * @package Limbonia
 */
trait ModelController
{
  /**
   * The type of Model that this controller uses
   *
   * @var string
   */
  protected $sModelType = '';

  /**
   * The model object associated with this controller
   *
   * @var \Limbonia\Model
   */
  protected $oModel = null;

  /**
   * List of column names in the order required
   *
   * @return array
   */
  protected function columnOrder()
  {
    return [];
  }

  /**
   * Do whatever setup is needed to make this controller work...
   */
  public function setup()
  {
    $this->oModel->setup();
  }

  /**
   * Initialize this controller's custom data, if there is any
   *
   * @throws \Limbonia\Exception
   */
  protected function init()
  {
    if (empty($this->sModelType))
    {
      $this->sModelType = $this->getType();
    }

    $sModelDriver = \Limbonia\Model::driver($this->sModelType);

    if (empty($sModelDriver))
    {
      throw new \Limbonia\Exception("Driver for type ($this->sModelType) not found");
    }

    $this->oModel = $this->oApp->modelFactory($this->sModelType);

    if (isset($this->oRouter->id) && strtolower($this->sType) == $this->oRouter->controller)
    {
      $this->oModel->load($this->oRouter->id);
    }

    if ($this->oModel->id > 0)
    {
      $this->hMenuItems['model'] = 'Model';
      $this->aAllowedActions[] = 'model';
    }
  }

  /**
   * Return the model object stored for use with this controller
   *
   * @return \Limbonia\Model
   */
  public function getModel()
  {
    return $this->oModel;
  }

  /**
   * Make sure a valid model is loaded
   *
   * @throws \Exception
   */
  protected function processApiCheckModel()
  {
    if ($this->oModel->id == 0)
    {
      throw new \Limbonia\Exception\Web($this->getType() . ' #' . $this->oRouter->call[1] . ' not found', null, 404);
    }
  }

  /**
   * Perform the base "GET" code then return null on success
   *
   * @return null
   * @throws \Exception
   */
  protected function processApiHead()
  {
    if (empty($this->oRouter->call[1]))
    {
      $oDatabase = $this->oApp->getDB();
      $oDatabase->query($oDatabase->makeSearchQuery($this->oModel->getTable(), ['id'], $this->oRouter->search, null));
      return null;
    }

    $this->processApiCheckModel();
    return null;
  }

  /**
   * Remove any ignored fields of the specified type from the specified data then return it
   *
   * @param string $sIgnoreType
   * @param array $hData
   * @return array
   */
  protected function removeIgnoredFields($sIgnoreType, $hData)
  {
    if (empty($this->aIgnore[$sIgnoreType]))
    {
      return $hData;
    }

    foreach ($this->aIgnore[$sIgnoreType] as $sField)
    {
      if (isset($hData[$sField]))
      {
        unset($hData[$sField]);
      }
    }

    return $hData;
  }


  protected function getList(array $aFields = [])
  {
    $sTable = $this->oModel->getTable();
    $oDatabase = $this->oApp->getDB();
    $aRawFields = empty($aFields) ? [] : array_merge(['id'], $aFields);
    $aFields = array_diff($oDatabase->verifyColumns($sTable, $aRawFields), $this->aIgnore['view']);

    //default order is according to the ID column of this model
    $aOrder = $this->oRouter->sort ?? ['id'];
    $oResult = $oDatabase->query($oDatabase->makeSearchQuery($sTable, $aFields, $this->oRouter->search, $aOrder));
    $hList = [];

    foreach ($oResult as $hRow)
    {
      //filter the data through the controller's model
      $oModel = $this->oApp->modelFromArray($sTable, $hRow);
      $hModel = $this->removeIgnoredFields('view', $oModel->getAll());

      if (empty($aFields))
      {
        $hList[$oModel->id] = $hModel;
      }
      else
      {
        $hTemp = [];

        foreach ($aFields as $sField)
        {
          if (isset($hModel[$sField]))
          {
            $hTemp[$sField] = $hModel[$sField];
          }
        }

        $hList[$oModel->id] = $hTemp;
      }
    }

    return $hList;
  }

    /**
   * Generate and return the default list of data, filtered and ordered by API controls
   *
   * @return array
   * @throws \Exception
   */
  protected function processApiGetList()
  {
    $aField = empty($this->oRouter->fields) ? [] : $this->oRouter->fields;
    return array_values($this->getList($aField));
  }

  /**
   * Generate and return the default model data, filtered by API controls
   *
   * @return array
   * @throws \Exception
   */
  protected function processApiGetModel()
  {
    $hRaw = $this->removeIgnoredFields('view', $this->oModel->getAll());

    if ($this->oRouter->fields)
    {
      $hResult = [];
      $sTable = $this->oModel->getTable();

      foreach ($this->oRouter->fields as $sColumn)
      {
        $sRealColumn = $this->oApp->getDB()->hasColumn($sTable, $sColumn);

        if ($sRealColumn)
        {
          if (isset($hRaw[$sRealColumn]))
          {
            $hResult[$sRealColumn] = $hRaw[$sRealColumn];
          }
        }
      }

      return $hResult;
    }

    return $hRaw;
  }

  /**
   * Perform and return the default "GET" code
   *
   * @return array
   * @throws \Exception
   */
  protected function processApiGet()
  {
    if (empty($this->oRouter->call[1]))
    {
      return $this->processApiGetList();
    }

    $this->processApiCheckModel();
    return $this->processApiGetModel();
  }

  protected function putData()
  {
    $hLowerModel = \array_change_key_case($this->oRouter->data, CASE_LOWER);

    foreach ($this->aIgnore['edit'] as $sField)
    {
      $sLowerField = strtolower($sField);

      if (isset($hLowerModel[$sLowerField]))
      {
        unset($hLowerModel[$sLowerField]);
      }
    }

    return $hLowerModel;
  }

  /**
   * Update the API specified model with the API specified data then return the updated model
   *
   * @return \Limbonia\Model
   * @throws \Exception
   */
  protected function processApiPutModel()
  {
    $this->oModel->setAll($this->putData());
    $this->oModel->save();
    return $this->oModel;
  }

  /**
   * Update the API specified list of models with the API specified data then return the updated list
   *
   * @return array
   * @throws \Exception
   */
  protected function processApiPutList()
  {
    if (empty($this->oRouter->search))
    {
      throw new \Limbonia\Exception\Web("No list criteria specified", null, 403);
    }

    $hModelList = $this->getList(['id']);

    if (empty($hModelList))
    {
      return true;
    }

    $aModelList = array_keys($hModelList);
    $aList = [];
    $sTable = $this->oModel->getTable();
    $hPutData = $this->putData();

    foreach ($aModelList as $iModel)
    {
      $oModel = $this->oApp->modelFromId($sTable, $iModel);
      $oModel->setAll($hPutData);
      $oModel->save();
      $aList[] = $oModel->getAll();
    }

    return $aList;
  }

  /**
   * Run the default "PUT" code and return the updated data
   *
   * @return array
   * @throws \Exception
   */
  protected function processApiPut()
  {
    if (!is_array($this->oRouter->data) || count($this->oRouter->data) == 0)
    {
      throw new \Exception('No valid data found to process');
    }

    if (empty($this->oRouter->call[1]))
    {
      return $this->processApiPutList();
    }

    $this->processApiCheckModel();
    return $this->processApiPutModel();
  }

  protected function modelFromArray($hModel)
  {
    $sTable = $this->oModel->getTable();
    $sIdColumn = strtolower($this->oModel->getIDColumn());
    $hLowerModel = \array_change_key_case($hModel, CASE_LOWER);

    if (isset($hLowerModel['id']))
    {
      unset($hLowerModel['id']);
    }

    if (isset($hLowerModel[$sIdColumn]))
    {
      unset($hLowerModel[$sIdColumn]);
    }

    return $this->oApp->modelFromArray($sTable, $hLowerModel);
  }

  /**
   * Create the API specified model with the API specified data then return the created model
   *
   * @return \Limbonia\Model
   * @throws \Exception
   */
  protected function processApiPostModel()
  {
    $oModel = $this->modelFromArray($this->oRouter->data);
    $oModel->save();
    header('Location: ' . $this->oApp->getDomain()->currenturl . '/' . $this->oRouter->rawPath . '/' . $oModel->id);
    return $oModel->getAll();
  }

  /**
   * Create the API specified list of models with the API specified data then return that list
   *
   * @return array
   * @throws \Exception
   */
  protected function processApiPostList()
  {
    $hList = [];

    foreach ($this->oRouter->data as $hModel)
    {
      $oModel = $this->modelFromArray($hModel);
      $oModel->save();
      $hList[$oModel->id] = $oModel->getAll();
    }

    $aIdList = array_keys($hList);
    header('Location: ' . $this->oApp->getDomain()->currenturl . '/' . $this->oRouter->rawPath . '/?id=' . implode(',', $aIdList));
    return array_values($hList);
  }

  /**
   * Run the default "POST" code and return the created data
   *
   * @return array
   * @throws \Limbonia\Exception
   */
  protected function processApiPost()
  {
    if (!is_array($this->oRouter->data) || count($this->oRouter->data) == 0)
    {
      throw new \Limbonia\Exception('No valid data found to process');
    }

    $aKeys = array_keys($this->oRouter->data);

    //if the first data key is numeric
    if (is_numeric($aKeys[0]))
    {
      //then we must be processing a list of models...
      return $this->processApiPostList();
    }

    //otherwise it is a single model
    return $this->processApiPostModel();
  }

  /**
   * Delete the API specified model then return true
   *
   * @return \Limbonia\Model
   * @throws \Exception
   */
  protected function processApiDeleteModel()
  {
    return $this->oModel->delete();
  }

  /**
   * Delete the API specified list of models then return true
   *
   * @return array
   * @throws \Exception
   */
  protected function processApiDeleteList()
  {
    if (empty($this->oRouter->search))
    {
      throw new \Limbonia\Exception\Web("No list criteria specified", null, 403);
    }

    $hList = $this->getList(['id']);
    $aList = array_keys($hList);

    if (empty($aList))
    {
      throw new \Limbonia\Exception\Web("List criteria produced no results", null, 403);
    }

    $sTable = $this->oModel->getTable();
    $sIdColumn = $this->oModel->getIDColumn();
    $sSql = "DELETE FROM $sTable WHERE $sIdColumn IN (" . implode(', ', $aList) . ")";
    $iRowsDeleted = $this->oApp->getDB()->exec($sSql);

    if ($iRowsDeleted === false)
    {
      $aError = $this->errorInfo();
      throw new \Limbonia\Exception\DBResult("Model list not deleted from $sTable: {$aError[0]} - {$aError[2]}", $this->getType(), $sSql, $aError[1]);
    }

    return true;
  }

  /**
   * Run the default "DELETE" code and return true
   *
   * @return boolean - True on success
   * @throws \Exception
   */
  protected function processApiDelete()
  {
    if (empty($this->oRouter->call[1]))
    {
      return $this->processApiDeleteList();
    }

    $this->processApiCheckModel();
    $this->processApiDeleteModel();
  }

  /**
   * Run the code needed to display the default "list" view
   */
  protected function prepareViewList()
  {
    $this->prepareViewPostSearch();
  }

  /**
   * Run the code needed to display the default "create" view
   */
  protected function prepareViewGetCreate()
  {
    $this->oModel->setAll($this->getApp()->get->getRaw());
  }

  /**
   * Run the code needed to display the default "create" view
   */
  protected function prepareViewCreate()
  {
    $this->getApp()->viewData('fields', $this->getColumns('create'));
  }

  /**
   * Run the code needed to display the default "edit" view
   */
  protected function prepareViewEdit()
  {
    if (!$this->allow('edit') || isset($this->oApp->post['No']))
    {
      $this->oApp->viewData('close', true);
      return null;
    }

    $this->oApp->viewData('fields', $this->getColumns('Edit'));
  }

  /**
   * Run the code needed to display the default "search" view
   */
  protected function prepareViewSearch()
  {
    $this->oApp->viewData('fields', $this->getColumns('search'));
  }

  /**
   * Run the code needed to display the default "view" view
   */
  protected function prepareViewView()
  {
    $this->oApp->viewData('fields', $this->getColumns('View'));
  }

  /**
   * Process the default "create" code then display the results
   */
  protected function prepareViewPostCreate()
  {
    try
    {
      $this->oModel->setAll($this->processCreateGetData());
      $this->oModel->save();
      $this->getApp()->viewData('success', "Successfully created new " . $this->getType() . "<a class=\"model\" href=\"" . $this->generateUri('create') . "\">Create another?</a>");
    }
    catch (\Exception $e)
    {
      $this->getApp()->viewData('failure', 'Failed creating new ' . $this->getType() . ': ' . $e->getMessage());
    }

    $this->sCurrentAction = 'view';
  }

  /**
   * Process the default "edit" code then display the results
   */
  protected function prepareViewPostEdit()
  {
    try
    {
      $this->oModel->setAll($this->editGetData());
      $this->oModel->save();
      $this->oApp->viewData('success', "This " . $this->getType() . " update has been successful.");
    }
    catch (\Exception $e)
    {
      $this->oApp->viewData('failure', "This " . $this->getType() . " update has failed: " . $e->getMessage());
    }

    if (isset($_SESSION['EditData']))
    {
      unset($_SESSION['EditData']);
    }

    $this->sCurrentAction = 'view';
  }

  /**
   * Process the default "search" code then display the results
   */
  protected function prepareViewPostSearch()
  {
    $hSearch = $this->processSearchTerms($this->processSearchGetCriteria());
    $oData = $this->processSearchGetData($hSearch);

    if ($oData->count() == 1)
    {
      if (isset($this->oRouter->ajax))
      {
        $this->oModel = $oData[0];
        $this->hMenuItems['model'] = 'Model';
        $this->aAllowedActions[] = 'model';
        $this->sCurrentAction = 'view';
        return true;
      }

      if (isset($this->oRouter->subAction) && $this->oRouter->subAction == 'quick')
      {
        $oModel = $oData[0];
        header('Location: '. $this->generateUri($oModel->id));
      }
    }

    $this->oApp->viewData('data', $oData);
    $this->oApp->viewData('idColumn', preg_replace("/.*?\./", '', $this->oModel->getIDColumn()));
    $aColumns = array_keys($this->getColumns('Search'));

    foreach (array_keys($aColumns) as $sKey)
    {
      $this->processSearchColumnHeader($aColumns, $sKey);
    }

    $this->oApp->viewData('dataColumns', $aColumns);
    $this->oApp->viewData('table', $this->oApp->widgetFactory('Table'));
  }

  /**
   * Prepare the view for display based on the current action and current method
   */
  public function prepareView()
  {
    $this->oApp->viewData('currentModel', $this->oModel);
    parent::prepareView();
  }

  /**
   * Return an array of data that is needed to display the controller's admin output
   *
   * @return array
   */
  public function getAdminOutput()
  {
    if ($this->oModel->id > 0)
    {
      return array_merge(parent::getAdminOutput(),
      [
        'modelTitle' => $this->getCurrentModelTitle(),
        'subMenu' => $this->getSubMenuItems(true),
        'id' => $this->oModel->id,
        'modelUri' => $this->generateUri($this->oModel->id)
      ]);
    }

    return parent::getAdminOutput();
  }

  /**
   * Return the name / title of this controller's current model, if there is one
   *
   * @return string
   */
  public function getCurrentModelTitle()
  {
    return isset($this->oModel->name) ? $this->oModel->name : '';
  }

  /**
   * Generate and return a list of columns based on the specified type
   *
   * @param string $sType (optional)
   * @return array
   */
  public function getColumns($sType = null)
  {
    $sLowerType = strtolower($sType);
    $hColumn = $this->oModel->getColumns();
    $sIDColumn = $this->oModel->getIDColumn();

    //remove the id column
    if (isset($hColumn[$sIDColumn]))
    {
      unset($hColumn[$sIDColumn]);
    }

    if (!empty($sLowerType) && !empty($this->aIgnore[$sLowerType]))
    {
      foreach ($this->aIgnore[$sLowerType] as $sIgnoreColumn)
      {
        if (isset($hColumn[$sIgnoreColumn]))
        {
          unset($hColumn[$sIgnoreColumn]);
        }
      }
    }

    if ($sLowerType == 'search')
    {
      foreach (array_keys($hColumn) as $sColumn)
      {
        if ($hColumn[$sColumn]['Type'] == 'text')
        {
          $hColumn[$sColumn]['Type'] = 'varchar';
        }

        if ($hColumn[$sColumn]['Type'] == 'date')
        {
          $hColumn[$sColumn]['Type'] = 'searchdate';
        }
      }
    }

    $aColumnOrder = $this->columnOrder();

    if (empty($aColumnOrder))
    {
      return $hColumn;
    }

    //reorder the columns
    $hOrderedColumn = [];

    //only order the columns that are in the list
    foreach ($aColumnOrder as $sColumn)
    {
      if (isset($hColumn[$sColumn]))
      {
        $hOrderedColumn[$sColumn] = $hColumn[$sColumn];
        unset($hColumn[$sColumn]);
      }
    }

    //add the rest of the columns at the end of the orderded columns
    return array_merge($hOrderedColumn, $hColumn);
  }

  /**
   * Echo the form generated by the specified data
   *
   * @param string $sType
   * @param array $hFields
   * @param array $hValues
   */
  public function getForm($sType, $hFields, $hValues = [])
  {
    $sForm = parent::getForm($sType, $hFields, $hValues);

    if ($this->oModel->id == 0)
    {
      return $sForm;
    }

    $sType = preg_replace('/ /', '', $sType);
    return preg_replace("/action=\".*?\"/", 'action="' . $this->generateUri($this->oModel->id, $sType) . '"', $sForm);
  }

  /**
   * Generate and return the data for the "Create" process
   *
   * @return array
   */
  protected function processCreateGetData()
  {
    $hData = isset($this->oApp->post[$this->sType]) ? $this->oApp->post[$this->sType] : [];

    foreach (array_keys($hData) as $sKey)
    {
      if (empty($hData[$sKey]))
      {
        unset($hData[$sKey]);
      }
    }

    foreach ($this->oModel->getColumns() as $sName => $hColumnData)
    {
      if (strtolower($hColumnData['Type']) == 'tinyint(1)')
      {
        $hData[$sName] = isset($hData[$sName]);
      }
    }

    return $hData;
  }

  /**
   * Return the name of the ID column to use in the search
   *
   * @return string
   */
  protected function processSearchGetSortColumn()
  {
    return $this->oModel->getIDColumn();
  }

  /**
   * Perform the search based on the specified criteria and return the result
   *
   * @param string|array $xSearch
   * @return \Limbonia\ModelList
   */
  protected function processSearchGetData($xSearch)
  {
    return $this->oApp->modelSearch($this->oModel->getTable(), $xSearch, $this->processSearchGetSortColumn());
  }

  /**
   * Generate and return the HTML displayed after the edit has finished
   *
   * @param string $sText
   * @param boolean $bReload
   * @return string
   */
  public function editFinish($sText)
  {
    if ($this->oModel->id > 0)
    {
      $sURL = $this->generateUri($this->oModel->id, 'view');
      $sClass = ' class="model"';
    }
    else
    {
      $sURL = $this->generateUri('list');
      $sClass = ' class="controller"';
    }

    if (isset($_SESSION['EditData']))
    {
      unset($_SESSION['EditData']);
    }

    return "<center><h1>$sText</h1> Click <a$sClass href=\"$sURL\">here</a> to continue.</center>";
  }

  /**
   * Generate and return the HTML for dealing with updates to rows of data
   *
   * @return string
   */
  public function editColumn()
  {
    if (!$this->allow('Edit') || (isset($this->oApp->post['No']) && $this->oApp->post['No'] == 1))
    {
      if (isset($_SESSION['EditData']))
      {
        unset($_SESSION['EditData']);
      }

      return "<script type=\"text/javascript\" language=\"javascript\">history.go(-2);</script>";
    }

    $sFullIDColumn = $this->oModel->getIDColumn();
    $sIDColumn = preg_replace("/.*?\./", "", $sFullIDColumn);

    if (isset($this->oApp->post[$sIDColumn]))
    {
      $_SESSION['EditData'][$sIDColumn] = $this->oApp->post[$sIDColumn];
    }

    if (isset($this->oApp->post['Delete']))
    {
      $_SESSION['EditData']['Delete'] = $this->oApp->post['Delete'];
    }

    if (isset($this->oApp->post['All']))
    {
      $_SESSION['EditData']['All'] = $this->oApp->post['All'];
    }

    if (isset($this->oApp->post['Column']))
    {
      $_SESSION['EditData']['Column'] = $this->oApp->post['Column'];
    }

    if (!isset($_SESSION['EditData'][$sIDColumn]) && !isset($_SESSION['EditData']['All']))
    {
      $sUse = isset($_SESSION['EditData']['Delete']) ? 'delete' : 'edit';
      //for now we are going to fail insted of asking to use all models...
      //return $this->editDialog("No IDs were checked!  Did you want to $sUse all of them?<br />\n", 'All');
      return $this->editFinish("No IDs were checked, $sUse has failed.  Please check some models and try again!<br />\n");
    }

    if (isset($_SESSION['EditData']['Delete']))
    {
      if (!isset($this->oApp->post['Check']))
      {
        return $this->editDialog("<input name=\"Check\" id=\"Check\" value=\"1\" type=\"hidden\">\nOnce deleted these models can <b>not</b> restored!  Continue anyway?\n", 'Check');
      }

      $bSuccess = false;

      $hWhere = isset($_SESSION['EditData']['All']) ? [] : [$sFullIDColumn => array_keys($_SESSION['EditData'][$sIDColumn])];
      $oModelList = \Limbonia\Model::search($this->getType(), $hWhere);

      if (isset($oModelList))
      {
        foreach ($oModelList as $oModel)
        {
          $oModel->delete();
        }

        $bSuccess = true;
      }

      $sSuccess = $bSuccess ? 'complete' : 'failed';
      return $this->editFinish("Deletion $sSuccess!");
    }

    if (!$sFullColumn = $_SESSION['EditData']['Column'])
    {
      return $this->editFinish("The column \"{$_SESSION['EditData']['Column']}\" does not exist!");
    }

    if (!isset($this->oApp->post['Update']))
    {
      $hColumn = $this->oModel->getColumn($sFullColumn);
      return $this->editDialog($this->getFormFields([$_SESSION['EditData']['Column'] => $hColumn]), 'Update');
    }

    //the first model in the _POST array will be our data
    $sData = array_shift($this->oApp->post);

    foreach ($_SESSION['EditData']['AdList'] as $oModel)
    {
      $oModel->setAll($sData);
      $oModel->save();
    }

    return $this->editFinish("Update complete!");
  }

  /**
   * Return the appropriate data for the current edit
   *
   * @return array
   * @throws \Limbonia\Exception
   */
  protected function editGetData()
  {
    $hPost = isset($this->oApp->post[$this->sType]) ? $this->oApp->post[$this->sType] : $this->oApp->post->getRaw();

    if (empty($hPost))
    {
      throw new \Limbonia\Exception('No POST data found');
    }

    $hTemp = $this->oModel->getColumns();
    $aIgnore = isset($this->aIgnore['boolean']) ? $this->aIgnore['boolean'] : [];

    foreach ($hTemp as $sName => $hColumnData)
    {
      if (!in_array($sName, $aIgnore) && strtolower($hColumnData['Type']) == 'tinyint(1)')
      {
        $hPost[$sName] = isset($hPost[$sName]);
      }
    }

    return $hPost;
  }
}
