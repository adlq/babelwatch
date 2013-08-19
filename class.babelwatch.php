<?php
require_once('common.php');
class Babelwatch
{

	private $repoName;
	private $repoPath;
	private $rootDir;
	private $extensions;

	private $assetPath;
	private $tmsToolkitPath;
	private $poToolkitPath;

	private $dbConf;
	private $dbHandle;

	private $resourceExtractor;
	private $dbInitScript = "babelwatch.sql";

	private $operations;
	private $revisions;

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
		$this->checkConfig();

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
	 * @throws Exception
	 */
	private function checkConfig()
	{
		if (!array_key_exists('mysql', $GLOBALS['conf']))
			throw new Exception('No MySQL configuration found in conf.php');

		if (!array_key_exists('host', $GLOBALS['conf']['mysql'])
			|| !array_key_exists('user', $GLOBALS['conf']['mysql'])
			|| !array_key_exists('pwd', $GLOBALS['conf']['mysql'])
			|| !array_key_exists('db', $GLOBALS['conf']['mysql']))
			throw new Exception('Missing parameter in MySQL configuration. Please refer to conf_sample.php');

		if (!array_key_exists('repo', $GLOBALS['conf']) && is_array($GLOBALS['conf']['repo']))
			throw new Exception("No repository configuration found in conf.php");

		foreach ($GLOBALS['conf']['repo'] as $repoName => $repoInfo)
		{
			if (!is_array($repoInfo))
				throw new Exception('Each entry in $GLOBALS[\'repo\'] must be an array');

			if (!array_key_exists('active', $repoInfo)
			|| !array_key_exists('operations', $repoInfo)
			|| !array_key_exists('repoPath', $repoInfo)
			|| !array_key_exists('sourceFolder', $repoInfo)
			|| !array_key_exists('extensions', $repoInfo)
			|| !array_key_exists('resourceExtractorClass', $repoInfo)
			|| !array_key_exists('projectSlug', $repoInfo)
			|| !array_key_exists('iterationSlug', $repoInfo)
			|| !array_key_exists('options', $repoInfo)
			|| !array_key_exists('sourceDocName', $repoInfo))
				throw new Exception("Missing parameter in configuration for repository $repoName. Please refer to conf_sample.php");

			if (!file_exists($repoInfo['repoPath']))
				throw new Exception('Specified repository path is invalid. No such directory');

			if (!file_exists($repoInfo['repoPath'] . $repoInfo['sourceFolder']))
				throw new Exception('Specified asset path is invalid. No such directory');
		}

		if (!array_key_exists('assetPath', $GLOBALS['conf']))
			throw new Exception('No asset path found in conf.php. This is where the PO/POT files will be stored');

		if (!file_exists($GLOBALS['conf']['assetPath']))
			throw new Exception('Specified asset path is invalid. No such directory');

		if (!array_key_exists('tmsToolkitPath', $GLOBALS['conf']))
			throw new Exception('No path to TMS toolkit found in conf.php.');

		if (!file_exists($GLOBALS['conf']['tmsToolkitPath']))
			throw new Exception('Specified TMS toolkit path is invalid. No such directory');

		if (!array_key_exists('pophpPath', $GLOBALS['conf']))
			throw new Exception('No path to pophp found in conf.php');

		if (!file_exists($GLOBALS['conf']['pophpPath']))
			throw new Exception('Specified pophp path is invalid. No such directory');
	}

