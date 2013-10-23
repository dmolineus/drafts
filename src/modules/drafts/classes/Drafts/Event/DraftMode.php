<?php
/**
 * Created by JetBrains PhpStorm.
 * User: david
 * Date: 22.10.13
 * Time: 21:49
 * To change this template use File | Settings | File Templates.
 */

namespace Drafts\Event;


use DcaTools\Event\Event;

class DraftMode
{
	protected static $objDataContainer;

	public static function initialize(Event $objEvent, $arrConfig)
	{
		if(\Input::get('draft') == '1')
		{
			/** @var \Drafts\DataContainer\ $objController */
			static::$objDataContainer = $objEvent->getSubject();
			$strClass = get_called_class();

			$objController->addListener('test', array($strClass, 'test'));
		}
	}

}