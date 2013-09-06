<?php
require_once('conf.php');
require_once('class.babelwatch.php');
require_once('class.front.php');

$front = new Front();

$front->echoHeader();

try
{
	Babelwatch::checkConfig();
}
catch (Exception $e)
{
	$front->displayException($e);
	exit();
}

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
				foreach ($changesetInfo as $field => $value)
				{
					// If the field contains other information than string actions
					// ('a' for 'added' and 'r' for 'removed'), ignore it
					if (!in_array($field, array('a', 'r')))
						continue;

					// Process added or removed strings
					foreach ($value as $stringArray)
					{
						$string = $stringArray['content'];
						$refs = $stringArray['references'];

						// Update $data
						array_push($data[$repoName]['changesets'][$changeset]['stringTable'][$field], array('content' => htmlentities($string), 'references' => $refs));
					}
				}
			}
		}
	}
}

// Front end stuff
$front->displayRepo($data);
$front->echoFooter();