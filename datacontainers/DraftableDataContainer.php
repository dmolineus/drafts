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
use Netzmacht\Drafts\Model\DraftableModel, Input, DraftsModel, DC_Table, Contao\Database\Mysql\Result;


// initialize draft modules
$GLOBALS['TL_CONFIG']['draftModules'] = unserialize($GLOBALS['TL_CONFIG']['draftModules']);

/**
 * DraftableDataContainer provides draft functionality for tables with dynamic ptable
 * 
 */
abstract class DraftableDataContainer extends \Netzmacht\Utils\DataContainer
{
	
	/**
	 * true if we are in draft mode
	 * @param bool
	 */
	protected $blnDraftMode;
	
	/**
	 * cache is published query
	 * @param bool
	 */
	protected $blnIsPublished = null;
	
	/**
	 * used int id
	 * @param int
	 */
	protected $intId;
	
	/**
	 * @param DraftsModel
	 */
	protected $objDraft;
	
	/**
	 * current action
	 * @param string
	 */
	protected $strAction;
	
	
	/**
	 * constructor sets draft mode
	 * 
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
		
		$this->blnDraftMode = Input::get('draft') == '1';
		$this->strAction = Input::get('act') == '' ? Input::get('key') : Input::get('act');
		
		if(Input::get('tid') != null)
		{
			$this->strAction = 'toggle';
			$this->intId = Input::get('tid');
		}
		else
		{
			$this->intId = Input::get('id');
		}
	}
	
	
	/**
	 * reset message to avoid wrong live mode message
	 */
	public function __destruct()
	{
		\Message::reset();	
	}
	
	
	/**
	 * apply changes of draft to original
	 * 
	 * @param DC_Table 
	 * @param \Model|null
	 * @param bool
	 */
	public function applyDraft($objDc, $objModel = null, $blnDoNoRedirect=false)
	{
		if($objModel === null)
		{
			$this->initialize($objDc);		
			$objModel = DraftableModel::findByPK($this->strTable, $this->intId);

			if($objModel === null)
			{
				$this->triggerError('Invalid approach to apply draft. No draft found', 'applyDraft', $blnDoNoRedirect);				
				return;
			}
		}
		
		$objOriginal = $objModel->getRelated();

		// delete original will also delete draft
		if($objModel->hasState('delete'))
		{
			$objDc->setId($objOriginal->id);
			$objDc->delete(true);
		}
		
		// create new original
		elseif($objModel->hasState('new'))
		{
			$objModel->draftState = 0;
			$objModel->draftRelated = null;
			$objModel->save();
		}
		
		elseif($objOriginal !== null)
		{
			// apply changes 
			if($objModel->hasState('modified'))
			{
				$objNew = $objModel->prepareCopy();
				$objNew->draftRelated = null;
				$objNew->save();
			}			
			else
			{
				// apply new sorting
				if($objModel->hasState('sorted'))
				{
					$objOriginal->sorting = $objModel->sorting;
				}
				
				// apply new visibility
				if($objModel->hasState('visibility'))
				{
					$objOriginal->invisible = $objModel->invisible;
				}
				
				$objOriginal->draftRelated = null;
				$objOriginal->setVersioning(true);
				$objOriginal->tstamp = time();
				$objOriginal->save();
			}
			
			$objModel->delete();
		}
		
		if(!$blnDoNoRedirect)
		{
			$this->redirect($this->getReferer());
		}
	}
	
	
	/**
	 * generate submit buttons
	 * 
	 * @param DC_Table
	 */
	public function generateSubmitButtons($objDc)
	{
		// Apply Drafts
		if(\Input::post('applyDrafts') !== null)
		{
			$arrIds = Input::post('IDS');
			
			if(!is_array($arrIds) || empty($arrIds))
			{
				$this->redirect($this->getReferer());
			}
			
			$strQuery = 'SELECT * FROM ' . $this->strTable . ' WHERE draftState > 0 AND ' . $this->Database->findInSet('id', $arrIds);
			$objResult = $this->Database->query($strQuery);

			while($objResult->next())
			{
				$objModel = new DraftableModel($objResult, false, $this->strTable);
				$this->applyDraft($objDc, $objModel, true);
			}
			
			$this->redirect($this->getReferer());
		}
		
		// Reset Drafts
		elseif(\Input::post('resetDrafts') !== null)
		{
			$arrIds = \Input::post('IDS');
			
			if(!is_array($arrIds) || empty($arrIds))
			{
				$this->redirect($this->getReferer());
			}
			
			$strQuery = 'SELECT id FROM ' . $this->strTable . ' WHERE draftState > 0 AND ' . $this->Database->findInSet('id', $arrIds);
			$objResult = $this->Database->query($strQuery);

			while($objResult->next())
			{
				$objDc->setId($objResult->id);
				$this->resetDraft($objDc, true);
			}
			
			$this->redirect($this->getReferer());
		}
		
		// not possible to use delete all drafts at the moment
		$GLOBALS['TL_DCA'][$this->strTable]['config']['notDeletable'] = true;	
		$strBuffer = '<input type="submit" class="tl_submit" name="resetDrafts" value="' . $GLOBALS['TL_LANG'][$this->strTable]['resetDrafts'] . '">';
		
		if($this->hasAccessOnPublished())
		{
			$strBuffer .= '<input type="submit" class="tl_submit" name="applyDrafts" value="' . $GLOBALS['TL_LANG'][$this->strTable]['applyDrafts'] . '">';
		}
		
		return $strBuffer;
	}

	
	/**
	 * initialize data container
	 */
	public function initialize($objDc)
	{
		$this->initializeDraft($objDc);
		$this->initializeModes($objDc);
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
		$strClass = get_class($this);
		
		// register onCut callback if any module use draft modules
		if($strTable == $this->strTable && !empty($GLOBALS['TL_CONFIG']['draftModules']))
		{
			$GLOBALS['TL_DCA'][$this->strTable]['config']['oncut_callback'][] = array($strClass, 'onCut');
		}
		
		if($strTable != $this->strTable || !in_array(Input::get('do'), $GLOBALS['TL_CONFIG']['draftModules']))
		{
			return false;
		}		
		
		// GENERAL SETTINGS
		
		// register callbacks
		$GLOBALS['TL_DCA'][$this->strTable]['config']['onload_callback'][] 		= array($strClass, 'initialize');
		$GLOBALS['TL_DCA'][$this->strTable]['config']['onload_callback'][] 		= array($strClass, 'checkPermission');				
		$GLOBALS['TL_DCA'][$this->strTable]['config']['oncopy_callback'][] 		= array($strClass, 'onCopy');
		$GLOBALS['TL_DCA'][$this->strTable]['config']['oncreate_callback'][] 	= array($strClass, 'onCreate');		
		$GLOBALS['TL_DCA'][$this->strTable]['config']['ondelete_callback'][] 	= array($strClass, 'onDelete');
		$GLOBALS['TL_DCA'][$this->strTable]['config']['onrestore_callback'][] 	= array($strClass, 'onRestore');
		$GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback'][] 	= array($strClass, 'onSubmit');			
		$GLOBALS['TL_DCA'][$this->strTable]['fields']['invisible']['save_callback'][] = array($strClass, 'onToggleVisibility');
		
		// register global operations
		$GLOBALS['TL_DCA'][$this->strTable]['list']['global_operations']['live'] = array
		(
			'label' 			=> &$GLOBALS['TL_LANG'][$this->strTable]['livemode'],
			'href' 				=> 'draft=0',
			'class'				=> 'header_live',
			'button_callback' 	=> array($strClass, 'generateGlobalButtonLive'),
			'button_rules' 		=> array('switchMode', 'generate'),
		);
			
		$GLOBALS['TL_DCA'][$this->strTable]['list']['global_operations']['draft'] = array
		(
			'label' 			=> &$GLOBALS['TL_LANG'][$this->strTable]['draftmode'],
			'href' 				=> 'draft=1',
			'class'				=> 'header_draft',
			'button_callback' 	=> array($strClass, 'generateGlobalButtonDraft'),
			'button_rules' 		=> array('switchMode:draft', 'generate'),
		);
		
		$strAttributes = sprintf('onclick="Backend.openModalIframe({\'width\':770,\'title\':\'%s\',\'url\':this.href});'
								.'draftAddSubmitButton(\'%s\');return false"', 
								$GLOBALS['TL_LANG'][$this->strTable]['task'][0],
								$GLOBALS['TL_LANG'][$this->strTable]['task'][2]
		);
			
		$GLOBALS['TL_DCA'][$this->strTable]['list']['global_operations']['task'] = array
		(
			'label' 			=> &$GLOBALS['TL_LANG'][$this->strTable]['task'],
			'href' 				=> 'contao/main.php?do=' . Input::get('do') . '&key=task',
			'class'				=> 'header_task',
			'attributes'		=> $strAttributes,
			'button_callback' 	=> array($strClass, 'generateGlobalButtonTask'),
			'button_rules' 		=> array('hasAccess:module=tasks', 'taskButton', 'generate'),
		);
		
		// DRAFT MODE SETTINGS		
		if($this->blnDraftMode)
		{
			// filter draft elements 
			$GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['filter'][] = array('(draftState>? OR (draftState = 0 AND draftRelated IS NULL))', '0');
			
			// data container
			$GLOBALS['TL_DCA'][$this->strTable]['config']['dataContainer'] = 'DraftableTable';
				
			// callbacks
			$GLOBALS['TL_DCA'][$this->strTable]['edit']['buttons_callback'][] = array($strClass, 'generateSubmitButtons');

			// set relation to eagerly
			$GLOBALS['TL_DCA']['tl_content']['fields']['draftRelated']['relation']['load'] = 'eager';
		
			// permission rules
			$GLOBALS['TL_DCA'][$this->strTable]['config']['permission_rules'] = array('generic:key=[,reset,apply]', 'hasAccessOnPublished:key=apply');
			
			// insert draft operation buttons
			array_insert($GLOBALS['TL_DCA'][$this->strTable]['list']['operations'], 1, array
			( 
				'draftDiff' => array
				(
					'label' 			=> &$GLOBALS['TL_LANG'][$this->strTable]['draftDiff'],
					'href' 				=> 'system/modules/drafts/diff.php',
					'icon'				=> 'diff.gif',
					'attributes'		=> 'onclick="Backend.openModalIframe({\'width\':860,\'title\':\'Unterschiede anzeigen\',\'url\':this.href});return false"',
					'button_callback' 	=> array($strClass, 'generateButtonDraftDiff'),
					'button_rules' 		=> array('draftState:modified', 'generate:plain:table:id'),
				),
				
				'draftReset' => array
				(
					'label' 			=> &$GLOBALS['TL_LANG'][$this->strTable]['draftReset'],
					'href' 				=> 'key=reset',
					'icon'				=> 'system/modules/drafts/assets/reset.png',
					'button_callback' 	=> array($strClass, 'generateButtonDraftReset'),
					'button_rules' 		=> array('draftState', 'generate'),
				),
				
				'draftApply' => array
				(
					'label' 			=> &$GLOBALS['TL_LANG'][$this->strTable]['draftApply'],
					'href' 				=> 'key=apply',
					'icon'				=> 'system/modules/drafts/assets/publish.png',
					'button_callback' 	=> array($strClass, 'generateButtonDraftApply'),
					'button_rules' 		=> array('draftState', 'hasAccessOnPublished', 'generate'),
				),
			));
		}

		// LIVE MODE SETTINGS
		else
		{
			// filter draft elements 
			$GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['filter'][] = array('draftState=?', '0');
			
			$GLOBALS['TL_DCA'][$this->strTable]['config']['permission_rules'] = array('hasAccessOnPublished:act=[edit,delete,cut,copy,select,deleteAll,editAll,overrideAll,cutAll,copyAll]');			
			
			// close table if user has no access to insert new content element
			if(!$this->hasAccessOnPublished() && $this->intId != '' && $this->isPublished())
			{
				$GLOBALS['TL_DCA'][$this->strTable]['config']['closed'] = true;
				$GLOBALS['TL_DCA'][$this->strTable]['config']['notEditable'] = true;
			}
			
		}
		
		return true;
	}

	
	/**
	 * keep draft in sync by copying elements
	 * 
	 * @param int id
	 * @param DC_Table
	 */
	public function onCopy($insertID, $objDc)
	{
		// label copied element as new
		if(!$this->blnDraftMode && $this->objDraft !== null)
		{
			$objModel = DraftableModel::findByPK($this->strTable, $insertID);
			
			$objNew = $objModel->prepareCopy(true, true);
			$objNew->save();
			
			$objModel->draftRelated = $objNew->id;
			$objModel->save();
		}
	}
	
	
	/**
	 * keep draft in sync with 
	 * 
	 * @param string table
	 * @param int id
	 * @param array 
	 * @param DC_Table
	 */
	public function onCreate($strTable, $intId, $arrSet, $objDc)
	{
		if($this->blnDraftMode)
		{
			$objModel = new DraftableModel($this->strTable);			
			$objModel->id = $intId;
			$objModel->draftState = 1;
			$objModel->save();
		}
	}
	
	
	/**
	 * handle syncing if elements are moved
	 * 
	 * @param DC_Table 
	 */
	public function onCut($objDc)
	{
		//return;
		$objModel = DraftableModel::findByPK($this->strTable, Input::get('id'), array('uncached'=>true));		
		$objRelated = DraftableModel::findOneByDraftRelated($this->strTable, Input::get('id'), array('uncached'=>true));
				
		// no related exists, check if we need to create a new draft
		if($objRelated === null && !$objModel->isDraft())
		{
			$objRelated = $objModel->prepareCopy();
			$objRelated->save();
				
			$objModel->draftRelated = $objRelated->id;
			$objModel->save();
			
			return;
		}
			
		// both are no drafts, draft was move into a live place
		if(!$objRelated->isDraft() && !$objModel->isDraft())
		{
			$objRelated->draftRelated = null;
			$objModel->draftRelated = null;
			$objModel->save();
			
			if(true) // TODO: check new parents
			{
				$objNew = $objRelated->prepareCopy();
				$objNew->sorting = $objRelated->sorting;				
				$objNew->setState('delete');
				$objNew->save(true);
					
				$objRelated->draftRelated = $objNew->id;
				$objRelated->setVersioning(false);				
				$objRelated->save();
			}

			if(true) // TODO: check new parents
			{			
				$objNew = clone $objRelated;
				$objNew->draftRelated = $objModel->id;
				$objNew->sorting = $objModel->sorting;
				$objNew->save(true);
					
				$objModel->draftRelated = $objNew->id;
				$objModel->setVersioning(false);
				$objModel->save();
			}
		}

		// both are drafts now, so remove relation
		elseif($objRelated->isDraft() && $objModel->isDraft())
		{
			$objModel->draftRelated = null;
			$objModel->save();
			
			$objRelated->draftRelated = null;
			$objRelated->save();
		}
		
		// model has not moved to new parent, only update sortings
		elseif(!$this->blnDraftMode && $objRelated->isDraft() && $objModel->pid == $objRelated->pid)
		{			
			$strQuery 	= 'SELECT t.id, t.draftState, j.sorting FROM ' . $this->strTable . ' t'
						. ' LEFT JOIN ' . $this->strTable . ' j ON j.id = t.draftRelated'
						. ' WHERE t.pid=? AND t.draftState > 0 AND t.sorting != j.sorting';
							
			$objResult = $this->Database->prepare($strQuery)->execute($objModel->pid);
	
			if($objResult->numRows < 1)
			{
				return;
			}
			
			while($objResult->next()) 
			{
				// sorting will be updated because live sorting was selected
				$objNew = new DraftableModel($objResult, true, $this->strTable);
				$objNew->tstamp = time();
				$objNew->save();
			}
		}
			
		// move draft to new place
		elseif($objModel->pid != $objRelated->pid && $objRelated->isDraft())
		{
			// new parent has also a draft
			if(true) // TODO: check new parents
			{
				$objRelated->pid = $objModel->pid;
				$objRelated->setState('draft');
				$objRelated->sorting = $objModel->sorting;
				$objRelated->save();
			}
			else 
			{
				$objRelated->delete();					
			}
		}
		
		// draft is moved
		elseif($objModel->pid != $objRelated->pid && $objModel->draftState > 0)
		{
			// model has not moved to new parent
			if($objRelated->pid == $objModel->pid)
			{
				return;
			}
			
			// create new draft for formerly related and mark as delete, because it is moved to another place
			$objNewDrafts = \DraftsModel::findOneByPidAndTable($objRelated->pid, $objRelated->ptable);	
			$objRelated->draftRelated = null;
			$objModel->draftRelated = null;
			$objModel->save();			
			
			if($objNewDrafts !== null)
			{				
				$objNew = clone $objModel;
				$objNew->draftRelated = $objRelated->id;
				$objNew->sorting = $objRelated->sorting;	
				$objNew->setState('delete');
				$objNew->setState('draft');
				$objNew->setVersioning(true);
				$objNew->save(true);
				
				$objRelated->draftRelated = $objNew->id;
			}

			$objRelated->setVersioning(false);
			$objRelated->save();
		}
	}
	
	
	/**
	 * keep draft and live mode in sync
	 * 
	 * @param DC_Table
	 * @return void
	 */
	public function onDelete($objDc)
	{
		// just reset relation if reset is called
		if($this->strAction == 'reset')
		{
			$objModel = new DraftableModel($objDc->activeRecord, false, $this->strTable);
			
			if($objModel->hasRelated())
			{
				$objReleated = $objModel->getRelated();
				$objReleated->draftRelated = null;
				$objReleated->save();
			}
			
			return;
		}
		elseif($objDc->activeRecord->draftState > 0)
		{
			// delete all mode, do nothing here. applyDraft is handling the delete task
			if(Input::post('IDS') != '')
			{
				return;
			}
			
			$objModel = new DraftableModel($objDc->activeRecord, false, $this->strTable);

			// delete new elements
			if($objModel->hasState('new'))
			{
				return;
			}
			elseif($objModel->hasState('delete'))
			{
				// no state set, let's delete it
				if(!$objModel->hasState('visibility') && !$objModel->hasState('modified') && !$objModel->hasState('sorted'))
				{
					$objRelated = $objModel->getRelated();
					
					if($objRelated !== null)
					{
						$objRelated->draftRelated = null;
						$objRelated->save();
					}
					
					return;
				}

				$objModel->removeState('delete');					

			}
			else 
			{
				$objModel->setState('delete');
			}
			
			// save and redirect so element is not deleted
			$objModel->save();
			$this->redirect($this->getReferer());
		}
		
		// add draft element to tl_undo as well
		elseif($objDc->activeRecord->draftRelated != null)
		{
			// get last undo
			$objUndo = $this->Database->prepare('SELECT * FROM tl_undo WHERE fromTable=? AND pid=? ORDER BY id DESC')->limit(1)->execute($this->strTable, $this->User->id);
			
			// no undo set found, just delete it
			if($objUndo->numRows < 1)
			{
				$objDc->setId($objDc->activeRecord->draftRelated);
				$objDc->delete(true);
				$objDc->setId($objDc->activeRecord->id);
				return;
			}
			
			// store draft in tl_undo
			// TODO: handle ctable elements
			$objSave = DraftableModel::findOneBy($this->strTable, 'draftRelated', $objDc->id);
			
			if($objSave === null)
			{
				return;
			}
			
			$arrData = unserialize($objUndo->data);
			$arrData[$this->strTable][$objSave->id] = $objSave->row();
		
			$arrSet = array
			(
				'tstamp'	=> time(),
				'data'		=> $arrData,
				'affectedRows'	=> $objUndo->affectedRows+1,
			);
			
			$this->Database->prepare('UPDATE tl_undo %s WHERE id=?')->set($arrSet)->execute($objUndo->id);
			$objSave->delete();
		}
	}
	
	
	/**
	 * make sure that draft version is updated when version is restored
	 * 
	 * @param int id
	 * @param string table
	 * @param array data
	 * @param int current version
	 */
	public function onRestore($intId, $strTable, $arrData, $intVersion)
	{
		// set draft state to modified because we do not know what has changed
		if($this->blnDraftMode)
		{			
			$arrSet = array('draftState' => 2, 'tstamp' => time);
			$this->Database->prepare('UPDATE ' . $strTable . ' %s WHERE id=?')->set($arrSet)->executeUncached($intId);
		}
		// create new version of draft
		elseif($this->objDraft !== null)
		{
			$objResult = $this->Database->prepare('SELECT id, draftRelated FROM ' . $this->strTable . ' WHERE id=?')->execute($intId);
			
			if($objResult->numRows == 1 && $objResult->draftRelated > 0)
			{
				$objModel = DraftableModel::findByPK($this->strTable, $intId)->prepareCopy(); 
				$objModel->save();
			}
		}
	}
	
	
	/**
	 * store modified draft
	 */
	public function onSubmit($objDc)
	{
		// nothing changed	
		if(!$objDc->activeRecord->isModified)
		{
			return;
		}

		$objModel = new DraftableModel($objDc->activeRecord, false, $this->strTable);
		
		// update label
		if($this->blnDraftMode)
		{
			if($objModel->hasState('new') || $objModel->hasState('modified'))
			{
				return;
			}
			
			$objModel->setState('modified');
			$objModel->tstamp = time();
			$objModel->save();
		}
		
		// udate draft to newest live version
		elseif($this->objDraft !== null)
		{
			if($objDc->activeRecord->draftRelated != null)
			{
				$objNew = $objModel->prepareCopy();
				$objNew->save();
			}
		}
	}


