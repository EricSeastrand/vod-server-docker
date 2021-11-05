

async function multiAngleSelector(videoElement) {

	var urlParams = new URLSearchParams(window.location.hash.replace("#","?"));
	var videoPath = urlParams.get('video');
	var videoFile = videoPath.replace('/video_files/', '')
	var filesWithOverlap;

	await init()
	async function init() {

		filesWithOverlap = await getFilesWithOverlap();

		if(filesWithOverlap.length < 2) {
			console.log("No overlapping files found for multi-angle viewing.")
			triggerEvent('noAnglesAvailable')
			return;
		}
		console.log(filesWithOverlap)
		triggerEvent('anglesAvailable', {files: filesWithOverlap})

		onVideoTimeUpdate()
		//videoElement.addEventListener('timeupdate', onVideoTimeUpdate)
		window.setInterval(onVideoTimeUpdate, 1000);
	}

	function onVideoTimeUpdate() {
		var angles = calculateOtherAvailableAnglesAtTime()
		triggerEvent('angleTimeOverlapUpdate', {angles: angles})
	}

	function calculateOtherAvailableAnglesAtTime() {
		const currentVideoTime = videoElement.currentTime;

		const myFile = filesWithOverlap.find(f => f.file == videoFile)

		var angles = filesWithOverlap.map(function(otherFile) {
			const startTimeOffset = otherFile.timestamp - myFile.timestamp

			const angle = {'file' : otherFile, canPlayFromHere: true}

			angle.equivalentStart = currentVideoTime + startTimeOffset

			if(angle.equivalentStart < 0) {
				angle.canPlayFromHere = false
				angle.canPlayFromHereError = 'Recording had not started yet.'
			}
			if(angle.equivalentStart > otherFile.length) {
				angle.canPlayFromHere = false
				angle.canPlayFromHereError = 'Recording ended before this point.'
			}

			if(otherFile.file == myFile.file) {
				angle.isCurrentlyPlaying = true
				angle.canPlayFromHere = false
				angle.canPlayFromHereError = "You're watching this one right now."
			}

			angle.open = function() {
				openOtherAngle(angle)
			}

			return angle
		})
		console.log("Calculated angles", angles.length, angles)

		return angles
	}

	function openOtherAngle(angleToShow) {
		var urlParams = new URLSearchParams({
			'time': angleToShow.equivalentStart,
			'video': '/video_files/'+angleToShow.file.file
		});
			

		const angleUrl = '/play.html#'+urlParams

		console.log(angleUrl)
		window.open(angleUrl)
	}
	window.calculateOtherAvailableAnglesAtTime = calculateOtherAvailableAnglesAtTime
	window.openOtherAngle = openOtherAngle

	async function getFilesWithOverlap() {
		const urlParams = new URLSearchParams({
		    action: 'findOverlappingVideos',
		    file: videoFile, // Ex: Wrager-1635645803.2021-10-30_21-03-23.mp4
		})
		const response = await fetch('/api.php?' + urlParams);

		return await response.json()
	}

	function triggerEvent(eventName, detail={}) {
		var event = new CustomEvent('multi-angle-video.' + eventName, {detail: detail});
    	player.dispatchEvent(event);
	}

	function onAvailableAnglesFound() {
		
	}
}

export {multiAngleSelector}