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


/**
 * Use DraftsDataContainer for tl_content 
 */
class Content extends DraftableDataContainer
{
	
	/**
	 * @var array 
	 */
	protected $arrContentElements = null;
	
	
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


	/**
	 * generate preview element of content element
	 * Thanks to rms for this great idea and solution
	 * HOOK: getContentElement
	 * 
	 * @see ReleaseManagementSystem rms
	 * @param Database\Result
	 * @param string
	 * @return string
	 */
	public function previewContentElement($objElement, $strBuffer)
	{
		// only render on preview
		if(\Input::get('draft') != '1' && \Input::cookie('DRAFT_MODE') != '1')
		{
			return $strBuffer;
		}
		
		$strBuffer = '';
		
		// get all draft elements from database
		if($this->arrContentElements === null)
		{
			$this->arrContentElements = array();
		
			$this->import('Database');
			$this->objDraft = \DraftsModel::findOneByPidAndTable($objElement->pid, $objElement->ptable);
			
			$objResult = $this->Database->prepare('SELECT * FROM ' . $this->strTable . ' WHERE pid=? AND ptable=? ORDER BY sorting')
										->execute($this->objDraft->id, 'tl_drafts');
			
			while($objResult->next())
			{
				$this->arrContentElements[$objResult->id]['model'] = new \ContentModel($objResult);
				$this->arrContentElements[$objResult->id]['generated'] = false;
			}
		}
		
		// current element is first, everything
		$objFirst = reset($this->arrContentElements);

		if($objFirst->draftRelated == $objElement->id)
		{
			$strBuffer .= $this->generateContentElement($objElement);
			return $strBuffer;
		}
		
		// generate all content elements until current is found, required to display new elements
		while(list($intId, $arrElement) = each($this->arrContentElements))
		{
			if(!$arrElement['generated'])
			{
				$strBuffer .= $this->generateContentElement($arrElement['model']);
				unset($this->arrContentElements[$intId]);
			}
			if($intId == $objElement->draftRelated)
			{
				break;
			}
		}
		
		return $strBuffer;
	}


	/**
	 * generate a single content element
	 * 
	 * @param Model
	 * @return string
	 */
	protected function generateContentElement($objModel)
	{
		$this->arrContentElements[$objModel->id]['generated'] = true;
		$arrState = unserialize($objModel->draftState);
		
		// element is marked as deleted, so do not generate
		if(is_array($arrState) && in_array('delete', $arrState))
		{
			return;			
		}

		$strClass = $this->findContentElement($objModel->type);
		$objElement = new $strClass($objModel);
				
	    return $objElement->generate();
	}
}