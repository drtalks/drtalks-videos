/**
 * DrTalks player bridge — parent side.
 *
 * Talks to the /embed/videos/{slug} iframe over window.postMessage using a
 * small custom protocol (the embed side lives in the DrTalks frontend at
 * src/components/players/embed-message-bridge.tsx — keep the two in sync):
 *
 *   Parent → embed:
 *     { type: 'drtalks:hello' }                 announce; embed replies with chapters (if any)
 *     { type: 'drtalks:seek', time: number }    seek playhead (seconds) and play
 *   Embed → parent:
 *     { type: 'drtalks:ready' }
 *     { type: 'drtalks:chapters', chapters: [{ title, start, end }] }
 *     { type: 'drtalks:timeupdate', currentTime: number }
 *
 * If this protocol ever needs more than seek/chapters/timeupdate, consider
 * switching both sides to the Player.js spec
 * (https://github.com/embedly/player.js) rather than growing the custom one.
 *
 * Chapters arrive from the embed at runtime (the embed already fetches them
 * from Bunny), so nothing chapter-related is synced or stored in WordPress.
 * This script fills the [data-drtalks-chapters] container that the plugin
 * templates render hidden next to each player.
 */
(function () {
	'use strict';

	var EMBED_PATH = '/embed/videos/';

	/** iframe registrations: { iframe, origin, chaptersEl, rows: [{ el, start, end }] } */
	var players = [];

	function formatTime(seconds) {
		var h = Math.floor(seconds / 3600);
		var m = Math.floor((seconds % 3600) / 60);
		var s = Math.floor(seconds % 60);
		var mm = h > 0 ? String(m).padStart(2, '0') : String(m);
		var ss = String(s).padStart(2, '0');
		return h > 0 ? h + ':' + mm + ':' + ss : mm + ':' + ss;
	}

	function send(player, message) {
		if (player.iframe.contentWindow) {
			player.iframe.contentWindow.postMessage(message, player.origin);
		}
	}

	function renderChapters(player, chapters) {
		var container = player.chaptersEl;
		if (!container || !Array.isArray(chapters) || chapters.length === 0) {
			return;
		}

		var list = container.querySelector('.drtalks-chapters-list');
		if (!list) {
			return;
		}
		list.textContent = '';
		player.rows = [];

		chapters.forEach(function (chapter) {
			if (typeof chapter.title !== 'string' || typeof chapter.start !== 'number') {
				return;
			}
			var row = document.createElement('button');
			row.type = 'button';
			row.className = 'drtalks-chapter-row';

			var time = document.createElement('span');
			time.className = 'drtalks-chapter-time';
			time.textContent = formatTime(chapter.start);

			var title = document.createElement('span');
			title.className = 'drtalks-chapter-title';
			title.textContent = chapter.title;

			row.appendChild(time);
			row.appendChild(title);
			row.addEventListener('click', function () {
				send(player, { type: 'drtalks:seek', time: chapter.start });
			});

			list.appendChild(row);
			player.rows.push({
				el: row,
				start: chapter.start,
				end: typeof chapter.end === 'number' ? chapter.end : Infinity,
			});
		});

		if (player.rows.length > 0) {
			container.hidden = false;
			// The video layout hides the whole sidebar when there is no transcript;
			// un-hide it now that it has chapters to show.
			var sidebar = container.closest('.drtalks-yt-sidebar');
			if (sidebar) {
				sidebar.hidden = false;
			}
		}
	}

	function highlightActive(player, currentTime) {
		player.rows.forEach(function (row) {
			row.el.classList.toggle(
				'is-active',
				currentTime >= row.start && currentTime < row.end
			);
		});
	}

	function onMessage(event) {
		var msg = event.data;
		if (!msg || typeof msg !== 'object' || typeof msg.type !== 'string') {
			return;
		}

		// Only accept messages from a registered embed iframe, from its own origin.
		var player = null;
		for (var i = 0; i < players.length; i++) {
			if (players[i].iframe.contentWindow === event.source) {
				player = players[i];
				break;
			}
		}
		if (!player || event.origin !== player.origin) {
			return;
		}

		switch (msg.type) {
			case 'drtalks:ready':
				// Embed (re)announced itself — ask for chapters in case we missed them.
				send(player, { type: 'drtalks:hello' });
				break;
			case 'drtalks:chapters':
				renderChapters(player, msg.chapters);
				break;
			case 'drtalks:timeupdate':
				if (typeof msg.currentTime === 'number') {
					highlightActive(player, msg.currentTime);
				}
				break;
		}
	}

	function register(iframe) {
		var origin;
		try {
			origin = new URL(iframe.src).origin;
		} catch (e) {
			return;
		}

		// The chapters container lives inside the same plugin wrapper as the player.
		var wrapper = iframe.closest('.drtalks-single-video, .drtalks-video-embed');
		var chaptersEl = wrapper ? wrapper.querySelector('[data-drtalks-chapters]') : null;

		var player = { iframe: iframe, origin: origin, chaptersEl: chaptersEl, rows: [] };
		players.push(player);

		// Handshake: hello now (embed may already be up) and again on iframe load
		// (covers the usual case where the embed finishes loading after us).
		send(player, { type: 'drtalks:hello' });
		iframe.addEventListener('load', function () {
			send(player, { type: 'drtalks:hello' });
		});
	}

	function init() {
		var iframes = document.querySelectorAll('iframe[src*="' + EMBED_PATH + '"]');
		if (iframes.length === 0) {
			return;
		}
		iframes.forEach(register);
		window.addEventListener('message', onMessage);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
