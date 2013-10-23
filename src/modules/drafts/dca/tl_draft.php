<?php


$GLOBALS['TL_DCA']['tl_draft'] = array
(
	'config' => array
	(
		'dataContainer' => 'General',
		'ctable'        => array('tl_content'),
		'enableVersioning' => true,
		'ptable'        => '',
		'dynamicPtable' => true,
		'closed'        => true,

		'sql' => array
		(
			'keys' => array
			(
				'id' => 'primary',
				'pid' => 'index',
				'ptable' => 'index'
			)
		),

	),

	'metapalettes' => array
	(
		'default' => array
		(

		),
	),

	'fields' => array
	(
		'id' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL auto_increment"
		),
		'pid' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL default '0'"
		),
		'ptable' => array
		(
			'sql'                     => "varchar(64) NOT NULL default ''"
		),
		'tstamp' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL default '0'"
		),
	)

);


switch(\input::get('do'))
{
	case 'article':
		$GLOBALS['TL_DCA']['tl_draft']['ptable'] = 'tl_article';
		break;

	case 'news':
		$GLOBALS['TL_DCA']['tl_draft']['ptable'] = 'tl_news';
		break;

	case 'calendar':
		$GLOBALS['TL_DCA']['tl_draft']['ptable'] = 'tl_calendar_events';
		break;
}