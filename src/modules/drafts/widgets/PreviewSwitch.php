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
 
namespace Drafts\Widget;
use Input;


/**
 * PreviewSwitch provides a wigdet for switching between draft and live mode in preview switch
 */
class PreviewSwitch extends \SelectMenu
{
	
	/**
	 * initiate values of preview switch
	 */
	public function __construct($arrAttributes=null)
	{
		$arrAttributes['id'] = 'draft';
		$arrAttributes['name'] = 'draft';
		$arrAttributes['label'] = $GLOBALS['MSC']['draftModesLabel']; 
		$arrAttributes['value'] = Input::post('draft') == '' ? Input::cookie('DRAFT_MODE') : Input::post('draft');
		$arrAttributes['forAttribute'] = true;
		parent::__construct($arrAttributes);		
		
		$this->arrOptions[0]['value'] = '0';
		$this->arrOptions[0]['label'] = $GLOBALS['MSC']['draftModes'][0];
		$this->arrOptions[1]['value'] = '1';
		$this->arrOptions[1]['label'] = $GLOBALS['MSC']['draftModes'][1];
		
		$time = time();
		
		if(Input::post('draft') == '1')
		{
			$this->setCookie('DRAFT_MODE', 1, ($time + $GLOBALS['TL_CONFIG']['sessionTimeout']));			
		}
		else 
		{
			$this->setCookie('DRAFT_MODE', 0, ($time - 86400));			
		} 
	}
	
}