	/**
	 * handle visibility changes of element
	 * 
	 * @param bool visible state
	 * @param DC_Table
	 * @return void
	 */
	public function onToggleVisibility($blnVisible, $objDc)
	{
		if($this->blnDraftMode)
		{
			return $blnVisible;
		}
		
		$strField = 'id';
		
		// ajax request
		if($this->objDraft === null && get_class($objDc) == get_class($this))
		{
			$this->objDraft = $objDc;
			$strField = 'draftRelated';
		}
		
		if($this->objDraft !== null)
		{
			$objModel = DraftableModel::findOneBy($this->strTable, $strField, $strField == 'id' ? $objDc->activeRecord->draftRelated : $this->intId);
			
			if($objModel !== null && $blnVisible != $objModel->invisible)
			{
				$objModel->setVersioning(true);
				$objModel->invisible = $blnVisible;
				$objModel->tstamp = time();
				$objModel->save();
			}
		}
		
		return $blnVisible;
	}


	/**
	 * reset draft will delete it
	 * 
	 * @param DC_DraftableTable
	 * @param bool
	 */
	public function resetDraft($objDc, $blnDoNoRedirect=false)
	{	
		$objDc->delete(true);
		
		if(!$blnDoNoRedirect)
		{
			$this->redirect($this->getReferer());
		}
	}


