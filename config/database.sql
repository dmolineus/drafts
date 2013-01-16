CREATE TABLE `tl_task` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `draftPid` int(10) unsigned NOT NULL default '0',
  `draftPtable` varchar(64) NOT NULL default '',
  `draftModule` varchar(64) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;