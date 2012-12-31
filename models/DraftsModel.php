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


class DraftsModel extends Model
{
	
	/**
	 * table name
	 * @var string
	 */
	protected static $strTable = 'tl_drafts';
	
	/**
	 * Find draft by their parent ID and parent table
	 * 
	 * @param integer $intPid         The article ID
	 * @param string  $strParentTable The parent table name
	 * 
	 * @return Model|null 
	 */
	public static function findOneByPidAndTable($intPid, $strParentTable)
	{
		$t = static::$strTable;
		$arrColumns = array("$t.pid=? AND $t.ptable=?");

		return static::findOneBy($arrColumns, array($intPid, $strParentTable));
	}
	
	
	/**
	 * Find draft by their parent ID and parent table
	 * 
	 * @param integer $intPid         The article ID
	 * @param string  $strParentTable The parent table name
	 * 
	 * @return Model|null 
	 */
	public static function findOneByChildIdAndTable($intId, $strChildTable)
	{
		$t = static::$strTable;
		
		$arrColumns = array("$t.id=(SELECT pid FROM {$strChildTable} WHERE id=?) AND ptable=?");
		return static::findOneBy($arrColumns, array($intId, $GLOBALS['TL_DCA'][$strChildTable]['config']['dtable']));
	}
	
}
