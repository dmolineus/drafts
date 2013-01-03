<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2012 Leo Feyer
 * 
 * @package Core
 * @link    http://contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * Initialize the system
 */
define('TL_MODE', 'BE');
require_once '../../initialize.php';


/**
 * Class DiffController
 *
 * Show the difference between two versions of a record.
 * @copyright  Leo Feyer 2005-2012
 * @author     Leo Feyer <http://contao.org>
 * @package    Core
 */
class TaskController extends Backend
{

	/**
	 * Initialize the controller
	 *
	 * 1. Import the user
	 * 2. Call the parent constructor
	 * 3. Authenticate the user
	 * 4. Load the language files
	 * DO NOT CHANGE THIS ORDER!
	 */
	public function __construct()
	{
		$this->import('BackendUser', 'User');
		parent::__construct();

		$this->User->authenticate();
		$this->loadLanguageFile('default');
	}


	/**
	 * Run the controller
	 */
	public function run()
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
		
		$this->import('BackendUser', 'User');
				
		if($this->objDraft->taskid == '0' || $this->objDraft->taskid == '')
		{
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
		// hotfix: switch the created id to the current user, otherwise it is not possible to edit it
		// see: https://github.com/cliffparnitzky/TaskCenter/issues/13
		else 
		{
			$this->Database->query('UPDATE tl_task SET createdBy=' . $this->User->id . ' WHERE id=' . $this->objDraft->taskid);		
		}
		
		Input::setGet('do', 'tasks');
		Input::setGet('act', 'edit');
		Input::setGet('id', $this->objDraft->taskid);
		
		TemplateLoader::addFiles(array 
		(
			'be_task_edit'	=> 'system/modules/drafts/templates/'
		));
		
		$this->Template = new BackendTemplate('be_drafts_task');
		$this->getBackendModule('tasks');
		// Template variables
		$this->Template->theme = $this->getTheme();
		$this->Template->base = Environment::get('base');
		$this->Template->language = $GLOBALS['TL_LANGUAGE'];
		$this->Template->title = specialchars($GLOBALS['TL_LANG']['MSC']['filepicker']);
		$this->Template->charset = $GLOBALS['TL_CONFIG']['characterSet'];

		$GLOBALS['TL_CONFIG']['debugMode'] = false;
		$this->Template->output();
	}
}


/**
 * Instantiate the controller
 */
$objController = new TaskController();
$objController->run();
