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

// enable draft mode for enabled modules
foreach ($GLOBALS['TL_CONFIG']['draftModules'] as $strModule) 
{
	$GLOBALS['BE_MOD']['content'][$strModule]['apply']		= array('Netzmacht\Drafts\DataContainer\Content', 'applyDraft');
	$GLOBALS['BE_MOD']['content'][$strModule]['reset']		= array('Netzmacht\Drafts\DataContainer\Content', 'resetDraft');
	$GLOBALS['BE_MOD']['content'][$strModule]['task']		= array('Netzmacht\Drafts\Module\ModuleDrafts', 'createTask');
	$GLOBALS['BE_MOD']['content'][$strModule]['tables'][] 	= 'tl_drafts';
	$GLOBALS['BE_MOD']['content'][$strModule]['stylesheet'] = 'system/modules/drafts/assets/style.css';
}

if(!empty($GLOBALS['TL_CONFIG']['draftModules']))
{
	$GLOBALS['TL_HOOKS']['loadDataContainer'][] = array('Netzmacht\Drafts\DataContainer\Content', 'onLoadDataContainer');
}

// font awesome
$GLOBALS['ICON_REPLACER']['buttons']['styleIcons'][] = array('edit', 'header_draft');
$GLOBALS['ICON_REPLACER']['buttons']['styleIcons'][] = array('check', 'header_live');
$GLOBALS['ICON_REPLACER']['buttons']['styleIcons'][] = array('tasks', 'header_task');

$GLOBALS['ICON_REPLACER']['context']['imageIcons'][] = array('check', 'publish.png');
$GLOBALS['ICON_REPLACER']['context']['imageIcons'][] = array('remove-sign', 'reset.png');
$GLOBALS['ICON_REPLACER']['context']['imageIcons'][] = array('list-alt', 'diff.gif');