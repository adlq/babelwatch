<?php
require_once('conf.php');
require_once('class.babelwatch.php');
require_once('class.front.php');
require_once('class.projectResourceExtractor.php');

$front = new Front();

$front->echoHeader();

$repoName = 'test';
$repoInfo = $GLOBALS['conf']['repo'][$repoName];
$blacklist = (array_key_exists('blacklist', $repoInfo)) ? $repoInfo['blacklist'] : array();

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


$changesets = $tracker->getRevisions('test');

$diffTable = '';

if (isset($_GET['start']) && isset($_GET['end']))
{
	$start = $_GET['start'];
	$end = $_GET['end'];

	$potfile = $tracker->getPotAtRevision($start);

	$diffInfo = $tracker->diffBetweenRevisions($start, $end);
	$stringTable = array('a' => array(), 'r' => array());

	$tmsToolkit = $tracker->getTmsToolkit();

	// Process added strings
	if (array_key_exists('a', $diffInfo))
	{
		foreach ($diffInfo['a'] as $string)
		{
			// If the added string already exists at the start date, ignore it
			if ($potfile->getEntry($string) !== false)
				continue;
			$url = $tmsToolkit->getTextflowWebTransUrl($string, 'fr-FR', 'fr', $repoInfo['sourceDocName']);

			// Update $data
			array_push($stringTable['a'], array('string' => htmlentities($string), 'url' => $url));
		}
	}

	if (array_key_exists('r', $diffInfo))
	{
		foreach ($diffInfo['r'] as $string)
		{
			// If the removed string didn't exist to start with, ignore it
			if ($potfile->getEntry($string) === false)
				continue;

			array_push($stringTable['r'], array('string' => htmlentities($string)));
		}
	}
	$diffTable = $front->displayStringTable($stringTable);
}


echo <<<HTML
<form action="" method=get>
	<select name=start>
HTML;

foreach ($changesets as $hgId => $chg)
{
	$id = $chg['id'];
	$summary = $chg['summary'];
	$date = $chg['date'];
	$selected = (isset($start) && $start === $id) ? 'selected' : '';
	echo <<<SELECT
	<option value="$hgId" $selected>$summary - $date</option>
SELECT;
}

echo <<<HTML
	</select>
	<select name=end>
HTML;

foreach ($changesets as $hgId => $chg)
{
	$id = $chg['id'];
	$summary = $chg['summary'];
	$date = $chg['date'];
	$selected = (isset($end) && $end === $id) ? 'selected' : '';
	echo <<<SELECT
	<option value="$hgId" $selected>$summary - $date</option>
SELECT;
}

echo <<<HTML
	</select>
	<input type=submit value=Go>
</form>

$diffTable
HTML;

$front->echoFooter();