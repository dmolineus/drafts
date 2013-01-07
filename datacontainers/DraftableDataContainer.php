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
use Netzmacht\Utils\DataContainer, 
	Netzmacht\Drafts\Model\VersioningModel, 
	Input, 
	DraftsModel,
	DC_Table,
	Contao\Database\Mysql\Result;


// initialize draft modules
$GLOBALS['TL_CONFIG']['draftModules'] = unserialize($GLOBALS['TL_CONFIG']['draftModules']);

/**
 * DraftableDataContainer provides draft functionality for tables with dynamic ptable
 * 
 */
abstract class DraftableDataContainer extends DataContainer
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
	 * draft object
	 * @param DraftModel
	 */
	protected $objDraft;
	
	/**
	 * current action
	 * @param string
	 */
	protected $strAction;
	
	/**
	 * current action
	 * @param string
	 */
	protected $strModel;
	
	
	/**
	 * constructor sets draft mode
	 * 
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
		
		$this->blnDraftMode = Input::get('draft') == '1';
		$this->strModel = $this->getModelClassFromTable($this->strTable);
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
		$strModel = $this->strModel;
		
		if($objModel === null)
		{
			$this->initialize();		
			$objModel = $strModel::findByPK($this->intId);

			if($objModel === null)
			{
				$this->triggerError('Invalid approach to apply draft. No draft found', 'applyDraft', $blnDoNoRedirect);				
				return;
			}
		}
		
		$objOriginal = $strModel::findByPK($objModel->draftRelated);

		// delete original will also delete draft
		if($this->hasState($objModel, 'delete'))
		{
			$this->switchMode($objModel->draftRelated, 'delete');
			$dc = new DC_Table($this->strTable);
			$dc->delete(true);
			$this->switchMode($this->intId);
		}
		
		// create new original
		elseif($this->hasState($objModel, 'new'))
		{
			$objNew = $this->prepareModel($objModel, false, true, true);
			$objNew->save(true);
			
			$objModel->draftState = '';
			$objModel->draftRelated = $objNew->id;
			$objModel->tstamp = time();
			$objModel->save();
		}
		
		elseif($objOriginal !== null)
		{
			$blnSave = false;
		
			// apply changes 
			if($this->hasState($objModel, 'modified'))
			{
				$objNew = $this->prepareModel($objModel, false);
				$objNew->save();
			}
			
			// apply new sorting
			if($this->hasState($objModel, 'sorted'))
			{
				$objOriginal->sorting = $objModel->sorting;
				$blnSave = true;
			}
			
			// apply new visibility
			if($this->hasState($objModel, 'visibility'))
			{
				$objOriginal->invisible = $objModel->invisible;
				$blnSave = true;
			}
			
			if($blnSave)
			{
				$objOriginal = new VersioningModel($objOriginal);
				$objOriginal->tstamp = time();
				$objOriginal->save();
			}
			
			$objModel->draftState= '';
			$objModel->save();
			
		}
		
		if(!$blnDoNoRedirect)
		{
			$this->redirect($this->getReferer());
		}
	}
	
	
	/**
	 * generate parent header
	 * modified snippet of DC_Table::parentView
	 * 
	 * @author Leo Feyer <https://contao.org>
	 * @see Contao/DC_Table::parentView headerFields handling
	 * @param array $add
	 * @param DC_Table
	 * @return array
	 */
	public function generateParentHeader($add, $objDc)
	{
		$add = array();
		$objParent = $this->Database->query('SELECT * FROM ' . $this->objDraft->ptable . ' WHERE id=' . $this->objDraft->pid);
		
		$this->loadDataContainer($this->objDraft->ptable);
		$this->loadLanguageFile($this->objDraft->ptable);
		
		// we have to copy&paste the whole stuff because Contao does not really provide useful methods to parse stuff like this
		foreach ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['headerFields'] as $v) 
		{
			$_v = deserialize($objParent->$v);

			if (is_array($_v))
			{
				$_v = implode(', ', $_v);
			}
			elseif ($GLOBALS['TL_DCA'][$this->objDraft->ptable]['fields'][$v]['inputType'] == 'checkbox' && !$GLOBALS['TL_DCA'][$this->objDraft->ptable]['fields'][$v]['eval']['multiple'])
			{
				$_v = ($_v != '') ? $GLOBALS['TL_LANG']['MSC']['yes'] : $GLOBALS['TL_LANG']['MSC']['no'];
			}
			elseif ($_v && $GLOBALS['TL_DCA'][$this->objDraft->ptable]['fields'][$v]['eval']['rgxp'] == 'date')
			{
				$_v = $this->parseDate($GLOBALS['TL_CONFIG']['dateFormat'], $_v);
			}
			elseif ($_v && $GLOBALS['TL_DCA'][$this->objDraft->ptable]['fields'][$v]['eval']['rgxp'] == 'time')
			{
				$_v = $this->parseDate($GLOBALS['TL_CONFIG']['timeFormat'], $_v);
			}
			elseif ($_v && $GLOBALS['TL_DCA'][$this->objDraft->ptable]['fields'][$v]['eval']['rgxp'] == 'datim')
			{
				$_v = $this->parseDate($GLOBALS['TL_CONFIG']['datimFormat'], $_v);
			}
			elseif ($v == 'tstamp')
			{
				$objMaxTstamp = $this->Database->prepare("SELECT MAX(tstamp) AS tstamp FROM " . $this->strTable . " WHERE pid=?")
											   ->execute($objParent->id);

				if (!$objMaxTstamp->tstamp)
				{
					$objMaxTstamp->tstamp = $objParent->tstamp;
				}

				$_v = $this->parseDate($GLOBALS['TL_CONFIG']['datimFormat'], max($objParent->tstamp, $objMaxTstamp->tstamp));
			}
			elseif (isset($GLOBALS['TL_DCA'][$this->objDraft->ptable]['fields'][$v]['foreignKey']))
			{
				$arrForeignKey = explode('.', $GLOBALS['TL_DCA'][$this->objDraft->ptable]['fields'][$v]['foreignKey'], 2);

				$objLabel = $this->Database->prepare("SELECT " . $arrForeignKey[1] . " AS value FROM " . $arrForeignKey[0] . " WHERE id=?")
										   ->limit(1)
										   ->execute($_v);

				if ($objLabel->numRows)
				{
					$_v = $objLabel->value;
				}
			}
			elseif (is_array($GLOBALS['TL_DCA'][$this->objDraft->ptable]['fields'][$v]['reference'][$_v]))
			{
				$_v = $GLOBALS['TL_DCA'][$this->objDraft->ptable]['fields'][$v]['reference'][$_v][0];
			}
			elseif (isset($GLOBALS['TL_DCA'][$this->objDraft->ptable]['fields'][$v]['reference'][$_v]))
			{
				$_v = $GLOBALS['TL_DCA'][$this->objDraft->ptable]['fields'][$v]['reference'][$_v];
			}
			elseif ($GLOBALS['TL_DCA'][$this->objDraft->ptable]['fields'][$v]['eval']['isAssociative'] || array_is_assoc($GLOBALS['TL_DCA'][$this->objDraft->ptable]['fields'][$v]['options']))
			{
				$_v = $GLOBALS['TL_DCA'][$this->objDraft->ptable]['fields'][$v]['options'][$_v];
			}

			// Add the sorting field
			if ($_v != '')
			{
				$key = isset($GLOBALS['TL_LANG'][$this->objDraft->ptable][$v][0]) ? $GLOBALS['TL_LANG'][$this->objDraft->ptable][$v][0] : $v;
				$add[$key] = $_v;
			}
		}

		return $add;
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
			
			// limit by draft to avoid hacking attemps
			$this->import('Database');
			$strQuery = 'SELECT * FROM ' . $this->strTable . ' WHERE draftState != "" AND pid=? AND ptable=? AND ' . $this->Database->findInSet('id', $arrIds);
			$objResult = $this->Database->prepare($strQuery)->execute($this->objDraft->id, 'tl_drafts');

			while($objResult->next())
			{
				$objModel = new $this->strModel($objResult);
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
			
			$this->import('Database');
			$strQuery = 'SELECT * FROM ' . $this->strTable . ' WHERE draftState != "" AND pid=? AND ptable=? AND ' . $this->Database->findInSet('id', $arrIds);
			$objResult = $this->Database->prepare($strQuery)->execute($this->objDraft->id, 'tl_drafts');

			while($objResult->next())
			{
				$objModel = new $this->strModel($objResult);
				$this->resetDraft($objDc, $objModel, true);
			}
			
			$this->redirect($this->getReferer());
		}
		
		// not possible to use delete all at the moment
		$GLOBALS['TL_DCA'][$this->strTable]['config']['notDeletable'] = true;
		
		$arrAttributes['ptable'] = true;
		$arrAttributes['alexf'] = 'published';
		
		$strBuffer = '<input type="submit" class="tl_submit" name="resetDrafts" value="' . $GLOBALS['TL_LANG'][$this->strTable]['resetDrafts'] . '">';
		
		if($this->genericHasAccess($arrAttributes))
		{
			$strBuffer .= '<input type="submit" class="tl_submit" name="applyDrafts" value="' . $GLOBALS['TL_LANG'][$this->strTable]['applyDrafts'] . '">';
		}
		
		return $strBuffer;
	}

	
	/**
	 * initialize data container
	 */
	public function initialize()
	{
		$this->initializeDraft();
		$this->initializeModes();
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
		if($strTable != $this->strTable || !in_array(Input::get('do'), $GLOBALS['TL_CONFIG']['draftModules']))
		{
			return false;
		}
		
		$strClass = get_class($this);
		
		// GENERAL SETTINGS
		
		// register callbacks
		$GLOBALS['TL_DCA'][$this->strTable]['config']['onload_callback'][] 		= array($strClass, 'initialize');
		$GLOBALS['TL_DCA'][$this->strTable]['config']['onload_callback'][] 		= array($strClass, 'checkPermission');				
		$GLOBALS['TL_DCA'][$this->strTable]['config']['oncopy_callback'][] 		= array($strClass, 'onCopy');
		$GLOBALS['TL_DCA'][$this->strTable]['config']['oncreate_callback'][] 	= array($strClass, 'onCreate');
		$GLOBALS['TL_DCA'][$this->strTable]['config']['oncut_callback'][] 		= array($strClass, 'onCut');		
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
			'button_rules' 		=> array('validate:get:var=draft:is=1', 'switchMode', 'generate'),
		);
			
		$GLOBALS['TL_DCA'][$this->strTable]['list']['global_operations']['draft'] = array
		(
			'label' 			=> &$GLOBALS['TL_LANG'][$this->strTable]['draftmode'],
			'href' 				=> 'draft=1',
			'class'				=> 'header_draft',
			'button_callback' 	=> array($strClass, 'generateGlobalButtonDraft'),
			'button_rules' 		=> array('validate:get:var=draft:not=1', 'switchMode:draft', 'generate'),
		);
		
		$strAttributes = sprintf('onclick="Backend.openModalIframe({\'width\':770,\'title\':\'%s\',\'url\':this.href});'
								.'addSubmitButton(\'%s\');return false"', 
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
			// callbacks
			$GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['header_callback'] = array($strClass, 'generateParentHeader');
			$GLOBALS['TL_DCA'][$this->strTable]['edit']['buttons_callback'][] = array($strClass, 'generateSubmitButtons');
			
			// set ptable dynamically and store old ptable 
			$GLOBALS['TL_DCA'][$this->strTable]['config']['dtable'] = $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable'];
			$GLOBALS['TL_DCA'][$this->strTable]['config']['ptable'] = 'tl_drafts';
			
			// remove default permission callback for draft mode
			if($GLOBALS['TL_DCA'][$this->strTable]['config']['onload_callback'][0][1] == 'checkPermission')	
			{			
				$GLOBALS['TL_DCA'][$this->strTable]['config']['permission_rules'] = array('draftPermission:class=' . $GLOBALS['TL_DCA'][$this->strTable]['config']['onload_callback'][0][0]);
				unset($GLOBALS['TL_DCA'][$this->strTable]['config']['onload_callback'][0]);
			}
		
			// permission rules
			$arrRules = array('generic:key=[,reset,apply]', 'hasAccess:key=apply:alexf=published:ptable');
			
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
					'button_rules' 		=> array('hasAccess:alexf=published:ptable', 'draftState', 'generate'),
				),
			));
		}

		// LIVE MODE SETTINGS
		else
		{
			$arrRules = array('hasAccessOnPublished:act=[edit,delete,cut,copy,select,deleteAll,editAll,overrideAll,cutAll,copyAll]:ptable:alexf=published');
		}
		
		// PERMISSION RULES 
		if(is_array($GLOBALS['TL_DCA'][$this->strTable]['config']['permission_rules']))
		{
			$GLOBALS['TL_DCA'][$this->strTable]['config']['permission_rules'] = array_merge($GLOBALS['TL_DCA'][$this->strTable]['config']['permission_rules'], $arrRules);
		}
		else 
		{
			$GLOBALS['TL_DCA'][$this->strTable]['config']['permission_rules'] = $arrRules;
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
		if($this->blnDraftMode)
		{
			$arrState = array('new');
			$arrSet = array('tstamp' => time(), 'draftState' => $arrState, 'draftRelated' => null);
			$this->Database->prepare('UPDATE ' . $this->strTable . ' %s WHERE id=?')->set($arrSet)->execute($insertID);
		}
		elseif($this->objDraft !== null)
		{
			$strModel = $this->strModel;
			$objModel = $strModel::findByPK($insertID);
			
			$objNew = $this->prepareModel($objModel, true, true, true);
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
			$arrSet = array('draftState' => array('new'));
			$this->Database->prepare('UPDATE ' . $this->strTable . ' %s WHERE id=?')->set($arrSet)->execute($intId);
		}
		// create draft
		elseif($this->objDraft !== null)
		{
			$objModel = $this->prepareModel($arrSet, true);
			$objModel->save();
			
			$arrSet = array('draftRelated' => $objModel->id, 'tstamp' => time());
			$this->Database->prepare('UPDATE ' . $this->strTable . ' %s WHERE id=?')->set($arrSet)->execute($intId);
		}
	}
	
	
	/**
	 * keep sorting in sync or label changes
	 * 
	 * @param DC_Table
	 * @return void
	 */
	public function onCut($objDc)
	{
		if($this->objDraft === null)
		{
			return;
		}

		$strQuery 	= 'SELECT t.id, t.draftState, t.sorting FROM ' . $this->strTable . ' t'
					. ' LEFT JOIN ' . $this->strTable . ' j ON j.id = t.draftRelated'
					. ' WHERE t.pid=? AND t.ptable=? AND t.sorting != j.sorting';
							
		$objResult = $this->Database->prepare($strQuery)->execute($this->objDraft->id, 'tl_drafts');

		if($objResult->numRows < 1)
		{
			return;
		}
		
		while($objResult->next()) 
		{
			$arrSet = array();
			
			$objModel = new VersioningModel(new $this->strModel($objResult));
			
			if($this->blnDraftMode && !$this->hasState($objModel, 'sorted'))
			{
				$this->setState($objModel, 'sorted');
			}
			elseif(!$this->blnDraftMode)
			{
				$this->removeState($objModel, 'sorted');
				$arrSet['sorting'] = $objResult->sorting;
			}
			else 
			{
				continue;
			}
			
			$objModel->tstamp = time();
			$objModel->save();
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
		if($this->blnDraftMode)
		{
			// delete all mode, do nothing here. applyDraft is handling the delete task
			if(Input::post('IDS') != '')
			{
				return;
			}
			
			$objModel = new $this->strModel($objDc->activeRecord);

			// delete new elements
			if($this->hasState($objModel, 'new'))
			{
				return;
			}
			elseif(!$this->removeState($objModel, 'delete'))
			{
				$this->setState($objModel, 'delete');
			}
			
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
				$this->switchMode($objDc->activeRecord->draftRelated);
				$dc = new DC_Table($this->strTable);
				$dc->delete(true);
				$this->switchMode($objDc->id);
				return;
			}
			
			// store draft in tl_undo
			// TODO: handle ctable elements
			$strModel = $this->strModel;
			$objSave = $strModel::findOneBy('draftRelated', $objDc->id);
			
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
			$arrSet = array('draftState' => array('modified'), 'tstamp' => time);
			$this->Database->prepare('UPDATE ' . $strTable . ' %s WHERE id=?')->set($arrSet)->executeUncached($intId);
		}
		// create new version of draft
		elseif($this->objDraft !== null)
		{
			$objResult = $this->Database->prepare('SELECT id, draftRelated FROM ' . $this->strTable . ' WHERE id=?')->execute($intId);
			
			if($objResult->numRows == 1 && $objResult->draftRelated > 0)
			{
				$strModel = $this->strModel;
				$objModel = $this->prepareModel($strModel::findByPK($intId), true);
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

		// update label
		if($this->blnDraftMode)
		{
			$objModel = new $this->strModel($objDc->activeRecord);
			
			if($this->hasState($objModel, 'new') || $this->hasState($objModel, 'modified'))
			{
				return;
			}
			
			$this->setState($objModel, 'modified');
			$objModel->tstamp = time();
			$objModel->save();
		}
		
		// udate draft to newest live version
		elseif($this->objDraft !== null)
		{
			if($objDc->activeRecord->draftRelated != null)
			{
				$objModel = $this->prepareModel($objDc->activeRecord, true);
				$objModel->save();
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
		$strModel = $this->strModel;
		
		if($this->blnDraftMode)
		{
			$objModel = $strModel::findByPK($this->intId);

			if($blnVisible == ($objModel->invisible == '1') || $this->hasState($objModel, 'visibility'))
			{
				return $blnVisible;
			}
			
			$this->setState($objModel, 'visibility');
			$objModel->invisible = $blnVisible;
			$objModel->tstamp = time();
			$objModel->save();
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
			$objModel = $strModel::findOneBy($strField, $strField == 'id' ? $objDc->activeRecord->draftRelated : $this->intId);
			
			if($objModel !== null && $blnVisible != $objModel->invisible)
			{
				$objModel = new VersioningModel($objModel);
				$arrState = unserialize($objModel->draftState);
				
				$this->removeState($objModel, 'visibility');
				$objModel->invisible = $blnVisible;
				$objModel->tstamp = time();
				$objModel->save();
			}
		}
		
		return $blnVisible;
	}


	/**
	 * reset single draft
	 * 
	 * @param Model|null
	 */
	public function resetDraft($objDc, $objModel = null, $blnDoNoRedirect=false)
	{
		$strModel = $this->strModel;
		
		// try to find model
		if($objModel === null)
		{
			$this->initialize();
			$objModel = $strModel::findByPK($this->intId);
			
			if($objModel === null)
			{
				$this->triggerError('Invalid approach to reset draft. No draft found', 'resetDraft', $blnDoNoRedirect);
				return;
			}
		}
		
		$objModel = new VersioningModel($objModel);
		$objOriginal = $strModel::findOneBy('draftRelated', $objModel->id);
		$blnSave = false;

		// modified draft, reset to original
		if($this->hasState($objModel, 'modified')) 
		{
			$objNew = $this->prepareModel($objOriginal, true);
			$objNew->save();
		}
		
		// delete new one
		elseif($this->hasState($objModel, 'new'))
		{
			// use existing driver
			if($this->intId == $objModel->id)
			{
				$objDc->delete(true);
			}
			else
			{
				// let's use dc_table so undo record is created
				Input::setGet('id', $objModel->id);
				Input::setGet('act', 'delete');
				$dc = new DC_Table($this->strTable);
				$dc->delete(true);	
				Input::setGet('id', $this->intId);
			}
		}
		
		else
		{
			// just reset draft state
			if($this->hasState($objModel, 'delete') || $objOriginal === null)
			{
				$blnSave = true;
			}
			
			// sorting changed, reset to original
			elseif($this->hasState($objModel, 'sorted')) 
			{
				$objModel->sorting = $objOriginal->sorting;
				$blnSave = true;
			}
			
			// sorting changed, reset to original
			elseif($this->hasState($objModel, 'visibility')) 
			{
				$objModel->invisible = $objOriginal->invisible;
				$blnSave = true;
			}
			
			if($blnSave) 
			{
				$objModel->tstamp = time();
				$objModel->draftState = '';
				$objModel->save();
			}
		}
		
		if(!$blnDoNoRedirect)
		{
			$this->redirect($this->getReferer());
		}
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
		if($this->objDraft === null || !in_array('tasks', $this->Config->getActiveModules()) || !$GLOBALS['TL_CONFIG']['draftsUseTaskModule'])
		{
			return false;
		}
		
		$arrAttributes['plain'] = true;
		$arrAttributes['__set__'][] = 'plain';

		$strHref = 'system/modules/drafts/task.php?id=' . $this->objDraft->id . '&rt=' . REQUEST_TOKEN;
		return true;
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
		$arrState = unserialize($arrRow['draftState']);
		
		if(isset($arrAttributes['modified']))
		{
			return  (is_array($arrState) && in_array('modified', $arrState));
		}
		
		return (is_array($arrState) && !empty($arrState));
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
		$arrAttributes['ptable'] = true;
		$arrAttributes['alexf'] = 'published';
		
		// grant access if not published
		if(!$this->isPublished($arrRow))
		{
			return true;
		}
		
		if($arrRow === null || isset($arrAttributes['hide']))
		{
			return $this->genericHasAccess($arrAttributes);
		}
		
		$arrAttributes['rule'] = 'hasAccess';
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
		if($this->objDraft === null && !$this->isPublished())
		{
			return false;
		}
		
		if(isset($arrAttributes['draft']))
		{
			$strHref .= '&table=' . $this->strTable . (($this->objDraft === null) ? ('&mode=create&id=' . $this->intId) : ('&id=' . $this->objDraft->id));
			return true;
		}

		$strHref .= '&table=' . $this->strTable . '&id=' . $this->objDraft->pid;
		return true;
	}
	
	
	/**
	 * validate values
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
	protected function buttonRuleValidate(&$strButton, &$strHref, &$strLabel, &$strTitle, &$strIcon, &$strAttributes, &$arrAttributes, $arrRow=null)
	{
		if(isset($arrAttributes['get']))
		{
			$strValue = \Input::get($arrAttributes['var']);
		}
		elseif(isset($arrAttributes['post']))
		{
			$strValue = \Input::post($arrAttributes['var']);
		}
		elseif(isset($arrAttributes['row']))
		{
			$strValue = $arrRow[$arrAttributes['var']];
		}
		elseif(isset($arrAttributes['ptable']))
		{
			$this->import('Database');
			
			$strTable = ($arrRow !== null && isset($arrRow['ptable'])) ? $arrRow['ptable'] : $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable'];
			
			if($strTable == 'tl_drafts')
			{
				$strTable = $GLOBALS['TL_DCA'][$this->strTable]['config']['dtable'];
			}

			$strQuery = sprintf('SELECT * FROM %s WHERE id=%s',
				$strTable, $arrRow === null ? $this->intId : $arrRow['id']
			);
			
			$objResult = $this->Database->query($strQuery);
			$strValue = $objResult->{$arrAttributes['var']};
		}
		else 
		{
			return false;
		}
			
		if(isset($arrAttributes['is']))
		{
			return $strValue == $arrAttributes['is'];
		}
			
		if(isset($arrAttributes['not']))
		{
			return $strValue != $arrAttributes['not'];
		}
		
		return false;
	}

	/**
	 * check if model has a state
	 * 
	 * @param Model
	 * @param string 
	 */
	protected function hasState($objModel, $strState)
	{
		if(!is_array($objModel->draftState))
		{
			$arrData = unserialize($objModel->draftState);
			$objModel->draftState = is_array($arrData) ? $arrData : array();
		}
		
		return in_array($strState, $objModel->draftState);
	}
	
	
	/**
	 * create initial draft version
	 * 
	 * @return void
	 */
	protected function initializeDraft()
	{
		// try to find draft in live mode
		if(!$this->blnDraftMode)
		{
			if(in_array($this->strAction, array(null, 'select', 'create')) || ($this->strAction == 'paste' && Input::get('mode') == 'create'))
			{
				$this->objDraft = DraftsModel::findOneByPidAndTable($this->intId, $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable']);
			}
			else 
			{		
				$strQuery = 'SELECT * FROM tl_drafts WHERE pid=(SELECT pid FROM ' . $this->strTable. ' WHERE id=?) AND ptable=?';
				$objResult = $this->Database->prepare($strQuery)->execute($this->intId, $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable']);
				
				if($objResult->numRows == 1)
				{
					$this->objDraft = new DraftsModel($objResult);
				}
			}
			
			return;
		}
		
		// create draft
		elseif(Input::get('mode') == 'create' && $this->strAction == null)
		{
			if($this->objDraft === null)
			{
				$this->objDraft = new DraftsModel;
				$this->objDraft->pid = $this->intId;
				$this->objDraft->ptable = $GLOBALS['TL_DCA'][$this->strTable]['config']['dtable'];
				$this->objDraft->tstamp = time();
				$this->objDraft->ctable = $this->strTable;
				$this->objDraft->module = Input::get('do');
				$this->objDraft->save();

				$objResult = $this->Database->prepare('SELECT * FROM ' . $this->strTable . ' WHERE pid=? AND ptable=?')->execute($this->objDraft->pid, $this->objDraft->ptable);
				
				while($objResult->next())
				{
					$objModel = new $this->strModel($objResult);
					
					$objNew = $this->prepareModel($objModel, true);
					$objNew->save(true);
					
					$objModel->draftRelated = $objNew->id;
					$objModel->tstamp = time();
					$objModel->save();
				}
			}
			
			$this->redirect('contao/main.php?do=' . Input::get('do') . '&table=' . $this->strTable . '&draft=1&id=' . $this->objDraft->id .'&rt=' . REQUEST_TOKEN);
		}

		// find by child id
		elseif(!in_array($this->strAction, array(null, 'select', 'create')) && !($this->strAction == 'paste' && Input::get('mode') == 'create'))
		{		
			$this->objDraft = DraftsModel::findOneByChildIdAndTable($this->intId, $this->strTable);
		}
		else
		{
			$this->objDraft = DraftsModel::findByPK($this->intId);
		}		

		if($this->objDraft === null)
		{
			$this->triggerError('No Draft Model found', initializeDraft);
		}
	}
	
	
	/**
	 * initial draft mode
	 */
	protected function initializeModes()
	{
		// set session data for draft mode
		if($this->blnDraftMode)
		{
			if($this->strAction == null)
			{
				// set session data
				$this->import('Session');
				$session = $this->Session->get('referer');
				
				if(\Environment::get('requestUri') != $session['current'])
				{
					$session['last'] = $session['current'];
				}
				$session['current'] = \Environment::get('requestUri');
				$this->Session->set('referer', $session);
			}

			return;
		}
		
		$arrAttributes['ptable'] = true;
		$arrAttributes['alexf'] = 'published';
		$blnHasAccess = $this->genericHasAccess($arrAttributes);
		
		if($this->strAction != 'task' && $this->strAction != 'show' && Input::post('isAjaxRequest') == '')
		{
			$intState = !$this->isPublished() ? 2 : ($blnHasAccess ? 1 : 0);
			\Message::addRaw('<p class="tl_warning">' . $GLOBALS['TL_LANG'][$this->strTable]['livemodewarning'][$intState] . '</p>');
		}

		if($blnHasAccess || !$this->isPublished())
		{
			return;
		}
		
		// close table if user has no access to insert new content element
		$GLOBALS['TL_DCA'][$this->strTable]['config']['closed'] = true;
		$GLOBALS['TL_DCA'][$this->strTable]['config']['notEditable'] = true;
	
		// disable sorting by adding space so Contao can not detect it as sortable
		if($this->isPublished() && $GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['fields'][0] == 'sorting')
		{
			$GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['fields'][0] = 'sorting ';
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
		if($this->blnIsPublished !== null)
		{
			return $this->blnIsPublished;
		}
		
		if($this->blnDraftMode)
		{
			if($this->objDraft === null)
			{
				$this->blnIsPublished = true;
				return true;
			}
			
			$strQuery = 'SELECT published FROM ' . $GLOBALS['TL_DCA'][$this->strTable]['config']['dtable'] . ' WHERE id=' . $this->objDraft->pid;
		}
		else
		{
			$strQuery = 'SELECT published FROM ' . $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable'] . ' WHERE id=' . $this->intId;
		}
		
		$objResult = $this->Database->query($strQuery);
		$this->blnIsPublished = ($objResult->published == '1' ? true : false);
		
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
		if(!$this->isPublished())
		{
			return true;
		}
		
		return $this->permissionRuleHasAccess($objDc, $arrAttributes, $strError);
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
		$strModel = $this->strModel;
		
		// Live mode
		
		// permission is already granted by default dca checkPermission 
		// Prepare permission checking for draft mode
		if(!$this->blnDraftMode)
		{
			if(!in_array($this->strAction, array(null, 'select', 'create', 'toggle')))
			{
				return true;
			}
			
			$arrPerm = $this->Session->get('draftPermission');
			if($arrPerm[Input::get('do')][$this->objDraft->ptable] === null || !is_array($arrPerm[Input::get('do')][$this->objDraft->ptable]))
			{
				$arrPerm[Input::get('do')][$this->objDraft->ptable] = array();
			}

			$arrPerm[Input::get('do')][$this->objDraft->ptable][$this->objDraft->pid] = true;
			$this->Session->set('draftPermission', $arrPerm);
			
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
		$arrPerm = $this->Session->get('draftPermission');
		if(Input::get('key') != '' || isset($arrPerm[Input::get('do')][$this->objDraft->ptable][$this->objDraft->pid]))
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
				$objModel = $strModel::findOneBy('draftRelated', Input::get('tid'));
				
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
		$objDraft = $strModel::findByPK($this->intId);
		$objModel = $strModel::findByPK($objDraft->draftRelated);
		
		if($objModel === null)
		{
			if($this->hasState($objDraft, 'new'))
			{
				return true;
			}
			
			if($this->strAction != 'paste' && !($this->strAction == 'create' && Input::get('mode') != '') && $this->strAction != 'copy')
			{
				$this->setState($objDraft, 'new');
				$objDraft->tstamp = time();
				$objDraft->save();
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
				$objModel = $strModel::findOneBy('draftRelated', $intPid);
				
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
	 * create a new model by cloning a reference and replace
	 * 
	 * @param \Model|array model or result array
	 * @param bool true if new model is a draft
	 * @param bool switch id and draftRelated
	 * @param bool true if forcing a new model 
	 * @param bool true is versioning shall be used
	 */
	protected function prepareModel($objCopy, $blnDraft=false, $blnSwitchIds=true, $blnNew=false, $blnVersioning=true)
	{
		if(is_array($objCopy))
		{
			$objNew = new $this->strModel();
			$objNew->setRow($objCopy);
		}
		elseif($objCopy instanceof Result)
		{
			$objNew = new $this->strModel($objCopy);
		}
		else
		{
			$objNew = clone $objCopy;
		}
		
		// versioning
		if($blnVersioning)
		{
			if(!$objNew instanceof VersioningModel)
			{
				$objNew = new VersioningModel($objNew);
			}
		}
		elseif($objNew instanceof VersioningModel)
		{
			$objNew = $objNew->getModel();
		}
		
		// id and draft related
		if($blnSwitchIds)
		{
			$objNew->id = $blnNew ? null : $objCopy->draftRelated;
			$objNew->draftRelated = $objCopy->id;
		}
		elseif($blnNew)
		{
			$objNew->id = null;
		}
		
		// pid and ptable
		if($blnDraft)
		{
			$objNew->pid = $this->objDraft->id;
			$objNew->ptable = 'tl_drafts';
		}
		else
		{
			$objNew->pid = $this->objDraft->pid;
			$objNew->ptable = $this->objDraft->ptable;
		}
		
		// empty draft state
		if(!$blnDraft)
		{
			$objNew->draftState = '';
		}
		
		$objNew->tstamp = time();
		return $objNew;
	}


	/**
	 * semove state from model
	 * 
	 * @param Model
	 * @param string
	 * @return bool
	 */
	protected function removeState($objModel, $strState)
	{
		if($this->hasState($objModel, $strState))
		{
			$intPos = array_search($strState, $objModel->draftState);
			$arrState = $objModel->draftState;
			unset($arrState[$intPos]);			
			$objModel->draftState = $arrState;
			return true;
		}
		
		return false;
	}


	/**
	 * set state of model
	 * 
	 * @param Model
	 * @param string 
	 */
	protected function setState($objModel, $strState)
	{
		if(!$this->hasState($objModel, $strState))
		{
			$arrState = $objModel->draftState;
			$arrState[] = $strState;
			$objModel->draftState = $arrState;
		}
	}


	/**
	 * switch to other mode
	 * this is neccesary loading DC_Table
	 * 
	 * @param int|null id
	 * @param string|null action
	 */
	protected function switchMode($intId=null, $strAct=null)
	{
		if($this->blnDraftMode)
		{
			$GLOBALS['TL_DCA'][$this->strTable]['config']['ptable'] = $GLOBALS['TL_DCA'][$this->strTable]['config']['dtable'];
			Input::setGet('draft', '0');
		}
		else
		{
			$GLOBALS['TL_DCA'][$this->strTable]['config']['dtable'] = $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable'];
			$GLOBALS['TL_DCA'][$this->strTable]['config']['ptable'] = 'tl_drafts';
			Input::setGet('draft', '1');
		}
		
		$this->blnDraftMode = !$this->blnDraftMode;
		Input::setGet('id', $intId === null ? $this->intId : $intId);
		
		if($strAct !== null)
		{
			$strMode = ($strAct == 'apply' || $strAct == 'reset') ? 'key' : 'act';
			Input::setGet($strMode, $strAct);
		}
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
