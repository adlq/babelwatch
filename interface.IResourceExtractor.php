<?php
interface IResourceExtractor
{
	public function buildGettextFiles($output, $keepPreviousFile, $verbose = false);

	public function getGettextFilesPath();
}
