<?php 

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2012 Leo Feyer
 * 
 * @package   drafts 
 * @author    David Molineus 
 * @license   GNU/LGPL 
 * @copyright 2012 David Molineus netzmacht creative 
 */

require TL_ROOT . '/system/config/localconfig.php';

$GLOBALS['TL_CONFIG']['draftModulesOptions'] = array('article', 'news', 'calendar');
$GLOBALS['TL_CONFIG']['draftModules'] = unserialize($GLOBALS['TL_CONFIG']['draftModules']);

$GLOBALS['TL_JAVASCRIPT'][] = 'system/modules/drafts/assets/script.js';

// enable draft mode for articles
if(in_array('article', $GLOBALS['TL_CONFIG']['draftModules']))
{
	$GLOBALS['BE_MOD']['content']['article']['apply']		= array('Netzmacht\Drafts\DataContainer\Content', 'applyDraft');
	$GLOBALS['BE_MOD']['content']['article']['reset']		= array('Netzmacht\Drafts\DataContainer\Content', 'resetDraft');
	$GLOBALS['BE_MOD']['content']['article']['task']		= array('Netzmacht\Drafts\Module\DraftsModule', 'createTask');
	$GLOBALS['BE_MOD']['content']['article']['tables'][] 	= 'tl_drafts';
	$GLOBALS['BE_MOD']['content']['article']['stylesheet'] 	= 'system/modules/drafts/assets/style.css';
}

// enable draft mode for news
if(in_array('news', $GLOBALS['TL_CONFIG']['draftModules']))
{
	$GLOBALS['BE_MOD']['content']['news']['apply']			= array('Netzmacht\Drafts\DataContainer\Content', 'applyDraft');
	$GLOBALS['BE_MOD']['content']['news']['reset']			= array('Netzmacht\Drafts\DataContainer\Content', 'resetDraft');
	$GLOBALS['BE_MOD']['content']['news']['task']			= array('Netzmacht\Drafts\Module\DraftsModule', 'createTask');
	$GLOBALS['BE_MOD']['content']['news']['tables'][] 		= 'tl_drafts';
	$GLOBALS['BE_MOD']['content']['news']['stylesheet'] 	= 'system/modules/drafts/assets/style.css';
}

// enable draft mode for calendar
if(in_array('calendar', $GLOBALS['TL_CONFIG']['draftModules']))
{
	$GLOBALS['BE_MOD']['content']['calendar']['apply']		= array('Netzmacht\Drafts\DataContainer\Content', 'applyDraft');
	$GLOBALS['BE_MOD']['content']['calendar']['reset']		= array('Netzmacht\Drafts\DataContainer\Content', 'resetDraft');
	$GLOBALS['BE_MOD']['content']['calendar']['task']		= array('Netzmacht\Drafts\Module\DraftsModule', 'createTask');
	$GLOBALS['BE_MOD']['content']['calendar']['tables'][] 	= 'tl_drafts';
	$GLOBALS['BE_MOD']['content']['calendar']['stylesheet'] = 'system/modules/drafts/assets/style.css';
}
