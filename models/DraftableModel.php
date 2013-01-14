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
use Model;

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
	public function __construct($objModel, $blnVersioning=false, $strTable=null)
	{
		if($strTable === null)
		{
			if($objModel instanceof Model)
			{
				$strTable = $objModel->getTable();
			}
			else
			{
				$strTable = $objModel;
			}
		}
		
		$this->loadDataContainer($strTable);
		
		if(!isset($GLOBALS['TL_DCA'][$strTable]['fields']['draftRelated']))
		{
			throw new \Exception('Table "' . $strTable . '" is not draftable.');
		}
		
		parent::__construct($objModel, $blnVersioning, $strTable);
	}
	
	
	/**
	 * make sure that ptable cannot be empty for contaos behavior handling
	 * empty ptables
	 * 
	 * @param string key
	 * @param mixed
	 */
	public function __get($strKey)
	{
		$strValue = parent::__get($strKey);
		
		// and again dynamic ptable backwards compatibility
		if($strKey == 'ptable' && $strValue == '' && $this->objModel->getTable() == 'tl_content')
		{
			return 'tl_article';
		}
		
		return $strValue;
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
		
		return parent::getRelated($strKey);		
	}
	
	
	/**
	 * check if element has a draft
	 */
	public function hasDraft()
	{
		return !$this->isDraft() && $this->draftRelated > 0;
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
		
		return (($this->objModel->draftState & $intFlag) == $intFlag);
	}
	
	
	/**
	 * check if current model is draft
	 */
	public function isDraft()
	{
		return $this->hasState('draft');
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
		
		// pid and ptable
		if($blnDraft)
		{
			$objNew->setState('draft');
		}
		else
		{
			$objNew->removeState('draft');
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
			case 'draft':
				$intFlag = 1;
				break;
				
			case 'modified':
				$intFlag = 2;
				break;
			
			case 'delete':
				$intFlag = 4;
				break;
				
			default:
				$intFlag = 0;
				break;
		}
		
		return $intFlag;
	}

}
