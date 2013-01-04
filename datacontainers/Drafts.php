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
use Backend, Input;

/**
 * DataContainer class for tl_drafts
 */
class Drafts extends Backend
{
	
	/**
	 * we redirect edit access to tl_drafts to it related element
	 * 
	 * @param DC_Table
	 */
	public function goToPtable($objDc)
	{
		if(Input::get('act') == 'edit' && Input::get('id') != null)
		{
			$this->import('Database');
			$objResult = $this->Database->query('SELECT * FROM tl_drafts WHERE id=' . Input::get('id'));
			
			if($objResult->numRows == 1)
			{
				$this->redirect($this->addToUrl('table=' . $objResult->ptable . '&draft=0&id=' . $objResult->pid));
			}
		}
	}
	
}
