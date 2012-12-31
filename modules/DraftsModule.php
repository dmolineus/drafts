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

namespace Netzmacht\Drafts\Module;
use BackendModule, Input, DraftsModel;


/**
 * 
 */
class DraftsModule extends BackendModule
{
	protected $objDraft;
	
	/**
	 * create task if it does not exists and redirect
	 */
	public function createTask()
	{
		// clean task references
		$this->import('Database');
		$this->Database->query('UPDATE tl_drafts d SET taskid="" WHERE taskid>0 AND NOT EXISTS (SELECT id FROM tl_task WHERE id = d.taskid)');
		
		$this->objDraft = DraftsModel::findByPK(Input::get('id'));
		
		if($this->objDraft === null)
		{
			$this->log('No Draft with id "' .Input::get('id'). '" found', 'DraftsModule createTask()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}
				
		if($this->objDraft->taskid == '0' || $this->objDraft->taskid == '')
		{
			$this->import('BackendUser', 'User');
			$this->loadLanguageFile('tl_drafts');
			$objResult = $this->Database->query('SELECT * FROM ' . $this->objDraft->ptable . ' WHERE id=' . $this->objDraft->pid);
			
			$strTitle = sprintf($GLOBALS['TL_LANG']['tl_drafts']['draftTaskTitle'],
				$GLOBALS['TL_LANG']['MOD'][Input::get('do')][0],
				$this->objDraft->pid,
				$objResult->title != '' ? $objResult->title : $GLOBALS['TL_LANG']['tl_drafts']['draftTaskNoTitle']
			); 
			
			

			// Insert task
			$arrSet = array
			(
				'tstamp' => time(),
				'createdBy' => $this->User->id,
				'title' => $strTitle
			);

			$objTask = $this->Database->prepare("INSERT INTO tl_task %s")->set($arrSet)->execute();
			$this->objDraft->taskid = $objTask->insertId;
			$this->objDraft->save();
		}
		
		$this->redirect('contao/main.php?do=tasks&act=edit&id=' . $this->objDraft->taskid . '&rt=' . REQUEST_TOKEN);
	}


	/*
	 * 
	 */
	protected function compile()
	{
		
	} 
}