console.log("test")

var renderTarget = document.getElementById('render-target')

renderFileList();

async function renderFileList() {
	const files = await getFiles()
	console.log(files)

	const videoCards = files.map(composeVideoCard)

	const videoWrapper = document.createElement('div')
	videoWrapper.className = 'video-list'

	videoWrapper.append(...videoCards)

	renderTarget.append(videoWrapper)
	renderTarget.classList.add('loaded')
}

function composeVideoCard(video) {
	const wrapper = document.createElement('a')
	wrapper.className = 'video-card'
	

	const title = document.createElement('div')
	title.className = 'video-card__title'
	if(video.human_time) {
		title.innerHTML = reformatHumanTime(video.human_time)
	}else {
		title.textContent = video.file		
	}

	const author = document.createElement('div')
	author.className = 'video-card__author'
	if(video.uploaded_by) {
		author.textContent = 'By: '+video.uploaded_by
	}
	

	const time = document.createElement('div')
	time.className = 'video-card__duration'	


	var thumbnail;
	if(video.unplayable == true) {
		wrapper.classList.add('video-unplayable')
		loadingSpinner = document.createElement('div')
		loadingSpinner.className = "lds-dual-ring video-card__thumbnail"
		thumbnail = loadingSpinner

		time.classList.add('still-recording')
		updateRecordingTimeLive(video, time)

	} else {
		thumbnail = document.createElement('img')
		thumbnail.className = 'video-card__thumbnail'
		thumbnail.src = '/thumbnails/' + video.thumb
		thumbnail.setAttribute('loading', 'lazy')

		time.textContent = secondsToDuration(video.length)
		wrapper.href = createPlayUrl(video)
	}



	wrapper.append(thumbnail, title, time, author)

	if(video.scenes && video.scenes.img) {
		var multiFramePreview;
		wrapper.addEventListener('mouseenter', e=> {
			if(multiFramePreview)
				return
			multiFramePreview = initMultiFramePreview(wrapper, video, {offsetX:e.offsetX})
		})
	}	

	return wrapper;
}

async function getFiles() {
	const response = await fetch('/api.php');

	return await response.json()
}

function secondsToDuration(seconds) {
	var seconds = parseInt(seconds, 10); // don't forget the second param
    var hours   = Math.floor(seconds / 3600);
    var minutes = Math.floor((seconds - (hours * 3600)) / 60);
    seconds = seconds - (hours * 3600) - (minutes * 60);

    var time    = minutes+'m '+seconds+'s';

	if(hours > 0) {
		time = hours+'h ' + time;
	}

    return time;
}
function createPlayUrl(video, time){
	const paramData = {
	  video: '/video_files/'+video.file
	}
	if(time)
		paramData['time'] = time

	const params = new URLSearchParams(paramData);
	
	const playUrl = '/play.html#'+params.toString()
	return playUrl
}

function updateRecordingTimeLive(video, timer) {
	function doTimedUpdate(){
		const currentTime = (new Date().getTime()) / 1000;

		const recordStartTime = video.timestamp

		const timeSpentRecording = currentTime - recordStartTime

		timer.textContent = secondsToDuration(timeSpentRecording)
	}

	window.setInterval(doTimedUpdate, 1000)
	doTimedUpdate()
}

// ToDo: Return these separate from API? Or format locally for timezone conversion.
function reformatHumanTime(humanTimeString) {
	const [date, time] = humanTimeString.split(' @ ')

	return time+"<br/>"+date
}

