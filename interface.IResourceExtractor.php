<?php
interface IResourceExtractor
{
	public function buildGettextFiles($rootDir, $extensions = array());

	public function getGettextFilesPath();
}
