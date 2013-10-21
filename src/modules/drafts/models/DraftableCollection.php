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

namespace Drafts\Model;


/**
 * DraftableCollection creates automatically a collection
 * of draftable models
 */
class DraftableCollection extends \Collection
{
	
	/**
	 * Fetch the next result row and create the Draftable model
	 *
	 * @return boolean True if there was another row
	 */
	protected function fetchNext()
	{
		if ($this->objResult->next() == false)
		{
			return false;
		}

		$this->arrModels[$this->intIndex + 1] = new DraftableModel($this->strTable, $this->objResult);
		return true;
	}
	
}