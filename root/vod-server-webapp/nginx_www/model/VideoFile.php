<?php

require_once(__DIR__.'/VideoFilenameParser.php');

class VideoFile {
	protected $filePath;
	protected $fileDir;
	protected $fileName;
	protected $ffProbeResult;
	protected $fileIsUnplayable;

	function __construct($videoFile) {
		if(!file_exists($videoFile)) {
			trigger_error("Attempt to get length of nonexistent video file: {$videoFile}", E_USER_WARNING);
			return false;
		}

		$this->filePath = $videoFile;
		$this->fileName = basename($videoFile);
		$this->fileDir  = dirname($videoFile);
		$this->localPath = realpath($this->filePath);
		$this->metaDataFile = "/video_meta/{$this->fileName}.json";
	}

	function getLength() {
		$probe = $this->getFullFfprobe();

		$duration = $probe['format']['duration'];

		return floatval($duration);
	}

	function getFirstVideoStream() {
		$probe = $this->getFullFfprobe();

		$videoStreams = array_filter($probe['streams'], function($stream){
			return $stream['codec_type'] == 'video';
		});
		return $videoStreams[0];
	}

	function getResolution() {
		$firstVideoStream = $this->getFirstVideoStream();

		return [
			'width' => $firstVideoStream['width'],
			'height'=> $firstVideoStream['height']
		];
	}

	function getFramerate() {
		$firstVideoStream = $this->getFirstVideoStream();

		$frameRateFraction = $firstVideoStream['avg_frame_rate'];

		list($numerator, $denominator) = explode('/', $frameRateFraction);

		$fps = $numerator / $denominator;

		return $fps;
	}

	function getFullFfprobe() {
		if($this->ffProbeResult) {
			return $this->ffProbeResult;
		}

		$command = "ffprobe -v error -print_format json -show_format -show_streams {$this->getShellSafeFilePath()} 2>&1";

		$result = shell_exec($command);
		if(strpos($result, 'moov atom not found') !== false) {
			$this->fileIsUnplayable = true;
			return [];
		}
		
		$result = json_decode($result, true);

		$this->ffProbeResult = $result;

		return $result;
	}


	function getThumbnail($atTime = 1 /* seconds */) {
		$thumbnailFilename = "{$this->fileName}.thumb.jpg";
		//$thumbnailPath = "{$this->fileDir}/$thumbnailFilename";
		$thumbnailPath = "/thumbnails/$thumbnailFilename";

		if(file_exists($thumbnailPath)) {
			return $thumbnailFilename;
		}
		$thumbnailArg = escapeshellarg($thumbnailPath);

		$atTimeParam = escapeshellarg($atTime);
		
		//$command = "ffmpeg -i {$this->getShellSafeFilePath()} -ss {$atTimeParam} -frames:v 1 {$thumbnailArg} 2>&1";
		$command = "ffmpeg -ss {$atTimeParam} -i {$this->getShellSafeFilePath()} -vf scale=720:-2 -frames:v 1 -q:v 10 -y {$thumbnailArg} 2>&1";
		$result = exec($command, $output, $result_code);
		if(isset($_GET['ffmpeg_debug'] )) {
			print_r(compact('command', 'result_code', 'result', 'output'));
		}
		if(!$result_code == 0) {
			trigger_error("Thumbnail creation failed with command: {$command}.", E_USER_WARNING);
			return false;
		}

		return $thumbnailFilename;
	}

	function getShellSafeFilePath() {
		return escapeshellarg($this->localPath);
	}

	function _getMetadata() {

		$parser = new VideoFilenameParser($this->fileName);
		$parsedFilename = $parser->parseFilename();
		$fileDateTime = $parsedFilename['date'];

		$toReturn = [
			'_metadata_timestamp' => time(),
			'file'       => $this->fileName,
			'human_time' => $this->formatHumanDate($fileDateTime),
			'timestamp'  => ($fileDateTime instanceof DateTime) ? $fileDateTime->getTimestamp() : false,
			'uploaded_by'=> $parsedFilename['uploaded_by']
		];

		$fullFfprobe = $this->getFullFfprobe();

		if(!$this->fileIsUnplayable) {
			$videoData = [
				'length'     => $this->getLength(),
				'thumb'      => $this->getThumbnail(),
				'resolution' => $this->getResolution(),
				'fps'        => $this->getFramerate(),
				'scenes'     => $this->getScenesThumbnails()
			];
			$videoData['end_timestamp'] = $toReturn['timestamp'] + $videoData['length'];
			$toReturn = array_merge($toReturn, $videoData);
		} else {
			$toReturn['unplayable'] = $this->fileIsUnplayable;
		}


		if(isset($_GET['include_full_ffprobe'])) {
			$toReturn['ffprobe'] = $fullFfprobe;
		}

		return $toReturn;
	}

	function parseTimeFromFilename() {
		// Example: 2021-09-10 19-37-36.mp4
		$timeFromFile = pathinfo($this->filePath, PATHINFO_FILENAME);
		
		$dateTime = DateTime::createFromFormat('Y-m-d H-i-s', $timeFromFile, new DateTimeZone('CST'));
		
		return $dateTime;
	}

	function formatHumanDate($dateTime) {
		if(!$dateTime) {
			// Couldn't parse time probably
			return '';
		}

		return $dateTime->format('D M j Y @ g:i A T'); // Fri Sept 10 2021 9:16 PM CST
	}

	function getMetadata($noCache = false) {
		if(!$noCache) {
			$cachedMetadata = @file_get_contents($this->metaDataFile);
		}

		if(isset($cachedMetadata) && $cachedMetadata) {
			//echo "Cache Hit";
			return json_decode($cachedMetadata, true);
		}

		//echo "Cache Miss";
		$freshMetadata = $this->_getMetadata();

		if(!$this->fileIsUnplayable) {
			// Don't want to cache metadata until the video is actually playable. It's probably still recording.
			file_put_contents($this->metaDataFile, json_encode($freshMetadata));
		}

		return $freshMetadata;
	}

	function getScenesThumbnails() {
		$framesToExtract = 50;
		$thumbnailFilename = "{$this->fileName}.preview.{$framesToExtract}.jpg";
		$thumbnailPath = "/thumbnails/$thumbnailFilename";

		if(!file_exists($thumbnailPath)) {
			return false;
		}

		return [
			'img'=> $thumbnailPath,
			'frames'=> $framesToExtract
		];
	}
}


