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
use Netzmacht\Drafts\Model\DraftableModel, Input, DC_Table, Contao\Database\Mysql\Result;


// initialize draft modules
$GLOBALS['TL_CONFIG']['draftModules'] = unserialize($GLOBALS['TL_CONFIG']['draftModules']);


/**
 * DraftableDataContainer provides draft functionality for tables with dynamic ptable
 * 
 */
abstract class DraftableDataContainer extends \Netzmacht\Utils\DataContainer
{
	
	/**
	 * configure button rules for live view
	 * @param array
	 */
	protected $arrButtonRules = array
	(
		'__default__' 	=> array('hasAccessOnPublished'),
		'show'			=> array('generate'),
	);
	 
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
	 * store if parent view is used and not a single element is accessed
	 * @param bool
	 */
	protected $blnParentView;
	
	/**
	 * used int id
	 * @param int
	 */
	protected $intId;
	
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
		
		$this->strAction = Input::get('key') == '' ? Input::get('act') : Input::get('key');
		
		if(Input::get('tid') != null)
		{
			$this->strAction = 'toggle';
			$this->intId = Input::get('tid');
		}
		else
		{
			$this->intId = Input::get('id');
		}
		
		$this->blnDraftMode = Input::get('draft') == '1';
		$this->blnParentView = in_array($this->strAction, array(null, 'select', 'create')) || ($this->strAction == 'paste' && Input::get('mode') == 'create');
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
				$this->log('Invalid approach to apply draft. No draft found', get_class($this) . ' applyDraft()', TL_ERROR);
				
