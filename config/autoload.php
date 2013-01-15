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
 
ClassLoader::addClasses(array
(
	// datacontainers
	'Netzmacht\Drafts\DataContainer\DraftableDataContainer' => 'system/modules/drafts/datacontainers/DraftableDataContainer.php',
	'Netzmacht\Drafts\DataContainer\Content' 				=> 'system/modules/drafts/datacontainers/Content.php',
	'Netzmacht\Drafts\DataContainer\Drafts' 				=> 'system/modules/drafts/datacontainers/Drafts.php',
	
	// drivers
	'DC_DraftableTable' 									=> 'system/modules/drafts/drivers/DC_DraftableTable.php',
	
	// models
	'ContentModel'											=> 'system/modules/drafts/models/ContentModel.php',
	'Netzmacht\Drafts\Model\VersioningCollection'			=> 'system/modules/drafts/models/VersioningCollection.php',
	'Netzmacht\Drafts\Model\VersioningModel' 				=> 'system/modules/drafts/models/VersioningModel.php',
	'Netzmacht\Drafts\Model\DraftableCollection'			=> 'system/modules/drafts/models/DraftableCollection.php',
	'Netzmacht\Drafts\Model\DraftableModel' 				=> 'system/modules/drafts/models/DraftableModel.php',
	
	// modules
	'ModuleTasks' 											=> 'system/modules/drafts/modules/ModuleTasks.php',
	
	// widgets
	'Netzmacht\Drafts\Widget\PreviewSwitch'					=> 'system/modules/drafts/widgets/PreviewSwitch.php',
	
));

TemplateLoader::addFiles(array 
(
	'be_drafts_diff'										=> 'system/modules/drafts/templates/',
	'be_drafts_task'										=> 'system/modules/drafts/templates/',
	'be_switch'												=> 'system/modules/drafts/templates/',
));
