<?php
require_once('common.php');
$GLOBALS['conf'] = array(
	'mysql' => array(
		'host' => '',
		'user' => '',
		'pwd' => '',
		'db' => ''),

	'repo' => array(
		'{name of the repo}' => array(
			'active' => true,

			'repoPath' => '',

			'operations' => UPDATE_POT,
			'extensions' => array(),
			'sourceFolder' => '',

			'resourceExtractorClass' => '',

			'projectSlug' => '',
			'iterationSlug' => '',
			'sourceDocName' => '',

			'options' => array()
		)
	),

	'assetPath' => '',
	'tmsToolkitPath' => '',
	'pophpPath' => ''
);
