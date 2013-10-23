<?php
/**
 * Created by JetBrains PhpStorm.
 * User: david
 * Date: 21.10.13
 * Time: 21:12
 * To change this template use File | Settings | File Templates.
 */

namespace Drafts\Event;

use DcaTools\Event;
use Drafts\Controller;

class Draftable
{

	/**
	 * @param Event\Permission $objEvent
	 * @param array $arrConfig
	 * @param bool $blnStop
	 *
	 * @return bool
	 */
	public static function hasAccessOnPublished(Event\Permission $objEvent, array $arrConfig=array(), $blnStop=true)
	{
		/** @var \Drafts\Controller\Draftable $objController */
		$objController = $objEvent->getSubject();

		// not published, so user can access content
		if(!$objController->isParentPublished())
		{
			return true;
		}

		// permission does not affect current page
		if(!Event\Listener\DataContainer::hasGenericPermission($objEvent, $arrConfig))
		{
			return true;
		}

		// User is not allowed to access published Content
		if(!$objController->hasAccessOnPublished())
		{
			if($blnStop)
			{
				$objEvent->denyAccess();
			}

			return false;
		}

		return true;
	}


	public static function initializeDynamicParent(Event\Event $objEvent, array $arrConfig=array())
	{
		/** @var Controller $objController */
		$objController = $objEvent->getSubject();

		switch(\Input::get('do'))
		{
			case 'article':
				$GLOBALS['TL_DCA'][$objController->getName()]['config']['ptable'] = 'tl_article';
				break;

			case 'news':
				$GLOBALS['TL_DCA'][$objController->getName()]['config']['ptable'] = 'tl_news';
				break;

			case 'calendar':
				$GLOBALS['TL_DCA'][$objController->getName()]['config']['ptable'] = 'tl_calendar_events';
				break;
		}
	}

}