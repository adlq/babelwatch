<?php
require_once('conf.php');
require_once('class.babelwatch.php');
require_once('class.front.php');

$front = new Front();

$front->echoHeader();

$dbHandle = new PDO('mysql:host=svrtest10;dbname=zanata', 'zanata', 'zanata');

function textFlowUrl($project, $iteration, $doc, $textflowId)
{
	return "http://svrtest10:8080/zanata/webtrans/translate?project=$project&iteration=$iteration&localeId=fr-FR&locale=fr#view:doc;doc:$doc;textflow:$textflowId";
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
				if (array_key_exists('a', $changesetInfo))
				{
					foreach ($changesetInfo['a'] as $string)
					{
						// Generate Zanata URL for each added string
						$sql = 'SELECT tf.potEntryData_id, tf.content0
									FROM HTextFlow as tf
									INNER JOIN HDocument AS doc
										ON tf.document_id = doc.id
									INNER JOIN HProjectIteration AS it
										ON it.id = doc.project_iteration_id
									INNER JOIN HProject AS project
										ON project.id = it.project_id
									WHERE doc.name LIKE :doc
									AND project.slug LIKE :project
									AND it.slug LIKE :iteration
									AND tf.content0 LIKE :string
									LIMIT 0,1';

						$query = $dbHandle->prepare($sql);
						$query->bindParam(':project', $repoInfo['projectSlug']);
						$query->bindParam(':iteration', $repoInfo['iterationSlug']);
						$query->bindParam(':doc', $repoInfo['sourceDocName']);
						$query->bindParam(':string', $string);

						$query->execute();

						$row = $query->fetch(PDO::FETCH_ASSOC);
						$resId = $row['potEntryData_id'];

						$url = textFlowUrl($repoInfo['projectSlug'], $repoInfo['iterationSlug'], $repoInfo['sourceDocName'], $resId);

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