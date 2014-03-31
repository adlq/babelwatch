<?php
require_once('class.standardResourceExtractor.php');
require_once('interface.IResourceExtractor.php');

class ProjectResourceExtractor extends StandardResourceExtractor implements IResourceExtractor
{
	/**
	 * Build gettext files
	 *
	 * @param string	$output Name of the output file
	 * @param boolean $keepPreviousFile Whether to keep the existing
	 * POT file as an older version or not
	 * @param bool $verbose Verbosity
	 *
	 * @return array Array containing the paths to the output files
	 * @throws RuntimeException
	 */
	public function buildGettextFiles($output, $keepPreviousFile, $verbose = false)
	{
		$potFiles = $this->buildGettextFromAllStrings($output, $keepPreviousFile, $verbose);

		return $potFiles;
	}
}
