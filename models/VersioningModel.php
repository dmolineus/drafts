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
use Controller;

/**
 * Versioning Model allows to save model and use the versioning of contao
 * 
 */
class VersioningModel extends Controller
{
	
	/**
	 * model reference
	 * @var \Model
	 */
	protected $objModel;
	
	
	/**
	 * set model
	 * 
	 * @param \Model
	 */
	public function __construct($objModel)
	{
		parent::__construct();
		
		$this->objModel = $objModel;
		$this->import('Database');
	}
	
	
	/**
	 * call models method
	 * 
	 * @param string method name
	 * @param array arguments
	 * @return mixed
	 */
	public function __call($strMethod, $arrArguments)
	{
		return call_user_func_array(array($this->objModel, $strMedhod), $arrArguments);
	}
	
	
	/**
	 * get model data
	 * 
	 * @param string key
	 * @return mixed
	 */
	public function __get($strKey)
	{
		if(parent::__get($strKey) !== null)
		{
			return parent::__get($strKey);
		}
		
		return $this->objModel->$strKey;
	}
	
	
	/**
	 * clone the model
	 */
	public function __clone()
	{
		$this->objModel = clone $this->objModel;
	}
	
	
	/**
	 * check if model argument isset 
	 * 
	 * @param string var name
	 * @return bool
	 */
	public function __isset($strKey)
	{
		return isset($this->objModel->$strKey);
	}
	
	
	/**
	 * set moodel value
	 * 
	 * @param string var name
	 * @param mixed value
	 */
	public function __set($strKey, $mixedValue)
	{
		$this->objModel->$strKey = $mixedValue;
	}
	
	
	/**
	 * get model
	 * 
	 * @return \Model
	 */
	public function getModel()
	{
		return $this->objModel;
	}
	
	
	/**
	 * save model and create model
	 * 
	 * @param bool force to inset
	 * @return VersioningModel
	 */
	public function save($blnForceInsert=false, $blnIgnoreVersioning=false)
	{
		$blnVersioning = !$blnIgnoreVersioning && $GLOBALS['TL_DCA'][$strTable]['config']['enableVersioning'];
		$strTable = $this->objModel->getTable();
		$strPk = $this->objModel->getPk();
		
		if($blnVersioning && !$blnForceInsert && isset($this->$strPk))
		{
			$this->createInitialVersion($strTable, $this->$strPk);
		}
		
		$this->objModel->save($blnForceInsert);
		
		if($blnVersioning)
		{
			$this->createNewVersion($strTable, $this->$strPk);
		}
		
		return $this;
	}
	
}
