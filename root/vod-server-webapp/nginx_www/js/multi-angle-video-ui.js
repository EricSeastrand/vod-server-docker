

function ui(player){
	bindEvents()
	var angles;
	function bindEvents() {
		const eventPrefix = 'multi-angle-video.'
		const events = [
			['anglesAvailable', onAnglesAvailable],
			['angleTimeOverlapUpdate', angleTimeOverlapUpdate]
		]
		events.forEach((e) => player.addEventListener(eventPrefix+e[0], e[1]) )
		
	}

	function onAnglesAvailable(e) {
		angles = e.detail;
		console.log("onAnglesAvailable", angles);
		
		outputUi()
	}

	var wrapper, angleList, summaryLabel;
	function outputUi() {
		wrapper = wrapper || document.createElement('details')
		wrapper.className = 'multi-angle-video'
		document.querySelector('#video-wrap').append(wrapper)
		
		const labelText = 'Multiple Angles Available'

		summaryLabel = document.createElement('summary')
		summaryLabel.textContent = labelText

		angleList = document.createElement('div')
		wrapper.append(summaryLabel, angleList)
	}
	function updateSummaryText(text) {
		summaryLabel.textContent = text
	}

	function angleTimeOverlapUpdate(e) {
		//ToDo: Debounce
		const angles = e.detail.angles;
		onOverlapChange(angles)
	}

	function onOverlapChange(angles) {
		console.log("onOverlapChange", angles);
		updateSummaryText('View Other Angles: ' + angles.length)
		buildAngleSelectorLinks(angles)
	}


	function buildAngleSelectorLinks(angles) {
		const elements = angles.map(function(angle) {
			
			const anchor = document.createElement('a')
			var anchorText = angle.file.uploaded_by
			
			anchor.className = 'multi-angle-video__selector'

			anchor.addEventListener('click', angle.open)
			if(!angle.canPlayFromHere) {
				anchor.className += ' unavailable'
				anchor.title = angle.canPlayFromHereError || 'Not Available'
			}
			if(angle.isCurrentlyPlaying) {
				anchorText += ' (Current)'
			}
			anchor.textContent = anchorText

			return anchor
		})
		angleList.innerHTML = '' // Empty it out.
		elements.forEach(e => angleList.append(e));
	}

}

export { ui }