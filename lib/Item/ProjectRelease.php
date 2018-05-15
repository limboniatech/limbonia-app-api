<?php
namespace Limbonia\Item;

/**
 * Limbonia Project Release Item Class
 *
 * Item based wrapper around the ProjectRelease table
 *
 * @author Lonnie Blansett <lonnie@limbonia.tech>
 * @package Limbonia
 */
class ProjectRelease extends \Limbonia\Item
{
  /**
   * List of columns that shouldn't be updated after the data has been created
   *
   * @var array
   */
  protected $aNoUpdate = ['TicketID'];

  /**
   * Loop through the specified array looking for keys that match column names.  For each match
   * set that column to the value for that key in the array then unset that value in the array.
   * After each matching key has been used return the remainder of the array.
   *
   * @param array $hItem
   * @return array
   */
  public function setAll(array $hItem = [])
  {
    $hExtra = parent::setAll($hItem);

    foreach (array_keys($hExtra) as $sName)
    {
      if (strtolower($sName) == 'version')
      {
        $this->__set('version', $hExtra[$sName]);
        unset($hExtra[$sName]);
        break;
      }
    }

    return $hExtra;
  }

  /**
   * Get the specified data
   *
   * @param string $sName
   * @return mixed
   */
  public function __get($sName)
  {
    $sLowerName = strtolower($sName);

    if ($sLowerName == 'version')
    {
      return "$this->major.$this->minor.$this->patch";
    }

    if ($sLowerName == 'released')
    {
      return $this->ticket->status === 'closed';
    }

    return parent::__get($sName);
  }

  /**
   * Sets the specified values if possible
   *
   * @param string $sName
   * @param mixed $xValue
   */
  public function __set($sName, $xValue)
  {
    $sLowerName = strtolower($sName);

    if ($sLowerName == 'version')
    {
      $aVersion = array_map('trim', explode('.', $xValue));
      $iMajor = isset($aVersion[0]) ? (integer)$aVersion[0] : 0;
      $iMinor = isset($aVersion[1]) ? (integer)$aVersion[1] : 0;
      $iPatch = isset($aVersion[2]) ? (integer)$aVersion[2] : 0;

      parent::__set('major', $iMajor);
      parent::__set('minor', $iMinor);
      parent::__set('patch', $iPatch);
    }
    else
    {
      parent::__set($sName, $xValue);
    }
  }

  /**
   * Determine if the specified value is set (exists) or not...
   *
   * @param string $sName
   * @return boolean
   */
  public function __isset($sName)
  {
    if (in_array(strtolower($sName), ['version', 'released']))
    {
      return true;
    }

    return parent::__isset($sName);
  }

  /**
   * Created a row for this object's data in the database
   *
   * @return integer The ID of the row created on success or false on failure
   */
  protected function create()
  {
    $hTicket =
    [
      'Status' => 'pending',
      'Priority' => 'normal',
      'Type' => 'system',
      'Subject' => "Project release: {$this->project->name}  {$this->version}"
    ];

    $oTicket = $this->oController->itemFromArray('Ticket', $hTicket);
    $this->ticketId = $oTicket->save();

    try
    {
      return parent::create();
    }
    catch (\Exception $e)
    {
      //since the release wasn't create successfully remove it's ticket
      $oTicket->delete();
      throw $e;
    }
  }

  /**
   * Update this object's data in the data base with current data
   *
   * @return integer The ID of this object on success or false on failure
   */
  protected function update()
  {
    $xSuccess = parent::update();

    try
    {
      $this->ticket->subject = "Project release: {$this->project->name}  {$this->version}";
      $this->ticket->save();
    }
    catch (\Exception $e) {}

    return $xSuccess;
  }

  /**
   * Delete the row representing this object from the database
   *
   * @return boolean
   */
  public function delete()
  {
    $oTicket = $this->ticket;
    $bSuccess = parent::delete();
    $oTicket->delete();
    return $bSuccess;
  }

  /**
   * Return a list of tickets associated with this release depending on the specified type
   *
   * @param string $sType (optional)
   * @return array|\Limbonia\ItemList
   */
  public function getTicketList($sType = '')
  {
    $sLowerType = strtolower(trim($sType));

    if ($sLowerType == 'complete')
    {
      $sSQL = "SELECT DISTINCT Ticket.* FROM Ticket WHERE ProjectID = $this->projectId AND ReleaseID = $this->id AND (DevStatus = 'complete' OR Status = 'closed')";
      return $this->oController->itemList('Ticket', $sSQL);
    }

    $hCriteria =
    [
      'ProjectID' => $this->projectId,
      'ReleaseID' => $this->id
    ];

    if ($sLowerType == 'incomplete')
    {
      $hCriteria['DevStatus'] = "!=:complete";
      $hCriteria['Status'] = '!=:closed';
      $oTicket = $this->oController->itemFactory('Ticket');
      $hIncomplete = [];

      foreach ($oTicket->priorityList as $sPriority)
      {
        $hCriteria['Priority'] = $sPriority;
        $hIncomplete[$sPriority] = $this->oController->itemSearch('Ticket', $hCriteria);
      }

      return $hIncomplete;
    }

    return $this->oController->itemSearch('Ticket', $hCriteria);
  }
}