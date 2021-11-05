<?php

require_once(__DIR__. "/VideoFile.php");

class VideoFileTimeOverlap {
	public $mainFile;
	public $filesToConsider;

	function __construct($mainFilePath, $filesToConsider){
		$this->filesToConsider = $filesToConsider;
		$this->mainFile = new VideoFile($mainFilePath);
	}

	function findOverlappedFiles() {
		
		// Files that
		// Started before I stopped
		//	their start time < my end time
		// Ended after I started
		//  their end time > my start time

		$fileMeta = $this->mainFile->getMetadata();
		
		$myStartTime = $fileMeta['timestamp'];
		$myEndTime = $fileMeta['end_timestamp'];

		$filesWithOverlap = [];
		foreach($this->filesToConsider as $otherFile) {
			if(
				$otherFile['timestamp'] <= $myEndTime
			 && $otherFile['end_timestamp'] >= $myStartTime) {

				$filesWithOverlap[] = $otherFile;
			}
		}

		return $filesWithOverlap;
	}

}