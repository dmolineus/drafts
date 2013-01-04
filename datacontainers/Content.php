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
use Netzmacht\Utils\DataContainer;


/**
 * Use DraftsDataContainer for tl_content 
 */
class Content extends DraftableDataContainer
{
	
	/**
	 * provide a generateChildRecord callback
	 * Modified tl_content::addCteType
	 * 
	 * @see tl_content::addCteType
	 * @param array row
	 * @return string
	 */
	public function generateChildRecord($arrRow)
	{
		$key = $arrRow['invisible'] ? 'unpublished' : 'published'; 
		$type = $GLOBALS['TL_LANG']['CTE'][$arrRow['type']][0] ?: '&nbsp;';
		$class = 'limit_height';
		$label = '';

		// Add the type of accordion element
		if ($arrRow['type'] == 'accordion' && $arrRow['mooType'] != 'mooSingle')
		{
			$class = '';
			$type .= ' [' . $GLOBALS['TL_LANG']['tl_content'][$arrRow['mooType']][0] . ']';
		}

		// Add the ID of the aliased element
		if ($arrRow['type'] == 'alias')
		{
			$type .= ' ID ' . $arrRow['cteAlias'];
		}
		// Add the protection status
		if ($arrRow['protected'])
		{
			$type .= ' (' . $GLOBALS['TL_LANG']['MSC']['protected'] . ')';
		}
		elseif ($arrRow['guests'])
		{
			$type .= ' (' . $GLOBALS['TL_LANG']['MSC']['guests'] . ')';
		}

		// Limit the element's height
		if (!$GLOBALS['TL_CONFIG']['doNotCollapse'])
		{
			$class .=  ' h64';
		}
		
		// Generate labels
		$arrState = unserialize($arrRow['draftState']);
		$label = '';
		
		if(is_array($arrState) && !empty($arrState))
		{
			asort($arrState);
			foreach ($arrState as $strState) 
			{
				$label .= sprintf('<div class="draft_label %s">%s</div>', $strState, $GLOBALS['TL_LANG'][$this->strTable]['draftState_' . $strState]);			
			}
		}
		
		return sprintf
		(
			'<div class="cte_type %s">%s %s</div><div class="%s">%s</div>' . "\n",
			$key, $type, $label, trim($class), $this->getContentElement($arrRow['id'])
		);
	}

}