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


/**
 * Class ModuleTasks
 *
 * Back end module "tasks".
 * @copyright  Leo Feyer 2005-2012
 * @author     Leo Feyer <http://www.contao.org>
 * @package    Controller
 */
class ModuleTasks extends Contao\ModuleTasks
{
	
	/**
	 * check permission for current task
	 * 
	 * @param object task
	 * @param bool redirect to error page
	 * @param string error action
	 * @param bool
	 */
	protected function checkPermission($objTask, $blnRedirect=false, $strErrorAction='access')
	{
		if($GLOBALS['TL_CONFIG']['draftUseTaskModule'] && $objTask->draftsid > 0)
		{
			$objDraft = DraftsModel::findByPK($objTask->draftsid);
			
			if($objDraft === null)
			{
				$this->Database->query('UPDATE tl_task SET draftsid=0 WHERE id=' . $objTask->id);
				return true;
			}
			
			$intPerm = $this->Session->get('draftPermission');
			
			if($intPerm == $objDraft->id)
			{
				return true;
			}
			elseif($blnRedirect && \Input::get('redirect') != '2')
			{
				$this->redirect(sprintf('contao/main.php?do=%s&table=%s&id=%s&redirect=task&taskid=%s&rt=%s', $objDraft->module, $objDraft->ctable, $objDraft->pid, $objTask->id, REQUEST_TOKEN));
			}
			
			
		}
		elseif($this->User->isAdmin || $this->User->id == $objTask->createdBy)
		{
			return true;
		}
		
		if($blnRedirect)
		{
			$this->log('Not enough permissions to ' . $strErrorAction . ' task ID "' . \Input::get('id') . '"', 'ModuleTask editTask()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}
		
		return false;		
	}
	
	
	/**
	 * Generate the module
	 * @return void
	 */
	protected function compile()
	{
		$this->import('BackendUser', 'User');
		$this->loadLanguageFile('tl_task');

		// Check the request token (see #4007)
		if (isset($_GET['act']))
		{
			if (!isset($_GET['rt']) || !\RequestToken::validate(\Input::get('rt')))
			{
				$this->Session->set('INVALID_TOKEN_URL', $this->Environment->request);
				$this->redirect('contao/confirm.php');
			}
		}

		// Dispatch
		switch (\Input::get('act'))
		{
			case 'create':
				$this->createTask();
				break;

			case 'edit':
				$this->editTask();
				break;

			case 'delete':
				$this->deleteTask();
				break;

			default:
				$this->showAllTasks();
				break;
		}

		$this->Template->request = ampersand($this->Environment->request, true);

		// plugins folder does not exists anymore in contao 3
		// Add the CSS and JavaScript files
		//$GLOBALS['TL_CSS'][] = 'plugins/mootools/tablesort/css/tablesort.css';
		//$GLOBALS['TL_MOOTOOLS'][] = '<script src="' . TL_PLUGINS_URL . 'plugins/mootools/tablesort/js/tablesort.js"></script>';
	}


	/**
	 * Show all tasks
	 * @return void
	 */
	protected function showAllTasks()
	{
		$this->Template->tasks = array();

		// Clean up
		$this->Database->execute("DELETE FROM tl_task WHERE tstamp=0");
		$this->Database->execute("DELETE FROM tl_task_status WHERE tstamp=0");
		$this->Database->execute("DELETE FROM tl_task_status WHERE pid NOT IN(SELECT id FROM tl_task)");

		// Set default variables
		$this->Template->apply = $GLOBALS['TL_LANG']['MSC']['apply'];
		$this->Template->noTasks = $GLOBALS['TL_LANG']['tl_task']['noTasks'];
		$this->Template->createTitle = $GLOBALS['TL_LANG']['tl_task']['new'][1];
		$this->Template->createLabel = $GLOBALS['TL_LANG']['tl_task']['new'][0];
		$this->Template->editLabel = $GLOBALS['TL_LANG']['tl_task']['edit'][0];
		$this->Template->deleteLabel = $GLOBALS['TL_LANG']['tl_task']['delete'][0];

		$this->Template->thTitle = $GLOBALS['TL_LANG']['tl_task']['title'][0];
		$this->Template->thAssignedTo = $GLOBALS['TL_LANG']['tl_task']['assignedTo'];
		$this->Template->thStatus = $GLOBALS['TL_LANG']['tl_task']['status'][0];
		$this->Template->thProgress = $GLOBALS['TL_LANG']['tl_task']['progress'][0];
		$this->Template->thDeadline = $GLOBALS['TL_LANG']['tl_task']['deadline'][0];

		$this->Template->createHref = $this->addToUrl('act=create');

		// Get task object
		if (($objTask = $this->getTaskObject()) != true)
		{
			return;
		}

		$count = -1;
		$time = time();
		$max = ($objTask->numRows - 1);
		$arrTasks = array();

		// List tasks
		while ($objTask->next())
		{
			$trClass = 'row_' . ++$count . (($count == 0) ? ' row_first' : '') . (($count >= $max) ? ' row_last' : '') . (($count % 2 == 0) ? ' odd' : ' even');
			$tdClass = '';

			// Completed
			if ($objTask->status == 'completed')
			{
				$tdClass .= ' completed';
			}

			// Due
			elseif ($objTask->deadline < $time)
			{
				$tdClass .= ' due';
			}

			$deleteHref = '';
			$deleteTitle = '';
			$deleteIcon = TL_FILES_URL . 'system/themes/' . $this->getTheme() . '/images/delete_.gif';
			$deleteConfirm = '';

			// Check delete permissions
			if ($this->checkPermission($objTask))
			{
				$deleteHref = $this->addToUrl('act=delete&amp;id=' . $objTask->id);
				$deleteTitle = sprintf($GLOBALS['TL_LANG']['tl_task']['delete'][1], $objTask->id);
				$deleteIcon = TL_FILES_URL . 'system/themes/' . $this->getTheme() . '/images/delete.gif';
				$deleteConfirm = sprintf($GLOBALS['TL_LANG']['tl_task']['delConfirm'], $objTask->id);
			}

			$arrTasks[] = array
			(
				'id' => $objTask->id,
				'user' => $objTask->name,
				'title' => $objTask->title,
				'progress' => $objTask->progress,
				'deadline' => $this->parseDate($GLOBALS['TL_CONFIG']['dateFormat'], $objTask->deadline),
				'status' => $GLOBALS['TL_LANG']['tl_task_status'][$objTask->status] ?: $objTask->status,
				'creator' => sprintf($GLOBALS['TL_LANG']['tl_task']['createdBy'], $objTask->creator),
				'editHref' => $this->addToUrl('act=edit&amp;id=' . $objTask->id),
				'editTitle' => sprintf($GLOBALS['TL_LANG']['tl_task']['edit'][1], $objTask->id),
				'editIcon' => TL_FILES_URL . 'system/themes/' . $this->getTheme() . '/images/edit.gif',
				'deleteHref' => $deleteHref,
				'deleteTitle' => $deleteTitle,
				'deleteIcon' => $deleteIcon,
				'deleteConfirm' => $deleteConfirm,
				'trClass' => $trClass,
				'tdClass' => $tdClass
			);
		}

		$this->Template->tasks = $arrTasks;
	}


	/**
	 * Create a task
	 * @return void
	 */
	protected function createTask()
	{
		$this->Template = new \BackendTemplate('be_task_create');
		$fs = $this->Session->get('fieldset_states');

		$this->Template->titleClass = (isset($fs['tl_tasks']['title_legend']) && !$fs['tl_tasks']['title_legend']) ? ' collapsed' : '';
		$this->Template->assignClass = (isset($fs['tl_tasks']['assign_legend']) && !$fs['tl_tasks']['assign_legend']) ? ' collapsed' : '';
		$this->Template->statusClass = (isset($fs['tl_tasks']['status_legend']) && !$fs['tl_tasks']['status_legend']) ? ' collapsed' : '';
		$this->Template->historyClass = (isset($fs['tl_tasks']['history_legend']) && !$fs['tl_tasks']['history_legend']) ? ' collapsed' : '';

		$this->Template->title = $this->getTitleWidget();
		$this->Template->deadline = $this->getDeadlineWidget();
		$this->Template->assignedTo = $this->getAssignedToWidget();
		$this->Template->notify = $this->getNotifyWidget();
		$this->Template->comment = $this->getCommentWidget();

		$this->Template->goBack = $GLOBALS['TL_LANG']['MSC']['goBack'];
		$this->Template->headline = $GLOBALS['TL_LANG']['tl_task']['new'][1];
		$this->Template->submit = $GLOBALS['TL_LANG']['tl_task']['createSubmit'];
		$this->Template->titleLabel = $GLOBALS['TL_LANG']['tl_task']['title'][0];
		$this->Template->assignLabel = $GLOBALS['TL_LANG']['tl_task']['assignedTo'];
		$this->Template->statusLabel = $GLOBALS['TL_LANG']['tl_task']['status'][0];

		// Create task
		if (\Input::post('FORM_SUBMIT') == 'tl_tasks' && $this->blnSave)
		{
			$time = time();
			$deadline = new \Date($this->Template->deadline->value, $GLOBALS['TL_CONFIG']['dateFormat']);

			// Insert task
			$arrSet = array
			(
				'tstamp' => $time,
				'createdBy' => $this->User->id,
				'title' => $this->Template->title->value,
				'deadline' => $deadline->dayBegin
			);

			$objTask = $this->Database->prepare("INSERT INTO tl_task %s")->set($arrSet)->execute();
			$insertId = $objTask->insertId;

			// Insert status
			$arrSet = array
			(
				'pid' => $insertId,
				'tstamp' => $time,
				'assignedTo' => $this->Template->assignedTo->value,
				'comment' => trim($this->Template->comment->value),
				'status' => 'created',
				'progress' => 0
			);

			$this->Database->prepare("INSERT INTO tl_task_status %s")->set($arrSet)->execute();

			// Notify user
			if (\Input::post('notify'))
			{
				$objUser = $this->Database->prepare("SELECT email FROM tl_user WHERE id=?")
										  ->limit(1)
										  ->execute($this->Template->assignedTo->value);

				if ($objUser->numRows)
				{
					$objEmail = new \Email();

					$objEmail->from = $GLOBALS['TL_ADMIN_EMAIL'];
					$objEmail->fromName = $GLOBALS['TL_ADMIN_NAME'];
					$objEmail->subject = $this->Template->title->value;

					$objEmail->text = trim($this->Template->comment->value);
					$objEmail->text .= sprintf($GLOBALS['TL_LANG']['tl_task']['message'], $this->User->name, $this->Environment->base . 'contao/main.php?do=tasks&act=edit&id=' . $insertId);

					$objEmail->sendTo($objUser->email);
				}
			}

			// Go back
			$this->redirect('contao/main.php?do=tasks');
		}
	}


	/**
	 * Edit a task
	 * @return void
	 */
	protected function editTask()
	{
		$this->Template = new \BackendTemplate('be_task_edit');
		$fs = $this->Session->get('fieldset_states');

		$this->Template->titleClass = (isset($fs['tl_tasks']['title_legend']) && !$fs['tl_tasks']['title_legend']) ? ' collapsed' : '';
		$this->Template->assignClass = (isset($fs['tl_tasks']['assign_legend']) && !$fs['tl_tasks']['assign_legend']) ? ' collapsed' : '';
		$this->Template->statusClass = (isset($fs['tl_tasks']['status_legend']) && !$fs['tl_tasks']['status_legend']) ? ' collapsed' : '';
		$this->Template->historyClass = (isset($fs['tl_tasks']['history_legend']) && !$fs['tl_tasks']['history_legend']) ? ' collapsed' : '';

		$this->Template->goBack = $GLOBALS['TL_LANG']['MSC']['goBack'];
		$this->Template->headline = sprintf($GLOBALS['TL_LANG']['tl_task']['edit'][1], \Input::get('id'));

		$objTask = $this->Database->prepare("SELECT *, (SELECT name FROM tl_user u WHERE u.id=t.createdBy) AS creator FROM tl_task t WHERE id=?")
								  ->limit(1)
								  ->execute(\Input::get('id'));

		if ($objTask->numRows < 1)
		{
			$this->log('Invalid task ID "' . \Input::get('id') . '"', 'ModuleTask editTask()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}

		// Check if the user is allowed to edit the task
		$this->checkPermission($objTask, true, 'edit');

		// Advanced options
		$this->blnAdvanced = ($this->User->isAdmin || $objTask->createdBy == $this->User->id);
		$this->Template->advanced = $this->blnAdvanced;

		$this->Template->title = $this->blnAdvanced ? $this->getTitleWidget($objTask->title) : $objTask->title;
		$this->Template->deadline = $this->blnAdvanced ? $this->getDeadlineWidget($this->parseDate($GLOBALS['TL_CONFIG']['dateFormat'], $objTask->deadline)) : $this->parseDate($GLOBALS['TL_CONFIG']['dateFormat'], $objTask->deadline);

		$arrHistory = array();

		// Get the status
		$objStatus = $this->Database->prepare("SELECT *, (SELECT name FROM tl_user u WHERE u.id=s.assignedTo) AS name FROM tl_task_status s WHERE pid=? ORDER BY tstamp")
									->execute(\Input::get('id'));

		while($objStatus->next())
		{
			$arrHistory[] = array
			(
				'creator' => $objTask->creator,
				'date' => $this->parseDate($GLOBALS['TL_CONFIG']['dateFormat'], $objStatus->tstamp),
				'status' => $GLOBALS['TL_LANG']['tl_task_status'][$objStatus->status] ?: $objStatus->status,
				'comment' => (($objStatus->comment != '') ? nl2br_html5($objStatus->comment) : '&nbsp;'),
				'assignedTo' => $objStatus->assignedTo,
				'progress' => $objStatus->progress,
				'class' => $objStatus->status,
				'name' => $objStatus->name
			);
		}

		$this->Template->assignedTo = $this->getAssignedToWidget($objStatus->assignedTo);
		$this->Template->notify = $this->getNotifyWidget();
		$this->Template->status = $this->getStatusWidget($objStatus->status, $objStatus->progress);
		$this->Template->progress = $this->getProgressWidget($objStatus->progress);
		$this->Template->comment = $this->getCommentWidget();

		// Update task
		if (\Input::post('FORM_SUBMIT') == 'tl_tasks' && $this->blnSave)
		{
			// Update task
			if ($this->blnAdvanced)
			{
				$deadline = new \Date($this->Template->deadline->value, $GLOBALS['TL_CONFIG']['dateFormat']);

				$this->Database->prepare("UPDATE tl_task SET title=?, deadline=? WHERE id=?")
							   ->execute($this->Template->title->value, $deadline->dayBegin, \Input::get('id'));
			}

			// Insert status
			$arrSet = array
			(
				'pid' => \Input::get('id'),
				'tstamp' => time(),
				'assignedTo' => $this->Template->assignedTo->value,
				'status' => $this->Template->status->value,
				'progress' => (($this->Template->status->value == 'completed') ? 100 : $this->Template->progress->value),
				'comment' => trim($this->Template->comment->value)
			);

			$this->Database->prepare("INSERT INTO tl_task_status %s")->set($arrSet)->execute();

			// Notify user
			if (\Input::post('notify'))
			{
				$objUser = $this->Database->prepare("SELECT email FROM tl_user WHERE id=?")
										  ->limit(1)
										  ->execute($this->Template->assignedTo->value);

				if ($objUser->numRows)
				{
					$objEmail = new \Email();

					$objEmail->from = $GLOBALS['TL_ADMIN_EMAIL'];
					$objEmail->fromName = $GLOBALS['TL_ADMIN_NAME'];
					$objEmail->subject = $objTask->title;

					$objEmail->text = trim($this->Template->comment->value);
					$objEmail->text .= sprintf($GLOBALS['TL_LANG']['tl_task']['message'], $this->User->name, $this->Environment->base . 'contao/main.php?do=tasks&act=edit&id=' . $objTask->id);

					$objEmail->sendTo($objUser->email);
				}
			}

			// Go back
			$this->redirect('contao/main.php?do=tasks');
		}

		$this->Template->history = $arrHistory;
		$this->Template->historyLabel = $GLOBALS['TL_LANG']['tl_task']['history'];
		$this->Template->deadlineLabel = $GLOBALS['TL_LANG']['tl_task']['deadline'][0];
		$this->Template->dateLabel = $GLOBALS['TL_LANG']['tl_task']['date'];
		$this->Template->assignedToLabel = $GLOBALS['TL_LANG']['tl_task']['assignedTo'];
		$this->Template->createdByLabel = $GLOBALS['TL_LANG']['tl_task']['creator'];
		$this->Template->statusLabel = $GLOBALS['TL_LANG']['tl_task']['status'][0];
		$this->Template->progressLabel = $GLOBALS['TL_LANG']['tl_task']['progress'][0];
		$this->Template->submit = $GLOBALS['TL_LANG']['tl_task']['editSubmit'];
		$this->Template->titleLabel = $GLOBALS['TL_LANG']['tl_task']['title'][0];
		$this->Template->assignLabel = $GLOBALS['TL_LANG']['tl_task']['assignedTo'];
	}


	/**
	 * Delete a task
	 * @return void
	 */
	protected function deleteTask()
	{
		$objTask = $this->Database->prepare("SELECT * FROM tl_task WHERE id=?")
								  ->limit(1)
								  ->execute(\Input::get('id'));

		if ($objTask->numRows < 1)
		{
			$this->log('Invalid task ID "' . \Input::get('id') . '"', 'ModuleTask deleteTask()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}

		// Check if the user is allowed to delete the task
		$this->checkPermission($objTask, true, 'delete');

		$affected = 1;
		$data = array();
		$data['tl_task'][] = $objTask->row();

		// Get status records
		$objStatus = $this->Database->prepare("SELECT * FROM tl_task_status WHERE pid=? ORDER BY tstamp")
									->execute(\Input::get('id'));

		while ($objStatus->next())
		{
			$data['tl_task_status'][] = $objStatus->row();
			++$affected;
		}

		$objUndoStmt = $this->Database->prepare("INSERT INTO tl_undo (pid, tstamp, fromTable, query, affectedRows, data) VALUES (?, ?, ?, ?, ?, ?)")
									  ->execute($this->User->id, time(), 'tl_task', 'DELETE FROM tl_task WHERE id= ' . \Input::get('id'), $affected, serialize($data));

		// Delete data and add a log entry
		if ($objUndoStmt->affectedRows)
		{
			$this->Database->prepare("DELETE FROM tl_task WHERE id=?")->execute(\Input::get('id'));
			$this->Database->prepare("DELETE FROM tl_task_status WHERE pid=?")->execute(\Input::get('id'));

			$this->log('DELETE FROM tl_task WHERE id=' . \Input::get('id'), 'ModuleTask deleteTask()', TL_GENERAL);
		}

		// Go back
		$this->redirect($this->getReferer());
	}

}
