<?php

function run_test_script($scriptFile) {
	include(__DIR__ . "/$scriptFile");
}

function test_log($msg) {
	echo $msg;
	echo "\n";
}

run_test_script('unit/filename_parser.php');

