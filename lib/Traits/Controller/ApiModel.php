<?php
namespace Limbonia\Traits\Controller;

/**
 * Limbonia ModelController Trait
 *
 * This trait allows an inheriting controller to use an model
 *
 * @author Lonnie Blansett <lonnie@limbonia.tech>
 * @package Limbonia
 */
trait ApiModel
{
  /**
   * Initialize this controller's custom data, if there is any
   *
   * @throws \Limbonia\Exception
   */
  protected function apiModelInit()
  {
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
}