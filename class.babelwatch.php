<?php
require_once('common.php');
class Babelwatch
{

	private $repoName;
	private $repoPath;

	private $assetPath;
	private $tmsToolkitPath;
	private $poToolkitPath;

	private $tmsToolkit;

	private $dbConf;
	private $dbHandle;

	private $resourceExtractor;
	private $dbInitScript = "babelwatch.sql";

	private $operations;

	/**
	 * Constructor
	 * Note: the path should end with a slash
	 *
	 * @param string $repoName Name of the repo folder
	 * @param string $repoPath Full path to the repo
	 * @param string $assetPath Directory containing all the assets (generated
	 * POT files, PO files, temp files)
	 * @param string $tmsToolkitPath Directory containing TMS toolkit (zanata-php-toolkit)
	 * @param string $poToolkitPath Directory containing pophp
	 * @param array $dbConf Array containing DB configuration
     * @param ResourceExtractor $resourceExtractor A localizable resource extractor
     * @param int $operations The operations to perform (see common.php)
	 */
	public function __construct(
				$repoName,
				$repoPath,
				$assetPath,
				$tmsToolkitPath,
				$poToolkitPath,
				$dbConf,
				$resourceExtractor = null,
				$operations = 7)
	{
		$this->repoName = $repoName;
		$this->repoPath = $repoPath;

		$this->assetPath = $assetPath;
		$this->tmsToolkitPath = $tmsToolkitPath;
		$this->poToolkitPath = $poToolkitPath;

		$this->dbConf = $dbConf;

		$this->resourceExtractor = $resourceExtractor;

		$this->operations = $operations;

		$this->prepareDatabase();
		date_default_timezone_set('Europe/Paris');
	}

	/**
	 * Check conf.php for missing parameters
	 *
	 * @throws RuntimeException
	 */
	public static function checkConfig()
	{
		if (!array_key_exists('mysql', $GLOBALS['conf']))
			throw new RuntimeException('No MySQL configuration found in conf.php');

		$mysqlExceptionContext = array('context' => 'mysql');

		Babelwatch::checkConfigKey('host', $GLOBALS['conf']['mysql'], $mysqlExceptionContext);
		Babelwatch::checkConfigKey('user', $GLOBALS['conf']['mysql'], $mysqlExceptionContext);
		Babelwatch::checkConfigKey('pwd', $GLOBALS['conf']['mysql'], $mysqlExceptionContext);
		Babelwatch::checkConfigKey('db', $GLOBALS['conf']['mysql'], $mysqlExceptionContext);

		if (!array_key_exists('repo', $GLOBALS['conf']) && is_array($GLOBALS['conf']['repo']))
			throw new RuntimeException("No repository configuration found in conf.php");


		foreach ($GLOBALS['conf']['repo'] as $repoName => $repoInfo)
		{
			$repoExceptionContext = array('context' => 'repo', 'repoName' => $repoName);

			if (!is_array($repoInfo))
			throw new RuntimeException('Each entry in $GLOBALS[\'repo\'] must be an array');

			Babelwatch::checkConfigKey('active', $repoInfo, $repoExceptionContext);
			Babelwatch::checkConfigKey('operations', $repoInfo, $repoExceptionContext);
			Babelwatch::checkConfigKey('repoPath', $repoInfo, $repoExceptionContext);
			Babelwatch::checkConfigKey('sourceFolder', $repoInfo, $repoExceptionContext);
			Babelwatch::checkConfigKey('extensions', $repoInfo, $repoExceptionContext);
			Babelwatch::checkConfigKey('resourceExtractorClass', $repoInfo, $repoExceptionContext);
			Babelwatch::checkConfigKey('projectSlug', $repoInfo, $repoExceptionContext);
			Babelwatch::checkConfigKey('iterationSlug', $repoInfo, $repoExceptionContext);
			Babelwatch::checkConfigKey('blacklist', $repoInfo, $repoExceptionContext);
			Babelwatch::checkConfigKey('sourceDocName', $repoInfo, $repoExceptionContext);

			if (!file_exists($repoInfo['repoPath']))
				throw new RuntimeException("Specified repository path ('{$repoInfo['repoPath']}') is invalid. No such directory");

			if (!file_exists($repoInfo['repoPath'] . $repoInfo['sourceFolder']))
				throw new RuntimeException("Specified source folder ('{$repoInfo['sourceFolder']}') is invalid. No such directory");
		}

		$globalExceptionContext = array('context' => 'global');

		Babelwatch::checkConfigKey('assetPath', $GLOBALS['conf'], $globalExceptionContext);
		Babelwatch::checkConfigKey('tmsToolkitPath', $GLOBALS['conf'], $globalExceptionContext);
		Babelwatch::checkConfigKey('pophpPath', $GLOBALS['conf'], $globalExceptionContext);
		Babelwatch::checkConfigKey('hgrcPath', $GLOBALS['conf'], $globalExceptionContext);
		Babelwatch::checkConfigKey('mailTo', $GLOBALS['conf'], $globalExceptionContext);
		Babelwatch::checkConfigKey('mailNotifications', $GLOBALS['conf'], $globalExceptionContext);
	}

