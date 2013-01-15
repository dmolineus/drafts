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
 * DC_DraftableTable extends DC_Table with allowing switching the current id 
 */
class DC_DraftableTable extends DC_Table
{
	
	/**
	 * set new id
	 * 
	 * @param int
	 */
	public function setId($intId)
	{
		$this->intId = $intId;
	}
	
}
