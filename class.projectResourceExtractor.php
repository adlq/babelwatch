<?php
require_once('class.standardResourceExtractor.php');
require_once('interface.IResourceExtractor.php');

class ProjectResourceExtractor extends StandardResourceExtractor implements IResourceExtractor
{
	public function buildGettextFiles($output, $keepPreviousFile, $verbose = false)
	{
		$potFiles = $this->buildGettextFromAllStrings($output, $keepPreviousFile, $verbose);

		return $potFiles;
	}
}
