<?php

test_log("It must'nt give fatal errors when included.");
require_once(__DIR__ . '/../../nginx_www/model/VideoFilenameParser.php');

test_log("It can be instantiated, even if the video file does not actually exist");
$parser = new VideoFilenameParser('not-a-real-video-file');

#### Stream Saver format
test_log("It can be instantiated, with a video file from RTSP video stream saver");
$parser = new VideoFilenameParser('Def-1672473010.2022-12-31_01-50-10.flv.mp4');

test_log("It doesn't give an error when we parse a filename");
$parsedFilename = $parser->parseFilename();

test_log("Parsed filenames contain a date-like object");
if ($parsedFilename['date'] instanceof DateTime) {
	test_log("Pass!");
} else {
	test_log("Fail!");
}

test_log("It properly parses video timestamps created by vod-receiver RTSP Video streams");
$timestampMatches = date_timestamp_get($parsedFilename['date']) == 1672473010;
if($timestampMatches) {
	test_log("Pass!");
} else {
	test_log("Fail!");
}

test_log("It properly parses video uploader name created by vod-receiver RTSP Video streams");
$expectedUploadedBy = 'Def';
if($parsedFilename['uploaded_by'] == $expectedUploadedBy) {
	test_log("Pass!");
} else {
	test_log("Fail! uploaded_by was:{$parsedFilename['uploaded_by']} but expected {$expectedUploadedBy}");
}



#### Legacy format from OBS
#2021-09-10 19-37-36.mp4

test_log("It can be instantiated, with OBS / Legacy datestamp format");
$parser = new VideoFilenameParser('2021-09-10 19-37-36.mp4');

test_log("It detects the obs legacy format");
$fileNameScheme = $parser->determineFilenameScheme();
if($fileNameScheme == 'legacy_obs') {
	test_log("Pass!");
} else {
	test_log("Fail! Filename scheme was supposedly {$fileNameScheme}");
}


test_log("It doesn't give an error when we parse a filename");
$parsedFilename = $parser->parseFilename();

test_log("Parsed filenames contain a date-like object");
if ($parsedFilename['date'] instanceof DateTime) {
	test_log("Pass!");
} else {
	test_log("Fail!");
}

test_log("It properly parses video timestamps created by OBS");
$timestampDiff = date_timestamp_get($parsedFilename['date']) - 1631324256; // Assumptions made about daylight savings.
if($timestampDiff === 0) {
	test_log("Pass!");
} else {
	test_log("Fail! Mismatched by {$timestampDiff} seconds.");
}

#### Date + Arbitrary text format from "home movies"
test_log("It can be instantiated, with a video file that contains a date and arbitrary text but no time");
$parser = new VideoFilenameParser('2022-05-02 Milo and Mom play.mov');

test_log("Parsing that filename does not error");
$parsedFilename = $parser->parseFilename();

test_log("It detects the filename scheme is date with text");
$fileNameScheme = $parser->determineFilenameScheme();
if($fileNameScheme == 'date_with_text') {
	test_log("Pass!");
} else {
	test_log("Fail! Filename scheme was supposedly {$fileNameScheme}");
}

test_log("Parsed filename contains a date-like object");
if ($parsedFilename['date'] instanceof DateTime) {
	test_log("Pass!");
} else {
	test_log("Fail!");
}

test_log("Parsed filename date is accurate");
$actualDate = $parsedFilename['date']->format('Y-m-d');
if ($actualDate == '2022-05-02') {
	test_log("Pass!");
} else {
	test_log("Fail! We got {$actualDate} for date.");
}

test_log("Video Title is accurate");
if ( $parsedFilename['title'] == "Milo and Mom play") {
	test_log("Pass!");
} else {
	test_log("Fail! We got {$parsedFilename['title']} for title.");
}
