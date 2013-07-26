<?php
require_once('class.standardResourceExtractor.php');
require_once('interface.IResourceExtractor.php');

class SubProjectResourceExtractor extends StandardResourceExtractor implements IResourceExtractor
{
	public function buildGettextFiles($rootDir, $extensions = array())
	{
		if (empty($extensions))
					throw new Exception("No extension specified for string extraction");

		$potFiles = $this->buildGettextFromAllStrings($rootDir, $extensions);
		if (isset($this->options['refPot']))
		{
			$this->extractExclusive($potFiles['new']);
			$this->extractExclusive($potFiles['old']);
		}

		return $potFiles;
	}

	private function extractExclusive($potFile)
	{
		// Generate latest POT file
		$diff = $this->poUtils->compare($potFile, $this->options['refPot']);

		$res = '';

		foreach($diff['firstOnly'] as $entry)
		{
			$res .= $entry->__toString();
		}

		$this->poUtils->initGettextFile($potFile);
		file_put_contents($potFile, $res, FILE_APPEND);
	}
}
