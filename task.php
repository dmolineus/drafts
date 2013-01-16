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
		$this->import('Database');
			
		$this->loadLanguageFile('default');
		$this->loadLanguageFile('modules');
		$this->loadLanguageFile('tl_task');
	}


	/**
	 * Run the controller
	 */
	public function run()
	{
		$strTable = Input::get('table');
		$strModule = Input::get('do');
		$intId = Input::get('id');
		 
		if(!strlen($strTable) || !strlen($intId) || !strlen($strModule))
		{
			$this->log('Required attributes not set', 'TaskController run()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}
		
		// load Data Container so that permission is check
		$this->loadDataContainer($strTable);		
		$dc = new DC_Table($strTable);

		$objTask = $this->Database->prepare('SELECT id FROM tl_task WHERE draftPid=? AND draftPtable=?')->execute($intId, $strTable);			
				
		if($objTask->numRows < 1)
		{			
			$objResult = $this->Database->prepare('SELECT * FROM ' . $strTable . ' WHERE id=?')->execute($intId);
			
			$strTitle = $objResult->title === null ? ($objResult->headline === null ? '' : $objResult->headline) : $objResult->title;
			$strTitle = sprintf($GLOBALS['TL_LANG']['tl_task']['draftTaskTitle'],
				$GLOBALS['TL_LANG']['MOD'][$strModule][0],
				$intId,
				$strTitle != '' ? $strTitle : $GLOBALS['TL_LANG']['tl_task']['draftTaskNoTitle']
			); 
			
			// Insert task
			$arrSet = array
			(
				'tstamp' => time(),
				'createdBy' => $this->User->id,
				'title' => $strTitle,
				'draftPid' => $intId,
				'draftPtable' => $strTable,
				'draftModule' => $strModule,
				'deadline'	=> time() + $GLOBALS['TL_CONFIG']['draftTaskDefaultDeadline'] * 86400,
			);

			$objTask = $this->Database->prepare("INSERT INTO tl_task %s")->set($arrSet)->execute();
			$intTaskId = $objTask->insertId;
			
		}
		else
		{
			$intTaskId = $objTask->id;
		}
		
		Input::setGet('do', 'tasks');
		Input::setGet('act', 'edit');
		Input::setGet('table', null);
		Input::setGet('id', $intTaskId);
		
		TemplateLoader::addFiles(array 
		(
			'be_task_edit'	=> 'system/modules/drafts/templates/'
		));
		
		$this->Template = new BackendTemplate('be_drafts_task');
		$this->getBackendModule('tasks');
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
