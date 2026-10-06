(function () {
	'use strict';

	const data = window.MLHCData;
	const startButton = document.getElementById('mlhc-start');
	const stopButton = document.getElementById('mlhc-stop');
	const panel = document.getElementById('mlhc-progress-panel');
	const progress = panel.querySelector('[role="progressbar"]');
	const progressBar = document.getElementById('mlhc-progress-bar');
	const percentText = document.getElementById('mlhc-percent');
	const statusText = document.getElementById('mlhc-status');
	const notice = document.getElementById('mlhc-notice');
	const results = document.getElementById('mlhc-results');
	const resultsBody = document.getElementById('mlhc-results-body');
	const unavailable = document.getElementById('mlhc-unavailable');
	const noFilterResults = document.getElementById('mlhc-no-filter-results');
	const filterButtons = document.querySelectorAll('.mlhc-filters button');
	const counters = {
		scanned: document.getElementById('mlhc-scanned'),
		total: document.getElementById('mlhc-total'),
		healthy: document.getElementById('mlhc-healthy'),
		error: document.getElementById('mlhc-errors'),
		warning: document.getElementById('mlhc-warnings'),
		info: document.getElementById('mlhc-info')
	};
	const filterCounts = {
		all: document.getElementById('mlhc-filter-all'),
		error: document.getElementById('mlhc-filter-error'),
		warning: document.getElementById('mlhc-filter-warning'),
		info: document.getElementById('mlhc-filter-info')
	};
	const categoryLabels = {
		files: data.i18n.files,
		images: data.i18n.images,
		wordpress: data.i18n.wordpress
	};
	const severityLabels = {
		error: data.i18n.errorSeverity,
		warning: data.i18n.warningSeverity,
		info: data.i18n.infoSeverity
	};

	let state = createState();

	function createState() {
		return {
			running: false,
			stopRequested: false,
			lastId: 0,
			total: 0,
			scanned: 0,
			healthy: 0,
			findings: 0,
			error: 0,
			warning: 0,
			info: 0,
			activeFilter: 'all'
		};
	}

	function resetUi() {
		state = createState();
		resultsBody.textContent = '';
		unavailable.querySelector('ul').textContent = '';
		results.hidden = true;
		unavailable.hidden = true;
		notice.hidden = true;
		notice.className = 'notice inline';
		noFilterResults.hidden = true;
		updateProgress(0);
		updateCounters();
		setFilter('all');
	}

	function setRunning(running) {
		state.running = running;
		startButton.disabled = running;
		stopButton.disabled = !running;
		startButton.textContent = running ? data.i18n.runningButton : data.i18n.restartButton;
	}

	function updateProgress(value) {
		const safeValue = Math.max(0, Math.min(100, value));
		progressBar.style.width = safeValue + '%';
		percentText.textContent = Math.round(safeValue) + '%';
		progress.setAttribute('aria-valuenow', String(Math.round(safeValue)));
	}

	function updateCounters() {
		counters.scanned.textContent = state.scanned.toLocaleString();
		counters.total.textContent = '/ ' + state.total.toLocaleString();
		counters.healthy.textContent = state.healthy.toLocaleString();
		counters.error.textContent = state.error.toLocaleString();
		counters.warning.textContent = state.warning.toLocaleString();
		counters.info.textContent = state.info.toLocaleString();
		filterCounts.all.textContent = state.findings.toLocaleString();
		filterCounts.error.textContent = state.error.toLocaleString();
		filterCounts.warning.textContent = state.warning.toLocaleString();
		filterCounts.info.textContent = state.info.toLocaleString();
	}

	function showNotice(message, type) {
		notice.className = 'notice inline notice-' + type;
		notice.querySelector('p').textContent = message;
		notice.hidden = false;
	}

	function createCell(row, text, className) {
		const cell = document.createElement('td');
		cell.textContent = text;
		if (className) {
			cell.className = className;
		}
		row.appendChild(cell);
		return cell;
	}

	function appendFinding(item, finding) {
		const row = document.createElement('tr');
		row.dataset.severity = finding.severity;

		const attachmentCell = document.createElement('td');
		const attachmentName = item.name || data.i18n.unknownFile;
		const link = document.createElement(item.editUrl ? 'a' : 'span');
		link.textContent = attachmentName;
		if (item.editUrl) {
			link.href = item.editUrl;
		}
		attachmentCell.appendChild(link);

		const meta = document.createElement('small');
		const metaParts = ['#' + item.id, item.mime, item.dimensions, item.fileSize].filter(Boolean);
		meta.textContent = metaParts.join(' · ');
		attachmentCell.appendChild(meta);
		row.appendChild(attachmentCell);

		createCell(row, categoryLabels[finding.category] || finding.category, 'mlhc-category');
		const severityCell = createCell(row, severityLabels[finding.severity] || finding.severity, 'mlhc-severity');
		severityCell.dataset.severity = finding.severity;

		const findingCell = document.createElement('td');
		const label = document.createElement('strong');
		label.textContent = finding.label;
		const message = document.createElement('p');
		message.textContent = finding.message;
		findingCell.appendChild(label);
		findingCell.appendChild(message);
		row.appendChild(findingCell);

		resultsBody.appendChild(row);
		results.hidden = false;
	}

	function appendUnavailable(items) {
		if (!Array.isArray(items) || items.length === 0) {
			return;
		}

		const list = unavailable.querySelector('ul');
		items.forEach(function (item) {
			const listItem = document.createElement('li');
			listItem.textContent = item.reason;
			list.appendChild(listItem);
		});
		unavailable.hidden = false;
	}

	function setFilter(filter) {
		state.activeFilter = filter;
		filterButtons.forEach(function (button) {
			const active = button.dataset.filter === filter;
			button.classList.toggle('is-active', active);
			button.setAttribute('aria-pressed', active ? 'true' : 'false');
		});

		let visibleRows = 0;
		resultsBody.querySelectorAll('tr').forEach(function (row) {
			const visible = filter === 'all' || row.dataset.severity === filter;
			row.hidden = !visible;
			if (visible) {
				visibleRows += 1;
			}
		});
		noFilterResults.hidden = visibleRows !== 0 || state.findings === 0;
	}

	async function scanNextBatch() {
		if (state.stopRequested) {
			setRunning(false);
			statusText.textContent = data.i18n.stopped;
			showNotice(data.i18n.stopped, 'warning');
			return;
		}

		const body = new URLSearchParams({
			action: data.action,
			nonce: data.nonce,
			lastId: String(state.lastId),
			total: String(state.total)
		});

		try {
			const response = await fetch(data.ajaxUrl, {
				method: 'POST',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				credentials: 'same-origin',
				body: body.toString()
			});
			const payload = await response.json().catch(function () {
				throw new Error(data.i18n.error);
			});

			if (!response.ok || !payload.success) {
				throw new Error(payload.data && payload.data.message ? payload.data.message : data.i18n.error);
			}

			const responseData = payload.data;
			state.total = Number(responseData.total) || 0;
			state.lastId = Number(responseData.lastId) || state.lastId;
			state.scanned += Number(responseData.processed) || 0;
			state.healthy += Number(responseData.healthy) || 0;
			appendUnavailable(responseData.unavailable);

			(Array.isArray(responseData.items) ? responseData.items : []).forEach(function (item) {
				(Array.isArray(item.findings) ? item.findings : []).forEach(function (finding) {
					state.findings += 1;
					if (Object.prototype.hasOwnProperty.call(state, finding.severity)) {
						state[finding.severity] += 1;
					}
					appendFinding(item, finding);
				});
			});

			updateCounters();
			setFilter(state.activeFilter);
			updateProgress(state.total > 0 ? (state.scanned / state.total) * 100 : 100);

			if (responseData.done) {
				finishScan();
				return;
			}

			window.setTimeout(scanNextBatch, 20);
		} catch (error) {
			setRunning(false);
			statusText.textContent = data.i18n.error;
			showNotice(error.message || data.i18n.error, 'error');
		}
	}

	function finishScan() {
		setRunning(false);
		updateProgress(100);
		statusText.textContent = data.i18n.finished;

		if (state.findings === 0) {
			showNotice(data.i18n.healthy, 'success');
			return;
		}

		const message = state.findings === 1
			? data.i18n.findingsOne
			: data.i18n.findingsMany.replace('%s', state.findings.toLocaleString());
		showNotice(message, state.error > 0 ? 'error' : 'warning');
	}

	startButton.addEventListener('click', function () {
		if (state.scanned > 0 && !window.confirm(data.i18n.confirmRestart)) {
			return;
		}

		resetUi();
		panel.hidden = false;
		statusText.textContent = data.i18n.starting;
		setRunning(true);
		window.setTimeout(function () {
			statusText.textContent = data.i18n.running;
			scanNextBatch();
		}, 50);
	});

	stopButton.addEventListener('click', function () {
		state.stopRequested = true;
		stopButton.disabled = true;
	});

	filterButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			setFilter(button.dataset.filter);
		});
	});
}());
