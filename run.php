<?php
require_once('conf.php');

foreach ($GLOBALS['conf']['repo'] as $repoName => $repoInfo)
{
	if ($repoInfo['active'])
	{
		if (array_key_exists('repoPath', $repoInfo))
		{
			chdir($repoInfo['repoPath']);
			//exec('hg incoming --bundle incoming.hg && hg pull incoming.hg && hg update --clean && cd ' . __DIR__ . ' && php build.php ' . $repoName);
			exec('hg incoming --bundle incoming.hg && hg pull incoming.hg && cd ' . __DIR__ . ' && php build.php ' . $repoName . ' build');
		}
	}
}
?>
