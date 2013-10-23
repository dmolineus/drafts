<?php
/**
 * Created by JetBrains PhpStorm.
 * User: david
 * Date: 22.10.13
 * Time: 21:36
 * To change this template use File | Settings | File Templates.
 */

namespace Drafts\Event;

use DcaTools\Event;
use DcaTools\Event\Listener;
use Drafts\Controller\Controller;

class Permission
{

	/**
	 * @param Event\Event $objEvent
	 * @param array $arrConfig
	 * @param bool $blnStop
	 *
	 * @return bool
	 */
	public static function hasAccessOnPublished(Event\Event $objEvent, array $arrConfig=array(), $blnStop=true)
	{
		if(Listener\DataContainer::hasGenericPermission($objEvent, $arrConfig))
		{
			return Draftable::hasAccessOnPublished($objEvent, $arrConfig);
		}

		return true;
	}


	/**
	 * @param Event\Event $objEvent
	 * @param array $arrConfig
	 * @param bool $blnStop
	 *
	 * @return bool|void
	 */
	public static function hasAccessOnArchived(Event\Event $objEvent, array $arrConfig=array(), $blnStop=true)
	{
		if(Listener\DataContainer::hasGenericPermission($objEvent, $arrConfig))
		{
			return Listener\DataContainer::hasAccess($objEvent, $arrConfig);
		}

		return true;


	}


	public static function hasAccessOnDraft(Event\Event $objEvent, array $arrConfig=array(), $blnStop=true)
	{

	}


	public static function isAllowedToPublished(Event\Event $objEvent, array $arrConfig=array(), $blnStop=true)
	{

	}

}