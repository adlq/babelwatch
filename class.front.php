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
			$tabs .= "<input type=radio id=$formattedRepoName name=tab $checked><label class=tab_label for=$formattedRepoName>$repoName</label>";

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
				background: rgba(255, 255, 255, 0.5);
			}
			</style>
STYLE;

			$body .= <<<REPO
				<div class=$formattedRepoName>
				<h1>$repoName (<a href="diff.php?repo=$repoName">Diff</a>)</h1><br>
REPO;

			foreach ($repoData['changesets'] as $changeset => $changesetData)
			{
				$summary = $this->processRevisionSummary($changesetData['summary']);
				$body .= "<b>$summary</b> [$changeset] (<i>{$changesetData['user']}</i>)<br><br>";
				$body .= $this->displayStringTable($changesetData['stringTable']);
			}

			$body .= '</div>';
		}

		echo $tabs;
		echo $style;
		echo $body;
	}

	/**
	 *
	 * @param $stringTable
	 *
	 * @return string
	 */
	public function displayStringTable($stringTable)
	{
		$out = <<<BIGTABLE
				<table class=big_table>
				<tr>
BIGTABLE;

		foreach ($stringTable as $action => $entries)
		{
			$sign = ($action === 'a') ? '+' : '-';

			$count = count($stringTable[$action]);

			$out .= <<<TABLE
				<td><table class='changeset'>
					<th>$sign ($count)</th>
TABLE;

			foreach ($entries as $entry)
			{
				$rowContent = ($action === 'a') ? "<a target=_blank class=added_string href={$entry['url']}>\"{$entry['string']}\"</a>" : "\"{$entry['string']}\"";
				$rowClass = ($action === 'a') ? 'addedRow' : 'removedRow';

				$out .= <<<ROW
					<tr>
					<td class=$rowClass>$rowContent</td>
					</tr>
ROW;
			}
			$out .= "</table></td>";
		}

		$out .= "</tr></table><br><br>";
		return $out;
	}

	public function processRevisionSummary($string)
	{
		$spiraRegex = "/\[([A-Z]+:0*(\d+))\]/";
		$matches = array();
		if (preg_match_all($spiraRegex, $string, $matches) && count($matches) === 3)
		{
			foreach($matches[1] as $match)
				$string = preg_replace("/$match/", "<a class=spira_link target=_blank href=http://prod.epistema.com/spira/?artifact={$match}>$match</a>", $string);
		}

		return $string;
	}

	public function displayException($exception)
	{
		echo <<<EXCEPTION
		<div class=exception>
		{$exception->getMessage()}<br>
		</div>
EXCEPTION;
	}
}
