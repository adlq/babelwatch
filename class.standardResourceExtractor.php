<?php
class StandardResourceExtractor
{
	private $repoName;
	private $repoPath;
	private $rootDir;
	private $extensions;
	private $blacklist;
	private $assetPath;
	private $pophpPath;

	private $poUtils;

	private $fileLists;
	private $potPath;
	private $potFileName;
	private $oldPotFileName;

	private $frPoPath;
	private $refPo;
	private $frPoFile;

	/**
	 * Constructor
	 *
	 * @param string    	$repoName Name of the repo
	 * @param string      $repoPath Path to the repo
	 * @param string      $rootDir Relative path to directory containing the files we need to watch over
	 * @param array				$extensions Array of extensions to consider when building Gettext files
	 * @param string      $assetPath Path to folder
	 * containing the assets
	 * @param string      $pophpPath Path to pophp library
	 * @param array				$blacklist Blacklisted folders
	 * @param array 			$options Misc options
	 */
	public function __construct(
		$repoName,
		$repoPath,
		$rootDir,
		$extensions,
		$assetPath,
		$pophpPath,
		$blacklist = array())
	{
		$this->repoName = $repoName;
		$this->repoPath = $repoPath;
		$this->rootDir = $rootDir;
		$this->extensions = $extensions;
		$this->blacklist = $blacklist;
		$this->assetPath = $assetPath;
		$this->pophpPath = $pophpPath;

		require_once($this->pophpPath . 'POUtils.php');
		$this->poUtils = new POUtils();

		/**
		 * Directory containing .pot files
		 */
		$this->potPath = $this->assetPath . 'pot' . DIRECTORY_SEPARATOR;

		// Create the directory if it doesn't exist
		if (!file_exists($this->potPath))
			mkdir($this->potPath);

		$this->potFileName = $this->potPath . $this->repoName . '.pot';
		// Create the .pot file if it doesn't exist
		if (!file_exists($this->potFileName))
			$this->poUtils->initGettextFile($this->potFileName);

		$this->oldPotFileName = $this->potPath . $this->repoName . 'old.pot';

		/**
		 * Directory containing fr-FR .po files
		 * This is only temporary while we wait to bootstrap the fr-FR locale
		 * on Zanata
		 */
		$this->frPoPath = $this->assetPath . 'po' . DIRECTORY_SEPARATOR;
		// Create the directory if it doesn't exist
		if (!file_exists($this->frPoPath))
			mkdir($this->frPoPath);

		$frPath = $this->repoPath . 'source' . DIRECTORY_SEPARATOR . 'locales' . DIRECTORY_SEPARATOR . 'fr-FR' . DIRECTORY_SEPARATOR . 'lang.po';
		if (file_exists($frPath))
			$this->refPo = $this->repoPath
					. 'source'
					. DIRECTORY_SEPARATOR
					. 'locales'
					. DIRECTORY_SEPARATOR
					. 'fr-FR'
					. DIRECTORY_SEPARATOR
					. 'lang.po';

		$this->frPoFile = $this->frPoPath . $this->repoName . '.po';
		if (!file_exists($this->frPoFile))
			$this->poUtils->initGettextFile($this->frPoFile);
	}

