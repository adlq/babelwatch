<?php
require_once('common.php');
require_once('conf.php');
require_once('Babelwatch.php');
require_once('class.subProjectResourceExtractor.php');
require_once('class.projectResourceExtractor.php');

foreach ($GLOBALS['conf']['repo'] as $repoName => $repoInfo)
{
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

		$tracker->run($repoInfo['sourceFolder'], $repoInfo['extensions'], $repoInfo['operations']);
	}
}
?>
