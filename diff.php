<?php
ignore_user_abort(true);
set_time_limit(0);
require_once('conf.php');
require_once('class.babelwatch.php');
require_once('class.front.php');
require_once('class.projectResourceExtractor.php');

$front = new Front();

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

// Initialize resource extractor
$resUpdater = new $repoInfo['resourceExtractorClass'](
	$repoName,
	$repoInfo['repoPath'],
	$repoInfo['sourceFolder'],
	$repoInfo['extensions'],
	$GLOBALS['conf']['assetPath'],
	$GLOBALS['conf']['pophpPath'],
	$blacklist);

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

if (isset($_POST['export']))
{
	$poFileName = $GLOBALS['conf']['assetPath'] . 'new.strings.po';
	// The user wants to export the new strings to a PO file
	require_once($GLOBALS['conf']['pophpPath'] . 'POUtils.php');
	header("Content-Type: application/octet-stream");
	header("Content-disposition: attachment; filename=" . pathinfo($poFileName, PATHINFO_BASENAME));

	file_put_contents($poFileName, POUtils::getGettextHeader(), LOCK_EX);
	$i = 0;

	while (isset($_POST['newStringContent_' . $i]) && isset($_POST['newStringContext_' . $i]))
	{
		// Echo out all the hidden form elements as POEntry
		$entry = new POEntry($_POST['newStringContent_' . $i], '', $_POST['newStringContext_' . $i]);
		file_put_contents($poFileName, $entry->__toString(), FILE_APPEND | LOCK_EX);
		$i++;
	}

	if ($_POST['export'] == 'po')
	{
		readfile($poFileName);
		unlink($poFileName);
		exit();
	}

	// Convert to XLF
	if (!isset($_POST['destLocale']))
		exit();

	$destLocale = $_POST['destLocale'];
	$outputXlf = $GLOBALS['conf']['assetPath'] . $destLocale . '.xlf';
	$tempXlf = $GLOBALS['conf']['assetPath'] . $destLocale . '.temp.xlf';
	header("Content-disposition: attachment; filename=" . pathinfo($outputXlf, PATHINFO_BASENAME));

	exec("msguniq --no-location --no-wrap --sort-output $poFileName -o $poFileName");

	exec("msgattrib --no-obsolete --no-wrap --no-location --sort-output $poFileName -o $poFileName");

  exec("po2xliff -i $poFileName -o $tempXlf");

	chdir($GLOBALS['conf']['l10nScriptsPath']);
	exec("php xliff2lb.php $tempXlf en-GB $outputXlf $destLocale");

	unlink($tempXlf);
	unlink($poFileName);
	readfile($outputXlf);
	unlink($outputXlf);
	exit();
}

$front->echoHeader();

if (isset($_GET['start']) && isset($_GET['end']) && isset($_GET['repoUrl']) && !isset($_POST['export']))
{

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

	try
	{
		if (!empty($startRevisionFullId) && !empty($endRevisionFullId) && $proceed)
		{
			// Else if one of the revisions is not in the db,
			// Rebuild POT for both revisions and compare them
			$startPot = $tracker->getPotAtRevision($startRevisionFullId);
			$endPot = $tracker->getPotAtRevision($endRevisionFullId);

			require_once($GLOBALS['conf']['pophpPath'] . 'POUtils.php');
			$utils = new POUtils();

			$diffInfo = $utils->compare($startPot, $endPot);

			$stringTable = array('a' => array(), 'r' => array());

			$hiddenStringId = 0;
			echo '<form action="" method="post">';
			// Process added strings
			foreach ($diffInfo['secondOnly'] as $entry)
			{
				$string = $entry->getSource();
				$ref = $entry->getReferences($repoInfo['sourceFolder']);

				// Update $data
				array_push($stringTable['a'], array('content' => htmlentities($string), 'references' => $ref));

				// Prepare to save added strings info in the page
				$source = htmlentities($entry->getSource());
				$context = htmlentities($entry->getContext());
				echo <<<HTML
				<input type=hidden name=newStringContent_$hiddenStringId value="$source">
				<input type=hidden name=newStringContext_$hiddenStringId value="$context">
HTML;
				$hiddenStringId++;
			}
			echo <<<HTML
			<select name=export>
				<option value=po>PO</option>
				<option value=xlf>XLF</option>
			</select>
		<select name="destLocale">
HTML;

			foreach ($GLOBALS['conf']['destLocales'] as $locale)
				echo "<option value=$locale>$locale</option>";

			echo <<<HTML
		</select>
			<input type=submit value="Export">
			</form>
HTML;

			// Process removed strings
			foreach ($diffInfo['firstOnly'] as $entry)
			{
				$string = $entry->getSource();
				$ref = $entry->getReferences($repoInfo['sourceFolder']);

				array_push($stringTable['r'], array('content' => htmlentities($string), 'references' => $ref));
			}

			$diffTable = $front->displayStringTable($stringTable, false);

		}

		// Delete the lock
		unlink($lockName);
	}
	catch (Exception $e)
	{
		$front->displayException($e);
		unlink($lockName);
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
