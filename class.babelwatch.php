<?php
require_once('common.php');
class Babelwatch
{

	private $repoName;
	private $repoPath;
	private $assetPath;
	private $tmsToolkitPath;
	private $poToolkitPath;

	private $dbConf;
	private $dbHandle;

	/**
	 * Constructor
	 * Note: the path should end with a slash
	 *
	 * @param string $hgDir Parent directory of the repo
	 * @param string $repoName Name of the repo folder
	 * @param string $assetDir Directory containing all the assets (generated
	 * POT files, PO files, temp files)
	 * @param string $tmsToolkitPath Directory containing TMS toolkit (zanata-php-toolkit)
	 * @param string $poToolkitPath Directory containing pophp
	 * @param array $dbConf Array containing DB configuration
	 */
	public function __construct(
				$repoName,
				$repoPath,
				$assetPath,
				$tmsToolkitPath,
				$poToolkitPath,
				$dbConf,
				$resourceExtractor)
	{
		$this->checkConfig();

		$this->repoName = $repoName;
		$this->repoPath = $repoPath;
		$this->assetPath = $assetPath;
		$this->tmsToolkitPath = $tmsToolkitPath;
		$this->poToolkitPath = $poToolkitPath;
		$this->dbConf = $dbConf;
		$this->resourceExtractor = $resourceExtractor;

		$this->dbInitScript = "babelwatch.sql";
		$this->prepareDatabase();

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
				throw new Exception("Missing parameter in configuration for repository $repo. Please refer to conf_sample.php");

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
	 * Watch over Babel
	 *
	 * @param string $rootDir The name of the directory
	 * containing all the files that we have to parse
	 * @param array $extensions Array containg all the
	 * extensions to take into account
	 * @param int $operations Flags specifying which operations
	 * to execute
	 */
	public function run($rootDir, $extentions, $operations = 7)
	{
		$opArray = array(UPDATE_POT, UPDATE_TMS, UPDATE_TRACKING);

		if (($operations & UPDATE_POT) === UPDATE_POT)
		{
			$potFiles = $this->resourceExtractor->buildGettextFiles($rootDir, $extentions);

			if (($operations & UPDATE_TMS) === UPDATE_TMS)
				$this->updateTMS($potFiles['new']);

			if (($operations & UPDATE_TRACKING) === UPDATE_TRACKING)
				$this->updateTracking($potFiles['old'], $potFiles['new']);

			return;
		}

		$potFiles = $this->resourceExtractor->getGettextFilesPath();

		if (($operations & UPDATE_TMS) === UPDATE_TMS)
			$this->updateTMS($potFiles['new']);

		if (($operations & UPDATE_TRACKING) === UPDATE_TRACKING)
			$this->updateTracking($potFiles['old'], $potFiles['new']);
	}

	/**
	 * Trace
	 */
	public function trace($rev1, $rev2)
	{
		$revisions = range($rev1, $rev2);

		foreach ($revisions as $rev)
		{
			// Update the code
			chdir($this->repoPath);
			exec("hg update --clean --rev $rev");

			$this->updatePot();
			$this->updateTracking();
		}
	}

	/**
	 * Upload pot entries to the TMS
	 */
	public function updateTMS($newPot)
	{
		echo "Update TMS...";
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
			exit("Unknown project, no section $repoName in conf.php file");
		}

		// Update the source entries on Zanata!
		$zanataToolkit = new ZanataPHPToolkit($user, $apiKey, $projectSlug, $iterationSlug, $zanataUrl, true);

		$zanataToolkit->pushPotEntries($newPot, $GLOBALS['conf']['repo'][$this->repoName]['sourceDocName'], 'en-GB');
		echo "Done\n";
	}

	/**
	 * Main update function, does 3 things:
	 *	- Create a row in bw_repo for the repo if it doesn't exist
	 *	- Create a row in bw_changeset for the changeset if it doesn't exist
	 *  - Update information about strings (content, references, added/removed)
	 */
	public function updateTracking($oldPot, $newPot)
	{
		echo "Update tracking...\n";
		require_once($this->poToolkitPath . 'POUtils.php');

		// Compare the new and old pot files
		$utils = new POUtils();
		$diff = $utils->compare($oldPot, $newPot);

		// Retrieve added/removed strings
		$newStrings = $diff['secondOnly'];
		$removedStrings = $diff['firstOnly'];

		echo "new: " . count($newStrings) . ", removed: " . count($removedStrings) ."\n";

		// Retrieve changeset info
		chdir($this->repoPath);
		// Note: the tip designates the last changeset, not the version of the code
		// The code version is revision '.'
//		$user = trim(shell_exec('hg log -r . | grep -G "^user" | sed "s/^user:[[:space:]]*//g"'));
		$changeset = trim(shell_exec('hg log -r . | grep -G "^changeset" | sed "s/^changeset:[[:space:]]*//g" | sed "s/:.*//g"'));

		$repoId = $this->updateRepo();
		$changesetId = $this->updateChangeset($changeset, $repoId);
		$this->updateStringState($changesetId, $newStrings, 'a');
		$this->updateStringState($changesetId, $removedStrings, 'r');
		echo "Done\n";
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
	 * @param int $repoId The id of the repo to which the changeset belong
	 * @return type
	 */
	private function updateChangeset($changeset, $repoId)
	{
		echo "\tUpdate changeset...";
		// Create the changeset and bind it to the repo
		$sqlNewChangeset =
				'INSERT INTO bw_changeset (hg_id, repo_id)
					VALUES (:changeset, :repoId)
					ON DUPLICATE KEY UPDATE hg_id = hg_id';
		$queryNewChangeset = $this->dbHandle->prepare($sqlNewChangeset);
		$queryNewChangeset->bindParam(':changeset', $changeset, PDO::PARAM_INT);
		$queryNewChangeset->bindParam(':repoId', $repoId, PDO::PARAM_STR);
		$queryNewChangeset->execute();

		// Check changeset
		$sqlChangesetCheck =
				'SELECT * FROM bw_changeset
					WHERE hg_id = :changeset
					AND repo_id = :repoId
					LIMIT 0,1';
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

//		echo "Updating action for string $stringId @ changeset $changesetId, $action\n";

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
				'SELECT chg.hg_id, string.content, glue.action
					FROM bw_changeset_string AS glue
					JOIN bw_string AS string ON glue.string_id = string.id
					JOIN bw_changeset AS chg ON glue.changeset_id = chg.id';
		$query = $this->dbHandle->prepare($sql);
		$query->execute();

		$logs = array();

		while($row = $query->fetch(PDO::FETCH_ASSOC))
		{
			$changeset = $row['hg_id'];
			$action = $row['action'];
			$string = $row['content'];

			if (!array_key_exists($changeset, $logs))
					$logs[$changeset] = array();

			if (!array_key_exists($action, $logs[$changeset]))
					$logs[$changeset][$action] = array();

			array_push($logs[$changeset][$action], $string);
		}

		return $logs;
	}

	public function getLatestStrings()
	{
		require_once($this->poToolkitPath . 'POFile.php');

		$sql = "select distinct last_action.content
		from (select distinct str.content, chgstr.action
					from bw_changeset_string as chgstr
					inner join bw_string as str
					on chgstr.string_id = str.id
					order by chgstr.changeset_id desc)
		as last_action where last_action.action LIKE 'a'";

		$query = $this->dbHandle->prepare($sql);
		$query->execute();

		while($row = $query->fetch(PDO::FETCH_NUM))
		{
			$entry = new POEntry($row[0], '');
			echo $entry;
		}
	}
}