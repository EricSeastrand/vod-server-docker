<?php

$_ENV['FFMPEG_DEBUG'] = true;

require_once(__DIR__ . '/../nginx_www/model/VideoFile.php');





class TESTVideoFile extends VideoFile {

	function generateMultiFramePreview() {
		$framesToExtract = 50;
		$firstVideoStream = $this->getFirstVideoStream();
		//print_r($firstVideoStream);

		$framesInVideo = $firstVideoStream['nb_frames'];

		$thumbnailFilename = "{$this->fileName}.preview.{$framesToExtract}.jpg";
		$thumbnailPath = "/thumbnails/$thumbnailFilename";
		
		$MOVIE = $this->getShellSafeFilePath();
		$NTH_FRAME = floor($framesInVideo/$framesToExtract);
		$OUT_FILEPATH  = escapeshellarg($thumbnailPath);
		$COLS=1;
		$ROWS=$framesToExtract;

		$HEIGHT = $firstVideoStream['height'] / 2;

		// Copied from https://www.binpress.com/generate-video-previews-ffmpeg/
		$command = "ffmpeg -loglevel panic -i $MOVIE -y -frames 1 -q:v 1 -vf 'select=not(mod(n\,$NTH_FRAME)),scale=-1:$HEIGHT,tile={$COLS}x{$ROWS}' $OUT_FILEPATH";

		return $command;
	}
}

/*
$file = @$_GET['file'];
if($file) {
	$video = new TESTVideoFile($file);
	print_r($video->generateMultiFramePreview());
	return;
}

*/

$lock = new PidLock();
$lock->acquire();

generateThumbnails();

$lock->release();

function getVideosNeedingThumbnails() {
	writeToLog('Starting to generate thumbnail commands.');
	
	$path = __DIR__ . '/../../video_files/*.mp4';

	$files = glob($path);
	
	$videos = array_map(function($file){
		$video = new TESTVideoFile($file);

		return $video;
	}, $files);

	$videosWithoutThumbs = array_filter($videos, function($video){ return !$video->getScenesThumbnails(); });

	$videosWithoutThumbs = array_values($videosWithoutThumbs); // Reindex from 0

	$paths = array_map(function($video){
		return $video->localPath;
	}, $videosWithoutThumbs);


	writeToLog([
		'files' => count($files),
		'needingThumbnail' => count($videosWithoutThumbs),
		'videos' => $paths
	]);

	
	return $videosWithoutThumbs;
}

function runCommand($ffMpegCommand) {
	if(getenv('DRY_RUN')) {
		echo "Would have run: $ffMpegCommand";
		return false;
	}

	$result = exec($ffMpegCommand, $output, $result_code);
	if($_ENV['FFMPEG_DEBUG']) {
		$commandResult = compact('ffMpegCommand', 'result_code', 'result', 'output');
		print_r($commandResult);
	}
	if(!$result_code == 0) {
		logWarning("Thumbnail creation failed with command: {$command}.");
		return false;
	}

	return true; // Assume it worked.
}

function generateThumbnails() {
	$videos = getVideosNeedingThumbnails();
	$successes = 0;
	$totalVideos = count($videos);
	for ($i=0; $i < $totalVideos; $i++) { 
		$video = $videos[$i];
		$currentNumber = $i+1;
		$command = $video->generateMultiFramePreview();

		writeToLog("Running command {$currentNumber} of {$totalVideos}");
		$timer = new Stopwatch();
		$success = runCommand($command);
		writeToLog("Command completed after {$timer->getElapsed()} sec.");

		if($success) {
			$successes++;
			$video->getMetadata(true /*force refresh cached metadata */);
		}
	}
	
	writeToLog("Process completed after processing {$totalVideos}. Successes: {$successes}");
}

function logWarning($message) {
	trigger_error($message, E_USER_WARNING);
	writeToLog(["Warning" => $message]);
}

function writeToLog($content) {
	$toWrite = json_encode($content, JSON_PRETTY_PRINT);

	$logFile = __DIR__.'/../log/multiframe-thumbnail.log';

	file_put_contents($logFile, $toWrite . "\n", FILE_APPEND | LOCK_EX);
}

class Stopwatch {
	public $startTime;
	
	function __construct() {
		$this->start();
	}

	function start() {
		$this->startTime = microtime(true);
	}

	function getElapsed(){
		$now = microtime(true);
  
		return ($now - $this->startTime);
	}
}

/*Handles "locking" to prevent two of this script running at the same time. 
I adapted this SE answer into a class: https://stackoverflow.com/a/24665209/884734
*/
class PidLock {
	public $pidFile;
	public $fileHandle;

	function __construct() {
		$file = basename(__FILE__);
		$this->pidFile = realpath(__DIR__."/../log/{$file}.pid");
		echo "PID file: {$this->pidFile}\n";
	}

	function acquire() {
		$this->fileHandle = fopen($this->pidFile, 'c');
		$got_lock = flock($this->fileHandle, LOCK_EX | LOCK_NB, $wouldblock);
		if ($this->fileHandle === false || (!$got_lock && !$wouldblock)) {
		    throw new Exception(
		        "Unexpected error opening or locking lock file. Perhaps you " .
		        "don't  have permission to write to the lock file or its " .
		        "containing directory?"
		    );
		}
		else if (!$got_lock && $wouldblock) {
		    exit("Another instance is already running; terminating.\n");
		}

		// Lock acquired; let's write our PID to the lock file for the convenience
		// of humans who may wish to terminate the script.
		ftruncate($this->fileHandle, 0);
		fwrite($this->fileHandle, getmypid() . "\n");
	}

	function release() {
		// All done; we blank the PID file and explicitly release the lock 
		// (although this should be unnecessary) before terminating.
		ftruncate($this->fileHandle, 0);
		flock($this->fileHandle, LOCK_UN);
	}

}