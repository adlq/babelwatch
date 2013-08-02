<?php
class Front
{
	public function __construct()
	{

	}

	public function echoHeader()
	{
		echo <<<HEADER
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<link rel="stylesheet" href="style.css">
	<title>Localisation Dashboard</title>
</head>
<body>
HEADER;
	}

	public function echoFooter()
	{
		echo <<<FOOTER
		</body>
		</html>
FOOTER;
	}

	public function displayRepo($data)
	{
		$tabs = '';
		$body = '';
		$style = '';

		foreach ($data as $repoName => $repoData)
		{
			$formattedRepoName = str_replace('.', '', $repoName);

			$checked = array_key_exists('focused', $repoData) ? 'checked' : '';
			$tabs .= "<input type=radio id=$formattedRepoName name=tab $checked><label for=$formattedRepoName>$repoName</label>";

			$style .= <<<STYLE
			<style>
			#$formattedRepoName:checked ~ .$formattedRepoName
			{
				display: block;
			}
			.$formattedRepoName
			{
				padding: 10px;
				display: none;
				border: 1px solid;
			}
			</style>
STYLE;

			$body .= <<<REPO
				<div class=$formattedRepoName>
				<h1>$repoName</h1><br>
REPO;

			foreach ($repoData['changesets'] as $changeset => $changesetData)
			{
				$body .= "<b>{$changesetData['summary']}</b> [$changeset] (<i>{$changesetData['user']}</i>)<br><br>";
				$body .= $this->displayStringTable($changesetData['stringTable']);
			}

			$body .= '</div>';
		}

		echo $tabs;
		echo $style;
		echo $body;
	}

	public function displayStringTable($stringTable)
	{
		$out = '';

		$addedStringsNum = count($stringTable['a']);
		$removedStringsNum = count($stringTable['r']);
		$out .= <<<TABLE
				<br><table class='changeset'>
					<th>+ ($addedStringsNum)</th>
					<th>- ($removedStringsNum)</th>
TABLE;

		$addedStrings = new ArrayIterator($stringTable['a']);
		$removedStrings = new ArrayIterator($stringTable['r']);

		$rows = new MultipleIterator(MultipleIterator::MIT_NEED_ANY|MultipleIterator::MIT_KEYS_ASSOC);
		$rows->attachIterator($addedStrings, 'added');
		$rows->attachIterator($removedStrings, 'removed');

		foreach($rows as $row)
		{
			$addedRowContent = isset($row['added']) ? "<a href={$row['added']['url']}>\"{$row['added']['string']}\"</a>" : '';
			$removedRowContent = isset($row['removed']) ? "\"{$row['removed']['string']}\"" : '';

			$out .= <<<ROW
			<tr>
			<td class='addedRow'>$addedRowContent</td>
			<td class='removedRow'>$removedRowContent</td>
			</tr>
ROW;
		}
		$out .= "</table><br>";

		return $out;
	}
}
