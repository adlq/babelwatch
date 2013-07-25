<?php

$GLOBALS['conf'] = array(
	'mysql' => array(
		'host' => '',
		'user' => '',
		'pwd' => '',
		'db' => ''),

	'repo' => array(
		'{name of the repo}' => array(
			'active' => true,	// Whether to ignore this repo or not
			'repoPath' => '{absolute path to repo}',
			'resourceExtractorClass' => '{class name for resource extractor}',
			'projectSlug' => '{project short name on Zanata}',
			'iterationSlug' => '{project version on Zanata}',
			'options' => array()	// Options to be passed into the ResourceExtractor's constructor
		)
	),

	'assetPath' => '',
	'tmsToolkitPath' => '',
	'pophpPath' => ''
);
