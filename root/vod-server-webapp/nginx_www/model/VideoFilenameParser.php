<?php

class VideoFilenameParser {
	public $filePath;
	function __construct($videoFile) {
		$this->filePath = $videoFile;
	}

	function parseFilename_Legacy($fileName) {
		// Example: 2021-09-10 19-37-36.mp4
		return [
			'date' => DateTime::createFromFormat('Y-m-d H-i-s', $fileName, new DateTimeZone('CST')),
			'uploaded_by' => 'Def'
		];
	}

	function parseFilename() {
		$fileName = pathinfo($this->filePath, PATHINFO_FILENAME);

		if(!str_contains($fileName, '_') && !str_contains($fileName, '.')) {
			return $this->parseFilename_Legacy($fileName);
		}

		list($uploadedBy, $timeInfo) = explode('-', $fileName, 2);

		// New parsing - where we get the name of uploader from streamkey.
		list($epochTime, $readableTime) = explode('.', $timeInfo);
		return [
			// ToDo: Maybe should use epochtime instead since we have it..
			'date' => DateTime::createFromFormat('Y-m-d_H-i-s', $readableTime, new DateTimeZone('CST')),
			'uploaded_by' => $uploadedBy
		];
		
	}

}

// based on original work from the PHP Laravel framework
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}