	/**
	 * Create necessary tables & Initialize database handle
	 */
	private function prepareDatabase()
	{
		$this->dbHandle = new PDO(
				'mysql:host='
				. $this->dbConf['host'],
				$this->dbConf['user'], $this->dbConf['pwd']);

		$sqlDbCheck = 'SHOW DATABASES LIKE :dbName';
		$queryDbCheck = $this->dbHandle->prepare($sqlDbCheck);
		$queryDbCheck->bindParam(':dbName', $this->dbConf['db'], PDO::PARAM_STR);
		$queryDbCheck->execute();

		$queryDbResult = $queryDbCheck->fetch(PDO::FETCH_ASSOC);
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
	 * Run
	 */
	public function run()
	{
		chdir($this->repoPath);
		$opArray = array(UPDATE_POT, UPDATE_TMS, UPDATE_TRACKING);

		$potFiles = $this->resourceExtractor->getGettextFilesPath();

		echo "\nWORKING ON " . strtoupper($this->repoName) . "\n";

		// Retrieve incoming revisions
		$revisions = $this->getRightRevisions('.', 'tip');

		// Iterate over all the incoming revisions
		foreach ($revisions as $rev)
		{
			if ($rev !== '')
			{
				// Update and then execute operations
				echo "UPDATING TO REVISION $rev...\n";
				exec("hg update --clean --rev $rev");

				if ($this->hasToPerform(UPDATE_POT))
					$potFiles = $this->resourceExtractor->buildGettextFiles();

				// Only update the TMS and the tracking if there were new or removed strings
				$diffStrings = $this->comparePots($potFiles['old'], $potFiles['new']);
				$proceed = !empty($diffStrings['added']) || !empty($diffStrings['removed']);

				if ($this->hasToPerform(UPDATE_TMS) && $proceed)
						$this->updateTMS($potFiles['new']);

				if ($this->hasToPerform(UPDATE_TRACKING) && $proceed)
					$this->updateTracking($diffStrings);
			}
		}
		echo "\nWORK ON " . strtoupper($this->repoName) . " DONE\n";
		return;
	}

	/**
	 * Run babelwatch over a range of revisions
	 * NOTE: The revision number is local and
	 * thus unique to each version of the repo
	 *
	 * @param int $rev1 Starting revision
	 * @param int $rev2 Ending revision
	 */
	public function sweep($rev1, $rev2)
	{
		chdir($this->repoPath);

		// Retrieve the right revisions
		$localRevisions = $this->getRightRevisions($rev1, $rev2);
		// Clean init @ oldest revision
		$this->initAtRevision($localRevisions[0]);
		array_shift($localRevisions);

		foreach ($localRevisions as $rev)
		{
			// Update the code
			chdir($this->repoPath);
			echo "UPDATING TO REVISION $rev...\n";
			exec("hg update --clean --rev $rev");

			$potFiles = $this->resourceExtractor->buildGettextFiles();

			// Only update the TMS and the tracking if there were new or removed strings
			$diffStrings = $this->comparePots($potFiles['old'], $potFiles['new']);
			$proceed = !empty($diffStrings['added']) || !empty($diffStrings['removed']);

			if ($proceed)
			{
				if ($this->hasToPerform(UPDATE_TMS))
					$this->updateTMS($potFiles['new']);

				$this->updateTracking($diffStrings);
			}
		}
		return;
	}

	/**
	 * Given 2 revision local ids, compute the right subset
	 * of revisions in between to watch over. The parameters
	 * do not need to be ordered.
	 *
	 * @param string $rev1 The first revision
	 * @param string $rev2 The second revision
	 * @return array<int> An array of the revisions
	 */
	public function getRightRevisions($rev1, $rev2)
	{
		chdir($this->repoPath);

		// Sort the two given revisions first
		$sortedRevisions = $this->sortRevisionsByDate($rev1, $rev2);
		$start = $sortedRevisions['oldest'];
		$end = $sortedRevisions['newest'];

		/**
		 * Get the right subset of revisions
		 */
		$lastRevision = $end;

		$n = 1;
		$revisions = array();
		while ($lastRevision != $start)
		{
			array_push($revisions, $lastRevision);
			// The '~n' sign specifies that we want the nth first ancestor of the $end revision
			$lastRevision = trim(shell_exec('hg log -r ' . $end . '~' . $n . ' | grep -G "^changeset" | sed "s/^changeset:[[:space:]]*//g" | sed "s/:.*//g"'));
			$n++;
		}

		array_push($revisions, $lastRevision);
		$revisions = array_reverse($revisions);

		return $revisions;
	}

	/**
	 * Given 2 local revision ids, compute the oldest
	 * and the newest revisions.
	 *
	 * @param string $rev1 First revision
	 * @param string $rev2 Second revision
	 *
	 * @return array An associative array with 'oldest' and 'newest" keys
	 */
	private function sortRevisionsByDate($rev1, $rev2)
	{
		chdir($this->repoPath);

		/**
		 * Sort the revisions by date
		 */
		// Retrieve the dates and automatically sort the revisions
		$revInfo = shell_exec('hg log -r ' . $rev1 . ' -r ' . $rev2 . ' | grep -G "^date" | sed "s/^date:[[:space:]]*//g"');

		// Retrieve the two dates
		$lines = explode("\n", $revInfo);

		// Remove the empty element
		array_pop($lines);

		// Create a new date array from the lines arra
		$dates = array($rev1 => $lines[0], $rev2 => $lines[1]);

		// After the sort, the oldest revision is the
		// first element and the newest the second
		asort($dates, SORT_NUMERIC);
		$extrema = array_keys($dates);
		$start = $extrema[0];
		$end = $extrema[1];

		return array('oldest' => $start, 'newest' => $end);
	}

	/**
	 * Initializes the tracker at a specific revision
	 *
	 * @param string $rev The revision
	 */
	public function initAtRevision($rev)
	{
		echo "\nINITIALIZING " . strtoupper($this->repoName) . " AT REVISION $rev\n";
		chdir($this->repoPath);
		exec("hg update --clean --rev $rev");

		$this->resourceExtractor->buildGettextFiles();
	}

	/**
	 * Upload pot entries to the TMS
	 *
	 * @param string $newPot Path to the POT file
	 */
	public function updateTMS($newPot)
	{
		echo "===\nUpdating TMS for {$this->repoName}...\n";
		require_once($this->tmsToolkitPath . 'conf.php');
		require_once($this->tmsToolkitPath . 'ZanataPHPToolkit.php');

		$zanataUrl = $GLOBALS['conf']['zanata']['url'];
		$user = $GLOBALS['conf']['zanata']['user'];
		$apiKey = $GLOBALS['conf']['zanata']['apiKey'];
		$projectSlug = '';
		$iterationSlug = '';

		// Attempt to find the repo name in the config.ini file
		if (isset($GLOBALS['conf']['repo'][$this->repoName]))
		{
			$projectSlug = $GLOBALS['conf']['repo'][$this->repoName]['projectSlug'];
			$iterationSlug = $GLOBALS['conf']['repo'][$this->repoName]['iterationSlug'];
		}
		else
		{
			exit("Unknown project, no section $this->repoName in conf.php file");
		}

		// Update the source entries on Zanata!
		$zanataToolkit = new ZanataPHPToolkit($user, $apiKey, $projectSlug, $iterationSlug, $zanataUrl, true);

		$zanataToolkit->pushPotEntries($newPot, $GLOBALS['conf']['repo'][$this->repoName]['sourceDocName'], 'en-GB');
		echo "===\n";
	}

	/**
	 * Main update function, does 3 things:
	 *	- Create a row in bw_repo for the repo if it doesn't exist
	 *	- Create a row in bw_changeset for the changeset if it doesn't exist
	 *  - Update information about strings (content, references, added/removed)
	 *  @param array $diffStrings The diff array of strings
	 */
	public function updateTracking($diffStrings)
	{
		echo "===\nUpdating tracking for {$this->repoName}...\n";

		// Retrieve changeset info
		chdir($this->repoPath);
		// Note: the tip designates the last changeset, not the version of the code
		// The code version is revision '.'
		$logLines = explode("\n", shell_exec('hg log -r . | tail -n +2'));

		$revInfo = array();

		foreach($logLines as $line)
		{
			$matches = array();
			if (preg_match("/([^:]*):\s+(.+)/", $line, $matches))
			{
				$revInfo[$matches[1]] = $matches[2];
			}
		}
		$revInfo['changeset'] = trim(shell_exec('hg log --debug -r . | grep -G "^changeset" | sed "s/^changeset:[[:space:]]*//g" | sed "s/.*://g"'));

		$tag = array_key_exists('tag', $revInfo) ? $revInfo['tag'] : '';

		$repoId = $this->updateRepo();
		$changesetId = $this->updateChangeset($revInfo['changeset'], $revInfo['user'], $repoId, $revInfo['summary'], date('Y-m-d H:i:s', strtotime($revInfo['date'])), $tag);
		$this->updateStringState($changesetId, $diffStrings['added'], 'a');
		$this->updateStringState($changesetId, $diffStrings['removed'], 'r');
		echo "===\n";
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
		$queryNewUser->bindParam(':name', $user, PDO::PARAM_STR);
		$queryNewUser->execute();

		// Select the user
		$sqlUserCheck =
				"SELECT * FROM bw_user
					WHERE name LIKE :name
					LIMIT 0,1";
		$queryUserCheck = $this->dbHandle->prepare($sqlUserCheck);
		$queryUserCheck->bindParam(':name', $user, PDO::PARAM_STR);
		$queryUserCheck->execute();

		$userRow = $queryUserCheck->fetch(PDO::FETCH_ASSOC);

		// Create the changeset and bind it to the repo
		$sqlNewChangeset =
				"INSERT INTO bw_changeset (hg_id, repo_id, user_id, summary, tag, date)
					VALUES (UNHEX(:changeset), :repoId, :userId, :summary, :tag, :date)
					ON DUPLICATE KEY UPDATE hg_id = hg_id";
		$queryNewChangeset = $this->dbHandle->prepare($sqlNewChangeset);
		$queryNewChangeset->bindParam(':changeset', $changeset, PDO::PARAM_INT);
		$queryNewChangeset->bindParam(':repoId', $repoId, PDO::PARAM_INT);
		$queryNewChangeset->bindParam(':userId', $userRow['id'], PDO::PARAM_INT);
		$queryNewChangeset->bindParam(':summary', $summary, PDO::PARAM_STR);
		$queryNewChangeset->bindParam(':tag', $tag, PDO::PARAM_STR);
		$queryNewChangeset->bindParam(':date', $date, PDO::PARAM_INT);
		$queryNewChangeset->execute();

		// Check changeset
		$sqlChangesetCheck =
				"SELECT * FROM bw_changeset
					WHERE hg_id = UNHEX(:changeset)
					AND repo_id = :repoId
					LIMIT 0,1";
		$queryChangesetCheck = $this->dbHandle->prepare($sqlChangesetCheck);
		$queryChangesetCheck->bindParam(':changeset', $changeset, PDO::PARAM_INT);
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
		foreach($entry->getReferences('source') as $ref)
		{
			$matches = array();
			// Extract the filepath and line number
			if (preg_match("/(.*):(.*)/", $ref, $matches) && count($matches) === 3)
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
				'SELECT HEX(chg.hg_id) as chgId, chg.summary, user.name, string.content, glue.action
					FROM bw_changeset_string AS glue
					JOIN bw_string AS string ON glue.string_id = string.id
					JOIN bw_changeset AS chg ON glue.changeset_id = chg.id
					JOIN bw_repo AS repo ON chg.repo_id = repo.id
					JOIN bw_user AS user ON chg.user_id = user.id
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
			$string = $row['content'];
			$user = $row['name'];
			$summary = $row['summary'];

			if (!array_key_exists($changeset, $logs))
					$logs[$changeset] = array('user' => $user, 'summary' => $summary);

			if (!array_key_exists($action, $logs[$changeset]))
					$logs[$changeset][$action] = array();

			array_push($logs[$changeset][$action], $string);
		}

		return $logs;
	}

	public function findStringAtRev($rev)
	{
		$sql =
		'SELECT str.content, chgstr.action FROM bw_changeset_string AS chgstr
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
				WHERE chg.id = :rev
				GROUP BY chgstr.string_id) AS foo
				ON chg.date = foo.last_chg_time) AS bar
			ON chgstr.changeset_id = bar.last_chg_id
			AND chgstr.string_id = bar.string_id
		JOIN bw_string as str
			ON bar.string_id = str.id';
	}

	private function findRevId($rev)
	{
		$sql = 'SELECT chg.id FROM bw_changeset AS chg WHERE chg.hg_id = :rev';
		$query = $this->dbHandle->prepare($sql);
		$query->bindParam(':repoName', $this->repoName);
		$query->execute();
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
}