async function initMultiFramePreview(parent, video, defaultMoveEvent) {
	var self = {
		onLoad: function(){
			if(!defaultMoveEvent) return
			console.log("Handling default event", defaultMoveEvent)
			handleMouseMove(defaultMoveEvent)
		}
	}

	const wrapper = document.createElement('div')
	wrapper.className = "video-preview"
	parent.append(wrapper)


	function loadImage(src){
	  return new Promise((resolve, reject) => {
	    let img = new Image()
	    img.onload = () => resolve(img)
	    img.onerror = reject
	    img.src = src
	  })
	}

	startLoadingIndicator()
	var img = await loadImage(video.scenes.img)
	stopLoadingIndicator()
	

	var frames = video.scenes.frames;
    var frameHeight = img.naturalHeight / frames // 960
    var frameWidth = img.naturalWidth // 540

	var canvas = document.createElement('canvas')
    canvas.className = 'video-preview__canvas'
    canvas.setAttribute('width', frameWidth)
    canvas.setAttribute('height', frameHeight)
    var context = canvas.getContext("2d");

	wrapper.append(canvas)

	var sx = 0
	var sy = 0
	var sWidth = frameWidth
	var sHeight = frameHeight
	var dx = 0
	var dy = 0
	var dWidth = frameWidth
	var dHeight = frameHeight
	function scrubToFrame(frameNumber) {
		context.drawImage(img, sx, (540 * frameNumber-1), sWidth, sHeight, dx, dy, dWidth, dHeight);
	}
    var playhead = document.createElement('div')
    playhead.className = 'video-preview__playhead'
    playhead.style.display = 'none'
    playhead.setAttribute('title', 'Ctrl+Click to play from this point.')


    var playheadTimestamp = document.createElement('div')
    playheadTimestamp.className = 'video-preview__timestamp'


    wrapper.append(canvas, playhead, playheadTimestamp)

    function defaultPos() {
        img.style.top = 0
        playhead.style.display = 'none'
        wrapper.style.display = 'none'
    }

    //percent is between 1 to 100
    function updateScrubPosition(percent){
    	console.log(percent)

    	const frameNumberToShow = Math.round(percent * frames / 100)
		//  frame   percent
		//  --        --
		//  frames   100
		console.log("Show frame", frameNumberToShow)

		scrubToFrame(frameNumberToShow)
		//scrubByPercent(percent)
    }
    var currentPercent = 0
    function handleMouseMove(e) {
    	if(e.target === playhead) {
    		// console.log("Ignoring playhead mousemove")
    		return
    	}
		var mouseX = e.offsetX;
		var wrapperWidth = wrapper.offsetWidth;

		var percent = (mouseX * 100) / wrapperWidth
		//  x   percent
		//  --  --
		//  w   100

		currentPercent = percent
		updatePlayheadPosition(mouseX, percent)
		updateScrubPosition(percent)
	}
    wrapper.addEventListener('mousemove', handleMouseMove);

	wrapper.addEventListener('mouseleave', defaultPos)
	parent.addEventListener('mouseenter', e=> {wrapper.style.display = 'block'})

	wrapper.addEventListener('click', e => {
		if(!e.ctrlKey) return;
		e.preventDefault()

		const playUrl = createPlayUrl(video, currentPlayheadSeconds)

		window.open(playUrl)
	})

	var currentPlayheadSeconds = 0
    function updatePlayheadPosition(mouseX, percent) {
    	console.log(mouseX);
    	playhead.style.left = (mouseX - 1) + 'px' // -1 because it's 2px width
    	playhead.style.display = 'block'

    	currentPlayheadSeconds = video.length * (percent/100)
    	playheadTimestamp.textContent = secondsToDuration(currentPlayheadSeconds)

    }
    window.startLoadingIndicator = startLoadingIndicator;

    var overlay;
    function startLoadingIndicator(message) {
    	stopLoadingIndicator()

    	overlay = document.createElement('div')
    	overlay.className = "video-card__overlay_preview-loading video-card__overlay-icon lds-dual-ring";

    	const text = document.createElement('span')
    	text.className = "video-card__overlay-icon__text"

    	text.textContent = message || 'Loading Preview...';
    	
    	overlay.append(text)
    	parent.append(overlay)
    }
    function stopLoadingIndicator() {
    	overlay && overlay.remove();
    }

    self.onLoad()

    return true
    

}