	/**
	 * Look for a specific key in the given array,
	 * then check whether the value of that key
	 * is a valid file path (i.e. existing file or folder).
	 *
	 * This is used for the configuration check.
	 *
	 * @param string $key The key
	 * @param array $parentArray The array to check
	 * @param array $exceptionContext Context information
	 * @param boolean $isPath Whether the value is a path or not
	 *
	 * @throws RuntimeException|LogicException
	 */
	private static function checkConfigKey($key, $parentArray, $exceptionContext, $isPath = false)
	{
		if (!array_key_exists($key, $parentArray))
		{
			if (!array_key_exists('context', $exceptionContext))
				throw new LogicException("Missing exception context when looking for '$key'");

			switch ($exceptionContext['context'])
			{
				case 'repo':
					if (!array_key_exists('repoName', $exceptionContext))
						throw new LogicException("Missing repository name in context array when looking for '$key'");
					throw new RuntimeException("Missing parameter '$key' in conf.php for repository '{$exceptionContext['repoName']}'. Please refer to conf_sample.php");
				case 'mysql':
					throw new RuntimeException("Missing MySQL parameter '$key' in conf.php. Please refer to conf_sample.php");
				case 'global':
					throw new RuntimeException("No '$key' path found in conf.php");
			}
		}
		else
		{
			if ($isPath && !file_exists($parentArray[$key]))
				throw new RuntimeException("Specified '$key' path ('{$parentArray[$key]}') is invalid. No such directory");
		}
	}

	/**
	 * Create necessary tables & Initialize database handle
	 */
	private function prepareDatabase()
	{
		$this->dbHandle = new PDO(
				'mysql:host='
				. $this->dbConf['host'],
				$this->dbConf['user'], $this->dbConf['pwd'], array(\PDO::MYSQL_ATTR_INIT_COMMAND =>  'SET NAMES utf8'));

		$sqlDbCheck = 'SHOW DATABASES LIKE :dbName';
		$queryDbCheck = $this->dbHandle->prepare($sqlDbCheck);
		$queryDbCheck->bindParam(':dbName', $this->dbConf['db'], PDO::PARAM_STR);
		$queryDbCheck->execute();

		if ($queryDbCheck->rowCount() === 0)
		{
			$sqlSetup = file_get_contents($this->dbInitScript);
			$querySetup = $this->dbHandle->prepare($sqlSetup);
			$querySetup->execute();
		}
		else
		{
			$this->dbHandle = new PDO(
					'mysql:host='
					. $this->dbConf['host']
					. ';dbname='
					. $this->dbConf['db'],
					$this->dbConf['user'], $this->dbConf['pwd']);
		}

	}

	/**
	 * Run babelwatch over a range of revisions
	 * NOTE: The revision number is local and
	 * thus unique to each version of the repo
	 *
	 * @param int $rev1 Starting revision (any format)
	 * @param int $rev2 Ending revision (any format)
	 */
	public function sweep($rev1, $rev2)
	{
		echo "\nWORKING ON " . strtoupper($this->repoName) . "\n";

		chdir($this->repoPath);

		// Retrieve the right revisions
		try
		{
			$revisions = $this->getRightRevisions($rev1, $rev2);
		}
		catch (RuntimeException $e)
		{
			die ($e->getMessage());
		}

		// If we have processed all of the revisions, do nothing
		if (count($revisions) <= 1)
			exit("Nothing to do");

		// Clean init @ oldest revision
		$this->initAtRevision($revisions[0]);
		array_shift($revisions);

		foreach ($revisions as $rev)
		{
			// Update the code
			echo "UPDATING TO REVISION $rev...\n";
			exec("hg update --clean --rev $rev");

			$potFiles = $this->resourceExtractor->getGettextFilesPath();

			if ($this->hasToPerform(UPDATE_POT))
			{
				try
				{
					$potFiles = $this->resourceExtractor->buildGettextFiles($potFiles['new'], true, true);
				}
				catch (RuntimeException $e)
				{
					die($e->getMessage());
				}

				// Only update the TMS and the tracking if there were new or removed strings
				$diffStrings = $this->comparePots($potFiles['old'], $potFiles['new']);
				$proceed = !empty($diffStrings['added']) || !empty($diffStrings['removed']);

				// The strings have changed!
				if ($proceed)
				{
					// Send mail to the teams
					if ($GLOBALS['conf']['mailNotifications'] === true)
						$this->composeStringMail($rev, $diffStrings);

					if ($this->hasToPerform(UPDATE_TMS))
						$this->updateTMS($potFiles['new']);

					if ($this->hasToPerform(UPDATE_TRACKING))
					$this->updateTracking($diffStrings);
				}
			}
		}

		echo "\nWORK ON " . strtoupper($this->repoName) . " DONE\n";
		return;
	}

	/**
	 * Send an update mail to the right people
	 *
	 * @param array $diffStrings An array containing new and removed strings
	 * @param unknown_type $revision The revision full hash id
	 */
	private function composeStringMail($revision, $diffStrings)
	{
		try
		{
			$mail = $this->generateStringMailMessage($revision, $diffStrings);
		}
		catch (RuntimeException $e)
		{
			echo "Could not compose mail. {$e->getMessage()}\n\n";
			return;
		}
		$headers = "From:Localisation@crossknowledge.com \r\n";
		$headers .= "Reply-To: {$mail['replyToAddress']}\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
		$mailSent = mail($GLOBALS['conf']['mailTo'], $mail['subject'], $mail['body'], $headers);
	}