	/**
	 * Generate POT files for this repo, with the appropriate parameters
	 * (source folder, extensions, blacklisted folders).
	 *
	 * This method returns the following array:
	 * 'old' => path to the old POT file
	 * 'new' => path to the new POT file
	 *
	 * @param string $output Path of the output file
	 * @param boolean $keepPreviousFile Whether to keep the previous
	 * version of the POT file
	 * @param boolean $verbose Whether to print out actions or not
	 *
	 * @return array
	 * @throws RuntimeException
	 */
	public function buildGettextFromAllStrings($output, $keepPreviousFile, $verbose)
	{
		// Lock the directory
		$cwd = getcwd();
		chdir($this->potPath);
		// Check to see if there's a lock
		$lockName = $this->repoName . "_lock";

		// If the lock exists, ABORT
		if (file_exists($lockName))
			throw new RuntimeException("Another process is building POT files. Please try again later.");

		// Otherwise, create the lock
		file_put_contents($lockName, gethostname());

		// Return to the current working dir
		chdir($cwd);

		if ($verbose)
			echo "===\nUpdating po files for {$this->repoName}...\n";

		if (empty($this->extensions))
			throw new RuntimeException("No extension specified for string extraction");

		$potLists = array();

		if ($keepPreviousFile)
		{
			$output = $this->potFileName;
			if (!file_exists($output))
			{
				$this->poUtils->initGettextFile($this->oldPotFileName);
			}
			else
			{
				// Initialize the POT file
				// If there is an existing pot file, copy it and create a	fresher one
				copy($this->potFileName, $this->oldPotFileName);
				unlink($this->potFileName);
			}
		}

		// Whatever happens, we need to init the output file
		$this->poUtils->initGettextFile($output);

		/**
		 * Extract the strings with respect to the extensions
		 */
		foreach ($this->extensions as $ext)
		{
			// List files by extensions
			$this->fileLists[$ext] = $output . $ext . '_file_list_' . $this->repoName . '.txt';
			$this->listFilesToProcess($this->rootDir, $ext, $this->fileLists[$ext], $this->blacklist);

			$potLists[$ext] = $output . '_' . $ext . '.pot';
			$this->poUtils->initGettextFile($potLists[$ext]);

			// Extract gettext strings
			switch($ext)
			{
				case 'm':
					exec("xgettext --sort-output --add-location --omit-header --no-wrap -c --from-code=utf-8 --force-po --output={$potLists[$ext]} -j -f {$this->fileLists[$ext]} 1> nul 2>&1");
					break;
				case 'php':
					exec("xgettext --sort-output --add-location --omit-header --no-wrap -c --from-code=utf-8 --force-po --output={$potLists[$ext]} -j -k -kEpiLang -kEpiLangKey -kEpilang -kSingleEnquotedEpiLang -kSingleEnquotedEpilang -f {$this->fileLists[$ext]} 1> nul 2>&1");
					break;
				case 'js':
					exec("xgettext --language=Python --sort-output --add-location --omit-header --no-wrap -c --from-code=utf-8 --force-po --output={$potLists[$ext]} -j -k -kEpiLang -kEpiLangKey -kEpilang -kSingleEnquotedEpiLang -kSingleEnquotedEpilang -f {$this->fileLists[$ext]} 1> nul 2>&1");
					break;
			}
		}

		// Prepare the arguments for msgcat
		$potPieces = implode(" ", $potLists);

		// Process the POT files by extension
		foreach ($this->extensions as $ext)
			exec("msguniq --sort-output --add-location --no-wrap {$potLists[$ext]} -o {$potLists[$ext]} 1> nul 2>&1");

		// Concatenate all the POT files
		exec("msgcat --sort-output --add-location --no-wrap $potPieces -o {$output} 1> nul 2>&1");

		// Last check
		exec("msguniq --sort-output --add-location --no-wrap {$output} -o {$output} 1> nul 2>&1");

		// Remove temporary files
		foreach ($this->extensions as $ext)
		{
			unlink($potLists[$ext]);
			unlink($this->fileLists[$ext]);
		}

		if (isset($this->refPo))
		{
			if ($this->hasGettextEntries($this->frPoFile))
			{
				// Initial processing of the fr-FR PO file
				// Temporary solution to keep the FR bootstrap file up-to-date
				// Once we bootstrap the FR translations, we can delete these lines
				exec("msguniq --use-first {$this->frPoFile} -o {$this->frPoFile} 1> nul 2>&1");

				// Merge new strings with the fr-FR PO File
				// Temporary solution to keep the FR bootstrap file up-to-date
				// Once we bootstrap the FR translations, we can delete these lines
				exec("msgmerge {$this->frPoFile} {$this->refPo} --update --no-wrap --add-location --sort-output 1> nul 2>&1");
			}
			else
			{
				copy($this->refPo, $this->frPoFile);
			}
		}

		if ($verbose)
			echo "===\n";

		// Remove the lock
		$cwd = getcwd();
		chdir($this->potPath);
		// Try to remove the lock
		if (file_exists($lockName))
			unlink($lockName);
		// Return to the current working dir
		chdir($cwd);

		if ($keepPreviousFile)
			return array('old' => $this->oldPotFileName, 'new' => $this->potFileName);
		else
			return $output;
	}

	/**
	 * List all files with certain types in a given folder,
	 * and write them, one per line, in a file.
	 *
	 * @param string $dir Path of the directory inside the repo
	 * @param string $ext Extension to consider
	 * @param string $output Path to the output file
	 * @param array $blacklist Folders to be ignored
	 */
	private function listFilesToProcess($dir, $ext, $output, $blacklist = array())
	{
		chdir($this->repoPath);
		$files = shell_exec("hg locate --include $dir");
		$files = explode("\n", $files);

		foreach($files as $id => $entry)
		{
			if (pathinfo($entry, PATHINFO_EXTENSION) !== $ext)
			{
					unset($files[$id]);
			}
			else if (!empty($blacklist)) 				// Only check blacklist folders if $blacklist is non-empty
			{
				foreach ($blacklist as $blFolder)
				{
					$dirs = pathinfo($entry, PATHINFO_DIRNAME);
					// If at least one folder from the path coincides
					// with one of the blacklisted folders, ignore the file
					if (strpos($dirs, $blFolder) !== false)
						unset($files[$id]);
				}
			}
		}

		$files = implode("\n", $files);
		file_put_contents($output, $files);
	}

  /**
	 * Check whether a gettext file has non-empty entries
	 *
	 * @param string $file The file to check
	 * @return boolean True if the file has non-empty entries, false otherwise
	 * @throws Exception
	 */
	private function hasGettextEntries($file)
	{
		if (!file_exists($file))
			throw new Exception("Cannot open $file to check gettext entries");
		return (file_get_contents($file) !== $this->poUtils->getGettextHeader());
	}

  /**
	 * Return full path to the old and new POT files
	 */
	public function getGettextFilesPath()
	{
		return array(
			'old' => $this->oldPotFileName,
			'new' => $this->potFileName);
	}

	/**
	 * Return the main directory of the repo
	 * (Where the strings are extracted from)
	 * @return string
	 */
	public function getRootdir()
	{
		return $this->rootDir;
	}
}