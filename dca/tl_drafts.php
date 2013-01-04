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
		'dataContainer'					=> 'Table',
		'enableVersioning'				=> false,
		'ptable'						=> '',
		'ctable'						=> array('tl_content'),
		'dynamicPtable'					=> true,
		'onload_callback'				=> array
		(
			array('Netzmacht\Drafts\DataContainer\Drafts', 'goToPtable')
		),
		
		'sql' => array
		(
			'keys' => array
			(
				'id' => 'primary',
				'pid' => 'index',
				'ptable' => 'index',
				'taskid' => 'index',
			)
		),
	),
	
	'fields' => array
	(
		'id' => array
		(
			'sql'						=> "int(10) unsigned NOT NULL auto_increment"
		),
		'pid' => array
		(
			'sql'						=> "int(10) unsigned NOT NULL default '0'"
		),		
		'ptable' => array
		(
			'sql'						=> "varchar(64) NOT NULL default ''"
		),
		'taskid' => array
		(
			'sql'						=> "int(10) unsigned NOT NULL default '0'"
		),
		'tstamp' => array
		(
			'sql'						=> "int(10) unsigned NOT NULL default '0'"
		),
	),
);
