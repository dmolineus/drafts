<?php
/**
 * Created by JetBrains PhpStorm.
 * User: david
 * Date: 22.10.13
 * Time: 22:07
 * To change this template use File | Settings | File Templates.
 */

namespace Drafts\Dca;

use DcaTools\Event\Event;
use Drafts\Controller;
use Drafts\Helper\Query;


/**
 * Class LiveMode provides dca callbacks which are required in the live mode
 *
 * @package Drafts\Dca
 */
class LiveMode extends DataContainer
{

	/**
	 * Initialize the live mode will register required parameters
	 *
	 * @param Event $objEvent
	 * @param array $arrConfig
	 */
	public static function initialize(Event $objEvent, array $arrConfig=array())
	{
		parent::initialize($objEvent, $arrConfig);

		if(static::getController()->getMode() == Controller::MODE_LIVE)
		{
			$strClass = get_called_class();

			/** @var \DcaTools\Definition\DataContainer $objDefinition */
			$objDefinition = static::getController()->getDefinition();

			$objDefinition->registerCallback('onsubmit', array($strClass, 'callbackSubmit'));
			$objDefinition->registerCallback('oncut',    array($strClass, 'callbackCut'));
			$objDefinition->registerCallback('ondelete', array($strClass, 'callbackDelete'));
		}
	}


	public function callbackCut()
	{
		// TODO: make sure it is not move to another parent
	}


	/**
	 * Create a new archive entry
	 *
	 * @param \DC_DraftableTable $objDc
	 */
	public function callbackSubmit(\DC_DraftableTable $objDc)
	{
		static::getController()->createArchived($this->intId);
	}


	/**
	 * Create a new archive entry when new element is created
	 *
	 * @param $strTable
	 * @param $intId
	 * @param $arrSet
	 * @param $objDc
	 */
	public function callbackCreate($strTable, $intId, $arrSet, \DC_DraftableTable $objDc)
	{
		static::getController()->createArchived($this->intId, $intId);
	}


	/**
	 * Handle delete action
	 *
	 * @param $objDc
	 * @param int|null $intUndoId=null, only available in Contao 3.2 @see https://github.com/contao/core/pull/6234
	 */
	public function callbackDelete(\DC_DraftableTable $objDc, $intUndoId=null)
	{
		static::getController()->createArchived($objDc->activeRecord->pid, $objDc->id);

		// get related
		$arrDrafts = static::getController()->getDrafts($this->intId);
		$objDatabase = \Database::getInstance();

		if(!empty($arrDrafts))
		{
			// TODO: Improve query for Contao 3.2
			$strQuery = 'SELECT * FROM tl_undo WHERE userid=? AND fromTable=? AND pid=? ORDER BY tstamp DESC LIMIT 1';

			$objUndo = $objDatabase->prepare($strQuery)->execute(
				\BackendUser::getInstance()->id,
				static::getController()->getName(),
				$this->intId
			);

			// no undo found, just delete items
			if($objUndo->numRows < 1)
			{
				foreach($arrDrafts as $arrDraft)
				{
					$objDc->setId($arrDraft['id']);
					$objDc->delete(true);
				}

				$objDc->setId($this->intId);
			}
			else
			{
				$arrData = deserialize($objUndo->data);
				$arrIds  = array();
				$strTable = static::getController()->getName();

				foreach($arrDrafts as $arrDraft)
				{
					$arrData[$strTable][$arrDraft['id']] = $arrDraft;
					$arrIds[] = $arrDraft['id'];
				}

				$arrSet = array
				(
					'tstamp'	=> time(),
					'data'		=> $arrData,
					'affectedRows'	=> count($arrData),
				);

				$objDatabase->prepare('UPDATE tl_undo %s WHERE id=?')->set($arrSet)->execute($objUndo->id);

				$objQuery = Query::delete($strTable)->where('id %s')->in($arrIds);
				$objDatabase->query($objQuery);
			}
		}
	}
}