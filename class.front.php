<?php
class Front
{
	private $cellId;

	public function __construct()
	{
		$this->cellId = 0;
	}

	/**
	 * Print the HTML header
	 */
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

	/**
	 * Print the HTML footer
	 */
	public function echoFooter()
	{
		echo <<<FOOTER
		</body>
		<script type='text/javascript'>
		function toggleVisibility(id)
		{
			var e = document.getElementById(id);
			if (e.style.display == 'block')
				e.style.display = 'none';
			else
				e.style.display = 'block';
		}
		</script>
		</html>
FOOTER;
	}

	/**
	 * Return the HTML to display the tabs for given repositories
	 *
	 * @param array $data An array respecting the following structure:
	 *
	 * repo > changeset > action > entry > content
	 *																	 > url
	 *																	 > references
	 *
	 */
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
				<h1>$repoName</h1><br>
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
	 * Return the HTML to display a table containing:
	 * - 'added' and 'removed' string columns;
	 * - references for each string;
	 * - for 'added' strings, URL to the entry on the TMS
	 *
	 * @param array $stringTable An array respecting the
	 * following structure:
	 *
	 * action > entry > content
	 *								> url
	 *								> references
	 *
	 * action is either 'a' ('added') or 'r' ('removed')
	 *
	 * @param boolean $needButtons Whether to display buttons next
	 * to each string
	 *
	 * @return string
	 */
	public function displayStringTable($stringTable, $needButtons = true)
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
				$references = $this->displayReferences($entry['references']);
				$urlButton = array_key_exists('url', $entry) ? $this->displayUrl($entry['url']) : '';
				$rowClass = ($action === 'a') ? 'addedRow' : 'removedRow';

				$buttons = ($needButtons) ? "{$references['button']} $urlButton" : '';

				$out .= <<<ROW
					<tr>
					<td class=$rowClass>
						<table>
							<tr>
							<td>$buttons</td>
							<td><pre>{$entry['content']}</pre>{$references['content']}</td>
							</tr>
						</table>
					</td>
					</tr>
ROW;
			}
			$out .= "</table></td>";
		}

		$out .= "</tr></table><br><br>";
		return $out;
	}

	/**
	 * Output the HTML corresponding to a string's references.
	 *
	 * @param array $array An array containing the references
	 * @return string
	 */
	private function displayReferences($array)
	{
		$this->cellId++;
		$elementId = "ref_box_{$this->cellId}";
		$button = "<input type=submit value=R class=ref_vis_toggler onclick=\"toggleVisibility('$elementId');\">";
		$res = <<<REF
		<ul id=$elementId style="display: none">
REF;
		foreach ($array as $ref)
		{
			$res .= "<li>$ref</li>";
		}

		$res .= '</ul>';

		return array('button' => $button, 'content' => $res);
	}

	private function displayUrl($url)
	{
		return "<a target=_blank class=added_string href=$url><input type=submit value=Z class=tms_vis_toggler></a>";
	}

	/**
	 * Process a revision summary. Replace the Spira artifact
	 * by an URL.
	 *
	 * @param string $string The summary
	 *
	 * @return mixed
	 */
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

	/**
	 * Display a raised exception
	 *
	 * @param Exception $exception The exception
	 */
	public function displayException($exception)
	{
		echo <<<EXCEPTION
		<div class=exception>
		{$exception->getMessage()}<br>
		</div>
EXCEPTION;
	}
}