	/**
	 * Generate the body of the string update email
	 *
	 * @param array $diffStrings An array containing new and removed strings
	 * @param unknown_type $revision The revision full hash id
	 */
	private function generateStringMailMessage($revision, $diffStrings)
	{
		// Retrieve revision info
		$revInfo = $this->getRevisionInfo($revision);
		$replyToAddress = $this->getReplyToAddress($revInfo);

		require_once(__DIR__ . '/class.front.php');
		$front = new Front();
		$revInfo['summary'] = $front->processRevisionSummary($revInfo['summary']);

		$newCount = count($diffStrings['added']);
		$removedCount = count($diffStrings['removed']);

		$newStringText = ($newCount === 1) ? "Une chaîne a été ajoutée :" : "$newCount chaînes ont été ajoutées :";
		$removedStringText = ($removedCount === 1) ? "Une chaîne a été supprimée :" : "$removedCount chaînes ont été supprimées :";

		$newStringText .= "<br><ul>";
		$removedStringText .= "<br><ul>";

		if ($newCount === 0)
			$newStringText = '';
		if ($removedCount === 0)
			$removedStringText = '';

		foreach ($diffStrings['added'] as $newString)
		{
			$newStringText .= "<li>{$newString->getSource()}</li>";
		}

		foreach ($diffStrings['removed'] as $removedString)
		{
			$removedStringText .= "<li>{$removedString->getSource()}</li>";
		}

		$newStringText .= "</ul>";
		$removedStringText .= "</ul>";

		$text = <<<EMAIL
<html>
<body>
Bonjour,
<br><br>
A la révision <i>$revision</i> (soumise par <strong>{$revInfo['user']}</strong> dans <strong>{$revInfo['branch']}</strong>)
avec le commentaire :<br>
<strong>{$revInfo['summary']}</strong>
<br><br>
$newStringText
<br><br>
$removedStringText
<br><br>
Bien cordialement,
<br><br>
L'équipe "Localisation"
</body>
</html>
EMAIL;

		return array(
		'body' => $text,
		'replyToAddress' => $replyToAddress,
		'subject' => "Mise à jour de chaînes par {$revInfo['user']} (" . substr($revision, 0, 12) . ")");
	}

	/**
	 * Extract the e-mail address from the user entry in
	 * revision info.
	 *
	 * The user must respect the following format:
	 * Name <mail@address.com>
	 *
	 * @param array $revInfo Array containing various
	 * info about the revision
	 * @throws RuntimeException
	 */
	public function getReplyToAddress($revInfo)
	{
		if (!array_key_exists('user', $revInfo))
			throw new RuntimeException('No user entry found in revision information');

		$match = array();
		if (preg_match("/<(.+)>/", $revInfo['user'], $match) && isset($match[1]))
			return $match[1];

		// Default reply-to address
		return "dev_lms@crossknowledge.com";
	}

	/**
	 * Given 2 revision ids, compute the right subset
	 * of revisions in between to watch over. The parameters
	 * do not need to be ordered.
	 *
	 * The specified revisions must be on the same branch (in this
	 * case the main branch).
	 *
	 * @param string $rev1 The first revision (any format)
	 * @param string $rev2 The second revision (any format)
	 * @return array<int> An array of the revisions (full hash format)
	 *
	 * @throws RuntimeException
	 */
	public function getRightRevisions($rev1, $rev2)
	{
		chdir($this->repoPath);

		// The revisions can be specified in any format.
		// They are then converted to their full hash via
		// the sortRevisionsByDate method
		$sortedRevisions = $this->sortRevisionsByLocalId($rev1, $rev2);
		$start = $sortedRevisions['oldest'];
		$end = $sortedRevisions['newest'];

		if ($start == $end)
			return array($start);

		/**
		 * Get the right subset of revisions
		 */
		$lastRevision = $end;

		$n = 1;
		$revisions = array();
		while ($lastRevision != $start)
		{
			if ($this->getLocalRevisionId($lastRevision) < $this->getLocalRevisionId($start))
				throw new RuntimeException("Revision $start does not belong to the same branch as revision $end");

			array_push($revisions, $lastRevision);
			// The '~n' sign specifies that we want the nth first ancestor of the $end revision
			$lastRevision = trim(shell_exec('hg log --debug -r ' . $end . '~' . $n . ' | grep -G "^changeset" | sed "s/^changeset:[[:space:]]*//g" | sed "s/.*://g"'));
			$n++;
		}

		array_push($revisions, $lastRevision);

		$revisions = array_reverse($revisions);

		return $revisions;
	}

