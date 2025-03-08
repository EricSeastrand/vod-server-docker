<?php

require_once(__DIR__.'/VideoFilenameParser.php');
require_once(__DIR__.'/ShellCommand.php');

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

	function getEncoding() {
		$firstVideoStream = $this->getFirstVideoStream();

		return [
			'codec_name' => $firstVideoStream['codec_name']
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

		$command = "ffprobe -v error -print_format json -show_format -show_streams {$this->getShellSafeFilePath()}";

		$result = ShellCommand::run($command);
		$output = implode("\n", $result['output']);
		
		if(strpos($output, 'moov atom not found') !== false) {
			$this->fileIsUnplayable = true;
			return [];
		}
		
		$output = json_decode($output, true);

		$this->ffProbeResult = $output;

		return $output;
	}


	function getThumbnail($atTime = 0 /* seconds */) {
		$thumbnailFilename = "{$this->fileName}.thumb.avif";
		//$thumbnailPath = "{$this->fileDir}/$thumbnailFilename";
		$thumbnailPath = "/thumbnails/$thumbnailFilename";

		if(file_exists($thumbnailPath)) {
			return $thumbnailFilename;
		}
		$thumbnailArg = escapeshellarg($thumbnailPath);

		$atTimeParam = escapeshellarg($atTime);
		
		# Old command for generating jpgs
		# $command = "ffmpeg -ss {$atTimeParam} -i {$this->getShellSafeFilePath()} -vf scale=720:-2 -frames:v 1 -q:v 10 -y {$thumbnailArg}";

		$command = "ffmpeg -ss {$atTimeParam} -i {$this->getShellSafeFilePath()} -vf scale=720:-2 -frames:v 1 -crf 34 -y {$thumbnailArg}";
		
		$result = ShellCommand::run($command);
		$result_code = $result['result_code'];

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

		if(@$parsedFilename['title']) {
			$toReturn['title'] = $parsedFilename['title'];
		}

		$fullFfprobe = $this->getFullFfprobe();

		if(!$this->fileIsUnplayable) {
			$videoData = [
				'length'     => $this->getLength(),
				'thumb'      => $this->getThumbnail(),
				'resolution' => $this->getResolution(),
				'encoding'   => $this->getEncoding(),
				'fps'        => $this->getFramerate(),
				'scenes'     => $this->getScenesThumbnails(),
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

	function formatHumanDate($dateTime) {
		if(!$dateTime) {
			// Couldn't parse time probably
			return '';
		}

		if($dateTime->format('H:i:s') === '00:00:00') {
			// Date-only format.
			return $dateTime->format('D M j Y');
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
		$thumbnailFilename = "{$this->fileName}.preview.{$framesToExtract}.avif";
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


