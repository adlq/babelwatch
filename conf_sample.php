<?php
require_once('common.php');

$GLOBALS['conf'] = array(

	/**
	 * MySQL configuration
	 */
	'mysql' => array(
		'host' => '',
		'user' => '',
		'pwd' => '',
		'db' => ''),

	/**
	 * What to do, how to do it config for each repo
	 */
	'repo' => array(
		'{name of the repo}' => array(
			// Do we need to watch over this repo?
			'active' => true,
			// Will its tab be focused by default on the index.php page?
			'focused' => false,

			// The class that will be used to extract strings
			'resourceExtractorClass' => '',

			/**
			 * Configurations regarding the repo itself
			 */
			// Absolute path to the repo
			'repoPath' => '',

			// Operations to perform each time we pull new
			// changesets into the repo
			'operations' => UPDATE_POT,

			// File extensions to parse with xgettext
			// Currently supported extensions:
			// - php
			// - js
			// - m
			'extensions' => array(),

			// The folder, in the repo, that contains all the
			// relevant source files
			'sourceFolder' => '',

			// Blacklisted folders. Every source file whose path
			// includes one of these folders will be ignored
			'blacklist' => array(),

			/**
			 * Zanata configurations
			 */
			'projectSlug' => '',
			'iterationSlug' => '',
			'sourceDocName' => '',
		)
	),

	// Absolute path to the folder that will contain the generated POT/PO files
	'assetPath' => '',
	// Absolute path to the folder containing the TMS toolkit
	'tmsToolkitPath' => '',
	// Absolute path to the folder containing the pophp lib
	'pophpPath' => ''
);