	/**
	 * Given 2 revision ids, compute the oldest
	 * and the newest revisions, with respect to
	 * local ids.
	 *
	 * @param string $rev1 First revision (any format)
	 * @param string $rev2 Second revision (any format)
	 *
	 * @return array An associative array with 'oldest' and 'newest' keys
	 * with the full hash ids of the revisions
	 */
	private function sortRevisionsByLocalId($rev1, $rev2)
	{
		chdir($this->repoPath);

		/**
		 * If both revisions are the same, return a dummy array.
		 * We can only detect the case where 'tip'==='.' by comparing
		 * the full hash ids
		 */
		$rev1 = $this->getFullRevisionId($rev1);
		$rev2 = $this->getFullRevisionId($rev2);

		// If the revisions are the same, return a dummy array
		if ($rev1 == $rev2)
			return array('oldest' => $rev1, 'newest' => $rev2);

		/**
		 * Sort the revisions by date
		 */
		// Retrieve the local ids and automatically sort the revisions
		$revInfo = shell_exec('hg log -r ' . $rev1 . ' -r ' . $rev2 . ' | grep -G "^changeset" | sed "s/^changeset:[[:space:]]*//g" | sed "s/:.*//g"');

		// Retrieve the two local ids
		$lines = explode("\n", $revInfo);

		// Remove the empty element
		array_pop($lines);

		// Create a new ids array from the lines array
		$localIds = array($rev1 => $lines[0], $rev2 => $lines[1]);

		// After the sort, the oldest revision is the
		// first element and the newest the second
		asort($localIds, SORT_NUMERIC);
		$extrema = array_keys($localIds);
		$start = $extrema[0];
		$end = $extrema[1];

		return array('oldest' => $start, 'newest' => $end);
	}

	/**
	 * Initializes the tracker at a specific revision
	 *
	 * @param string $rev The revision (any format)
	 */
	public function initAtRevision($rev)
	{
		echo "\nINITIALIZING " . strtoupper($this->repoName) . " AT REVISION $rev\n";
		chdir($this->repoPath);
		exec("hg update --clean --rev $rev");

		$potFiles = $this->resourceExtractor->getGettextFilesPath();
		try
		{
			$this->resourceExtractor->buildGettextFiles($potFiles['new'], true, true);
		}
		catch (RuntimeException $e)
		{
			die($e->getMessage());
		}
	}

	/**
	 * Upload pot entries to the TMS
	 *
	 * @param string $newPot Path to the POT file
	 */
	public function updateTMS($newPot)
	{
		echo "===\nUpdating TMS for {$this->repoName}...\n";
		require_once($this->tmsToolkitPath . 'ZanataPHPToolkit.php');

		if (!isset($this->tmsToolkit))
			$this->createTmsToolkit();

		// Update the source entries on Zanata!
		$result = $this->tmsToolkit->pushPotEntries($newPot, $GLOBALS['conf']['repo'][$this->repoName]['sourceDocName'], 'en-GB');

		// Notify the result to the user
		if ($result === true)
			echo "TMS successfully updated\n";
		else
			echo "Failed to update TMS\n";

		echo "\n===\n";
	}


	/**
	 * Create the TMS toolkit if it has not been
	 * created yet
	 */
	private function createTmsToolkit()
	{
		if (isset($this->tmsToolkit))
			return;

		require_once($this->tmsToolkitPath . 'conf.php');
		require_once($this->tmsToolkitPath . 'ZanataPHPToolkit.php');
		// Retrieve parameters from the TMS conf.php file

		$zanataUrl = $GLOBALS['conf']['zanata']['url'];
		$user = $GLOBALS['conf']['zanata']['user'];
		$apiKey = $GLOBALS['conf']['zanata']['apiKey'];

		// Attempt to find the repo name in the TMS conf.php file
		if (isset($GLOBALS['conf']['repo'][$this->repoName]))
		{
			$projectSlug = $GLOBALS['conf']['repo'][$this->repoName]['projectSlug'];
			$iterationSlug = $GLOBALS['conf']['repo'][$this->repoName]['iterationSlug'];
		}
		else
		{
			exit("Unknown project, no section '{$this->repoName}' in conf.php file");
		}

		$this->tmsToolkit = new ZanataPHPToolkit($user, $apiKey, $projectSlug, $iterationSlug, $zanataUrl, false);
	}

	/**
	 * Return the TMS toolkit associated to this
	 * object. Create it if necessary.
	 *
	 * @return mixed
	 */
	public function getTmsToolkit()
	{
		if (!isset($this->tmsToolkit))
			$this->createTmsToolkit();

		return $this->tmsToolkit;
	}


	/**
	 * Main update function, does 3 things:
	 *	- Create a row in bw_repo for the repo if it doesn't exist
	 *	- Create a row in bw_changeset for the changeset if it doesn't exist
	 *  - Update information about strings (content, references, added/removed)
	 *
	 *  @param array $diffStrings The diff array of strings
	 */
	public function updateTracking($diffStrings)
	{
		echo "===\nUpdating tracking for {$this->repoName}...\n";

		// Retrieve changeset info
		chdir($this->repoPath);
		// Note: the tip designates the last changeset, not the version of the code
		// The code version is revision '.'
		$revInfo = $this->getRevisionInfo('.');

		// Retrieve the full hash id for the revision
		$revInfo['changeset'] = trim(shell_exec('hg log --debug -r . | grep -G "^changeset" | sed "s/^changeset:[[:space:]]*//g" | sed "s/.*://g"'));
		// Retrieve tag if it exists
		$tag = array_key_exists('tag', $revInfo) ? $revInfo['tag'] : '';

		$repoId = $this->updateRepo();
		$changesetId = $this->updateChangeset($revInfo['changeset'], $revInfo['user'], $repoId, $revInfo['summary'], date('Y-m-d H:i:s', strtotime($revInfo['date'])), $tag);
		$this->updateStringState($changesetId, $diffStrings['added'], 'a');
		$this->updateStringState($changesetId, $diffStrings['removed'], 'r');
		echo "===\n";
	}

