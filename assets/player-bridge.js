/**
 * DrTalks player bridge + chapters/transcript panel behavior.
 *
 * Chapter and transcript-cue rows are rendered server-side from synced data
 * (see includes/functions.php drtalks_render_media_panel()). This script:
 *
 *  - connects rows to the embedded DrTalks player over window.postMessage
 *      Parent → embed: { type: 'drtalks:seek', time: number }      seek (seconds) and play
 *      Embed → parent: { type: 'drtalks:timeupdate', currentTime } throttled during playback
 *  - switches the Chapters/Transcript tabs
 *  - highlights the active chapter/cue from playback position and keeps the
 *    active transcript line in view (paused while the reader scrolls or searches)
 *  - powers the transcript search (match highlighting + prev/next navigation)
 *
 * (The embed side lives in the DrTalks frontend at
 * src/components/players/embed-message-bridge.tsx — keep the two in sync.
 * If the protocol ever needs more than seek + timeupdate, consider switching
 * both sides to the Player.js spec — https://github.com/embedly/player.js —
 * rather than growing the custom one.)
 */
(function () {
	'use strict';

	var EMBED_PATH = '/embed/videos/';
	var SCROLL_HOLD_MS = 4000;

	var players = [];

	function send(player, message) {
		if (player.iframe.contentWindow) {
			player.iframe.contentWindow.postMessage(message, player.origin);
		}
	}

	function escapeRegExp(text) {
		return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	}

	// ---- Rows (seek + active highlight) --------------------------------------

	function collectRows(wrapper, selector, player) {
		var rows = [];
		wrapper.querySelectorAll(selector).forEach(function (el) {
			var start = parseFloat(el.getAttribute('data-start'));
			var end = parseFloat(el.getAttribute('data-end'));
			if (isNaN(start)) {
				return;
			}
			el.addEventListener('click', function () {
				send(player, { type: 'drtalks:seek', time: start });
			});
			rows.push({ el: el, start: start, end: isNaN(end) || end <= start ? Infinity : end });
		});
		return rows;
	}

	function setActive(rows, currentTime) {
		var active = null;
		rows.forEach(function (row) {
			var isActive = currentTime >= row.start && currentTime < row.end;
			row.el.classList.toggle('is-active', isActive);
			if (isActive) {
				active = row.el;
			}
		});
		return active;
	}

	/**
	 * Scroll `row` into view inside `container` (a line's height below the top
	 * edge, like the drtalks.com panel), scrolling only the container.
	 * Plain scrollTop assignment — the container's CSS scroll-behavior supplies
	 * smoothness where the browser supports it. The suppress window keeps our
	 * own scroll from being mistaken for the reader scrolling.
	 */
	function scrollRowIntoView(player, container, row, force) {
		var containerRect = container.getBoundingClientRect();
		var rowRect = row.getBoundingClientRect();
		if (!force && rowRect.top >= containerRect.top && rowRect.bottom <= containerRect.bottom) {
			return;
		}
		player.suppressScrollUntil = Date.now() + 600;
		container.scrollTop = Math.max(0, rowRect.top - containerRect.top + container.scrollTop - 32);
	}

	function highlightActive(player, currentTime) {
		setActive(player.chapterRows, currentTime);

		var activeCue = setActive(player.cueRows, currentTime);
		if (
			!activeCue ||
			!player.cueContainer ||
			player.searchQuery !== '' ||
			Date.now() < player.userScrollHold
		) {
			return;
		}
		scrollRowIntoView(player, player.cueContainer, activeCue, false);
	}

	// ---- Tabs -----------------------------------------------------------------

	function setupTabs(panel) {
		var tabs = panel.querySelectorAll('.drtalks-panel-tab');
		if (tabs.length === 0) {
			return;
		}
		tabs.forEach(function (tab) {
			tab.addEventListener('click', function () {
				var name = tab.getAttribute('data-drtalks-tab');
				tabs.forEach(function (t) {
					t.classList.toggle('is-active', t === tab);
				});
				panel.querySelectorAll('[data-drtalks-section]').forEach(function (section) {
					section.hidden = section.getAttribute('data-drtalks-section') !== name;
				});
			});
		});
	}

	// ---- Transcript search ----------------------------------------------------

	function setupSearch(panel, player) {
		var input = panel.querySelector('.drtalks-search-input');
		var countEl = panel.querySelector('.drtalks-search-count');
		var prevBtn = panel.querySelector('.drtalks-search-prev');
		var nextBtn = panel.querySelector('.drtalks-search-next');
		if (!input || player.cueRows.length === 0) {
			return;
		}

		// Original cue text, captured once — match highlighting rebuilds each
		// row's text from this, so repeated searches never compound markup.
		var texts = player.cueRows.map(function (row) {
			var span = row.el.querySelector('.drtalks-cue-text');
			return { span: span, text: span ? span.textContent : '' };
		});

		var matches = [];
		var current = -1;

		function renderRowText(entry, query) {
			if (!entry.span) {
				return false;
			}
			entry.span.textContent = '';
			if (!query) {
				entry.span.textContent = entry.text;
				return false;
			}
			var pattern = new RegExp('(' + escapeRegExp(query) + ')', 'gi');
			var parts = entry.text.split(pattern);
			var hit = false;
			parts.forEach(function (part, i) {
				if (i % 2 === 1) {
					hit = true;
					var mark = document.createElement('mark');
					mark.textContent = part;
					entry.span.appendChild(mark);
				} else if (part !== '') {
					entry.span.appendChild(document.createTextNode(part));
				}
			});
			return hit;
		}

		function updateCount() {
			var hasQuery = player.searchQuery !== '';
			countEl.hidden = prevBtn.hidden = nextBtn.hidden = !hasQuery;
			if (hasQuery) {
				countEl.textContent = ( matches.length === 0 ? 0 : current + 1 ) + '/' + matches.length;
				prevBtn.disabled = nextBtn.disabled = matches.length === 0;
			}
		}

		function setCurrent(index) {
			player.cueRows.forEach(function (row) {
				row.el.classList.remove('is-search-match');
			});
			current = index;
			if (current >= 0 && matches[current] !== undefined) {
				var row = player.cueRows[matches[current]];
				row.el.classList.add('is-search-match');
				scrollRowIntoView(player, player.cueContainer, row.el, true);
			}
			updateCount();
		}

		function runSearch() {
			var query = input.value.trim();
			player.searchQuery = query;
			matches = [];
			texts.forEach(function (entry, index) {
				if (renderRowText(entry, query)) {
					matches.push(index);
				}
			});
			setCurrent(-1);

			// Search cleared — jump back to the currently-playing line.
			if (query === '') {
				player.userScrollHold = 0;
				var active = player.cueContainer.querySelector('.drtalks-cue-row.is-active');
				if (active) {
					scrollRowIntoView(player, player.cueContainer, active, true);
				}
			}
		}

		function step(direction) {
			if (matches.length === 0) {
				return;
			}
			var next = current === -1
				? ( direction === 1 ? 0 : matches.length - 1 )
				: ( current + direction + matches.length ) % matches.length;
			setCurrent(next);
		}

		input.addEventListener('input', runSearch);
		input.addEventListener('keydown', function (event) {
			if (event.key !== 'Enter') {
				return;
			}
			event.preventDefault();
			step(event.shiftKey ? -1 : 1);
		});
		prevBtn.addEventListener('click', function () { step(-1); });
		nextBtn.addEventListener('click', function () { step(1); });
	}

	// ---- Player messages ------------------------------------------------------

	function onMessage(event) {
		var msg = event.data;
		if (!msg || typeof msg !== 'object' || msg.type !== 'drtalks:timeupdate' || typeof msg.currentTime !== 'number') {
			return;
		}

		// Only accept messages from a registered embed iframe, from its own origin.
		for (var i = 0; i < players.length; i++) {
			var player = players[i];
			if (player.iframe.contentWindow === event.source && event.origin === player.origin) {
				highlightActive(player, msg.currentTime);
				return;
			}
		}
	}

	// ---- Setup ----------------------------------------------------------------

	function register(iframe) {
		var origin;
		try {
			origin = new URL(iframe.src).origin;
		} catch (e) {
			return;
		}

		// The panel lives inside the same plugin wrapper as the player.
		var wrapper = iframe.closest('.drtalks-single-video, .drtalks-video-embed');
		var panel = wrapper ? wrapper.querySelector('.drtalks-media-panel') : null;
		if (!panel) {
			return;
		}

		var player = {
			iframe: iframe,
			origin: origin,
			chapterRows: [],
			cueRows: [],
			cueContainer: panel.querySelector('.drtalks-transcript-synced'),
			userScrollHold: 0,
			suppressScrollUntil: 0,
			searchQuery: '',
		};
		player.chapterRows = collectRows(panel, '.drtalks-chapter-row', player);
		player.cueRows = collectRows(panel, '.drtalks-cue-row', player);

		if (player.cueContainer) {
			// Reader scrolling the transcript pauses follow-playback for a few
			// seconds. wheel/touchmove are direct user intent (they never fire for
			// programmatic scrolls); the scroll listener covers scrollbar dragging
			// and keyboard, with the suppress window keeping our own follow
			// scrolls from counting as the reader's.
			var holdFollow = function () {
				player.userScrollHold = Date.now() + SCROLL_HOLD_MS;
			};
			player.cueContainer.addEventListener('wheel', holdFollow, { passive: true });
			player.cueContainer.addEventListener('touchmove', holdFollow, { passive: true });
			player.cueContainer.addEventListener('scroll', function () {
				if (Date.now() >= player.suppressScrollUntil) {
					holdFollow();
				}
			});
		}

		setupTabs(panel);
		setupSearch(panel, player);

		if (player.chapterRows.length > 0 || player.cueRows.length > 0) {
			players.push(player);
		}
	}

	function init() {
		document.querySelectorAll('iframe[src*="' + EMBED_PATH + '"]').forEach(register);
		if (players.length > 0) {
			window.addEventListener('message', onMessage);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
