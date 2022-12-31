<?php

class VideoFilenameParser {
	public $filePath;
	function __construct($videoFile) {
		$this->filePath = $videoFile;
	}

	function parseFilename_legacy_obs($fileName) {
		// Example: 2021-09-10 19-37-36.mp4
		return [
			'date' => DateTime::createFromFormat('Y-m-d H-i-s', $fileName, new DateTimeZone('CST')),
			'uploaded_by' => 'Def'
		];
	}

	function parseFilename_vod_server($fileName) {
		list($uploadedBy, $timeInfo) = explode('-', $fileName, 2);

		// New parsing - where we get the name of uploader from streamkey.
		list($epochTime, $readableTime) = explode('.', $timeInfo);
		return [
			// ToDo: Maybe should use epochtime instead since we have it..
			'date' => DateTime::createFromFormat('Y-m-d_H-i-s', $readableTime, new DateTimeZone('CST')),
			'uploaded_by' => $uploadedBy
		];
	}

	function parseFilename_date_with_text($fileName) {
		$dateRegex = '/\d{4}\-\d{2}-\d{2}/';

		preg_match($dateRegex, $fileName, $matches);

		$extractedDate = $matches[0];

		$cleanedTitle = str_replace($extractedDate, '', $fileName);
		$cleanedTitle = trim($cleanedTitle);

		return [
			'date' => DateTime::createFromFormat('Y-m-d', $extractedDate, new DateTimeZone('CST')),
			'title' => $cleanedTitle,
			'uploaded_by' => ''
		];
	}

	function parseFilename() {
		$fileName = pathinfo($this->filePath, PATHINFO_FILENAME);

		$fileNameScheme = $this->determineFilenameScheme();

		if($fileNameScheme == 'legacy_obs') {
			return $this->parseFilename_legacy_obs($fileName);			
		}

		if($fileNameScheme == 'vod-server-receiver-rtsp') {
			return $this->parseFilename_vod_server($fileName);
		}

		if($fileNameScheme == 'date_with_text') {
			return $this->parseFilename_date_with_text($fileName);
		}

		return [
			'title' => $fileName,
			'uploaded_by' => ''
		];
		
	}

	function determineFilenameScheme() {
		$fileName = pathinfo($this->filePath, PATHINFO_FILENAME);

		if(str_contains($fileName, '_') && str_contains($fileName, '.') && str_contains($fileName, '-')) {
			if(preg_match('/\w+-\d+\.\d{4}\-\d{2}-\d{2}\_\d{2}-\d{2}-\d{2}/', $fileName)) {
				return 'vod-server-receiver-rtsp';	
			}
		}

		if(preg_match('/\d{4}\-\d{2}-\d{2}\s\d{2}-\d{2}-\d{2}/', $fileName)) {			
			return 'legacy_obs';
		}

		if(preg_match('/\d{4}\-\d{2}-\d{2}/', $fileName) ) {
			return 'date_with_text';
		}

		return 'text-only';
	}

}

// based on original work from the PHP Laravel framework
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}