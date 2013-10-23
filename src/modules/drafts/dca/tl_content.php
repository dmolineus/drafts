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

/**
 * Config
 */
$GLOBALS['TL_DCA']['tl_content']['config']['dataContainer'] = 'Draftable';
$GLOBALS['TL_DCA']['tl_content']['config']['sql']['keys']['draftRelated'] 	= 'index';
\Environment::getInstance();
\Input::getInstance();
$GLOBALS['TL_DCA']['tl_content']['dcatools']['controller'] = 'Drafts\Controller\Content';


/**
 *
 */
//$GLOBALS['TL_DCA']['tl_content']['fields']['cteAlias']['options_callback'] = array('Drafts\DataContainer\Content', 'getAlias');

$GLOBALS['TL_DCA']['tl_content']['fields']['draftRelated'] = array
(
	'foreignKey'				=> 'tl_content.id',
	'relation'                	=> array('type'=>'hasOne', 'load'=>'lazy'),
	'default'					=> 0,
	'eval'						=> array('unique' => true),
	'sql' 						=> "int(10) unsigned NOT NULL default '0'",
);

$GLOBALS['TL_DCA']['tl_content']['fields']['draftState'] = array
(
	'sql' 						=> "tinyint(1) NOT NULL default '0'",
);