	/**
	 * Get revision info (branch, tag, user,
	 * date, summary) on the given revision.
	 *
	 * @param string $rev The revision (any format)
	 * @return array An associative array with the info field
	 */
	public function getRevisionInfo($rev)
	{
		$logLines = explode("\n", shell_exec("hg log -r $rev | tail -n +2"));

		$revInfo = array();

		foreach($logLines as $line)
		{
			$matches = array();
			if (preg_match("/([^:]*):\s+(.+)/", $line, $matches))
			{
				$revInfo[$matches[1]] = $matches[2];
			}
		}
		return $revInfo;
	}

	/**
	 * Compare two POT files and return the diff array
	 *
	 * @param string $oldPot Path to the old POT file
	 * @param string $newPot Path to the new POT file
	 * @return array The diff array
	 */
	private function comparePots($oldPot, $newPot)
	{
		require_once($this->poToolkitPath . 'POUtils.php');

		// Compare the new and old pot files
		$utils = new POUtils();
		$diff = $utils->compare($oldPot, $newPot);

		// Retrieve added/removed strings
		$addedStrings = $diff['secondOnly'];
		$removedStrings = $diff['firstOnly'];

		echo "new: " . count($addedStrings) . ", removed: " . count($removedStrings) ."\n";

		return array('added' => $addedStrings, 'removed' => $removedStrings);
	}

	/**
	 * Create the repo if needed.
	 *
	 * @return int The id of the repo in bw_repo
	 */
	private function updateRepo()
	{
		echo "\tUpdate repo...";
		// Check repo
		$sqlRepoCheck = 'SELECT * FROM bw_repo WHERE name LIKE :repoName';
		$queryRepoCheck = $this->dbHandle->prepare($sqlRepoCheck);
		$queryRepoCheck->bindParam(':repoName', $this->repoName, PDO::PARAM_STR);
		$queryRepoCheck->execute();

		$queryRepoResult = $queryRepoCheck->fetch(PDO::FETCH_ASSOC);
		if ($queryRepoCheck->rowCount() === 0)
		{
			// Create the repo
			$sqlNewRepo = 'INSERT INTO bw_repo (name) VALUES (:repoName)';
			$queryNewRepo = $this->dbHandle->prepare($sqlNewRepo);
			$queryNewRepo->bindParam(':repoName', $this->repoName, PDO::PARAM_STR);
			$queryNewRepo->execute();

			$repoId = $this->dbHandle->lastInsertId();
		}
		else
		{
			$repoId = $queryRepoResult['id'];
		}
		echo "Done\n";
		return $repoId;
	}

	/**
	 * Create the changeset if needed.
	 *
	 * @param int $changeset The changeset number
	 * @param string $user The name of the contributor
	 * @param int $repoId The id of the repo to which the changeset belong
	 * @param string $summary The summary of the changeset
	 * @param string $date Timestamp of the changeset
	 * @param string $tag The tag, if it exists
	 * @return type
	 */
	private function updateChangeset($changeset, $user, $repoId, $summary, $date, $tag = '')
	{
		echo "\tUpdate changeset...";

		// Create new user entry if he/she does not exist yet
		$sqlNewUser = "INSERT INTO bw_user (name)
					VALUES (:name)
					ON DUPLICATE KEY UPDATE id = id";
		$queryNewUser = $this->dbHandle->prepare($sqlNewUser);
		$queryNewUser->bindParam(':name', utf8_encode($user), PDO::PARAM_STR);
		$queryNewUser->execute();

		// Select the user
		$sqlUserCheck =
				"SELECT * FROM bw_user
					WHERE name LIKE :name
					LIMIT 0,1";
		$queryUserCheck = $this->dbHandle->prepare($sqlUserCheck);
		$queryUserCheck->bindParam(':name', utf8_encode($user), PDO::PARAM_STR);
		$queryUserCheck->execute();

		$userRow = $queryUserCheck->fetch(PDO::FETCH_ASSOC);

		// Create the changeset and bind it to the repo
		$sqlNewChangeset =
				"INSERT INTO bw_changeset (hg_id, repo_id, user_id, summary, tag, date)
					VALUES (:changeset, :repoId, :userId, :summary, :tag, :date)
					ON DUPLICATE KEY UPDATE hg_id = hg_id";
		$queryNewChangeset = $this->dbHandle->prepare($sqlNewChangeset);
		$queryNewChangeset->bindParam(':changeset', $changeset, PDO::PARAM_STR);
		$queryNewChangeset->bindParam(':repoId', $repoId, PDO::PARAM_INT);
		$queryNewChangeset->bindParam(':userId', $userRow['id'], PDO::PARAM_INT);
		$queryNewChangeset->bindParam(':summary', $summary, PDO::PARAM_STR);
		$queryNewChangeset->bindParam(':tag', $tag, PDO::PARAM_STR);
		$queryNewChangeset->bindParam(':date', $date, PDO::PARAM_INT);
		$queryNewChangeset->execute();

		// Check changeset
		$sqlChangesetCheck =
				"SELECT * FROM bw_changeset
					WHERE hg_id = :changeset
					AND repo_id = :repoId
					LIMIT 0,1";
		$queryChangesetCheck = $this->dbHandle->prepare($sqlChangesetCheck);
		$queryChangesetCheck->bindParam(':changeset', $changeset, PDO::PARAM_STR);
		$queryChangesetCheck->bindParam(':repoId', $repoId, PDO::PARAM_INT);
		$queryChangesetCheck->execute();

		$result = $queryChangesetCheck->fetch(PDO::FETCH_ASSOC);

		echo "Done\n";
		return $result['id'];
	}

