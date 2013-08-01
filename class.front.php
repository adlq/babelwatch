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

	public function displayStringTable($stringTable)
	{
		$addedStringsNum = count($stringTable['a']);
		$removedStringsNum = count($stringTable['r']);
		echo <<<TABLE
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

			echo <<<ROW
			<tr>
			<td class='addedRow'>$addedRowContent</td>
			<td class='removedRow'>$removedRowContent</td>
			</tr>
ROW;
		}
		echo "</table><br>";
	}
}