	/**
	 * decide which buttons can be displays depending on draft state
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
	protected function buttonRuleDraftState(&$strButton, &$strHref, &$strLabel, &$strTitle, &$strIcon, &$strAttributes, &$arrAttributes, $arrRow=null)
	{
		$objModel = new DraftableModel($this->strTable);
		$objModel->setRow($arrRow);

		if(!$objModel->hasRelated() && !$objModel->isDraft())
		{
			return false;
		}
		
		if(!$objModel->isDraft())
		{
			$or = $objModel;
			$objModel = $objModel->getRelated();		
		}
		
		if(isset($arrAttributes['modified']))
		{
			return  $objModel->hasState('modified');
		}
		
		return $objModel->draftState > 0 || $objModel->hasState('new') || $objModel->hasState('sorted') || $objModel->hasState('visibility');
	}
	
	
	/**
	 * button rule to check is user has access to edit published content. can be used for global an normal operations
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
	protected function buttonRuleHasAccessOnPublished(&$strButton, &$strHref, &$strLabel, &$strTitle, &$strIcon, &$strAttributes, &$arrAttributes, $arrRow=null)
	{
		// grant access if not published
		if(!$this->isPublished())
		{
			return true;
		}
		
		$blnAccess = $this->hasAccessOnPublished();
		
		if($arrRow === null || isset($arrAttributes['hide']))
		{
			return $blnAccess;
		}
		
		$arrAttributes['value'] = $blnAccess;
		
		return $this->buttonRuleDisableIcon($strButton, $strHref, $strLabel, $strTitle, $strIcon, $strAttributes, $arrAttributes);
	}
	
	
	/**
	 * switch between live mode and draft mode
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
	protected function buttonRuleSwitchMode(&$strButton, &$strHref, &$strLabel, &$strTitle, &$strIcon, &$strAttributes, &$arrAttributes, $arrRow=null)
	{
		$blnMode = isset($arrAttributes['draft']) ? (Input::get('draft') == '1') : (Input::get('draft') != '1');

		if(($this->objDraft === null && !$this->isPublished()) || $blnMode)
		{
			return false;
		}
		
		if(isset($arrAttributes['draft']))
		{
			$strHref .= '&amp;table=' . $this->strTable . ($this->objDraft === null ? '&amp;mode=create' : '') . '&amp;id=' . $this->intId;
			return true;
		}

		$strHref .= '&amp;table=' . $this->strTable . '&amp;id=' . $this->intId;
		return true;
	}
	

	/**
	 * add draft id to url or hide button
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
	protected function buttonRuleTaskButton(&$strButton, &$strHref, &$strLabel, &$strTitle, &$strIcon, &$strAttributes, &$arrAttributes, $arrRow=null)
	{
		if($this->objDraft === null || !in_array('tasks', $this->Config->getActiveModules()) || !$GLOBALS['TL_CONFIG']['draftUseTaskModule'])
		{
			return false;
		}
		
		$arrAttributes['plain'] = true;
		$arrAttributes['__set__'][] = 'plain';

		$strHref = 'system/modules/drafts/task.php?id=' . $this->objDraft->id . '&rt=' . REQUEST_TOKEN;
		return true;
	}

	
	/**
	 * check if user has accesss on published content
	 * 
	 * @return bool
	 */
	protected function hasAccessOnPublished()
	{
		return $this->User->hasAccess($this->strTable . '::published', 'alexf');		
	}
	
	
	/**
	 * create initial draft version
	 * 
	 * @return void
	 */
	protected function initializeDraft($objDc)
	{
		if(in_array($this->strAction, array(null, 'select', 'create')) || ($this->strAction == 'paste' && Input::get('mode') == 'create'))
		{
			$this->objDraft = DraftsModel::findOneByPidAndTable($this->intId, $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable']);
		}
		else 
		{		
			$strQuery = 'SELECT * FROM tl_drafts WHERE pid=(SELECT pid FROM ' . $this->strTable. ' WHERE id=?) AND ptable=?';
			$objResult = $this->Database->prepare($strQuery)->execute($this->intId, $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable']);
			
			if($objResult->numRows > 0)
			{
				$this->objDraft = new DraftsModel($objResult);
			}
		}

		// create draft
		//  || (Input::get('draft') == '' && !$this->blnDraftMode && !$this->hasAccessOnPublished() && $GLOBALS['TL_CONFIG']['draftModeAsDefault'] == 1) 
		if((Input::get('mode') == 'create' && $this->strAction == null))
		{
			if($this->objDraft === null)
			{
				$this->objDraft = new DraftsModel;
				$this->objDraft->pid = $this->intId;
				$this->objDraft->ptable = $objDc->parentTable;
				$this->objDraft->tstamp = time();
				$this->objDraft->ctable = $this->strTable;
				$this->objDraft->module = Input::get('do');
				$this->objDraft->save();

				// btw: Why does COntao not just update every empty ptable of tl_content to tl_article???
				/*
				$strFoo = ($this->objDraft->ptable == 'tl_article' ? '(ptable=? OR ptable=\'\')' : 'ptable=?');
				$objResult = $this->Database->prepare('SELECT * FROM ' . $this->strTable . ' WHERE pid=? AND ' . $strFoo)->execute($this->objDraft->pid, $this->objDraft->ptable);

				while($objResult->next())
				{
					$objModel = new DraftableModel($objResult, true, $this->strTable);

					$objNew = $objModel->prepareCopy();
					$objNew->pid = $this->objDraft->id;
					$objNew->save(true);					
					
					$objModel->draftRelated = $objNew->id;
					$objModel->tstamp = time();
					$objModel->save();
				}*/
			}
			
			$this->redirect('contao/main.php?do=' . Input::get('do') . '&table=' . $this->strTable . '&draft=1&id=' . $this->objDraft->id .'&rt=' . REQUEST_TOKEN);
		}

		if($this->blnDraftMode && $this->objDraft === null)
		{
			$this->triggerError('No Draft Model found', initializeDraft);
		}
	}
	
	
	/**
	 * initial modes
	 */
	protected function initializeModes($objDc)
	{
		// live mode
		if(!$this->blnDraftMode)
		{
			$blnHasAccess = $this->hasAccessOnPublished();
		
			if($this->strAction != 'show' && Input::post('isAjaxRequest') == '')
			{
				$intState = !$this->isPublished() ? 2 : ($blnHasAccess ? 1 : 0);
				\Message::addRaw('<p class="tl_warning">' . $GLOBALS['TL_LANG'][$this->strTable]['livemodewarning'][$intState] . '</p>');
			}
			
			// disable sorting by adding space so Contao can not detect it as sortable
			if(!$blnHasAccess && $this->isPublished() && $GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['fields'][0] == 'sorting')
			{
				$GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['fields'][0] = 'sorting ';
			}
			
							
			// default redirect to draft mode
			if(Input::get('draft') == '' && Input::get('redirect') == '' && $GLOBALS['TL_CONFIG']['draftModeAsDefault'] > 0 && (in_array($this->strAction, array(null, 'select', 'create')) || ($this->strAction == 'paste' && Input::get('mode') == 'create'))) 
			{
				if($this->objDraft !== null && (!$this->hasAccessOnPublished() || $GLOBALS['TL_CONFIG']['draftModeAsDefault'] == 2))
				{
					$this->redirect('contao/main.php?do=' . Input::get('do') . '&table='. $this->strTable . '&draft=1&id=' . $this->objDraft->id . '&rt=' . REQUEST_TOKEN);
				}
			}
		}
		
		//
		else
		{
			if(!in_array($this->strAction, array(null, 'select', 'create')) && !($this->strAction == 'paste' && Input::get('mode') == 'create'))
			{
				$objModel = DraftableModel::findByPK($this->strTable, $this->intId);
	
				if($objModel->isDraft())
				{
					$objDc->setId($objModel->id);
				}
				elseif(!$objModel->hasRelated())
				{
					$objDraft = $objModel->prepareCopy(true);
					$objDraft->save(true);
					
					$objModel->draftRelated = $objDraft->id;
					$objModel->save();
					
					$objDc->setId($objDraft->id);
					$this->intId = $objDc->id;
				}
				else
				{
					$objDc->setId($objModel->draftRelated);
					$this->intId = $objDc->id;	
				}
			}
		}
	}


	/**
	 * check if content is already published
	 * 
	 * @param array|null current row
	 * @return bool
	 */
	protected function isPublished($arrRow=null)
	{
		$intId = ($arrRow !== null ? $arrRow['pid'] : $this->intId);
		$strQuery = 'SELECT published FROM ' . $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable'] . ' WHERE id=' . $intId;

		return (bool) $this->Database->query($strQuery)->published;
	}


	/**
	 * check is user has access for actions on published one
	 * 
	 * @param Dc_Table
	 * @param array
	 * @param string
	 * @return bool
	 */
	protected function permissionRuleHasAccessOnPublished($objDc, &$arrAttributes, &$strError)
	{
		return !$this->isPublished() || !$this->permissionRuleGeneric($objDc, $arrAttributes, $strError) || $this->hasAccessOnPublished();
	}
	
	
	/**
	 * check permission for draft mode
	 *  
	 * @param Dc_Table
	 * @param array
	 * @param string
	 * @return bool
	 */
	protected function permissionRuleDraftPermission($objDc, &$arrAttributes, &$strError)
	{
		return true;
		// Live mode

		// permission is already granted by default dca checkPermission 
		// Prepare permission checking for draft mode
		if(!$this->blnDraftMode)
		{
			if(!in_array($this->strAction, array(null, 'select', 'create', 'toggle')))
			{
				return true;
			}
			
			$intPerm = $this->objDraft->id;
			$this->Session->set('draftPermission', $intPerm);
			
			// redirect back to draft mode
			if(Input::get('redirect') == '1' && $this->objDraft !== null)
			{				
				if(Input::get('ttid'))
				{
					$tid = '&tid=' . Input::get('ttid');
					$intId = Input::get('ttid');
				}
				else
				{
					$tid = '';
					$intId = $this->objDraft->id;
				}
				
				$this->redirect(sprintf('contao/main.php?do=%s&table=%s&id=%s&draft=1&redirect=2%s&rt=%s', Input::get('do'), $this->strTable, $intId, $tid, REQUEST_TOKEN));
			}
			
			// redirect back to task module
			elseif (Input::get('redirect') == 'task') 
			{
				$this->redirect(sprintf('contao/main.php?do=tasks&act=edit&id=%s&redirect=2&rt=%s', Input::get('taskid'), REQUEST_TOKEN));
			}
			
			return true;
		}		
		
		// Draft Mode 
		
		// only check if no key attribute is given, key checking is rule based
		// access is stored in session, so check it first
		$intPerm = $this->Session->get('draftPermission');

		if(Input::get('key') != '' || $intPerm == $this->objDraft->id)
		{
			return true;
		}
		
		// check access to root
		if(in_array($this->strAction, array(null, 'select', 'create', 'toggle')) || $this->strAction == 'paste' && Input::get('mode') == 'create')
		{
			if(Input::get('redirect') == '2')
			{
				return false;
			}
			
			// passby toggling id
			$tid = '';
			if(Input::get('tid'))
			{
				$objModel = DraftableModel::findOneBy($this->strTable, 'draftRelated', Input::get('tid'));
				
				if($objModel !== null)
				{
					$tid = '&ttid=' . Input::get('tid');					
				}
				else 
				{
					return true;
				}
			}
				
			$this->redirect(sprintf('contao/main.php?do=%s&table=%s&id=%s&redirect=1%s&rt=%s', Input::get('do'), $this->strTable, $this->objDraft->pid, $tid, REQUEST_TOKEN));			
		}

		// check permission for child element
		$objDraft = DraftableModel::findByPK($this->strTable, $this->intId);
		$objModel = $objDraft->getRelated();
		
		if($objModel === null)
		{
			if($objDraft->hasState('new'))
			{
				return true;
			}
			
			return false;
		}
		
		// fake ids to run original check permission method
		Input::setGet('id', $objModel->id);
		$intPid = Input::get('pid');
		
		if($intPid !== null)
		{
			if(Input::get('mode') == '2')
			{
				Input::setGet('pid', $this->objDraft->pid);
			}
			else 
			{
				$objModel = DraftableModel::findOneBy($this->strTable, 'draftRelated', $intPid);
				
				if($objModel !== null)
				{
					Input::setGet('pid', $objModel->id);					
				}
				// pid is new element so grant access
				else
				{
					return true;
				}
			}
		}

		$strClass = $arrAttributes['class'];
		$this->import($strClass);
		$this->$strClass->checkPermission($objDc);
		Input::setGet('id', $this->intId);
		
		if($intPid !== null)
		{
			Input::setGet('pid', $intPid);
		}
		
		return true;
	}
	
	
	/**
	 * triggers an error saves the log message and redirect
	 * 
	 * @param string error message
	 * @param string method
	 * @param bool set true if no redirect
	 * @param int error type
	 */
	protected function triggerError($strError, $strMethod, $blnDoNoRedirect=false, $intTye=TL_ERROR)
	{
		$this->log($strError, get_class($this) . ' ' . $strMethod . '()', $intTye);
				
		if(!$blnDoNoRedirect)
		{
			$this->redirect('contao/main.php?act=error');
		}
	}
	
}