	/**
	 * Update information about the strings (reference, status)
	 *
     * @param string $changesetId The full id of the changeset
	 * @param array<POEntry> $entries Array of POEntry objects
	 * @param string $action 'added' or 'removed'
	 */
	private function updateStringState($changesetId, $entries, $action)
	{
		echo "\tUpdate string state...";
		// Add/Modify strings info
		foreach($entries as $entry)
		{
			$string = $entry->getSource();
			$hash = hash('sha256', $string);

			// The string doesn't exist
			$sqlNewString = 'INSERT INTO bw_string (content, hash) VALUES (:string, :hash) ON DUPLICATE KEY UPDATE hash = hash';
			$queryNewString = $this->dbHandle->prepare($sqlNewString);
			$queryNewString->bindParam(':string', $string, PDO::PARAM_STR);
			$queryNewString->bindParam(':hash', $hash);
			$queryNewString->execute();

			// Does the string exist ?
			$sqlStringCheck = 'SELECT * FROM bw_string WHERE hash = :hash LIMIT 0,1';
			$queryStringCheck = $this->dbHandle->prepare($sqlStringCheck);
			$queryStringCheck->bindParam(':hash', $hash);
			$queryStringCheck->execute();
			$result = $queryStringCheck->fetch(PDO::FETCH_ASSOC);

			$stringId = $result['id'];

			// From now on, we can be sure the string exists
			$this->updateStringReferences($stringId, $entry);
			$this->updateStringAction($changesetId, $stringId, $action);
		}
		echo "Done\n";
	}

	/**
	 * Updates the added/removed status of a string in a specific changeset
	 *
	 * @param int $changesetId The id of the changeset in bw_changeset
	 * @param int $stringId The id of the string in bw_string
	 * @param string $action The action, 'a' for 'added', 'r' for 'removed'
	 * @throws Exception
	 */
	private function updateStringAction($changesetId, $stringId, $action)
	{
		if ($action !== 'a' && $action !== 'r')
			throw new Exception("Unexpected action '$action'");

		// Update added_changeset field in table to $changeset
		$sqlStringUpdate =
				"INSERT INTO bw_changeset_string (changeset_id, string_id, action)
					VALUES (:changesetId, :stringId, :action)
					ON DUPLICATE KEY UPDATE action = action";
		$queryStringUpdate = $this->dbHandle->prepare($sqlStringUpdate);
		$queryStringUpdate->bindParam(':changesetId', $changesetId, PDO::PARAM_INT);
		$queryStringUpdate->bindParam(':stringId', $stringId, PDO::PARAM_INT);
		$queryStringUpdate->bindParam(':action', $action, PDO::PARAM_STR);
		$queryStringUpdate->execute();
	}

	/**
	 * Updates the references of a string
	 * NOTE: For now, we don't distinguish strings with different references.
	 * In other words, a pot entry is only identified by its content, not by the
	 * source code line from which it came from.
	 *
	 * @param type $stringId
	 * @param type $entry
	 */
	private function updateStringReferences($stringId, $entry)
	{
		foreach($entry->getReferences($this->resourceExtractor->getRootDir()) as $ref)
		{
			$matches = array();
			// Extract the filepath and line number
			$bool = preg_match("/(.*):(.*)/", $ref, $matches);

			if ($bool && count($matches) === 3)
			{
				$filepath = $matches[1];
				$line = $matches[2];

				// Insert the ref
				$sqlNewRef =
					'INSERT INTO bw_reference (filepath, line, hash)
					VALUES (:filepath, :line, :hash)
					ON DUPLICATE KEY UPDATE line = line';
				$queryNewRef = $this->dbHandle->prepare($sqlNewRef);
				$queryNewRef->bindParam(':filepath', $filepath);
				$queryNewRef->bindParam(':line', $line);
				$queryNewRef->bindParam(':hash', hash('sha256', $filepath . $line));
				$queryNewRef->execute();

				// Bind the ref to the string
				$sqlBindRef =
						'INSERT INTO bw_string_ref (string_id, ref_id)
							SELECT string.id, ref.id
							FROM bw_string AS string, bw_reference AS ref
							WHERE string.id = :stringId
							AND ref.filepath = :filepath
							AND ref.line = :line
							ON DUPLICATE KEY UPDATE string_id = string_id';
				$queryBindRef = $this->dbHandle->prepare($sqlBindRef);
				$queryBindRef->bindParam(':stringId', $stringId);
				$queryBindRef->bindParam(':filepath', $filepath);
				$queryBindRef->bindParam(':line', $line);
				$queryBindRef->execute();
			}
		}
	}

