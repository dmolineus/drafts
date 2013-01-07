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
	$GLOBALS['TL_DCA']['tl_content']['fields']['invisible']['save_callback'][] 	= array('Netzmacht\Drafts\DataContainer\Content', 'onToggleVisibility');
	
	if(Input::get('draft') == '1')
	{
		// set ptable dynamically and store old ptable
		$GLOBALS['TL_DCA']['tl_content']['config']['dtable'] = $GLOBALS['TL_DCA']['tl_content']['config']['ptable'];
		$GLOBALS['TL_DCA']['tl_content']['config']['ptable'] = 'tl_drafts';
		
		// generate callback
		$GLOBALS['TL_DCA']['tl_content']['list']['sorting']['child_record_callback'] = array('Netzmacht\Drafts\DataContainer\Content', 'generateChildRecord');
		
		// remove default permission callback for draft mode	
		if($GLOBALS['TL_DCA']['tl_content']['config']['onload_callback'][0][1] == 'checkPermission')
		{			
			$GLOBALS['TL_DCA']['tl_content']['config']['permission_rules'] = array('draftPermission:class=' . $GLOBALS['TL_DCA']['tl_content']['config']['onload_callback'][0][0]);
			unset($GLOBALS['TL_DCA']['tl_content']['config']['onload_callback'][0]);
		}

		// insert draft operation buttons
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
		
		$GLOBALS['TL_DCA']['tl_content']['list']['operations']['toggle']['attributes']		= 'onclick="Backend.getScrollOffset();AjaxRequest.toggleVisibility(this,%s);return toggleDraftLabel(this, \'visibility\', \'' . urlencode($GLOBALS['TL_LANG']['tl_content']['draftState_visibility']) . '\')"';
		$GLOBALS['TL_DCA']['tl_content']['list']['operations']['toggle']['button_callback'] = array('Netzmacht\Drafts\DataContainer\Content', 'generateButtonToggle');
		$GLOBALS['TL_DCA']['tl_content']['list']['operations']['toggle']['button_rules']	= array('toggleIcon:field=invisible:inverted', 'generate');
	}

	// check permission for operations in live mode
	else
	{
		// permission rules
		$GLOBALS['TL_DCA']['tl_content']['config']['permission_rules'] = array('draftPermission');
		
		// global operations
		$GLOBALS['TL_DCA']['tl_content']['list']['global_operations']['all']['button_callback'] = array('Netzmacht\Drafts\DataContainer\Content', 'generateGlobalButtonAll');
		$GLOBALS['TL_DCA']['tl_content']['list']['global_operations']['all']['button_rules']	= array('hasAccessOnPublished', 'generate:table:id');
	
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
	'sql' 						=> "varchar(255) NOT NULL default ''",
);
