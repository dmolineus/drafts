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
 * Override default Contao's Content element to support rendering of drafted version
 */
abstract class ContentElement extends Contao\ContentElement
{
	
	/**
	 * @var bool 
	 */
	protected $blnPreview;
	
	/**
	 * constructor switch to draft
	 * 
	 * @param Model|Model\Collection
	 */
	public function __construct($objElement)
	{
		if($objElement instanceof \Model\Collection)
		{
			$objElement = $objElement->current();
		}
		
		$this->blnPreview = TL_MODE == 'FE' && Input::cookie('DRAFT_MODE') == '1';
		
		if($this->blnPreview && $objElement->ptable != 'tl_drafts' && $objElement->draftRelated !== null)
		{
			$objElement = $objElement->getRelated('draftRelated');
		}
		
		parent::__construct($objElement);
	}
	
	
	/**
	 * generate content element will deleted one in preview view
	 */
	public function generate()
	{
		if($this->blnPreview && $this->objModel->ptable == 'tl_drafts')
		{
			$objModel = new Netzmacht\Drafts\Model\DraftableModel($this->objModel);
			
			if($objModel->hasState('delete'))
			{
				return '';
			}
		}
		
		return parent::generate();
	}
	 
}
