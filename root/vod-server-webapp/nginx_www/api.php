<?php

if(isset($_GET['verbose_debug'])) {
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
}

require_once(__DIR__. "/model/VideoFile.php");

header('Content-Type: application/json; charset=utf-8');

$router = new ApiRouter();
$router->run();




class ApiRouter {
	function run() {
		try {
			$result = $this->handleRequest();
			$this->respond($result, @$result['status_code'] ?: 200);
		} catch(Exception $e) {
			$this->respond(['error' => 'Exception encountered', 'detail' =>$e->getMessage()], 500);
		}
	}

	function handleRequest() {
		$action = @$_GET['action'] ?: 'getFiles';

		if(!is_callable("ApiHandlers::$action")) {
			throw new Exception("No handler by the name {$action}.");
		}

		return ApiHandlers::$action();
	}

	function respond($response, $statusCode=200) {
		http_response_code($statusCode);
		echo json_encode($response, JSON_PRETTY_PRINT);
	}
}

class ApiHandlers {
	static function getFiles() {
		$path = __DIR__ . '/video_files/*.';
		$currentTime=time();

		$files = array_merge(
			glob($path . 'mp4'),
			glob($path . 'mov')
		);
		
		$filesDetails = array_map(function($file) use ($currentTime){
			$video = new VideoFile($file);

			return $video->getMetadata(isset($_GET['no_cache']));
		}, $files);

		array_multisort(array_column($filesDetails, 'timestamp'), SORT_DESC, $filesDetails);

		return $filesDetails;
	}

	static function updateSingleFileMeta() {
		$fileName = @$_GET['file'];

		$path = static::getVideoRealPath($_GET['file']);

		$video = new VideoFile($path);

		return $video->getMetadata(true /* No cache */);
	}

	static function findOverlappingVideos() {
		require_once(__DIR__. "/model/VideoFileTimeOverlap.php");
		$allFiles = static::getFiles();
		$mainFile = static::getVideoRealPath($_GET['file']);
		if(!$mainFile) {
			throw new Exception("No file specified.");
		}
		
		$finder = new VideoFileTimeOverlap($mainFile, $allFiles);

		return $finder->findOverlappedFiles();
	}

	static function getVideoRealPath($fileName) {
		$_path = __DIR__ . '/video_files/'.$fileName;
		$path = realpath($_path);
		if(!file_exists($path)) {
			throw new Exception("File does not exist: $_path");
		}
		return $path;
	}
}