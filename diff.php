<?php
ignore_user_abort(true);
set_time_limit(0);
require_once('conf.php');
require_once('class.babelwatch.php');
require_once('class.front.php');
require_once('class.projectResourceExtractor.php');

$front = new Front();

$front->echoHeader();

// Check conf.php
try
{
	Babelwatch::checkConfig();
}
catch (Exception $e)
{
	$front->displayException($e);
	exit();
}

// The URL of the changesets to pull from if needed
$repoUrl= isset($_GET['repoUrl']) ? $_GET['repoUrl'] : 'http://hg.epistema.com/elms_2013_v1_maintenance/';

// We will work on a special repo
$repoName = 'ckls.diff'; // NAME OF THE DIFF REPO, MAY CHANGE
$repoInfo = $GLOBALS['conf']['repo'][$repoName];
$blacklist = (array_key_exists('blacklist', $repoInfo)) ? $repoInfo['blacklist'] : array();

// Check to see if there's a lock on the repo
chdir($repoInfo['repoPath']);
$lockName = 'babellock';
try
{
	// If the lock exists, ABORT
	if (file_exists($lockName))
		throw new RuntimeException("A lock has been set on the current repository (at '{$repoInfo['repoPath']}'). Please try again later");

	// Otherwise, create the lock
	file_put_contents($lockName, gethostname());
}
catch (Exception $e)
{
	$front->displayException($e);
	exit();
}

// Initialize resource extractor
$resUpdater = new $repoInfo['resourceExtractorClass'](
	$repoName,
	$repoInfo['repoPath'],
	$repoInfo['sourceFolder'],
	$repoInfo['extensions'],
	$GLOBALS['conf']['assetPath'],
	$GLOBALS['conf']['pophpPath'],
	$blacklist,
	$repoInfo['options']);

// Initialize tracker
	$tracker = new Babelwatch(
		$repoName,
		$repoInfo['repoPath'],
		$GLOBALS['conf']['assetPath'],
		$GLOBALS['conf']['tmsToolkitPath'],
		$GLOBALS['conf']['pophpPath'],
		$GLOBALS['conf']['mysql'],
		$resUpdater,
		$repoInfo['operations']);

$diffTable = '';

if (isset($_GET['start']) && isset($_GET['end']) && isset($_GET['repoUrl']))
{

	$start = $_GET['start'];
	$end = $_GET['end'];
	$repoUrl = $_GET['repoUrl'];
	$proceed = true;

	try
	{
		// Pull the latest changesets from the URL
		$tracker->pullFromUrl($repoUrl);
		// Retrieve the id of the revisions (full hash format)
		$startRevisionFullId = $tracker->getFullRevisionId($start);
		$endRevisionFullId = $tracker->getFullRevisionId($end);
	}
	catch (RuntimeException $e)
	{
		$front->displayException($e);
		$proceed = false; // Don't do anything else
	}

	if (!empty($startRevisionFullId) && !empty($endRevisionFullId) && $proceed)
	{
		$tmsToolkit = $tracker->getTmsToolkit();

		// Else if one of the revisions is not in the db,
		// Rebuild POT for both revisions and compare them
		$startPot = $tracker->getPotAtRevision($startRevisionFullId);
		$endPot = $tracker->getPotAtRevision($endRevisionFullId);

		require_once($GLOBALS['conf']['pophpPath'] . 'POUtils.php');
		$utils = new POUtils();

		$diffInfo = $utils->compare($startPot, $endPot);

		$stringTable = array('a' => array(), 'r' => array());

		// Process added strings
		foreach ($diffInfo['secondOnly'] as $entry)
		{
			$string = $entry->getSource();
			$ref = $entry->getReferences($repoInfo['sourceFolder']);
			$url = $tmsToolkit->getTextflowWebTransUrl($string, 'fr-FR', 'fr', $repoInfo['sourceDocName']);

			// Update $data
			array_push($stringTable['a'], array('content' => htmlentities($string), 'url' => $url, 'references' => $ref));
		}

		foreach ($diffInfo['firstOnly'] as $entry)
		{
			$string = $entry->getSource();
			$ref = $entry->getReferences($repoInfo['sourceFolder']);

			array_push($stringTable['r'], array('content' => htmlentities($string), 'references' => $ref));
		}
		$diffTable = $front->displayStringTable($stringTable, false);
	}
}
else
{
	// Get latest tag to affect to start
	$start = $tracker->getLatestTag();
	$end = 'tip';
}

echo <<<HTML
<form action="" method=get>
	<label class=form_label>Start revision: </label>
	<input type=text name=start value=$start>

	<label class=form_label>End revision: </label>
	<input type=text name=end value=$end>
	<br>
	<label class=form_label>Repository URL: </label>
	<input type=text name=repoUrl size=70 value=$repoUrl>

	<input type=submit value=Go>
</form>

$diffTable
HTML;
$front->echoFooter();

// Delete the lock
try
{
	unlink($lockName);
}
catch (Exception $e)
{
	$front->displayException($e);
}