	/**
	 * Sample log method. Returns an array containing changesets and strings
	 * that were added or removed in those changesets
	 *
	 * @return array An array containing string updates for each changeset
	 */
	public function log()
	{
		$sql =
				'SELECT chg.hg_id as chgId, chg.summary, user.name, string.content, glue.action, ref.filepath, ref.line
					FROM bw_changeset_string AS glue
					JOIN bw_string AS string ON glue.string_id = string.id
					JOIN bw_changeset AS chg ON glue.changeset_id = chg.id
					JOIN bw_repo AS repo ON chg.repo_id = repo.id
					JOIN bw_user AS user ON chg.user_id = user.id
					JOIN bw_string_ref AS str_ref ON string.id = str_ref.string_id
					JOIN bw_reference AS ref ON str_ref.ref_id = ref.id
					WHERE repo.name LIKE :repoName
					ORDER BY chg.date DESC';
		$query = $this->dbHandle->prepare($sql);
		$query->bindParam(':repoName', $this->repoName);
		$query->execute();

		$logs = array();

		while($row = $query->fetch(PDO::FETCH_ASSOC))
		{
			$changeset = strtolower($row['chgId']);
			$action = $row['action'];
			$user = $row['name'];
			$summary = $row['summary'];

			$string = $row['content'];
			$filepath = $row['filepath'];
			$line = $row['line'];

			// We don't use the mysql CONCAT function because
			// there is a limit in the result size. And references
			// can be numerous...
			$reference = "$filepath:$line";


			if (!array_key_exists($changeset, $logs))
				$logs[$changeset] = array('user' => $user, 'summary' => $summary);

			if (!array_key_exists($action, $logs[$changeset]))
				$logs[$changeset][$action] = array();

			// If string already exist, then this means that we have more than one
			// reference. If so, push the new reference onto the string array
			$stringArrayId = $this->doesStringExist($string, $logs[$changeset][$action]);
			if ($stringArrayId !== false)
			{
				array_push($logs[$changeset][$action][$stringArrayId]['references'], $reference);
			}
			else
			{
				$stringArray = array('content' => $string, 'references' => array($reference));
				array_push($logs[$changeset][$action], $stringArray);
			}
		}

		return $logs;
	}

	/**
	 * Check whether a string already exists in an array
	 * that has the following structure:
	 *
	 * id > stringArray > content
	 * 									> other fields...
	 *
	 * @param string $string The string we're looking for
	 * @param $array
	 *
	 * @return miwed The id of the stringArray if it
	 * exists, False otherwise.
	 */
	private function doesStringExist($string, $array)
	{
		foreach ($array as $id => $stringArray)
		{
			if ($stringArray['content'] === $string)
				return $id;
		}

		return false;
	}

	/**
	 * Return an array containing new strings and removed
	 * strings between two revisions.
	 *
	 * @param string $rev1 The starting revision (full hash format)
	 * @param string $rev2 The ending revision (full hash format)
	 *
	 * @return array
	 */
	public function diffBetweenRevisions($rev1, $rev2)
	{
		$startDate = $this->getRevisionDateById($rev1);
		$endDate = $this->getRevisionDateById($rev2);

		$sql =
		'SELECT
		str.content AS str,
		chgstr.action AS action,
		ref.filepath, ref.line
		FROM bw_changeset_string AS chgstr
		JOIN
		(SELECT chg.id AS last_chg_id,
					foo.string_id
					FROM bw_changeset AS chg
			JOIN
			(SELECT
						str.id AS string_id,
						MAX(chg.date) AS last_chg_time
						FROM bw_changeset_string AS chgstr
				JOIN bw_changeset AS chg
					ON chg.id = chgstr.changeset_id
				JOIN bw_string as str
					ON chgstr.string_id = str.id
				JOIN bw_repo AS repo
					ON chg.repo_id = repo.id
				WHERE chg.date > :startDate
	AND chg.date <= :endDate
	AND repo.name = :repoName
				GROUP BY chgstr.string_id) AS foo
				ON chg.date = foo.last_chg_time) AS bar
			ON chgstr.changeset_id = bar.last_chg_id
	AND chgstr.string_id = bar.string_id
		JOIN bw_string as str
			ON bar.string_id = str.id
		JOIN bw_string_ref as strref
			ON str.id = strref.string_id
		JOIN bw_reference as ref
			ON strref.ref_id = ref.id';

		$query = $this->dbHandle->prepare($sql);
		$query->bindParam(':repoName', $this->repoName);
		$query->bindParam(':startDate', $startDate);
		$query->bindParam(':endDate', $endDate);
		$query->execute();

		$diffInfo = array('a' => array(), 'r' => array());
		while($row = $query->fetch(PDO::FETCH_ASSOC))
		{
			$string = $row['str'];
			$reference = "{$row['filepath']}:{$row['line']}";

			$stringArrayId = $this->doesStringExist($string, $diffInfo[$row['action']]);

			if ($stringArrayId !== false)
			{
				array_push($diffInfo[$row['action']][$stringArrayId]['references'], $reference);
			}
			else
			{
				$stringArray = array('content' => $string, 'references' => array($reference));
				array_push($diffInfo[$row['action']], $stringArray);
			}
		}

		// Process the diff result
		$potfile = $this->getPotAtRevision($rev1);
		$tmsToolkit = $this->getTmsToolkit();

		foreach ($diffInfo as $action => $entries)
		{
			foreach ($entries as $id => $entry)
			{
				// If the string was added but already existed in the starting revision, ignore it
				if ($action === 'a' && $potfile->getEntry($entry['content']) !== false)
				{
					unset($diffInfo['a'][$id]);
				}
				// If the string was removed but didn't exist in the starting revision, ignore it
				else if ($action === 'r' && $potfile->getEntry($entry['content']) === false)
				{
					unset($diffInfo['r'][$id]);
				}
				// If the string
				else if ($action === 'a')
				{
					$url = $tmsToolkit->getTextflowWebTransUrl($entry['content'], 'fr-FR', 'fr', $GLOBALS['conf']['repo'][$this->repoName]['sourceDocName']);
					$diffInfo[$action][$id]['url'] = $url;
					$diffInfo[$action][$id]['references'] = $entry['references'];
				}
			}
		}

		return $diffInfo;
	}

