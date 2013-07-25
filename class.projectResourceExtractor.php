<?php
require_once('class.standardResourceExtractor.php');
require_once('interface.IResourceExtractor.php');

class ProjectResourceExtractor extends StandardResourceExtractor implements IResourceExtractor
{
	public function buildGettextFiles($rootDir, $extensions = array())
	{
		if (empty($extensions))
					throw new Exception("No extension specified for string extraction");

		$potFiles = $this->buildGettextFromAllStrings($rootDir, $extensions);
	}
}
