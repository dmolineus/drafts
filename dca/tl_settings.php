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

$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] .= ';{drafts_legend},draftModules,draftsUseTaskModule';

$GLOBALS['TL_DCA']['tl_settings']['fields']['draftModules'] = array
(
	'label'			=> &$GLOBALS['TL_LANG']['tl_settings']['draftModules'],
	'inputType'		=> 'checkbox',
	'options'		=> &$GLOBALS['TL_CONFIG']['draftModulesOptions'],
	'reference'		=> &$GLOBALS['TL_LANG']['MOD'],
	
	'eval'			=> array('multiple' => true),
);

$GLOBALS['TL_DCA']['tl_settings']['fields']['draftsUseTaskModule'] = array
(
	'label'			=> &$GLOBALS['TL_LANG']['tl_settings']['draftsUseTaskModule'],
	'inputType'		=> 'checkbox',
	'eval'			=> array('tl_class' => 'w50 clr'),
);
