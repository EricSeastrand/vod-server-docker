<style>
	body {font-family:  sans-serif;}
</style>
<h1>Click a link below to watch a video.</h1>
<h4 style="margin-bottom: 0px">Keyboard Shortcuts</h4>
<pre>
B: Skip Backwards
N: Skip Forward [think N for "next"]

Speed
S: Slower
F: Faster
Optionally: Pause before hitting F/S and video will re-pause when you release key.

Tip: Hold Shift with any shortcut to make it more extreme.
</pre>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if(isset($_GET['as_json'])){
	echo json_encode(getFiles(), JSON_PRETTY_PRINT);
} else {
	outputFileLinks();
}

function getFiles() {
	$path = __DIR__ . '/video_files/*.{mp4,mov}';
	$currentTime=time();

	$files = glob($path);
	
	$filesDetails = array_map(function($file) use ($currentTime){
		$relativePath = str_replace(__DIR__, '', $file);
		$modifyTime = filectime($file);
		$timeSinceModify = $currentTime - $modifyTime;

		$fileDateTime = parseTimeFromFilename($file);
		return [
			'file' => $relativePath,
			'modified' => $modifyTime,
			'time_since_modify' => $timeSinceModify,
			'still_recording' => ($timeSinceModify < 10),
			'human_time' => formatHumanDate($fileDateTime),
			'timestamp' => ($fileDateTime instanceof DateTime) ? $fileDateTime->getTimestamp() : false
		];
	}, $files);

	array_multisort(array_column($filesDetails, 'modified'), SORT_DESC, $filesDetails);

	return $filesDetails;
}

function outputFileLinks() {
	$files = getFiles();

	foreach ($files as $i => $file) {
		$filePath = htmlentities(urlencode($file['file']));
		$label = $file['human_time'] ?: basename($filePath);
		$labelHtml = htmlentities($label);
		
		if($file['still_recording']) {
			echo "<a>{$labelHtml} (Still recording... Try refreshing?)</a>";
		} else {
			echo "<a href='play.html#video={$filePath}'>{$labelHtml}</a>";	
		}
		
		

		echo "</br>";
	}
}

function parseTimeFromFilename($filename) {
	// Example: 2021-09-10 19-37-36.mp4
	$timeFromFile = pathinfo($filename, PATHINFO_FILENAME);
	
	$dateTime = DateTime::createFromFormat('Y-m-d H-i-s', $timeFromFile, new DateTimeZone('CST'));
	
	return $dateTime;
}

function formatHumanDate($dateTime) {
	if(!$dateTime) {
		// Couldn't parse time probably
		return '';
	}

	return $dateTime->format('D M j Y @ g:i A T'); // Fri Sept 10 2021 9:16 PM CST
}