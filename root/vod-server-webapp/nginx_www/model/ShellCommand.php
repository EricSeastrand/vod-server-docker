<?php

class ShellCommand {
	public static $prefix = 'export LD_LIBRARY_PATH=/usr/lib:/lib &&';
	public static $suffix = '2>&1';

	public static function run($_command) {
		$command = implode(' ', [self::$prefix, $_command, self::$suffix]);
		$success = exec($command, $output, $result_code);
		$result = compact('command', 'result_code', 'success', 'output');
		if(isset($_GET['shell_debug'] )) {
			print_r($result);
		}
		if(!$result_code == 0) {
			trigger_error("Shell command returned nonzero result_code: {$command}.", E_USER_WARNING);
		}

		return $result;
	}
}