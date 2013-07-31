<?php
require_once('conf.php');
require_once('class.babelwatch.php');

$dbHandle = new PDO('mysql:host=svrtest10;dbname=zanata', 'zanata', 'zanata');

function textFlowUrl($project, $iteration, $doc, $textflowId)
{
	return "http://svrtest10:8080/zanata/webtrans/translate?project=$project&iteration=$iteration&localeId=fr-FR&locale=fr#view:doc;doc:$doc;textflow:$textflowId";
}

foreach ($GLOBALS['conf']['repo'] as $repoName => $repoInfo)
{
	if ($repoInfo['active'])
	{
		$tracker = new Babelwatch(
			$repoName,
			$repoInfo['repoPath'],
			$GLOBALS['conf']['assetPath'],
			$GLOBALS['conf']['tmsToolkitPath'],
			$GLOBALS['conf']['pophpPath'],
			$GLOBALS['conf']['mysql']);
		echo "<h1>$repoName</h1><br><ul>";
		$log = $tracker->log();

		foreach($log as $changeset => $changes)
		{
			if (array_key_exists('a', $changes))
			{
				echo "<li>$changeset<br>";
				foreach($changes['a'] as $string)
				{
					$sql = 'select tf.potEntryData_id, tf.content0
								from HTextFlow as tf
								inner join HDocument as doc
									on tf.document_id = doc.id
								inner join HProjectIteration as it
									on it.id = doc.project_iteration_id
								inner join HProject as project
									on project.id = it.project_id
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
					echo "<a href=$url>\"$string\"</a><br>";
				}
				echo "</li>";
			}
		}
		echo "</ul>";
	}
}
?>
