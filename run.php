<?php
require_once('conf.php');

foreach ($GLOBALS['conf']['repo'] as $repoName => $repoInfo)
{
	if ($repoInfo['active'])
	{
		if (array_key_exists('repoPath', $repoInfo))
		{
			chdir($repoInfo['repoPath']);
			exec('hg incoming --bundle incoming.hg && hg pull -update incoming.hg && cd /home/nduong/babelwatch/ && php build.php ' . $repoName);
		}
	}
}
?>
