<?php
require_once('conf.php');
require_once('class.babelwatch.php');
require_once('class.subProjectResourceExtractor.php');
require_once('class.projectResourceExtractor.php');

if (!isset($argv[1]))
	exit();

$repoName = $argv[1];

if (array_key_exists($repoName, $GLOBALS['conf']['repo']))
{
	$repoInfo = $GLOBALS['conf']['repo'][$repoName];
	if ($repoInfo['active'])
	{
		$resUpdater = new $repoInfo['resourceExtractorClass'](
			$repoName,
			$repoInfo['repoPath'],
			$GLOBALS['conf']['assetPath'],
			$GLOBALS['conf']['pophpPath'],
			$repoInfo['options']);

		$tracker = new Babelwatch(
			$repoName,
			$repoInfo['repoPath'],
			$GLOBALS['conf']['assetPath'],
			$GLOBALS['conf']['tmsToolkitPath'],
			$GLOBALS['conf']['pophpPath'],
			$GLOBALS['conf']['mysql'],
			$resUpdater);

		$revisions = array_key_exists('revisions', $repoInfo['options']) ? $repoInfo['options']['revisions'] : array();
		$tracker->run($repoInfo['sourceFolder'], $repoInfo['extensions'], $repoInfo['operations'], $revisions);
	}
}
?>
