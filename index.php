<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<link rel="stylesheet" href="style.css">
	<title>Localisation Dashboard</title>
</head>
<body>
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

		$log = $tracker->log();

		if (!empty($log))
		{
			echo <<<REPO
			<h1>$repoName</h1><br>
			<ul>
REPO;
			foreach($log as $changeset => $changesetInfo)
			{
				echo <<<TABLE
				<table class='changeset'>
					<th>+</th>
					<th>-</th>
TABLE;

				$stringsStates = array('a' => array(), 'r' => array());

				if (array_key_exists('a', $changesetInfo))
				{
					chdir ($repoInfo['repoPath']);

					$user = htmlentities($changesetInfo['user']);
					$summary = htmlentities($changesetInfo['summary']);

					echo "<li><b>$summary</b> [$changeset] (<i>$user</i>)<br><br>";
					foreach ($changesetInfo['a'] as $string)
					{
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

						array_push($stringsStates['a'], array('string' => htmlentities($string), 'url' => $url));
					}
				}

				if (array_key_exists('r', $changesetInfo))
				{
					foreach ($changesetInfo['r'] as $string)
					{
						array_push($stringsStates['r'], array('string' => htmlentities($string)));
					}
				}

				$addedStrings = new ArrayIterator($stringsStates['a']);
				$removedStrings = new ArrayIterator($stringsStates['r']);

				$rows = new MultipleIterator(MultipleIterator::MIT_NEED_ANY|MultipleIterator::MIT_KEYS_ASSOC);
				$rows->attachIterator($addedStrings, 'added');
				$rows->attachIterator($removedStrings, 'removed');

				foreach($rows as $row)
				{
					$addedRowContent = isset($row['added']) ? "<a href={$row['added']['url']}>\"{$row['added']['string']}\"</a>" : '';
					$removedRowContent = isset($row['removed']) ? "\"{$row['removed']['string']}\"" : '';

					echo <<<ROW
					<tr>
					<td class='addedRow'>$addedRowContent</td>
					<td class='removedRow'>$removedRowContent</td>
					</tr>
ROW;
				}
				echo "</table></li><br>";
			}
			echo "</ul>";
		}
	}
}
?>
</body>
</html>