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
 
class ContentModel extends Contao\ContentModel
{
	
	/**
	 * filter draft elements
	 * 
	 * @param array
	 */
	protected static function find(array $arrOptions)
	{		
		$t = static::$strTable;
		
		// do match if single column is passed
		if(isset($arrOptions['column']) && is_string($arrOptions['column']))
		{
			return parent::find($arrOptions);
		}
		elseif(!is_array($arrOptions['column']))
		{
			$arrOptions['column'] = array();
		}
		
		// get all draft elements
		if(TL_MODE == 'FE' && \Input::cookie('DRAFT_MODE') == '1')
		{
			$arrOptions['column'][] = "(($t.draftState = 0 AND $t.draftRelated IS NULL) OR $t.draftState > 0)";			
		}
		
		// filter all draft elements by default
		else 
		{
			$arrOptions['column'][] = "$t.draftState = 0";
		}
		
		return parent::find($arrOptions);
	}
	
}