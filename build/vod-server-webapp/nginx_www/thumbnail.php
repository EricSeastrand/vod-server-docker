<?php

require_once(__DIR__ . '/model/VideoFile.php');


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

$file = @$_GET['file'];
if($file) {
	$video = new TESTVideoFile($file);
	print_r($video->generateMultiFramePreview());
	return;
}

#$commands = getThumbCommands();
$commands = getThumbCommandsFast();
echo implode("\n", $commands);

function getThumbCommands() {
	$path = __DIR__ . '/video_files/*.mp4';
	$currentTime=time();

	$files = glob($path);
	
	$filesDetails = array_map(function($file) use ($currentTime){
		$video = new TESTVideoFile($file);

		return $video->getMetadata(isset($_GET['no_cache']));
	}, $files);


	array_multisort(array_column($filesDetails, 'timestamp'), SORT_DESC, $filesDetails);

	$filesWithoutThumbs = array_filter($filesDetails, function($f){ return !$f['scenes']; });

	$commands = array_map(function($file){
		$file = 'video_files/'.$file['file'];
		#print_r($file);
		$video = new TESTVideoFile($file);

		return $video->generateMultiFramePreview();
	}, $filesWithoutThumbs);

	return $commands;
}

function getThumbCommandsFast() {
	$path = __DIR__ . '/video_files/*.mp4';
	$currentTime=time();

	$files = glob($path);
	
	$filesDetails = array_map(function($file){
		$video = new TESTVideoFile($file);

		return $video;
	}, $files);



	$filesWithoutThumbs = array_filter($filesDetails, function($video){ return !$video->getScenesThumbnails(); });

	$commands = array_map(function($video){
		return $video->generateMultiFramePreview();
	}, $filesWithoutThumbs);

	
	#array_multisort(array_column($filesDetails, 'timestamp'), SORT_DESC, $filesDetails);
	
	return $commands;
}
