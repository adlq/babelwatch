<?php
require_once('conf.php');
require_once('class.babelwatch.php');
require_once('class.subProjectResourceExtractor.php');
require_once('class.projectResourceExtractor.php');

if (!isset($argv[1]) || !isset($argv[2]))
	exit();

$repoName = $argv[1];
$buildType = $argv[2];

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

		$revisions = array_key_exists('revisions', $repoInfo['options']) ? $repoInfo['options']['revisions'] : array();

		$tracker = new Babelwatch(
			$repoName,
			$repoInfo['repoPath'],
			$repoInfo['sourceFolder'],
			$repoInfo['extensions'],
			$GLOBALS['conf']['assetPath'],
			$GLOBALS['conf']['tmsToolkitPath'],
			$GLOBALS['conf']['pophpPath'],
			$GLOBALS['conf']['mysql'],
			$resUpdater,
			$repoInfo['operations'],
			$revisions);

		var_dump($buildType);
		switch($buildType)
		{
			case 'build':
				$tracker->run();
				break;
			case 'init':
				if (!array_key_exists(3, $argv))
					throw new Exception('Missing revision identifier');
				$tracker->initAtRevision($argv[3]);
				break;
			case 'sweep':
				if (!array_key_exists(3, $argv) || !array_key_exists(4, $argv))
					throw new Exception('Missing start and/or end revision identifier(s)');
				$tracker->sweep($argv[3], $argv[4]);
				break;
			default:
				throw new Exception('Unknown build type');
		}
	}
}
?>