				if(!$blnDoNoRedirect)
				{
					$this->redirect('contao/main.php?act=error');
				}
				
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
			$objModel->draftRelated = 0;
			$objModel->save();
		}
		
		elseif($objOriginal !== null)
		{
			// apply changes 
			if($objModel->hasState('modified'))
			{
				$objNew = $objModel->prepareCopy();

				$objNew->draftRelated = 0;
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
				
				$objOriginal->draftRelated = 0;
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
		if(\Input::post('applyDrafts') !== null || \Input::post('resetDrafts') !== null)
		{
			$arrIds = Input::post('IDS');
			
			if(!is_array($arrIds) || empty($arrIds))
			{
				$this->redirect($this->getReferer());
			}
			
			$strQuery = 'SELECT * FROM ' . $this->strTable . ' WHERE draftState > 0 AND ' . $this->Database->findInSet('id', $arrIds);
			$objResult = $this->Database->query($strQuery);
			
			// Apply Drafts
			if(\Input::post('applyDrafts') !== null)
			{
				while($objResult->next())
				{
					$objModel = new DraftableModel($objResult, false, $this->strTable);
					$this->applyDraft($objDc, $objModel, true);
				}
			}
			
			// Reset Drafts
			else
			{
				while($objResult->next())
				{
					$objDc->setId($objResult->id);
					$this->resetDraft($objDc, true);
				}
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
		}
		
		// draft mode
		elseif(!$this->blnParentView)
		{
			$objModel = DraftableModel::findByPK($this->strTable, $this->intId);
	
			if($objModel->isDraft())
			{
				$objDc->setId($objModel->id);
			}
			elseif(!$objModel->hasRelated())
			{
				$objRelated = $objModel->prepareCopy(true);
				$objRelated->save(true);
				
				$objModel->draftRelated = $objRelated->id;
				$objModel->save();

				Input::setGet('id', $objRelated->id);
				$objDc->setId($objRelated->id);
				$this->intId = $objDc->id;
			}
			else
			{
				$objDc->setId($objModel->draftRelated);
				$this->intId = $objDc->id;	
			}
		}
	}

	
	/**
	 * keep draft in sync by copying elements
	 * 
	 * @param int id
	 * @param DC_Table
	 */
	public function onCopy($insertID, $objDc)
	{
		$objModel = DraftableModel::findByPK($this->strTable, $insertID);
		
		// label copied element as new
		if(!$this->blnDraftMode)
		{
			if($objModel->hasRelated())
			{
				$objNew = $objModel->prepareCopy(true, true);
				$objNew->save();
				
				$objModel->draftRelated = $objNew->id;
				$objModel->save();
			}
		}
		else
		{
			$objModel->draftRelated = 0;
			$objModel->save();
		}
	}
	
	
	/**
	 * delete all drafts if parent is copied
	 * 
	 * @param int id
	 * @param DC_Table
	 */
	public function onCopyParent($insertID, $objDc)
	{
		$arrChildTable = $objDc->childTable;
		
		// no childTable given
		if(empty($arrChildTable))
		{
			return;
		}
		
		$intId = null;
		
		// direct parent was called, 
		if(in_array($this->strTable, $arrChildTable))
		{
			$intId = $insertID;
		}
		// check 2nd level, contao only support 2 levels for recursive children copy
		else
		{
			foreach ($arrChildTable as $strTable) 
			{
				$this->loadDataContainer($strTable);
				
				// make sure that current data container is on of the parents
				if(!empty($GLOBALS['TL_DCA'][$strTable]['config']['ctable']) && in_array($this->strTable, $GLOBALS['TL_DCA'][$strTable]['config']['ctable']))
				{
					// check if ptable is set
					if($GLOBALS['TL_DCA'][$strTable]['config']['dynamicPtable'])
					{
						$strPtable = $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable'] == 'tl_article' ? '(ptable=? OR ptable=\'\')' : 'ptable=?';
						$intId = $this->Database->prepare('SELECT id FROM ' . $strTable . ' WHERE pid=? AND ' . $strPtable)
												->execute($insertID, $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable'])->id;
					}
					else
					{
						$intId = $this->Database->prepare('SELECT id FROM ' . $strTable . ' WHERE pid=?')->execute($insertID)->id;						
					}
					
					break;					
				}				
			}
		}
		
		if($intId !== null)
		{
			$strPtable = $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable'] == 'tl_article' ? '(ptable=? OR ptable=\'\')' : 'ptable=?';
			$obj = $this->Database->prepare('DELETE FROM ' . $this->strTable . ' WHERE pid=? AND ' . $strPtable . ' AND draftState >0')
						->execute($intId, $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable']);
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
		$objModel = DraftableModel::findByPK($this->strTable, Input::get('id'), array('uncached'=>true));
		$objModel->setVersioning(false);
		
		$objRelated = DraftableModel::findOneByDraftRelated($this->strTable, Input::get('id'), array('uncached'=>true));
		$objRelated->setVersioning(false);
				
		// no related exists, nothing to do
		if($objRelated === null)
		{	
			return;
		}
		
		// NOTE: isDraft is not up to date here, because it checks draftState which is not yet updated
		
		// model, which was a draft, was moved into live mode, remove relation
		// model, which was a live version, was moved into draft mode, remove relation
		if((!$this->blnDraftMode && $objModel->isDraft() && !$objRelated->isDraft()) || ($this->blnDraftMode && !$objModel->isDraft() && $objRelated->isDraft()))
		{
			if($this->blnDraftMode)
			{
				$objModel->setState('draft');
				$objRelated->setState('draft');
			}
			else 
			{
				$objModel->draftState = 0;
				$objModel->draftState = 0;
			}
			
			$objRelated->draftRelated = 0;
			$objModel->save();
				
			$objModel->draftRelated = 0;
			$objModel->save();
		}
		
		// model has not moved to new parent, update sortings
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
				// sorting will be updated because live sorting is used by select
				$objNew = new DraftableModel($objResult, true, $this->strTable);
				$objNew->tstamp = time();
				$objNew->save();
			}
		}
			
		// element was moved into live, move draft to new place as well
		elseif(!$this->blnDraftMode && $objModel->pid != $objRelated->pid)
		{
			// model was a draft, delete old draft or	
			// delete it because we do not know if new ptable is also draftable, will not happen until #5234 is fixed 
			if($objModel->isDraft() || $objRelated->ptable != $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable'])
			{
				$objModel->draftRelated = 0;
				$objModel->draftState = 0;
				$objModel->save();
				
				$objRelated->delete();
			}
			
			// it is in the same ptable, so move draft as well
			else
			{
				$objRelated->pid = $objModel->pid;
				$objRelated->ptable = $objModel->ptable;
				$objRelated->setState('draft');
				$objRelated->sorting = $objModel->sorting;
				$objRelated->save();			
			}
		}
		
		// draft was moved to new parent, label original as to delete
		elseif($this->blnDraftMode && $objModel->pid != $objRelated->pid)
		{
			// model was a live element, now it's a draft, delete related draft
			if(!$objModel->isDraft())
			{
				$objModel->setState('draft');
				$objModel->draftRelated = 0;
				$objModel->save();
				
				$objRelated->delete();
			}
			
			// model was a draft, create new related for objRelated and label as delete
			else
			{
				$objModel->draftRelated = 0;
				$objModel->save();			
						
				$objNew = clone $objModel;
				$objNew->pid = $objRelated->pid;
				$objNew->ptable = $objRelated->ptable;
				$objNew->draftRelated = $objRelated->id;
				$objNew->sorting = $objRelated->sorting;	
				$objNew->setState('delete');
				$objNew->setState('draft');
				$objNew->setVersioning(true);
				$objNew->save(true);
					
				$objRelated->draftRelated = $objNew->id;
				$objRelated->setVersioning(false);
				$objRelated->save();
				
			}
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
				$objReleated->draftRelated = 0;
				$objReleated->save();
			}
			
			return;
		}
		elseif($this->blnDraftMode)
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
						$objRelated->draftRelated = 0;
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
		elseif($objDc->activeRecord->draftRelated > 0)
		{
			// get last undo
			$objUndo = $this->Database->prepare('SELECT * FROM tl_undo WHERE fromTable=? AND pid=? ORDER BY id DESC')->limit(1)->executeUncached($this->strTable, $this->User->id);

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
	 * initialize the data container
	 * Hook: loadDataContainer
	 * 
	 * @param string
	 * @param bool
	 */
	public function onLoadDataContainer($strTable)
	{
		$strClass = get_class($this);
		
		if(!empty($GLOBALS['TL_CONFIG']['draftModules']))
		{			
			// register onCut callback if any module use draft modules
			if($strTable == $this->strTable)
			{
				$GLOBALS['TL_DCA'][$this->strTable]['config']['oncut_callback'][] = array($strClass, 'onCut');
			}
			
			// register on copy callback to check if parent was moved
			else
			{
				$GLOBALS['TL_DCA'][$strTable]['config']['oncopy_callback'][] = array($strClass, 'onCopyParent');
			}
		}
		
		if($strTable != $this->strTable || !in_array(Input::get('do'), $GLOBALS['TL_CONFIG']['draftModules']))
		{
			return false;
		}
		
		// force draft mode
		if(Input::get('table') == $this->strTable && Input::get('draft') == '' && $this->isPublished() && !$this->hasAccessOnPublished())
		{
			$this->blnDraftMode = true;
			Input::setGet('draft', '1');
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
			'button_rules' 		=> array('switchMode', 'generate:table:id'),
		);
			
		$GLOBALS['TL_DCA'][$this->strTable]['list']['global_operations']['draft'] = array
		(
			'label' 			=> &$GLOBALS['TL_LANG'][$this->strTable]['draftmode'],
			'href' 				=> 'draft=1',
			'class'				=> 'header_draft',
			'button_callback' 	=> array($strClass, 'generateGlobalButtonDraft'),
			'button_rules' 		=> array('switchMode:draft', 'generate:table:id'),
		);
		
		$strAttributes = sprintf('onclick="Backend.openModalIframe({\'width\':770,\'title\':\'%s\',\'url\':this.href});'
								.'draftAddSubmitButton(\'%s\');return false"', 
								$GLOBALS['TL_LANG'][$this->strTable]['task'][0],
								$GLOBALS['TL_LANG'][$this->strTable]['task'][2]
		);
			
		$GLOBALS['TL_DCA'][$this->strTable]['list']['global_operations']['task'] = array
		(
			'label' 			=> &$GLOBALS['TL_LANG'][$this->strTable]['task'],
			'href' 				=> 'system/modules/drafts/task.php?do=' . Input::get('do') . '&amp;table=' . $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable'] . '&amp;id=' . $this->intId . '&amp;rt=' . REQUEST_TOKEN,
			'class'				=> 'header_task',
			'attributes'		=> $strAttributes,
			'button_callback' 	=> array($strClass, 'generateGlobalButtonTask'),
			'button_rules' 		=> array('hasAccess:module=tasks', 'taskButton', 'generate:plain'),
		);
		
		// DRAFT MODE SETTINGS		
		if($this->blnDraftMode)
		{
			// change data container and add permission rules
			$GLOBALS['TL_DCA'][$this->strTable]['config']['dataContainer'] = 'DraftableTable';
			$GLOBALS['TL_DCA'][$this->strTable]['config']['permission_rules'] = array('generic:key=[,reset,apply]', 'hasAccessOnPublished:key=apply');
			
			// add list filter for getting draft elements and add custom submit buttons 
			$GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['filter'][] = array('(draftState>? OR (draftState=0 AND draftRelated=0))', 0);
			$GLOBALS['TL_DCA'][$this->strTable]['edit']['buttons_callback'][] = array($strClass, 'generateSubmitButtons');

			// set relation to eagerly in draft mode
			$GLOBALS['TL_DCA'][$this->strTable]['fields']['draftRelated']['relation']['load'] = 'eager';
			
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
					'href' 				=> 'act=draft&amp;key=reset',
					'icon'				=> 'system/modules/drafts/assets/reset.png',
					'button_callback' 	=> array($strClass, 'generateButtonDraftReset'),
					'button_rules' 		=> array('draftState', 'generate'),
				),
				
				'draftApply' => array
				(
					'label' 			=> &$GLOBALS['TL_LANG'][$this->strTable]['draftApply'],
					'href' 				=> 'act=draft&amp;key=apply',
					'icon'				=> 'system/modules/drafts/assets/publish.png',
					'button_callback' 	=> array($strClass, 'generateButtonDraftApply'),
					'button_rules' 		=> array('draftState', 'hasAccessOnPublished', 'generate'),
				),
			));
		}

		// LIVE MODE SETTINGS
		else
		{
			// filter draft elements and add permission rules
			$GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['filter'][] = array('draftState=?', '0');			
			$GLOBALS['TL_DCA'][$this->strTable]['config']['permission_rules'] = array('hasAccessOnPublished:act=[*,show,]');			
			
			// close table if user has no access to insert new content element
			if(!$this->hasAccessOnPublished() && $this->intId != '' && $this->isPublished())
			{
				$GLOBALS['TL_DCA'][$this->strTable]['config']['closed'] = true;
				$GLOBALS['TL_DCA'][$this->strTable]['config']['notEditable'] = true;
			}

			// log button rules for each 
			foreach($GLOBALS['TL_DCA'][$this->strTable]['list']['operations'] as $strKey => $arrConfig)
			{
				// register callback
				$GLOBALS['TL_DCA'][$this->strTable]['list']['operations'][$strKey]['button_callback'] = array($strClass, 'generateButton' . ucfirst($strKey));
				
				// specific ruleset is registered, use it
				if(isset($this->arrButtonRules[$strKey]))
				{
					$GLOBALS['TL_DCA'][$this->strTable]['list']['operations'][$strKey]['button_rules'] = $this->arrButtonRules[$strKey];
				}
				
				// create default rules
				else 
				{
					// default generate rule
					$strGenerate = 'generate';
					
					// button already has a callback, pass it by to the generate rule
					if(isset($arrConfig['button_callback']))
					{
						$strGenerate .= ':callback=[' . implode(',', $arrConfig['button_callback']) . ']';					
					}

					$arrRules = $this->arrButtonRules['__default__'];
					$arrRules[] = $strGenerate;
					
					$GLOBALS['TL_DCA'][$this->strTable]['list']['operations'][$strKey]['button_rules'] = $arrRules;
				}
			}
		}
		
		return true;
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
		elseif($arrData['draftRelated'] > 0)
		{
			$objModel = new DraftableModel($this->strTable);
			$objModel->setRow($arrData);
			$objModel->prepareCopy()->save();
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
		if($this->blnDraftMode && !$objModel->hasState('new') && !$objModel->hasState('modified'))
		{
			$objModel->setState('modified');
			$objModel->tstamp = time();
			$objModel->save();
		}
		// udate draft to newest live version
		elseif(!$this->blnDraftMode && $objDc->activeRecord->draftRelated > 0)
		{
			$objNew = $objModel->prepareCopy();
			$objNew->save();
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
		if(!$this->blnDraftMode)
		{
			// ajax request => get_class($objDc) == get_class($this)
			$strField = get_class($objDc) == get_class($this) ? 'draftRelated' : 'id';
			$objModel = DraftableModel::findOneBy($this->strTable, $strField, $strField == 'id' ? $objDc->activeRecord->draftRelated : $this->intId);

			if($objModel !== null && $blnVisible != $objModel->invisible)
			{
				$objModel->setVersioning(true);
				$objModel->invisible = $blnVisible ? '1' : '';
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

		if(!$objModel->isDraft() && !$objModel->hasRelated())
		{
			return false;
		}
		elseif(!$objModel->isDraft())
		{
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
		$arrAttributes['value'] = $blnAccess;
		
		if($arrRow === null || isset($arrAttributes['hide']))
		{
			return $blnAccess;
		}
		
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
		return $this->isPublished() && (isset($arrAttributes['draft']) ? (Input::get('draft') != '1') : (Input::get('draft') == '1'));
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
		return $GLOBALS['TL_CONFIG']['draftUseTaskModule'] && in_array('tasks', $this->Config->getActiveModules());
	}

	
	/**
	 * check if user has accesss on published content
	 * 
	 * @return bool
	 */
	protected function hasAccessOnPublished()
	{
		return $this->User->hasAccess($GLOBALS['TL_DCA'][$this->strTable]['config']['ptable'] . '::published', 'alexf');		
	}


	/**
	 * check if content is already published
	 * 
	 * @return bool
	 */
	protected function isPublished()
	{
		if($this->blnIsPublished !== null)
		{
			return $this->blnIsPublished;
		}
		
		if($this->blnParentView)
		{
			$strQuery = 'SELECT published FROM ' . $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable'] . ' WHERE id=' . $this->intId;			
		}
		else 
		{
			$strQuery 	= 'SELECT published FROM ' . $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable'] 
						. ' WHERE id=(SELECT pid FROM ' . $this->strTable . ' WHERE id=' . $this->intId . ')';
		}

		$this->blnIsPublished = (bool) $this->Database->query($strQuery)->published;
		return $this->blnIsPublished;
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
	
}
