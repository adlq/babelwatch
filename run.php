<?php
require_once('conf.php');

/**
 * This file is to be run by a cron job.
 *
 * It will scan through each repo in the conf.php file
 * and do the following:
 *
 * - Pull the latest changesets from the 'default' URL
 * (set in the repo-specific hgrc file) if they exist.
 * - Perform the appropriate actions, with respect to
 * the configuration options set in conf.php. From the
 */
foreach ($GLOBALS['conf']['repo'] as $repoName => $repoInfo)
{
	if ($repoInfo['active'])
	{
		if (array_key_exists('repoPath', $repoInfo))
		{
			chdir($repoInfo['repoPath']);
			exec('hg incoming --bundle incoming.hg && hg pull incoming.hg && cd ' . __DIR__ . ' && php build.php ' . $repoName . ' build');
		}
	}
}
?>
