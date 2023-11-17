<?php
require_once(__DIR__. "/ShellCommand.php");
class BackendCommand {

	public $commandId;
	public $params;

	function __construct($commandId) {
		if($commandId !== 'import-media') {
			throw new Exception("Unknown Command");
			// For now this class is a one trick pony
		}

		$this->commandId = $commandId;
	}

	function setParams($params) {
		$this->params = [
			'input_file' => $params['input_file']
		];
	}

	function validate() {
		return [
			'HTTP_CLIENT_IP' => $_SERVER['HTTP_CLIENT_IP'],
			'HTTP_X_FORWARDED_FOR' => $_SERVER['HTTP_X_FORWARDED_FOR'],
			'HTTP_X_FORWARDED' => $_SERVER['HTTP_X_FORWARDED'],
			'HTTP_FORWARDED_FOR' => $_SERVER['HTTP_FORWARDED_FOR'],
			'HTTP_FORWARDED' => $_SERVER['HTTP_FORWARDED'],
			'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'],
		];
	}

	function importMediaFile($filePath, $deleteAfterImport=true) {
		$destinationPath = '/vod-server-webapp/nginx_www/video_files';
		$fileNameNoExtn = pathinfo($filePath, PATHINFO_FILENAME);
		$destinationFile = "$destinationPath/$fileNameNoExtn.mp4";
		$SAFE_destinationFile = escapeshellarg($destinationFile);
		$SAFE_inputFile = escapeshellarg($filePath);
		$out = [];
		
		# Remux into mp4, Fix the moov atom to be at beginning of file.
		$ffmpegCommand = join([
			'ffmpeg',
			'-i', $SAFE_inputFile,
			'-codec', 'copy',
			'-movflags', 'faststart',
			$SAFE_destinationFile
		], ' ');

		$result = ShellCommand::run($ffmpegCommand);
		$out[] = $result;

		if($result['result_code'] !== 0) {
			$out[] = 'Stopped after ffmpeg non success';
			return $out;
		}

		if($deleteAfterImport) {
			$out[] = 'Deleting...';
			$deleteResult = unlink($filePath);
			$out[] = 'Delete result: ' . ($deleteResult ? 'Success' : 'Fail');
		}
		
		$thumbnailGenerateScript = '/vod-server-webapp/commands/generate-multiframe-thumbnail.php';
		$thumbnailGenerateCommand = "php $thumbnailGenerateScript $SAFE_destinationFile";
		$result = ShellCommand::run($thumbnailGenerateCommand);
		$out[] = $result;

		return $out;
	}

	function run() {


		$commandPath = __DIR__.'/../../commands/'.$this->commandId;

		$commandRealPath = realpath($commandPath);
		if($commandRealPath === false) {
			return ['error' => "Command {$this->commandId} does not exist.", 'status_code' => 405];
		}

		$inputFile = $this->params['input_file'];
		$inputFileExtn = strtolower(pathinfo($inputFile, PATHINFO_EXTENSION));
		$allowedExtns = ['mp4', 'mov', 'mkv'];
		$fileExtnIsAllowed = in_array($inputFileExtn, $allowedExtns) === true;

		if(!$fileExtnIsAllowed) {
			return ['error' => "Extension {$inputFileExtn} is not allowed.", 'status_code' => 400];
		}


		$inputFile = basename($inputFile);
		$inputPath = "/vod-server-webapp/nginx_www/video_files/_recording/{$inputFile}";

		$inputFileRealPath = realpath($inputPath);
		if($inputFileRealPath === false) {
			return ['error' => "Input file {$inputFile} does not exist.", 'status_code' => 403];
		}
		
		$result = $this->importMediaFile($inputFileRealPath);

		#$actualCommand = $commandRealPath . " " . escapeshellarg($inputFileRealPath);
		return [
			#'ran' => $actualCommand,
			'result' => $result,
			'status_code' => 200
		];
	}
}

