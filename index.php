<?php
require_once('conf.php');
require_once('class.babelwatch.php');
require_once('class.front.php');

$front = new Front();

$front->echoHeader();

// Data to be sent to the front
$data = array();

foreach ($GLOBALS['conf']['repo'] as $repoName => $repoInfo)
{
	if ($repoInfo['active'] === true)
	{
		// Initialize tracker
		$tracker = new Babelwatch(
			$repoName,
			$repoInfo['repoPath'],
			$GLOBALS['conf']['assetPath'],
			$GLOBALS['conf']['tmsToolkitPath'],
			$GLOBALS['conf']['pophpPath'],
			$GLOBALS['conf']['mysql'],
			null,
			$repoInfo['operations']);

		$tmsToolkit = $tracker->getTmsToolkit();

		// Retrieve log
		$log = $tracker->log();

		if (!empty($log))
		{
			// Default tab or not
			$data[$repoName] = array('changesets' => array());
			if (array_key_exists('focused', $repoInfo) && $repoInfo['focused'])
				$data[$repoName]['focused'] = true;

			// Iterate over all the changesets
			foreach($log as $changeset => $changesetInfo)
			{
				// Prepare data of the changeset
				$data[$repoName]['changesets'][$changeset] = array('stringTable' => array('a' => array(), 'r' => array()));
				$data[$repoName]['changesets'][$changeset]['user'] = htmlentities($changesetInfo['user']);
				$data[$repoName]['changesets'][$changeset]['summary'] = htmlentities($changesetInfo['summary']);

				// Process added strings
				if (array_key_exists('a', $changesetInfo))
				{
					foreach ($changesetInfo['a'] as $string)
					{
						$url = $tmsToolkit->getTextflowWebTransUrl($string, 'fr-FR', 'fr', $repoInfo['sourceDocName']);

						// Update $data
						array_push($data[$repoName]['changesets'][$changeset]['stringTable']['a'], array('string' => htmlentities($string), 'url' => $url));
					}
				}

				if (array_key_exists('r', $changesetInfo))
				{
					foreach ($changesetInfo['r'] as $string)
					{
						// Update $data
						array_push($data[$repoName]['changesets'][$changeset]['stringTable']['r'], array('string' => htmlentities($string)));
					}
				}
			}
		}
	}
}

// Front end stuff
$front->displayRepo($data);
$front->echoFooter();