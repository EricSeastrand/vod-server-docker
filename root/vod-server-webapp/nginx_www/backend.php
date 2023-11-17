<?php

if(isset($_GET['verbose_debug'])) {
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
}

require_once(__DIR__. "/model/ApiRouter.php");
require_once(__DIR__. "/model/BackendCommand.php");

$router = new ApiRouter();
$router->run();


class ApiHandlers {

	static function invokeCommand() {
		$commandId = @$_REQUEST['command'];

		$command = new BackendCommand($commandId);

		$command->setParams(@$_REQUEST);

		$result = $command->run();

		return $result;
	}
}