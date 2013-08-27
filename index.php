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
					foreach ($changesetInfo['a'] as $stringArray)
					{
						$string = $stringArray['content'];
						$refs = $stringArray['references'];

						$url = $tmsToolkit->getTextflowWebTransUrl($string, 'fr-FR', 'fr', $repoInfo['sourceDocName']);

						// Update $data
						array_push($data[$repoName]['changesets'][$changeset]['stringTable']['a'], array('content' => htmlentities($string), 'url' => $url, 'references' => $refs));
					}
				}

				if (array_key_exists('r', $changesetInfo))
				{
					foreach ($changesetInfo['r'] as $stringArray)
					{
						$string = $stringArray['content'];
						$ref = $stringArray['references'];

						// Update $data
						array_push($data[$repoName]['changesets'][$changeset]['stringTable']['r'], array('content' => htmlentities($string), 'references' => $ref));
					}
				}
			}
		}
	}
}

// Front end stuff
$front->displayRepo($data);
$front->echoFooter();