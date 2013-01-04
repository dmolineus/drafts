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
	'Netzmacht\Drafts\DataContainer\DraftsDataContainer' 	=> 'system/modules/drafts/datacontainers/DraftsDataContainer.php',
	'Netzmacht\Drafts\DataContainer\Content' 				=> 'system/modules/drafts/datacontainers/Content.php',
	'Netzmacht\Drafts\DataContainer\Drafts' 				=> 'system/modules/drafts/datacontainers/Drafts.php',
	
	// models
	'DraftsModel' 											=> 'system/modules/drafts/models/DraftsModel.php',
	'Netzmacht\Drafts\Model\VersioningModel' 				=> 'system/modules/drafts/models/VersioningModel.php',
	
	// modules
	'Netzmacht\Drafts\Module\ModuleDrafts' 					=> 'system/modules/drafts/modules/ModuleDrafts.php',
	'ModuleTasks' 											=> 'system/modules/drafts/modules/ModuleTasks.php',
));

TemplateLoader::addFiles(array 
(
	'be_drafts_diff'	=> 'system/modules/drafts/templates/',
	'be_drafts_task'	=> 'system/modules/drafts/templates/',
));