	/**
	 * Check whether a revision is in babel's database
	 *
	 * @param string $revision The revision (full hash format)
	 *
	 * @return bool
	 */
	public function isRevisionInDb($revision)
	{
		$sql = 'SELECT * FROM bw_changeset AS chg
		JOIN bw_repo AS repo ON repo.id = chg.repo_id
		WHERE chg.hg_id = :revision AND repo.name LIKE :repoName';
		$query = $this->dbHandle->prepare($sql);
		$query->bindParam(':revision', $revision, PDO::PARAM_STR);
		$query->bindParam(':repoName', $this->repoName, PDO::PARAM_STR);
		$query->execute();

		if ($query->rowCount() === 0)
			return false;
		return true;
	}

	/**
	 * Return the array of POEntry objects
	 * present at a specific revision.
	 *
	 * This is often called by the web server,
	 * so appropriate rights will have to be set
	 * on the asset folder ($this->assetPath).
	 *
	 * @param string $rev The revision id (any format)
	 *
	 * @return POFile
	 */
	public function getPotAtRevision($rev)
	{
		chdir($this->repoPath);
		// Keep the current revision in mind
		$oldRev = trim(shell_exec('hg log -r . | grep -G "^changeset" | sed "s/^changeset:[[:space:]]*//g" | sed "s/:.*//g"'));

		// Update to the specified revision
		exec("hg update --clean --rev $rev");

		// Rebuild POT file
		$potName = $this->assetPath . 'pot' . DIRECTORY_SEPARATOR . $rev . ".pot";

		$potfile = $this->resourceExtractor->buildGettextFiles($potName, false, false);

		// Re-update to previous revision
		exec("hg update --clean --rev $oldRev");

		// Parse it and return the entries
		require_once($this->poToolkitPath . 'POFile.php');

		$potfile = new POFile($potfile);

		unlink($potName);

		return $potfile;
	}

	/**
	 * Return the date of a revision given
	 * its full hash id.
	 *
	 * @param string $revId The revision (full hash format)
	 *
	 * @return string
	 */
	public function getRevisionDateById($revId)
	{
		$sql =
				'SELECT chg.date AS date
				 FROM bw_changeset AS chg
						WHERE chg.hg_id = :revId
						LIMIT 0,1';

		$query = $this->dbHandle->prepare($sql);
		$query->bindParam(':revId', $revId);
		$query->execute();

		$row = $query->fetch(PDO::FETCH_ASSOC);

		return $row['date'];
	}

	/**
	 * Return the full hash id (the one
	 * used in the database) of the given
	 * revision.
	 *
	 * @param string $revision The revision (any format)
	 *
	 * @return string
	 * @throws Exception
	 */
	public function getFullRevisionId($revision)
	{
		/**
		 * The $revision parameter can be:
		 * a local id
		 * a full hash id (in which case nothing is to be done)
		 * a tag
		 */
		chdir($this->repoPath);
		$hash = trim(shell_exec('hg log --debug -r ' . escapeshellcmd($revision) . ' | grep -G "^changeset" | sed "s/^changeset:[[:space:]]*//g" | sed "s/.*://g"'));

		if (empty($hash))
			throw new RuntimeException("Revision '$revision' could not be found in the repository '{$this->repoName}'");

		return $hash;
	}

	/**
	 * Return the local id of the given
	 * revision.
	 *
	 * @param string $revision The revision (any format)
	 *
	 * @return string
	 * @throws Exception
	 */
	public function getLocalRevisionId($revision)
	{
		/**
		 * The $revision parameter can be:
		 * a local id
		 * a full hash id (in which case nothing is to be done)
		 * a tag
		 */
		chdir($this->repoPath);
		$hash = trim(shell_exec('hg log --debug -r ' . escapeshellcmd($revision) . ' | grep -G "^changeset" | sed "s/^changeset:[[:space:]]*//g" | sed "s/:.*//g"'));

		if (empty($hash))
			throw new RuntimeException("Revision '$revision' could not be found in the repository '{$this->repoName}'");

		return $hash;
	}

	/**
	 * Returns true if the class has to perform
	 * a specific operation
	 *
	 * @param int $operation The operation (see common.php)
	 *
	 * @return bool
	 */
	private function hasToPerform($operation)
	{
		return (($this->operations & $operation) === $operation);
	}

	public function getLatestTag()
	{
		chdir($this->repoPath);

		return trim(shell_exec('hg log -r tip --template "{latesttag}"'));
	}

	/**
	 * Pull all changesets from a distant repo
	 *
	 * @param string $url The distant repo's URL
	 *
	 * @throws RuntimeException
	 */
	public function pullFromUrl($url)
	{
		// Set username/password to get through HTTP authentification
		putenv('HGRCPATH=' . $GLOBALS['conf']['hgrcPath']);
		// Don't forget to sanitize
		exec('hg pull '. escapeshellcmd($url), $output, $result);

		if ($result !== 0)
			throw new RuntimeException("No valid hg repository can be found at '$url''");
	}
}