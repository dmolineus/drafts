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
	protected static $arrContentElements = array();
	
	
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
			$type .= ' [' . $GLOBALS['TL_LANG'][$this->strTable][$arrRow['mooType']][0] . ']';
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
	 * initialize the data container
	 * Hook: loadDataContainer
	 * 
	 * @param string
	 * @param bool
	 */
	public function initializeDataContainer($strTable)
	{
		if(!parent::initializeDataContainer($strTable))
		{
			return false;
		}

		$strClass = get_class($this);
		
		if($this->blnDraftMode)
		{
			// generate callback
			$GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['child_record_callback'] = array($strClass, 'generateChildRecord');
			
			$GLOBALS['TL_DCA'][$this->strTable]['list']['operations']['toggle']['attributes']		= 'onclick="Backend.getScrollOffset();AjaxRequest.toggleVisibility(this,%s);return toggleDraftLabel(this, \'visibility\', \'' . urlencode($GLOBALS['TL_LANG'][$this->strTable]['draftState_visibility']) . '\')"';
			$GLOBALS['TL_DCA'][$this->strTable]['list']['operations']['toggle']['button_callback'] 	= array($strClass, 'generateButtonToggle');
			$GLOBALS['TL_DCA'][$this->strTable]['list']['operations']['toggle']['button_rules']		= array('toggleIcon:field=invisible:inverted', 'generate');
		}
	
		// check permission for operations in live mode
		else
		{
			// permission rules
			$GLOBALS['TL_DCA'][$this->strTable]['config']['permission_rules'] = array('draftPermission');
			
			// global operations
			$GLOBALS['TL_DCA'][$this->strTable]['list']['global_operations']['all']['button_callback'] = array($strClass, 'generateGlobalButtonAll');
			$GLOBALS['TL_DCA'][$this->strTable]['list']['global_operations']['all']['button_rules']	= array('hasAccessOnPublished', 'generate:table:id');
		
			// operation callbacks
			$GLOBALS['TL_DCA'][$this->strTable]['list']['operations']['edit']['button_callback']	= array($strClass, 'generateButtonEdit');
			$GLOBALS['TL_DCA'][$this->strTable]['list']['operations']['edit']['button_rules']		= array('hasAccessOnPublished', 'generate');
			$GLOBALS['TL_DCA'][$this->strTable]['list']['operations']['copy']['button_callback'] 	= array($strClass, 'generateButtonCopy');
			$GLOBALS['TL_DCA'][$this->strTable]['list']['operations']['copy']['button_rules']		= array('hasAccessOnPublished', 'generate');
			$GLOBALS['TL_DCA'][$this->strTable]['list']['operations']['delete']['button_callback'] 	= array($strClass, 'generateButtonDelete');
			$GLOBALS['TL_DCA'][$this->strTable]['list']['operations']['delete']['button_rules']		= array('hasAccessOnPublished', 'disableIcon:rule=aliasElement', 'generate');
			$GLOBALS['TL_DCA'][$this->strTable]['list']['operations']['toggle']['button_callback'] 	= array($strClass, 'generateButtonToggle');
			$GLOBALS['TL_DCA'][$this->strTable]['list']['operations']['toggle']['button_rules']		= array('hasAccessOnPublished:icon=invisible.gif', 'toggleIcon:field=invisible:inverted', 'generate');
			$GLOBALS['TL_DCA'][$this->strTable]['list']['operations']['cut']['button_callback'] 	= array($strClass, 'generateButtonCut');
			$GLOBALS['TL_DCA'][$this->strTable]['list']['operations']['cut']['button_rules']		= array('hasAccessOnPublished', 'generate');
		}
		
		return true;
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
		if(\Input::cookie('DRAFT_MODE') != '1' || $objElement->ptable == 'tl_drafts')
		{
			if($objElement->ptable == 'tl_drafts')
			{
				unset(static::$arrContentElements[$objElement->pid][$objElement->id]);
			}
			return $strBuffer;
		}
		
		$objDraft = \DraftsModel::findOneByPidAndTable($objElement->pid, $objElement->ptable);		
		$pid = $objDraft->id;
		
		$strBuffer = '';
		
		// get all draft elements from database
		if(!isset(static::$arrContentElements[$pid]))
		{
			static::$arrContentElements[$pid] = array();
		
			$this->import('Database');
			
			$objResult = $this->Database->prepare('SELECT * FROM ' . $this->strTable . ' WHERE pid=? AND ptable=? ORDER BY sorting')
										->execute($objDraft->id, 'tl_drafts');
			
			while($objResult->next())
			{
				static::$arrContentElements[$pid][$objResult->id]['model'] = new \ContentModel($objResult);
				static::$arrContentElements[$pid][$objResult->id]['generated'] = false;
				static::$arrContentElements[$pid][$objResult->id]['new'] = $this->hasState(static::$arrContentElements[$pid][$objResult->id]['model'], 'new');
			}
		}
			
		$blnBreak = false;
		
		// generate all content elements until current is found, required to display new elements
		foreach(static::$arrContentElements[$pid] as $intId => $arrElement)
		{
			if($blnBreak && !$arrElement['new'])
			{
				break;
			}
			
			// call getContentElement so that getContentElement Hooks are called
			if($arrElement['new'])
			{
				// set new to false so that there won't be recursively calls
				static::$arrContentElements[$pid][$intId]['new'] = false;
				$strBuffer .= $this->getContentElement($arrElement['model']);
			}
			elseif(!$arrElement['generated'])
			{
				$strBuffer .= $this->generateContentElement($arrElement['model']);
				unset(static::$arrContentElements[$pid][$intId]);
			}
			
			if($intId == $objElement->draftRelated)
			{
				$blnBreak = true;
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
		static::$arrContentElements[$objModel->pid][$objModel->id]['generated'] = true;
		
		// element is marked as deleted, so do not generate
		if($this->hasState($objModel, 'delete'))
		{
			return;			
		}

		$strClass = $this->findContentElement($objModel->type);
		$objElement = new $strClass($objModel);
				
	    return $objElement->generate();
	}
}