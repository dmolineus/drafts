<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2012 Leo Feyer
 * 
 * @package   drafts
 * @author    David Molineus <http://www.netzmacht.de>
 * @license   GNU/LGPL 
 * @copyright Copyright 2012 David Molineus netzmacht creative 
 *  
 **/

namespace Netzmacht\Drafts\Model;


/**
 * DraftableModel provides a common interface which can handle draft features
 * for every model
 * 
 */
class DraftableModel extends VersioningModel
{
	
	/**
	 * collection class
	 * 
	 * @var string
	 */
	protected static $strCollectionClass = 'DraftableCollection';
	
	
	/**
	 * check if table is draftable
	 * 
	 * @param string table name
	 * @param \Database\Result|Model|null
	 * @param bool enable versioning
	 * @throws \Exception if table is not draftable
	 */
	public function __construct($strTable, $objModel=null, $blnVersioning=false)
	{
		$this->loadDataContainer($strTable);
		
		if(!isset($GLOBALS['TL_DCA'][$strTable]['fields']['draftRelated']))
		{
			throw new \Exception('Table "' . $strTable . '" is not draftable.');
		}
		
		parent::__construct($strTable, $objModel, $blnVersioning);
	}
	
	
	/**
	 * get draftRelated by default and do not throw exception if draft related is not set
	 * 
	 * @param string
	 * @return Model|null
	 */
	public function getRelated($strKey = null)
	{
		if($strKey === null)
		{
			$strKey = 'draftRelated';
		}
		
		if($strKey == 'draftRelated' && $this->$strKey === null)
		{
			return null;
		}
		
		return $this->objModel->getRelated($strKey);		
	}
	
	
	/**
	 * check if model has a state
	 * 
	 * @param string 
	 */
	public function hasState($strState)
	{
		switch($strState)
		{
			case 'new':
				return $this->objModel->draftRelated === null;
				break;
			
			case 'sorted':
				return !$this->hasState('new') && ($this->objModel->getRelated('draftRelated')->sorting != $this->objModel->sorting);
				break;
			
			case 'visibility':
				return !$this->hasState('new') && ($this->objModel->getRelated('draftRelated')->invisible != $this->objModel->invisible);
				break;
				
			default:
				$intFlag = $this->getStateFlag($strState);
				break;
		}
		
		if($intFlag == 0)
		{
			return false;
		}
		
		return $this->objModel->draftState & $intFlag;
	}
	
	
	/**
	 * check if current model is draft
	 */
	public function isDraft()
	{
		return $this->ptable == 'tl_drafts';
	}
	
	
	/**
	 * create a new model by cloning a reference and replace
	 * 
	 * @param bool true if new model is a draft
	 * @param bool switch id and draftRelated
	 * @param bool true if forcing a new model 
	 * @param bool true is versioning shall be used
	 */
	public function prepareCopy($blnDraft=false, $blnSwitchIds=true, $blnNew=false, $blnVersioning=true)
	{
		$objNew = clone $this;
		$objNew->setVersioning($blnVersioning);
		$objNew->draftState = 0;
		$objNew->tstamp = time();
				
		// id and draft related
		if($blnSwitchIds)
		{
			$objNew->id = $blnNew ? null : $this->draftRelated;
			$objNew->draftRelated = $this->id;
		}
		
		if($this->isDraft())
		{
			$objDraft = \DraftsModel::findByPK($this->pid);
		}
		else 
		{
			$objDraft = \DraftsModel::findOneByPidAndTable($this->pid, $this->ptable);
		}
		
		// pid and ptable
		if($blnDraft)
		{
			$objNew->pid = $objDraft->id;
			$objNew->ptable = 'tl_drafts';
		}
		else
		{
			$objNew->pid = $objDraft->pid;
			$objNew->ptable = $objDraft->ptable;
		}

		return $objNew;
	}
	
	
	/**
	 * semove state from model
	 * 
	 * @param string
	 */
	public function removeState($strState)
	{
		$this->objModel->draftState &= ~$this->getStateFlag($strState);
	}


	/**
	 * set state of model
	 * 
	 * @param string 
	 */
	public function setState($strState)
	{
		if(!$this->hasState($strState))
		{
			$this->objModel->draftState |= $this->getStateFlag($strState);
		}
	}
	
	
	/**
	 * get state flag 
	 * 
	 * @param string state
	 * @return int
	 */
	protected function getStateFlag($strState)
	{
		switch ($strState) 
		{
			case 'modified':
				$intFlag = 1;
				break;
			
			case 'delete':
				$intFlag = 2;
				break;
				
			default:
				$intFlag = 0;
				break;
		}
		
		return $intFlag;
	}

}
