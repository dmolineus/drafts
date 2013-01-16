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
		if($strKey == 'ptable' && $strValue == '' && $this->getTable() == 'tl_content')
		{
			return 'tl_article';
		}
		
		return $strValue;
	}
	
	
	/**
	 * make sure that relation will be unset by deleting a draftable model
	 * 
	 * @return int
	 */
	public function delete()
	{
		$intAffectedRows = parent::delete();
		
		if($intAffectedRows > 0 && $this->hasRelated())
		{
			// do not use getRelated because it could be cached, @see #5248
			$objRelated = new static($this->getTable());
			$objRelated->id = $this->draftRelated;
			$objRelated->draftRelated = 0;			
			$objRelated->save();
		}
		
		return $intAffectedRows;
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
		
		if($strKey == 'draftRelated' && $this->$strKey == 0)
		{
			return null;
		}
		
		return parent::getRelated($strKey);		
	}
	
	
	/**
	 * check if element has a draft
	 */
	public function hasRelated()
	{
		return $this->draftRelated > 0;
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
				return ($this->objModel->draftRelated == '' || $this->objModel->draftRelated == 0);
				break;
			
			case 'sorted':
				return !$this->hasState('new') && ($this->getRelated()->sorting != $this->objModel->sorting);
				break;
			
			case 'visibility':
				return !$this->hasState('new') && ($this->getRelated()->invisible != $this->objModel->invisible);
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
	 * @param bool switch between related and original
	 * @param bool true if forcing a new model 
	 * @param bool true is versioning shall be used
	 */
	public function prepareCopy($blnSwitch=true, $blnNew=false, $blnVersioning=true)
	{
		$objNew = clone $this;
		$objNew->setVersioning($blnVersioning);
		$objNew->tstamp = time();
		
		// switch ids and draft state
		if($blnSwitch)
		{
			$objNew->id = $blnNew ? null : $this->draftRelated;
			$objNew->draftRelated = $this->id;
			
			if($this->isDraft())
			{
				$objNew->draftState = 0;
			}
			else
			{
				$objNew->setState('draft');
			}
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
