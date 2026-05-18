/**
 * DrTalks Videos — archive page live search.
 * Fires an AJAX call as the user types; renders server-rendered HTML cards.
 * Uses AbortController so only the latest request's results are shown.
 */
/* global drtalksArchive */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		const input       = document.getElementById( 'drtalks-archive-search' );
		const clearBtn    = document.getElementById( 'drtalks-search-clear' );
		const loopWrap    = document.getElementById( 'drtalks-loop-wrap' );
		const searchWrap  = document.getElementById( 'drtalks-search-results' );
		const searchGrid  = document.getElementById( 'drtalks-search-grid' );
		const searchEmpty = document.getElementById( 'drtalks-search-empty' );
		const loading     = document.getElementById( 'drtalks-search-loading' );

		if ( ! input ) return;

		// Populate loading indicator content once (avoids flash of server-rendered text).
		loading.innerHTML = '<span class="drtalks-spinner" aria-hidden="true"></span><span>Searching\u2026</span>';

		const ajaxUrl = drtalksArchive.ajaxUrl;
		const nonce   = drtalksArchive.nonce;

		let controller  = null;
		let debounceTimer = null;

		function showLoop() {
			loopWrap.hidden   = false;
			searchWrap.hidden = true;
			loading.hidden    = true;
		}

		function showLoading() {
			loopWrap.hidden   = true;
			searchWrap.hidden = true;
			loading.hidden    = false;
		}

		function showResults( cards ) {
			loading.hidden   = true;
			loopWrap.hidden  = true;
			searchWrap.hidden = false;

			if ( ! cards || cards.length === 0 ) {
				searchGrid.innerHTML    = '';
				searchEmpty.hidden      = false;
			} else {
				searchEmpty.hidden      = true;
				searchGrid.innerHTML    = cards.join( '' );
			}
		}

		function doSearch( q ) {
			if ( ! q.trim() ) {
				showLoop();
				clearBtn.hidden = true;
				return;
			}

			clearBtn.hidden = false;
			showLoading();

			if ( controller ) {
				controller.abort();
			}
			controller = new AbortController();

			const body = new URLSearchParams( {
				action : 'drtalks_archive_search',
				nonce  : nonce,
				q      : q,
			} );

			fetch( ajaxUrl, { method: 'POST', body: body, signal: controller.signal } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( res && res.success ) {
						showResults( res.data.cards );
					} else {
						showLoop();
					}
				} )
				.catch( function ( err ) {
					if ( err && err.name !== 'AbortError' ) {
						showLoop();
					}
				} );
		}

		function debounce( fn, ms ) {
			return function () {
				var args = arguments;
				clearTimeout( debounceTimer );
				debounceTimer = setTimeout( function () { fn.apply( this, args ); }, ms );
			};
		}

		var debouncedSearch = debounce( function ( q ) { doSearch( q ); }, 400 );

		input.addEventListener( 'input', function () {
			debouncedSearch( this.value );
		} );

		clearBtn.addEventListener( 'click', function () {
			input.value     = '';
			this.hidden     = true;
			if ( controller ) {
				controller.abort();
			}
			showLoop();
			input.focus();
		} );

		// Trigger search if input has a pre-filled value on load.
		if ( input.value.trim() ) {
			clearBtn.hidden = false;
			doSearch( input.value );
		}
	} );
} )();
