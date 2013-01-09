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

namespace Netzmacht\Drafts\Model;


/**
 * VersioningCollection creates automatically a collection
 * of versioning models
 */
class VersioningCollection extends \Collection
{
	
	/**
	 * Fetch the next result row and create the Versioning model
	 * 
	 * @return boolean True if there was another row
	 */
	protected function fetchNext()
	{
		if(!parent::fetchNext())
		{
			return false;
		}
		
		$this->arrModels[$this->intIndex + 1] = new VersioningModel($this->arrModels[$this->intIndex + 1]);
		return true;
	}
	
}
