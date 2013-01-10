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
use Controller, Model, Model\Collection, Database\Result;

/**
 * Versioning Model allows to save model and use the versioning of contao
 * 
 */
class VersioningModel extends Controller
{
	
	/**
	 * enable versioning
	 * 
	 * @var bool
	 */
	protected $blnVersioning;
	
	/**
	 * model reference
	 * @var \Model
	 */
	protected $objModel;
	
	/**
	 * class of collection class
	 * 
	 * @var string
	 */
	protected static $strCollectionClass = 'VersioningCollection';
	
	
	/**
	 * create a new model, possible calls
	 * $obj = new VersioningModel('tl_content');
	 * $obj = new VersioningModel($objModel);
	 * $obj = new VersioningModel($objResult, true, 'tl_content');
	 * 
	 * @param \Model|Result|string
	 * @param bool
	 * @param string
	 */
	public function __construct($objModel, $blnVersioning=true, $strTable=null)
	{
		parent::__construct();
		
		// create empty model
		if(is_string($objModel))
		{
			$strClass = $this->getModelClassFromTable($objModel);
			$objModel = new $strClass();
		}
		
		elseif($objModel instanceof Result)
		{
			$strClass = $this->getModelClassFromTable($strTable);
			$objModel = new $strClass($objModel);
		}
		
		$this->blnVersioning = $blnVersioning;
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
		return call_user_func_array(array($this->objModel, $strMethod), $arrArguments);
	}
	
	
	/**
	 * create a static call
	 * First param has to be the table name, so it's possible to decide which model is used
	 * 
	 * VersioningModel::findByPK('tl_user', 1);
	 * 
	 * @param string
	 * @return VersioningModel|VersioningCollection 
	 */
	public static function __callStatic($strName, $arrArguments)
	{
		$strTable = array_shift($arrArguments);
		$strClass = static::getModelClassFromTable($strTable);
		$objModel = call_user_func_array(array($strClass, $strName), $arrArguments);
		
		if($objModel === null)
		{
			return null;
		}
		elseif($objModel instanceof Collection)
		{
			return new self::$strCollectionClass($objModel);			
		}
		else 
		{
			return new static($objModel);
		}
	}
	
	
	/**
	 * clone the model
	 */
	public function __clone()
	{
		$this->objModel = clone $this->objModel;
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
	 * get related also create a versioning model
	 * 
	 * @param string
	 * @return VersioiningModel|null
	 */
	public function getRelated($strKey)
	{
		$objReturn = $this->objModel->getRelated($strKey);
		
		if($objReturn === null)
		{
			return null;
		}
		
		return new static($objReturn);
	}
	
	
	/**
	 * save model and create model
	 * 
	 * @param bool force to inset
	 * @return VersioningModel
	 */
	public function save($blnForceInsert=false, $blnIgnoreVersioning=false)
	{
		$strTable = $this->objModel->getTable();
		$strPk = $this->objModel->getPk();
		$blnVersioning = $this->blnVersioning && !$blnIgnoreVersioning && $GLOBALS['TL_DCA'][$strTable]['config']['enableVersioning'];

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
	
	
	/**
	 * activate or deactive versioning
	 * 
	 * @paranm bool
	 */
	public function setVersioning($blnEnable)
	{
		$this->blnVersioning = (bool) $blnEnable;
	}
	
}
