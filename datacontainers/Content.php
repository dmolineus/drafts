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
use Netzmacht\Drafts\Model\DraftableModel;


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
		$objModel = new DraftableModel($this->strTable);
		$objModel->setRow($arrRow);
		
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
		if($objModel->isDraft())
		{
			$arrState = array();
			
			if ($objModel->hasState('new'))
			{
				$arrState[] = 'new';
			}
			else
			{
				if($objModel->hasState('modified'))
				{
					$arrState[] = 'modified';
				}
				
				if($objModel->hasState('sorted'))
				{
					$arrState[] = 'sorted';
				}
				
				if($objModel->hasState('delete'))
				{
					$arrState[] = 'delete';
				}
				
				if($objModel->hasState('visibility'))
				{
					$arrState[] = 'visibility';
				}
			}
			
			// pass draft labels as javascript
			if(!empty($arrState))
			{
				
				asort($arrState);
				foreach ($arrState as $strState) 
				{
					$label .= sprintf('<div class="draft_label %s">%s</div>', $strState, $GLOBALS['TL_LANG'][$this->strTable]['draftState_' . $strState]);			
				}
			}
		}
		
		static $blnLabelsRendered = false;
			
		if(!$blnLabelsRendered)
		{
			$strLabels = '<script>var DraftLabels = { sorted: \'' . $GLOBALS['TL_LANG']['tl_content']['draftState_sorted'] . '\''
						.', visibility: \'' . $GLOBALS['TL_LANG']['tl_content']['draftState_visibility'] .  '\'};</script>';
			$blnLabelsRendered = true;
		}
		
		return $strLabels . sprintf
		(
			'<div class="cte_type %s">%s %s</div><div class="%s">%s</div>' . "\n",
			$key, $type, $label, trim($class), $this->getContentElement($arrRow['id'])
		);
	}


	/**
	 * filter alias elements so that only life elements are used
	 * 
	 * @return array
	 */
	public function getAlias()
	{
		$objContent = new \tl_content();
		$arrAlias = $objContent->getAlias();
		
		$strPtable = $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable'] == 'tl_article' ? '(ptable=? OR ptable=\'\')' : 'ptable=?';
		$objResult = $this->Database->prepare('SELECT id FROM ' . $this->strTable . ' WHERE ' . $strPtable . ' AND draftState>0')
									->execute($GLOBALS['TL_DCA'][$this->strTable]['config']['ptable']);
		
		$arrIds = $objResult->fetchEach('id');
		
		foreach ($arrAlias as $key => $value)
		{
			foreach ($arrIds as $intId)
			{
				if(isset($arrAlias[$key][$intId]))
				{
					unset($arrAlias[$key][$intId]);
					unset($arrIds[$intId]);
				}
			}
		}
		
		return $arrAlias;
	}


	/**
	 * initialize the data container
	 * Hook: loadDataContainer
	 * 
	 * @param string
	 * @param bool
	 */
	public function onLoadDataContainer($strTable)
	{
		if(!parent::onLoadDataContainer($strTable))
		{
			return false;
		}

		$strClass = get_class($this);
		
		if($this->blnDraftMode)
		{
			// generate callback
			$GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['child_record_callback'] 		= array($strClass, 'generateChildRecord');
			
			// add draft visibility label toggling
			$GLOBALS['TL_DCA'][$this->strTable]['list']['operations']['toggle']['attributes'] 		= 'onclick="Backend.getScrollOffset();AjaxRequest.toggleVisibility(this,%s);return draftToggleLabel(this, \'visibility\', DraftLabels.visibility)"';
			$GLOBALS['TL_DCA'][$this->strTable]['list']['operations']['toggle']['button_callback'] 	= array($strClass, 'generateButtonToggle');
			$GLOBALS['TL_DCA'][$this->strTable]['list']['operations']['toggle']['button_rules']		= array('toggleIcon:field=invisible:inverted', 'generate');
		}
	
		// check permission for operations in live mode
		else
		{
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
	 * button true if no alias exists, used as rule for disabling icon
	 *
	 * @param string the button name 
	 * @param string href
	 * @param string label
	 * @param string title
	 * @param string icon class
	 * @param string added attributes
	 * @param array option data row of operation buttons
	 * @return bool true
	 */
	protected function buttonRuleAliasElement(&$strButton, &$strHref, &$strLabel, &$strTitle, &$strIcon, &$strAttributes, &$arrAttributes, $arrRow=null)
	{
		$objElement = $this->Database->prepare("SELECT id FROM tl_content WHERE cteAlias=? AND type='alias'")->limit(1)->execute($arrRow['id']);		
		return $objElement->numRows < 1;		
	}

}