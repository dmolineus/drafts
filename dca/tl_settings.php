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

$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] .= ';{drafts_legend},draftModules,draftUseTaskModule';
$GLOBALS['TL_DCA']['tl_settings']['palettes']['__selector__'][] = 'draftUseTaskModule';
$GLOBALS['TL_DCA']['tl_settings']['subpalettes']['draftUseTaskModule'] = 'draftTaskDefaultDeadline';

$GLOBALS['TL_DCA']['tl_settings']['fields']['draftModules'] = array
(
	'label'			=> &$GLOBALS['TL_LANG']['tl_settings']['draftModules'],
	'inputType'		=> 'checkbox',
	'options'		=> &$GLOBALS['TL_CONFIG']['draftModulesOptions'],
	'reference'		=> &$GLOBALS['TL_LANG']['MOD'],
	'eval'			=> array('multiple' => true),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['draftUseTaskModule'] = array
(
	'label'			=> &$GLOBALS['TL_LANG']['tl_settings']['draftUseTaskModule'],
	'inputType'		=> 'checkbox',
	'eval'			=> array('tl_class' => 'w50 m12', 'submitOnChange' => true),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['draftTaskDefaultDeadline'] = array
(
	'label'			=> &$GLOBALS['TL_LANG']['tl_settings']['draftsTaskDefaultDeadline'],
	'inputType'		=> 'text',
	'default' 		=> '1',
	'eval'			=> array('tl_class' => 'w50', 'rgxp' => 'digit', 'maxlength' => 3),
);
