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

$GLOBALS['TL_CONFIG']['draftModules'] = unserialize($GLOBALS['TL_CONFIG']['draftModules']);

if(in_array(Input::get('do'), $GLOBALS['TL_CONFIG']['draftModules']))
{
	// callbacks
	$GLOBALS['TL_DCA']['tl_content']['config']['onload_callback'][] 			= array('Netzmacht\Drafts\DataContainer\Content', 'initialize');
	$GLOBALS['TL_DCA']['tl_content']['config']['onload_callback'][] 			= array('Netzmacht\Drafts\DataContainer\Content', 'checkPermission');
	$GLOBALS['TL_DCA']['tl_content']['config']['ondelete_callback'][] 			= array('Netzmacht\Drafts\DataContainer\Content', 'onDeleteCallback');
	$GLOBALS['TL_DCA']['tl_content']['config']['oncreate_callback'][] 			= array('Netzmacht\Drafts\DataContainer\Content', 'onCreateCallback');
	$GLOBALS['TL_DCA']['tl_content']['config']['oncut_callback'][] 				= array('Netzmacht\Drafts\DataContainer\Content', 'onCutCallback');
	$GLOBALS['TL_DCA']['tl_content']['config']['onrestore_callback'][] 			= array('Netzmacht\Drafts\DataContainer\Content', 'onRestoreCallback');
	$GLOBALS['TL_DCA']['tl_content']['config']['onsubmit_callback'][] 			= array('Netzmacht\Drafts\DataContainer\Content', 'onSubmitCallback');
	
	$GLOBALS['TL_DCA']['tl_content']['fields']['invisible']['save_callback'][] 	= array('Netzmacht\Drafts\DataContainer\Content', 'onToggleVisibility');
	
	if(Input::get('draft') == '1')
	{
		// generate callback
		$GLOBALS['TL_DCA']['tl_content']['list']['sorting']['child_record_callback'] = array('Netzmacht\Drafts\DataContainer\Content', 'generateChildRecord');
		$GLOBALS['TL_DCA']['tl_content']['list']['sorting']['header_callback'] = array('Netzmacht\Drafts\DataContainer\Content', 'generateParentHeader');
		$GLOBALS['TL_DCA']['tl_content']['edit']['buttons_callback'][] = array('Netzmacht\Drafts\DataContainer\Content', 'generateSubmitButtons');
		
		// set ptable dynamically and store old ptable
		$GLOBALS['TL_DCA']['tl_content']['config']['dtable'] = $GLOBALS['TL_DCA']['tl_content']['config']['ptable'];
		$GLOBALS['TL_DCA']['tl_content']['config']['ptable'] = 'tl_drafts';
		
		// remove default permission callback for draft mode
		$intIndex = array_search(array('tl_content', 'checkPermission'), $GLOBALS['TL_DCA']['tl_content']['config']['onload_callback']);
	
		if($intIndex >= 0)
		{
			unset($GLOBALS['TL_DCA']['tl_content']['config']['onload_callback'][0]);
		}
		
		// protect applying changes
		$GLOBALS['TL_DCA']['tl_content']['config']['permission_rules'] 	= array('generic:key=[,reset,apply]', 'hasAccess:key=apply:alexf=published:ptable');
	}
	else
	{
		$GLOBALS['TL_DCA']['tl_content']['config']['permission_rules'] 	= array('hasAccessOnPublished:act=[edit,delete,cut,select,deleteAll,editAll]:ptable:alexf=published');
	}
	
	
	// insert global live mode/draft mode global operations
	array_insert($GLOBALS['TL_DCA']['tl_content']['list']['global_operations'], 0, array
	(
		'live' => array
		(
			'label' 			=> &$GLOBALS['TL_LANG']['tl_content']['livemode'],
			'href' 				=> 'draft=0',
			'class'				=> 'header_live',
			'button_callback' 	=> array('Netzmacht\Drafts\DataContainer\Content', 'generateGlobalButtonLive'),
			'button_rules' 		=> array('validate:get:var=draft:is=1', 'switchMode', 'generate'),
		),
		
		'draft' => array
		(
			'label' 			=> &$GLOBALS['TL_LANG']['tl_content']['draftmode'],
			'href' 				=> 'draft=1',
			'class'				=> 'header_draft',
			'button_callback' 	=> array('Netzmacht\Drafts\DataContainer\Content', 'generateGlobalButtonDraft'),
			'button_rules' 		=> array('validate:get:var=draft:not=1', 'switchMode:draft', 'generate'),
		),
		
		'task' => array
		(
			'label' 			=> &$GLOBALS['TL_LANG']['tl_content']['task'],
			'href' 				=> 'contao/main.php?do=' . Input::get('do') . '&key=task',
			'class'				=> 'header_task',
			'button_callback' 	=> array('Netzmacht\Drafts\DataContainer\Content', 'generateGlobalButtonTask'),
			'button_rules' 		=> array('hasAccess:module=tasks', 'taskButton', 'generate'),
		)
	));
	
	
	// insert draft operation buttons
	if(\Input::get('draft') == '1')
	{
		array_insert($GLOBALS['TL_DCA']['tl_content']['list']['operations'], 1, array
		( 
			'draftDiff' => array
			(
				'label' 			=> &$GLOBALS['TL_LANG']['tl_content']['draftDiff'],
				'href' 				=> 'system/modules/drafts/diff.php',
				'icon'				=> 'diff.gif',
				'attributes'		=> 'onclick="Backend.openModalIframe({\'width\':860,\'title\':\'Unterschiede anzeigen\',\'url\':this.href});return false"',
				'button_callback' 	=> array('Netzmacht\Drafts\DataContainer\Content', 'generateButtonDraftDiff'),
				'button_rules' 		=> array('draftState:modified', 'generate:plain:table:id'),
			),
			
			'draftReset' => array
			(
				'label' 			=> &$GLOBALS['TL_LANG']['tl_content']['draftReset'],
				'href' 				=> 'key=reset',
				'icon'				=> 'system/modules/drafts/assets/reset.png',
				'button_callback' 	=> array('Netzmacht\Drafts\DataContainer\Content', 'generateButtonDraftReset'),
				'button_rules' 		=> array('draftState', 'generate'),
			),
			
			'draftApply' => array
			(
				'label' 			=> &$GLOBALS['TL_LANG']['tl_content']['draftApply'],
				'href' 				=> 'key=apply',
				'icon'				=> 'system/modules/drafts/assets/publish.png',
				'button_callback' 	=> array('Netzmacht\Drafts\DataContainer\Content', 'generateButtonDraftApply'),
				'button_rules' 		=> array('hasAccess:alexf=published:ptable', 'draftState', 'generate'),
			),
		));
		
		$GLOBALS['TL_DCA']['tl_content']['list']['operations']['toggle']['button_callback'] = array('Netzmacht\Drafts\DataContainer\Content', 'generateButtonToggle');
		$GLOBALS['TL_DCA']['tl_content']['list']['operations']['toggle']['button_rules']	= array('toggleIcon:field=invisible:inverted', 'generate');
	}
	else
	{
		// global operations
		$GLOBALS['TL_DCA']['tl_content']['list']['global_operations']['all']['button_callback'] = array('Netzmacht\Drafts\DataContainer\Content', 'generateGlobalButtonAll');
		$GLOBALS['TL_DCA']['tl_content']['list']['global_operations']['all']['button_rules']	= array('hasAccessOnPublished:ptable:alexf=published', 'generate');
	
		// operation callbacks
		$GLOBALS['TL_DCA']['tl_content']['list']['operations']['edit']['button_callback']	= array('Netzmacht\Drafts\DataContainer\Content', 'generateButtonEdit');
		$GLOBALS['TL_DCA']['tl_content']['list']['operations']['edit']['button_rules']		= array('hasAccessOnPublished', 'generate');
		$GLOBALS['TL_DCA']['tl_content']['list']['operations']['copy']['button_callback'] 	= array('Netzmacht\Drafts\DataContainer\Content', 'generateButtonCopy');
		$GLOBALS['TL_DCA']['tl_content']['list']['operations']['copy']['button_rules']		= array('hasAccessOnPublished', 'generate');
		$GLOBALS['TL_DCA']['tl_content']['list']['operations']['delete']['button_callback'] = array('Netzmacht\Drafts\DataContainer\Content', 'generateButtonDelete');
		$GLOBALS['TL_DCA']['tl_content']['list']['operations']['delete']['button_rules']	= array('hasAccessOnPublished', 'generate');
		$GLOBALS['TL_DCA']['tl_content']['list']['operations']['toggle']['button_callback'] = array('Netzmacht\Drafts\DataContainer\Content', 'generateButtonToggle');
		$GLOBALS['TL_DCA']['tl_content']['list']['operations']['toggle']['button_rules']	= array('hasAccessOnPublished:icon=invisible.gif', 'toggleIcon:field=invisible:inverted', 'generate');
		$GLOBALS['TL_DCA']['tl_content']['list']['operations']['cut']['button_callback'] 	= array('Netzmacht\Drafts\DataContainer\Content', 'generateButtonCut');
		$GLOBALS['TL_DCA']['tl_content']['list']['operations']['cut']['button_rules']		= array('hasAccessOnPublished', 'generate');
	}
}

// fields
$GLOBALS['TL_DCA']['tl_content']['fields']['draftid'] = array
(
	'sql' 						=> 'int(10) unsigned NULL',
	'foreignKey'				=> 'tl_content.id',
	'relation'                	=> array('type'=>'hasOne', 'load'=>'lazy'),
);

$GLOBALS['TL_DCA']['tl_content']['fields']['draftState'] = array
(
	'sql' 						=> "varchar(255) NOT NULL default ''",
);
