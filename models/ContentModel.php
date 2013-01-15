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
		$blnPreview = TL_MODE == 'FE' && \Input::cookie('DRAFT_MODE') == '1';
		$t = static::$strTable;
		
		if(!isset($arrOptions['column']))
		{
			$arrOptions['column'] = array();
		}
		elseif(!is_array($arrOptions['column']))
		{
			// try to find related one
			// useful for ContentAlias for example
			if($blnPreview && $arrOptions['column'] == 'id')
			{
				$arrNew = $arrOptions;
				$arrNew['column'] = 'draftRelated';
				
				$objReturn = parent::find($arrNew);
				
				if($objReturn !== null)
				{
					return $objReturn;
				}
			}
			
			// load dca extrator so data container is loaded, can not use loadDataContainer is static context
			$objDca = new \DcaExtractor($t);
			$strKey = $arrOptions['column'];
			$arrKeys = $GLOBALS['TL_DCA'][$t]['config']['sql']['keys'];

			// do not add draftState if column is unique			
			if((isset($arrKeys[$strKey]) && ($arrKeys[$strKey] == 'unique' || $arrKeys[$strKey] == 'primary')) || $GLOBALS['TL_DCA'][$t]['fields'][$strKey]['eval']['unique'])
			{
				return parent::find($arrOptions);				
			}
			
			$arrOptions['column'] = array($arrOptions['column']);
		}
			
			
		// get all draft elements
		if($blnPreview)
		{
			$arrOptions['column'][] = "(($t.draftState = 0 AND $t.draftRelated IS NULL) OR $t.draftState > 0)";			
		}
		
		// limit to live elements by default
		else 
		{
			if(!is_array($arrOptions['column']))
			{
				$arrOptions['column'] = array($arrOptions['column']);
			}
			$arrOptions['column'][] = "$t.draftState = 0";
		}
		
		return parent::find($arrOptions);
	}
	
}