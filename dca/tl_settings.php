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
 
namespace Netzmacht\Drafts\DataContainer;

$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] .= ';{drafts_legend},draftModules,draftModeAsDefault,draftUseTaskModule,draftTaskDefaultDeadline';

$GLOBALS['TL_DCA']['tl_settings']['fields']['draftModules'] = array
(
	'label'			=> &$GLOBALS['TL_LANG']['tl_settings']['draftModules'],
	'inputType'		=> 'checkbox',
	'options'		=> &$GLOBALS['TL_CONFIG']['draftModulesOptions'],
	'reference'		=> &$GLOBALS['TL_LANG']['MOD'],
	
	'eval'			=> array('multiple' => true),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['draftModeAsDefault'] = array
(
	'label'			=> &$GLOBALS['TL_LANG']['tl_settings']['draftModeAsDefault'],
	'inputType'		=> 'checkbox',
	'eval'			=> array('tl_class' => 'w50 clr'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['draftUseTaskModule'] = array
(
	'label'			=> &$GLOBALS['TL_LANG']['tl_settings']['draftUseTaskModule'],
	'inputType'		=> 'checkbox',
	'eval'			=> array('tl_class' => 'w50'),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['draftTaskDefaultDeadline'] = array
(
	'label'			=> &$GLOBALS['TL_LANG']['tl_settings']['draftsTaskDefaultDeadline'],
	'inputType'		=> 'text',
	'default' 		=> '1',
	'eval'			=> array('tl_class' => 'w50 clr', 'rgxp' => 'digit', 'maxlength' => 3),
);