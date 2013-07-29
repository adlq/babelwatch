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
	if ($GLOBALS['conf']['repo'][$repoName]['active'])
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
<?php
