<?php
class StandardResourceExtractor
{
	private $repoName;
	private $repoPath;
	private $assetPath;
	private $pophpPath;
	private $rootDir;

	private $fileLists;
	private $potPath;
	private $potFileName;
	private $oldPotFileName;

	private $frPoPath;
	private $refPo;
	private $frPoFile;
	private $tempFrPoFile;

	public static $poUtils;
	public static $options;

	public function __construct($repoName, $repoPath, $assetPath, $pophpPath, $options = array())
	{
		$this->repoName = $repoName;
		$this->repoPath = $repoPath;
		$this->assetPath = $assetPath;
		$this->pophpPath = $pophpPath;
		require_once($this->pophpPath . 'POUtils.php');
		$this->poUtils = new POUtils();
		$this->options = $options;

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

	public function buildGettextFromAllStrings($rootDir, $extensions = array())
	{
		echo "===\nUpdating po files for {$this->repoName}...\n";
		if (empty($extensions))
			throw new Exception("No extension specified for string extraction");

		$potLists = array();

		if (!file_exists($this->potFileName))
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

		$this->poUtils->initGettextFile($this->potFileName);

		// List all the .php and .js files to extract messages from
		foreach ($extensions as $ext)
		{
			// File containing the list of .php and .js files
			$this->fileLists[$ext] = $this->assetPath . $ext . '_file_list_' . $this->repoName . '.txt';
			$this->listFilesToProcess($rootDir, $ext, $this->fileLists[$ext]);

			$potLists[$ext] = $this->potPath . $this->repoName . '_' . $ext . '.pot';
			$this->poUtils->initGettextFile($potLists[$ext]);

			// Extract gettext strings
			switch($ext)
			{
				case 'php':
					exec("xgettext --sort-output --add-location --omit-header --no-wrap -c --from-code=utf-8 --force-po --output={$potLists[$ext]} -j -k -kEpiLang -kEpiLangKey -kEpilang -kSingleEnquotedEpiLang -kSingleEnquotedEpilang -f {$this->fileLists[$ext]} 1> nul 2>&1");
					break;
				case 'js':
					exec("xgettext --language=Python --sort-output --add-location --omit-header --no-wrap -c --from-code=utf-8 --force-po --output={$potLists[$ext]} -j -k -kEpiLang -kEpiLangKey -kEpilang -kSingleEnquotedEpiLang -kSingleEnquotedEpilang -f {$this->fileLists[$ext]} 1> nul 2>&1");
					break;
			}
		}

		$potPieces = implode(" ", $potLists);
		exec("msgcat --sort-output --add-location --no-wrap $potPieces -o {$this->potFileName} 1> nul 2>&1");

		exec("msguniq --sort-output --add-location --no-wrap {$this->potFileName} -o {$this->potFileName} 1> nul 2>&1");

		// Remove temporary files
		foreach ($extensions as $ext)
		{
			unlink($potLists[$ext]);
			unlink($this->fileLists[$ext]);
		}

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

		echo "\n===\n";
		return array(
			'old' => $this->oldPotFileName,
			'new' => $this->potFileName);
	}

	/**
	 * List all files with certain types in a given folder,
	 * and write them, one per line, in a file.
	 *
	 * @param string $dir Path of the directory inside the repo
	 * @param string $ext Extension to consider
	 * @param string $output Path to the output file
	 */
	private function listFilesToProcess($dir, $ext, $output)
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
}