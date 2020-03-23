<?php namespace ProcessWire;

$info = array(

	'title' => 'Flowti Module Performance Monitor', 
	'summary' => 'Track and profile your Sites Admin Modules', 

	'version' => 11, 
	'author' => 'Luis Mendez', 
	'href' => 'https://github.com/Luis85/FlowtiAppPerformance',
	'icon' => 'flask', 
	'permission' => 'flowti-profiler', 
	'permissions' => array(
		'flowti-profiler' => 'Permission to open the Flowti Monitor'
	), 
	'page' => array(
		'name' => 'flowti-performance-monitor',
		'parent' => 'setup', 
		'title' => 'Flowti Monitor'	
	),
	'installs' => array('FlowtiModuleProfiler'),
	'nav' => array(
		array(
			'url' => 'database',
			'label' => 'Database', 
			'permission' => 'flowti-profiler',
			'icon' => 'database', 
		),
	),
	'requires' => array('PHP>=7','ProcessWire>=3.0.0'),

);