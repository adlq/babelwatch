<?php
require_once('conf.php');
require_once('Babelwatch.php');
require_once('class.subProjectResourceExtractor.php');
require_once('class.projectResourceExtractor.php');

foreach ($GLOBALS['conf']['repo'] as $repoName => $repoInfo)
{
	$resUpdater = new $repoInfo['resourceExtractorClass'](
		$repoName,
		$repoInfo['repoPath'],
		$GLOBALS['conf']['assetPath'],
		$GLOBALS['conf']['pophpPath']);

	$tracker = new Babelwatch(
		$repoName,
		$repoInfo['repoPath'],
		$GLOBALS['conf']['assetPath'],
		$GLOBALS['conf']['tmsToolkitPath'],
		$GLOBALS['conf']['pophpPath'],
		$GLOBALS['conf']['mysql'],
		$resUpdater);

	$tracker->run('source', array('php', 'js'));
}
//$tracker->trace(16763,16834);

/*
$logs = $tracker->log();

foreach($logs as $changesetId => $actions)
{
	echo "In changeset $changesetId, \n";
	foreach($actions as $action => $strings)
	{
		if ($action === 'a')
		{
			foreach ($strings as $string)
				echo "\t-'$string' was added\n";
		}
		else
		{
			foreach ($strings as $string)
				echo "\t-'$string' was removed\n";
		}
	}
}*/

?>
