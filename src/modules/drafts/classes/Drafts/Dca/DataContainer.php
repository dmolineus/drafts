<?php
/**
 * Created by JetBrains PhpStorm.
 * User: david
 * Date: 22.10.13
 * Time: 21:56
 * To change this template use File | Settings | File Templates.
 */

namespace Drafts\Dca;

use DcaTools\Component\Component;
use DcaTools\Event\Event;
use DcaTools\Event\EventDispatcher;
use Drafts\Controller;


class DataContainer extends EventDispatcher
{
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
	 * @var Controller
	 */
	private static $objController;


	public function __construct()
	{
		$this->strAction = \Input::get('key') == '' ? \Input::get('act') : \Input::get('key');

		if(\Input::get('tid') != null)
		{
			$this->strAction = 'toggle';
			$this->intId = \Input::get('tid');
		}
		else
		{
			$this->intId = \Input::get('id');
		}

		$this->blnParentView = in_array($this->strAction, array(null, 'select', 'create')) || ($this->strAction == 'paste' && \Input::get('mode') == 'create');
	}


	public static function initialize(Event $objEvent, array $arrConfig=array())
	{
		static::$objController = $objEvent->getSubject();
	}


	/**
	 * @return Controller
	 */
	public static function getController()
	{
		return static::$objController;
	}

	public function callbackCopy()
	{
	}


	public function callbackCopyParent()
	{
	}


	public function callbackGenerateSubmitButtons()
	{
	}


	/**
	 * @hook oncreate_callback
	 */
	public function callbackCreate()
	{
		
	}


	public function callbackCut()
	{
	}


	public function callbackDelete()
	{
	}


	public function callbackRestore()
	{
	}


	public function callbackSubmit()
	{
	}
	

	public function callbackToggleVisibility()
	{
	}


	public function hookLoadDataContainer()
	{
	}

}