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
 
 
$GLOBALS['TL_DCA']['tl_drafts'] = array
(
	'config' => array
	(
		'dataContainer'               => 'Table',
		'enableVersioning'            => true,
		'ptable'                      => '',
		'ctable'                      => array('tl_content'),
		'dynamicPtable'               => true,
		'onload_callback'             => array
		(
			array('tl_drafts', 'goToPtable')
		),
		
		'sql' => array
		(
			'keys' => array
			(
				'id' => 'primary',
				'pid' => 'index',
				'ptable' => 'index'
			)
		),
	),
	
	
	'fields' => array
	(
		'id' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL auto_increment"
		),
		'pid' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL default '0'"
		),
		'taskid' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL default '0'"
		),
		'ptable' => array
		(
			'sql'                     => "varchar(64) NOT NULL default ''"
		),
		'tstamp' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL default '0'"
		),
	),
);

class tl_drafts extends Backend
{
	public function goToPtable($objDc)
	{
		if(Input::get('act') == 'edit' && Input::get('id') != null)
		{
			$this->import('Database');
			$objResult = $this->Database->query('SELECT * FROM tl_drafts WHERE id=' . Input::get('id'));
			
			if($objResult->numRows == 1)
			{
				$this->redirect($this->addToUrl('table=' . $objResult->ptable . '&id=' . $objResult->id));
			}
		}
	}
}
