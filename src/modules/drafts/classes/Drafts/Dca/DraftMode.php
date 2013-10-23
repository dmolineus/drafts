<?php
/**
 * Created by JetBrains PhpStorm.
 * User: david
 * Date: 23.10.13
 * Time: 07:03
 * To change this template use File | Settings | File Templates.
 */

namespace Drafts\Dca;

use DcaTools\Event\Event;
use Drafts\Controller;
use Drafts\Model\DraftableModel;

class DraftMode extends DataContainer
{
	/**
	 * Initialize the draft mode
	 *
	 * @param Event $objEvent
	 * @param array $arrConfig
	 */
	public static function initialize(Event $objEvent, array $arrConfig=array())
	{
		parent::initialize($objEvent, $arrConfig);

		if(static::getController()->getMode() == Controller::MODE_DRAFT)
		{
			$strClass = get_called_class();

			/** @var \DcaTools\Definition\DataContainer $objDefinition */
			$objDefinition = static::getController()->getDefinition();

			$objDefinition->registerCallback('onsubmit', array($strClass, 'callbackSubmit'));
			$objDefinition->registerCallback('oncut',    array($strClass, 'callbackCut'));
			$objDefinition->registerCallback('ondelete', array($strClass, 'callbackDelete'));
		}
	}

	public function callbackDelete(\DC_DraftableTable $objDc)
	{
		// multiple delete not supported in draft mode
		if(\Input::post('IDS') != '')
		{
			return;
		}

		// reset draft to original
		if($this->strAction == 'reset')
		{
			static::getController()->resetDraft($this->intId);

			return;
		}

		$objModel = new DraftableModel($objDc->activeRecord, false, static::getController()->getName());

		// It is a new one, so just delete it
		if($objModel->hasState('new'))
		{
			return;
		}

		$objModel->toggleState('delete');
		$objModel->save();

		\Controller::redirect(\Controller::getReferer());
	}

}