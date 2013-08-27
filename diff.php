<?php
set_time_limit(0);
require_once('conf.php');
require_once('class.babelwatch.php');
require_once('class.front.php');
require_once('class.projectResourceExtractor.php');

$front = new Front();

$front->echoHeader();

// Choose the repo via the GET parameter
if (!isset($_GET['repo']))
	return;

$repoName = $_GET['repo'];
$repoInfo = $GLOBALS['conf']['repo'][$repoName];
$blacklist = (array_key_exists('blacklist', $repoInfo)) ? $repoInfo['blacklist'] : array();

$start = '';
$end = '';

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

if (isset($_GET['start']) && isset($_GET['end']))
{
	$start = $_GET['start'];
	$end = $_GET['end'];

	// Retrieve the id of the revisions (full hash format)
	try
	{
		$startRevisionFullId = $tracker->getFullRevisionId($start);
		$endRevisionFullId = $tracker->getFullRevisionId($end);
	}
	catch (RuntimeException $e)
	{
		$front->displayException($e);
	}

	if (!empty($startRevisionFullId) && !empty($endRevisionFullId))
	{
		$tmsToolkit = $tracker->getTmsToolkit();

		if ($tracker->isRevisionInDb($startRevisionFullId) &&	$tracker->isRevisionInDb($endRevisionFullId))
		{
			// If both revisions exist in the db
			// we only need to rebuild the POT file
			// for the starting revision
			$diffInfo = $tracker->diffBetweenRevisions($startRevisionFullId, $endRevisionFullId);

			$diffTable = $front->displayStringTable($diffInfo);
		}
		else
		{
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
			$diffTable = $front->displayStringTable($stringTable);
		}
	}
}


echo <<<HTML
<form action="" method=get>
	<label class=form_label>Start revision: </label>
	<input type=text name=start value=$start>
	<label class=form_label>End revision: </label>
	<input type=text name=end value=$end>
	<input type=hidden name=repo value=$repoName>
	<input type=submit value=Go>
</form>

$diffTable
HTML;
$front->echoFooter();