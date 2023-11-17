<?php

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
		header('Content-Type: application/json; charset=utf-8');
		http_response_code($statusCode);
		echo json_encode($response, JSON_PRETTY_PRINT);
	}
}
