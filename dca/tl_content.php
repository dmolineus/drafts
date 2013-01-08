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

$GLOBALS['TL_DCA']['tl_content']['config']['sql']['keys']['draftRelated'] = 'unique';

// fields
$GLOBALS['TL_DCA']['tl_content']['fields']['draftRelated'] = array
(
	'sql' 						=> "int(10) unsigned NULL",
	'foreignKey'				=> 'tl_content.id',
	'relation'                	=> array('type'=>'hasOne', 'load'=>'lazy'),
	'eval'						=> array('unique' => true),
);

$GLOBALS['TL_DCA']['tl_content']['fields']['draftState'] = array
(
	'sql' 						=> "tinyint(1) NOT NULL default '0'",
);
