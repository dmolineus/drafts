<?php
/**
 * Created by JetBrains PhpStorm.
 * User: david
 * Date: 22.10.13
 * Time: 21:21
 * To change this template use File | Settings | File Templates.
 */

namespace Drafts;

use Drafts\Helper\Query;
use Drafts\Model\DraftableModel;


class Controller extends \DcaTools\Controller
{

	/**
	 * Used when Drafts is in live mode
	 */
	const MODE_LIVE     = 'live';

	/**
	 * Used when Drafts is in draft mode
	 */
	const MODE_DRAFT    = 'draft';

	/**
	 * Used when Drafts in in archive mode
	 */
	const MODE_ARCHIVE  = 'archive';


	/**
	 * Cache published state
	 * @var bool
	 */
	private $blnIsPublished;


	/**
	 * Initialize the Controller
	 */
	public function initialize()
	{
		parent::initialize();
	}


	/**
	 * Get the current mode in which draft is running
	 *
	 * @return string
	 *      static::MODE_ARCHIVE
	 *      static::MODE_DRAFT
	 *      static::MODE_LIVE
	 */
	public function getMode()
	{
		if(\Input::get('table') == 'tl_draft_archive_content')
		{
			return static::MODE_ARCHIVE;
		}

		elseif(\Input::get('draft') == '1')
		{
			return static::MODE_DRAFT;
		}

		return static::MODE_LIVE;
	}


	/**
	 * Get the dynamic parent definition
	 *
	 * @return null|string
	 */
	public function getDynamicParent()
	{
		return $this->getDefinition()->getFromDefinition('config/ptable');
	}


	/**
	 * Check is archive mode is activated
	 *
	 * @return bool
	 */
	public function isArchivedActive()
	{
		return (bool) $GLOBALS['TL_CONFIG']['draftArchived'];
	}


	/**
	 * Check if draft mode is activated
	 *
	 * @return bool
	 */
	public function isDraftActive()
	{
		return (bool) $GLOBALS['TL_CONFIG']['draftDraftMode'];
	}


	/**
	 * @param $intContainerId
	 * @return DraftModel
	 */
	public function createDraft($intContainerId)
	{
		if($this->isDraftActive())
		{
			$objDraft = new DraftModel();
			$objDraft->tstamp = time();
			$objDraft->pid = $intContainerId;
			$objDraft->userid = \BackendUser::getInstance()->id;
			$objDraft->ptable = $this->getName();

			$objDraft->save();

			return $objDraft;
		}
	}

	public function applyDraft($intId)
	{

	}

	public function resetDraft($intId)
	{
		$objChildren = DraftableModel::findByPtableAndPid($this->getName(), $intId);

		if($objChildren === null)
		{
			return;
		}
	}

	public function createDraftChild($intDraftId, $strHash)
	{

	}


	public function applyDraftChild()
	{

	}


	/**
	 *
	 *
	 * @param $intDraftId
	 *
	 * @throws \RuntimeException
	 */
	public function resetDraftChild($intDraftId)
	{
		$objQuery = Query::select($this->getName(), 'id', 'ptable', 'pid', 'draftHash')->where('id=?');
		$objDraft = \Database::getInstance()->prepare($objQuery)->limit(1)->execute($intDraftId);

		if($objDraft->numRows < 1)
		{
			throw new \RuntimeException("Entry ID '$intDraftId' in table '{$this->getName()}' not found.");
		}

		$objQuery = Query::select($this->getName())
			->where('%s AND draftHash=?')
			->dynamicParent($objDraft->ptable);

		$objOriginal = \Database::getInstance()->prepare($objQuery)->limit(1)->execute($objDraft->draftHash);

		if($objOriginal->numRows < 1)
		{
			throw new \RuntimeException("Original for ID '$intDraftId' in table '{$this->getName()}' not found.");
		}

		$objModel = new DraftableModel($objDraft, false, $this->getName());
		$objModel->resetTo($objOriginal);
		$objModel->save();
	}

	public function deleteDraft()
	{

	}


	/**
	 * Create a new archived version of current element
	 *
	 * @param $intId
	 * @param null $varExclude
	 *
	 * @return bool
	 */
	public function createArchived($intId, $varExclude=null)
	{
		if(!$this->isArchivedActive())
		{
			return false;
		}

		$objQuery = Query::select($this->getName())
			->where('pid=? AND %s')
			->dynamicParent($this->getDynamicParent());

		if($varExclude !== null)
		{
			$objQuery->append(' AND id NOT %s')->in((array) $varExclude);
		}

		$objDatabase = \Database::getInstance();
		$objResult   = $objDatabase->prepare($objQuery)->execute($intId);

		if($objResult->numRows == 0)
		{
			return false;
		}

		$arrArchive = array('pid' => $intId, 'ptable' => $this->getDynamicParent());

		$objResult = $objDatabase->prepare("INSERT INTO tl_draft_archive %s")->set($arrArchive)->execute();

		while($objResult->next())
		{
			$arrSet = $objResult->fetchAssoc();

			unset($arrSet['id']);
			unset($arrSet['draftRoot']);

			$arrSet['ptable'] = 'tl_draft_archive';
			$arrSet['pid']    = $objResult->insertId;

			$objDatabase->prepare('INSERT INTO tl_draft_archive_content %s')->set($arrSet);
		}

		return true;
	}

	public function applyArchived($intId)
	{

	}

	public function deleteArchived($intArchivedId)
	{

	}

	public function isActive()
	{
		return in_array(\Input::get('do'), $GLOBALS['TL_CONFIG']['draftModules']);
	}

	/**
	 * check if content is already published
	 *
	 * @return bool
	 */
	public function isPublished()
	{
		if($this->blnIsPublished !== null)
		{
			return $this->blnIsPublished;
		}

		$strPtable = $this->getDefinition()->getFromDefinition('config/ptable');

		if($this->blnParentView)
		{
			$strQuery = 'SELECT published FROM ' . $strPtable . ' WHERE id=?';
		}
		else
		{
			$strQuery = 'SELECT published FROM ' . $strPtable . ' WHERE id=(SELECT pid FROM ' . $this->getName() . ' WHERE id=?)';
		}

		$this->blnIsPublished = (bool) \Database::getInstance()->prepare($strQuery)->execute($this->intId)->published;
		return $this->blnIsPublished;
	}

}