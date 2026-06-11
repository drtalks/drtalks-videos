/**
 * DrTalks Videos admin page — two-column UI for video archive and expert auto-sync.
 *
 * Depends on: drtalksAdmin (wp_localize_script)
 */
( function () {
	'use strict';

	const { ajaxUrl, nonce, siteUrl } = drtalksAdmin;

	// --- Confirm modal -------------------------------------------------------

	const confirmModal    = document.getElementById( 'drtalks-confirm-modal' );
	const confirmMessage  = document.getElementById( 'drtalks-confirm-modal-message' );
	const confirmOkBtn    = document.getElementById( 'drtalks-confirm-modal-confirm' );
	const confirmCancelBtn = document.getElementById( 'drtalks-confirm-modal-cancel' );

	function drtalksConfirm( message ) {
		return new Promise( resolve => {
			confirmMessage.textContent = message;
			confirmModal.style.display = 'flex';

			function cleanup( result ) {
				confirmModal.style.display = 'none';
				confirmOkBtn.removeEventListener( 'click', onOk );
				confirmCancelBtn.removeEventListener( 'click', onCancel );
				resolve( result );
			}
			function onOk()     { cleanup( true ); }
			function onCancel() { cleanup( false ); }

			confirmOkBtn.addEventListener( 'click', onOk );
			confirmCancelBtn.addEventListener( 'click', onCancel );
		} );
	}

	// --- State ---------------------------------------------------------------

	let state = {
		showWatchButton     : drtalksAdmin.showWatchButton !== false,
		archiveEnabled      : drtalksAdmin.archiveEnabled,
		archiveSlug         : drtalksAdmin.archiveSlug,
		syncSchedule        : drtalksAdmin.syncSchedule,
		videoTemplateStyle  : drtalksAdmin.videoTemplateStyle || 'theme',
		// Array of { slug, title, thumbnail_url, wp_post_url, drtalks_url }
		selectedVideos : drtalksAdmin.selectedVideos || [],
		// Array of { slug, name, photo_url, video_count }
		expertSlugs    : drtalksAdmin.expertSlugs || [],
		// Array of { slug, title, thumbnail_url, expert_slug, expert_name }
		expertVideos   : drtalksAdmin.expertVideos || [],
		// Array of { slug, title, thumbnail_url, expert_slug }
		hiddenVideos   : drtalksAdmin.hiddenVideos || [],
	};

	// --- DOM refs ------------------------------------------------------------

	const showWatchButtonCb = document.getElementById( 'drtalks-show-watch-button' );
	const archiveEnabledCb  = document.getElementById( 'drtalks-archive-enabled' );
	const archiveSettings   = document.getElementById( 'drtalks-archive-settings' );
	const archiveContent    = document.getElementById( 'drtalks-archive-content' );
	const archiveSlugInput  = document.getElementById( 'drtalks-archive-slug' );
	const archiveUrlPreview = document.getElementById( 'drtalks-archive-url-preview' );
	const videoSearchInput    = document.getElementById( 'drtalks-video-search' );
	const searchResultsEl     = document.getElementById( 'drtalks-video-search-results' );
	const selectedVideosEl    = document.getElementById( 'drtalks-selected-videos' );
	const expertSearchInput   = document.getElementById( 'drtalks-expert-search' );
	const expertSearchResults = document.getElementById( 'drtalks-expert-search-results' );
	const expertSearchWrapper = expertSearchInput ? expertSearchInput.closest( '.drtalks-card-list--searchable' ) : null;
	const addExpertError      = document.getElementById( 'drtalks-add-expert-error' );
	const expertCardsEl       = document.getElementById( 'drtalks-expert-cards' );
	const expertVideoSection  = document.getElementById( 'drtalks-expert-video-section' );
	const expertVideosEl      = document.getElementById( 'drtalks-expert-videos' );
	const hiddenVideosEl      = document.getElementById( 'drtalks-hidden-videos' );
	const syncScheduleEl      = document.getElementById( 'drtalks-sync-schedule' );
	const templateStyleSection = document.getElementById( 'drtalks-template-style-section' );
	const templateStyleCards   = templateStyleSection ? templateStyleSection.querySelectorAll( '.drtalks-style-card' ) : [];

	// --- Utilities -----------------------------------------------------------

	function debounce( fn, ms ) {
		let timer;
		return function ( ...args ) {
			clearTimeout( timer );
			timer = setTimeout( () => fn.apply( this, args ), ms );
		};
	}

	function ajax( action, data, signal ) {
		const body = new URLSearchParams( { action, nonce, ...data } );
		return fetch( ajaxUrl, { method: 'POST', body, signal } )
			.then( async r => {
				const text = await r.text();
				try {
					return JSON.parse( text );
				} catch ( e ) {
					console.error( '[drtalks] non-JSON response for', action, text.slice( 0, 500 ) );
					return { success: false, data: 'Server returned an invalid response. Check PHP error log.' };
				}
			} )
			.catch( err => {
				if ( err && err.name === 'AbortError' ) {
					return { success: false, data: null, aborted: true };
				}
				console.error( '[drtalks] AJAX failed for', action, err );
				return { success: false, data: 'Network error: ' + ( err && err.message ? err.message : 'request failed' ) };
			} );
	}

	function escHtml( str ) {
		const d = document.createElement( 'div' );
		d.textContent = String( str || '' );
		return d.innerHTML;
	}

	function setInnerHtml( el, html ) {
		el.innerHTML = html;
	}

	function showError( el, msg ) {
		el.textContent = msg;
		el.style.display = 'block';
	}

	function hideError( el ) {
		el.style.display = 'none';
	}

	function setListLoading( el ) {
		el.classList.add( 'drtalks-list-loading' );
		el.querySelectorAll( 'button' ).forEach( b => { b.disabled = true; } );
	}

	function clearListLoading( el ) {
		el.classList.remove( 'drtalks-list-loading' );
		el.querySelectorAll( 'button' ).forEach( b => { b.disabled = false; } );
	}

	// --- Archive toggle & slug -----------------------------------------------

	function updateArchiveUI() {
		archiveSettings.style.display  = state.archiveEnabled ? '' : 'none';
		archiveContent.style.display   = state.archiveEnabled ? '' : 'none';
		if ( templateStyleSection ) {
			templateStyleSection.style.display = state.archiveEnabled ? '' : 'none';
		}
		archiveUrlPreview.textContent = siteUrl + ( state.archiveSlug || 'videos' ) + '/';
	}

	showWatchButtonCb.addEventListener( 'change', function () {
		state.showWatchButton = this.checked;
		saveSettings();
	} );

	archiveEnabledCb.addEventListener( 'change', function () {
		state.archiveEnabled = this.checked;
		updateArchiveUI();
		saveSettings();
	} );

	// --- Template style cards ------------------------------------------------

	function updateTemplateStyleCards() {
		templateStyleCards.forEach( card => {
			const isSelected = card.dataset.style === state.videoTemplateStyle;
			card.classList.toggle( 'is-selected', isSelected );
			const radio = card.querySelector( 'input[type="radio"]' );
			if ( radio ) {
				radio.checked = isSelected;
			}
		} );
	}

	templateStyleCards.forEach( card => {
		card.addEventListener( 'click', function () {
			state.videoTemplateStyle = this.dataset.style;
			updateTemplateStyleCards();
			saveSettings();
		} );
	} );

	const saveSlug = debounce( saveSettings, 600 );
	archiveSlugInput.addEventListener( 'input', function () {
		state.archiveSlug = this.value.trim().replace( /[^a-z0-9-]/gi, '-' ).toLowerCase() || 'videos';
		archiveUrlPreview.textContent = siteUrl + state.archiveSlug + '/';
		saveSlug();
	} );

	syncScheduleEl.addEventListener( 'change', function () {
		state.syncSchedule = this.value;
		saveSettings();
	} );

	let saveIndicator = null;

	function saveSettings() {
		ajax( 'drtalks_save_settings', {
			show_watch_button   : state.showWatchButton ? 1 : 0,
			archive_enabled     : state.archiveEnabled ? 1 : 0,
			archive_slug        : state.archiveSlug,
			sync_schedule       : state.syncSchedule,
			video_template_style: state.videoTemplateStyle,
		} ).then( res => {
			showSaveIndicator( res.success );
			if ( res.success && res.data.pretty_permalinks === false ) {
				showPermalinkWarning();
			} else {
				hidePermalinkWarning();
			}
		} ).catch( () => showSaveIndicator( false ) );
	}

	function showSaveIndicator( success ) {
		if ( saveIndicator ) {
			clearTimeout( saveIndicator );
		}
		const el = document.getElementById( 'drtalks-save-status' );
		if ( ! el ) { return; }
		el.textContent  = success ? 'Settings saved.' : 'Save failed.';
		el.className    = 'drtalks-save-status ' + ( success ? 'saved' : 'error' );
		el.style.opacity = '1';
		saveIndicator = setTimeout( () => { el.style.opacity = '0'; }, 2500 );
	}

	function showPermalinkWarning() {
		const el = document.getElementById( 'drtalks-permalink-warning' );
		if ( el ) { el.style.display = ''; }
	}

	function hidePermalinkWarning() {
		const el = document.getElementById( 'drtalks-permalink-warning' );
		if ( el ) { el.style.display = 'none'; }
	}

	// --- Section 1: Video search & selection ---------------------------------

	let searchController = null;

	const doSearch = debounce( function ( q ) {
		if ( searchController ) {
			searchController.abort();
		}
		searchController = new AbortController();
		const signal = searchController.signal;

		if ( ! q ) {
			setInnerHtml( searchResultsEl, '<p class="drtalks-empty-state">Search above to find videos to add.</p>' );
			return;
		}

		setInnerHtml( searchResultsEl, '<p class="drtalks-loading">Searching…</p>' );

		ajax( 'drtalks_search_videos', { q, per_page: 20, page: 1 }, signal )
			.then( res => {
				if ( res.aborted ) return;
				if ( ! res.success || ! res.data.videos?.length ) {
					setInnerHtml( searchResultsEl, '<p class="drtalks-empty-state">No results found.</p>' );
					return;
				}
				const html = res.data.videos.map( v => renderSearchVideoCard( v ) ).join( '' );
				setInnerHtml( searchResultsEl, html );
				attachSearchCardListeners();
			} )
			.catch( () => {} );
	}, 400 );

	videoSearchInput.addEventListener( 'input', function () {
		doSearch( this.value.trim() );
	} );

	function isAlreadySelected( slug ) {
		return state.selectedVideos.some( v => v.slug === slug );
	}

	function renderSearchVideoCard( v ) {
		const slug     = escHtml( v.slug || '' );
		const title    = escHtml( v.title || slug );
		const thumb    = escHtml( v.thumbnail_url || '' );
		const selected = isAlreadySelected( v.slug );
		return `<div class="drtalks-video-card" data-slug="${slug}">
			${ thumb ? `<img src="${thumb}" class="drtalks-thumb" alt="">` : '<div class="drtalks-thumb-placeholder"></div>' }
			<div class="drtalks-card-body">
				<strong class="drtalks-card-title">${title}</strong>
			</div>
			<div class="drtalks-card-actions">
				<button class="button drtalks-add-video-btn" data-slug="${slug}" ${ selected ? 'disabled' : '' }>
					${ selected ? 'Already added' : 'Add' }
				</button>
			</div>
		</div>`;
	}

	function attachSearchCardListeners() {
		searchResultsEl.querySelectorAll( '.drtalks-add-video-btn' ).forEach( btn => {
			btn.addEventListener( 'click', function () {
				const slug = this.dataset.slug;
				const btn  = this;
				btn.textContent = 'Adding…';
				setListLoading( searchResultsEl );
				ajax( 'drtalks_add_video', { slug } )
					.then( res => {
						clearListLoading( searchResultsEl );
						if ( ! res.success ) {
							btn.textContent = 'Add';
							alert( res.data || 'Could not add video.' );
							return;
						}
						btn.textContent = 'Already added';
						btn.disabled = true;
						state.selectedVideos.push( res.data );
						renderSelectedVideos();
					} )
					.catch( () => {
						clearListLoading( searchResultsEl );
						btn.textContent = 'Add';
					} );
			} );
		} );
	}

	function renderSelectedVideos() {
		const expertSlugSet = new Set( state.expertSlugs.map( e => e.slug ) );
		const visible = state.selectedVideos.filter( v => ! expertSlugSet.has( v.expert_slug ) );

		updateSectionCount( 'drtalks-selected-count', visible.length );

		if ( ! visible.length ) {
			setInnerHtml( selectedVideosEl, '<p class="drtalks-empty-state">No videos added yet. Search on the left to get started.</p>' );
			return;
		}
		const html = visible.map( v => renderSelectedVideoCard( v ) ).join( '' );
		setInnerHtml( selectedVideosEl, html );
		attachSelectedCardListeners();
	}

	function updateSectionCount( elementId, count ) {
		const el = document.getElementById( elementId );
		if ( ! el ) { return; }
		el.textContent = count > 0 ? '(' + count + ')' : '';
	}

	function renderVideoCard( v, actionBtn ) {
		const slug  = escHtml( v.slug || '' );
		const title = escHtml( v.title || slug );
		const thumb = escHtml( v.thumbnail_url || '' );
		const name  = escHtml( v.expert_name || '' );
		const wpUrl = escHtml( v.wp_post_url || '' );
		const dtUrl = escHtml( v.drtalks_url || '' );
		return `<div class="drtalks-video-card" data-slug="${slug}">
			${ thumb ? `<img src="${thumb}" class="drtalks-thumb" alt="">` : '<div class="drtalks-thumb-placeholder"></div>' }
			<div class="drtalks-card-body">
				<strong class="drtalks-card-title">${title}</strong>
				${ name ? `<span class="drtalks-expert-name">${name}</span>` : '' }
				<div class="drtalks-card-links">
					${ wpUrl ? `<a href="${wpUrl}" target="_blank" rel="noopener">View on site ↗</a>` : '' }
					${ dtUrl ? `<a href="${dtUrl}" target="_blank" rel="noopener">View on DrTalks ↗</a>` : '' }
				</div>
			</div>
			<div class="drtalks-card-actions">
				${actionBtn}
			</div>
		</div>`;
	}

	function renderSelectedVideoCard( v ) {
		const slug = escHtml( v.slug || '' );
		return renderVideoCard( v, `<button class="button drtalks-remove-video-btn" data-slug="${slug}">Remove</button>` );
	}

	function attachSelectedCardListeners() {
		selectedVideosEl.querySelectorAll( '.drtalks-remove-video-btn' ).forEach( btn => {
			btn.addEventListener( 'click', function () {
				const slug = this.dataset.slug;
				this.disabled = true;
				ajax( 'drtalks_remove_video', { slug } )
					.then( res => {
						if ( ! res.success ) {
							this.disabled = false;
							return;
						}
						state.selectedVideos = state.selectedVideos.filter( v => v.slug !== slug );
						renderSelectedVideos();
						// Re-enable "Add" button in search results if visible.
						const addBtn = searchResultsEl.querySelector( `[data-slug="${slug}"].drtalks-add-video-btn` );
						if ( addBtn ) {
							addBtn.disabled = false;
							addBtn.textContent = 'Add';
						}
					} );
			} );
		} );
	}

	// --- Section 2: Expert cards ---------------------------------------------

	function updateExpertSearchVisibility() {
		const hasExperts  = state.expertSlugs.length > 0;
		const isSearching = expertSearchInput.value.trim().length > 0;

		if ( hasExperts && ! isSearching ) {
			expertSearchResults.style.display = 'none';
		} else {
			expertSearchResults.style.display = '';
			if ( ! hasExperts && ! isSearching ) {
				setInnerHtml( expertSearchResults, '<p class="drtalks-empty-state">Search above to find and add an expert.</p>' );
			}
		}
	}

	function renderExpertCards() {
		updateExpertSearchVisibility();
		if ( ! state.expertSlugs.length ) {
			setInnerHtml( expertCardsEl, '' );
			expertVideoSection.style.display = 'none';
			return;
		}
		const html = state.expertSlugs.map( e => renderExpertCard( e ) ).join( '' );
		setInnerHtml( expertCardsEl, html );
		expertVideoSection.style.display = '';
		renderExpertVideos();
		renderHiddenVideos();

		// If no CPT posts exist yet (cron hasn't run), load each expert's videos from the API.
		state.expertSlugs.forEach( e => {
			const hasVideos = state.expertVideos.some( v => v.expert_slug === e.slug );
			if ( ! hasVideos ) {
				mergeExpertVideosFromApi( e.slug, e.name );
			}
		} );
	}

	function renderExpertCard( e ) {
		const slug   = escHtml( e.slug || '' );
		const name   = escHtml( e.name || slug );
		const photo  = escHtml( e.photo_url || '' );
		const total  = Number( e.video_count ?? 0 );
		const synced = Number( e.synced_count ?? 0 );
		const countText = ( synced < total )
			? `${synced} of ${total} videos synced`
			: `${total} video${ total === 1 ? '' : 's' }`;
		return `<div class="drtalks-expert-card" data-slug="${slug}">
			${ photo ? `<img src="${photo}" class="drtalks-expert-photo" alt="${name}">` : '<div class="drtalks-expert-photo-placeholder"></div>' }
			<div class="drtalks-card-body">
				<strong class="drtalks-card-title">${name}</strong>
				<span class="drtalks-video-count">${countText}</span>
				<span class="drtalks-sync-indicator" id="drtalks-sync-${slug}"></span>
			</div>
			<div class="drtalks-card-actions">
				<button class="button drtalks-fetch-missing-btn" data-slug="${slug}" title="Adds any of this expert's DrTalks videos that aren't on your site yet.">Fetch Missing Videos</button>
				<button class="button drtalks-sync-now-btn" data-slug="${slug}" title="Re-downloads the newest details — titles, descriptions, hosts, guests, transcripts — for videos already added.">Update to Latest</button>
				<button class="button drtalks-remove-expert-btn" data-slug="${slug}">Remove</button>
			</div>
		</div>`;
	}

	// Delegated listener — attached once to the container, survives innerHTML replacements.
	expertCardsEl.addEventListener( 'click', function ( e ) {
		const removeBtn = e.target.closest( '.drtalks-remove-expert-btn' );
		if ( removeBtn && ! removeBtn.disabled ) {
			const slug = removeBtn.dataset.slug;
			drtalksConfirm( 'Remove this expert and all their synced videos from your site?' ).then( confirmed => {
				if ( ! confirmed ) return;
				removeBtn.disabled = true;
				removeBtn.textContent = 'Removing…';
				ajax( 'drtalks_remove_expert', { expert_slug: slug } ).then( res => {
					console.log( '[DrTalks] remove_expert AJAX response:', res );
					if ( ! res.success ) {
						removeBtn.disabled = false;
						removeBtn.textContent = 'Remove';
						drtalksConfirm( 'Error: ' + ( res.data || 'unknown error' ) );
						return;
					}
					state.expertSlugs  = state.expertSlugs.filter( ex => ex.slug !== slug );
					state.expertVideos = state.expertVideos.filter( v => v.expert_slug !== slug );
					renderExpertCards();
					renderSelectedVideos();
				} ).catch( err => {
					console.error( '[DrTalks] remove_expert AJAX error:', err );
					removeBtn.disabled = false;
					removeBtn.textContent = 'Remove';
				} );
			} );
			return;
		}

		// "Fetch Missing Videos" — queues a background batch (insert-only) and polls progress.
		const fetchBtn = e.target.closest( '.drtalks-fetch-missing-btn' );
		if ( fetchBtn && ! fetchBtn.disabled ) {
			const slug = fetchBtn.dataset.slug;
			fetchBtn.disabled = true;
			fetchBtn.textContent = 'Starting…';
			ajax( 'drtalks_fetch_missing_videos', { expert_slug: slug } ).then( res => {
				fetchBtn.disabled = false;
				fetchBtn.textContent = 'Fetch Missing Videos';
				if ( ! res.success ) {
					alert( 'Could not start fetch: ' + ( ( res.data && res.data.error ) || res.data || 'unknown error' ) );
					return;
				}
				// Runs in the background via Action Scheduler — poll for progress.
				pollSyncStatus( slug );
				clearTimeout( globalSyncTimer );
				globalSyncTimer = setTimeout( loadGlobalSyncStatus, 2000 );
			} );
			return;
		}

		const syncBtn = e.target.closest( '.drtalks-sync-now-btn' );
		if ( syncBtn && ! syncBtn.disabled ) {
			const slug = syncBtn.dataset.slug;
			syncBtn.disabled = true;
			syncBtn.textContent = 'Starting…';
			ajax( 'drtalks_sync_expert_now', { expert_slug: slug } ).then( res => {
				syncBtn.disabled = false;
				syncBtn.textContent = 'Update to Latest';
				if ( ! res.success ) {
					alert( 'Update failed: ' + ( ( res.data && res.data.error ) || res.data || 'unknown error' ) );
					return;
				}
				// Runs in the background via Action Scheduler — poll for progress.
				pollSyncStatus( slug );
				clearTimeout( globalSyncTimer );
				globalSyncTimer = setTimeout( loadGlobalSyncStatus, 2000 );
			} );
		}
	} );

	let expertSearchController = null;

	const doExpertSearch = debounce( function ( q ) {
		if ( expertSearchController ) {
			expertSearchController.abort();
		}
		expertSearchController = new AbortController();
		const signal = expertSearchController.signal;

		hideError( addExpertError );
		if ( ! q ) {
			setInnerHtml( expertSearchResults, '' );
			return;
		}
		setInnerHtml( expertSearchResults, '<p class="drtalks-loading">Searching…</p>' );
		ajax( 'drtalks_search_experts', { q, per_page: 10 }, signal )
			.then( res => {
				if ( res.aborted ) return;
				if ( ! res.success || ! res.data.experts?.length ) {
					setInnerHtml( expertSearchResults, '<p class="drtalks-empty-state">No experts found.</p>' );
					return;
				}
				const html = res.data.experts.map( e => renderExpertSearchCard( e ) ).join( '' );
				setInnerHtml( expertSearchResults, html );
				attachExpertSearchListeners();
			} )
			.catch( () => {} );
	}, 400 );

	expertSearchInput.addEventListener( 'input', function () {
		updateExpertSearchVisibility();
		doExpertSearch( this.value.trim() );
	} );

	function isAlreadyAddedExpert( slug ) {
		return state.expertSlugs.some( e => e.slug === slug );
	}

	function renderExpertSearchCard( e ) {
		const slug  = escHtml( e.slug || '' );
		const name  = escHtml( e.name || slug );
		const photo = escHtml( e.photo_url || '' );
		const title = escHtml( e.professional_title || '' );
		const count = ( e.video_count === null || e.video_count === undefined ) ? null : Number( e.video_count );
		const added = isAlreadyAddedExpert( e.slug );
		return `<div class="drtalks-video-card" data-slug="${slug}">
			${ photo ? `<img src="${photo}" class="drtalks-thumb" alt="">` : '<div class="drtalks-thumb-placeholder"></div>' }
			<div class="drtalks-card-body">
				<strong class="drtalks-card-title">${name}</strong>
				${ title ? `<span class="drtalks-card-expert">${title}</span>` : '' }
				${ count !== null ? `<span class="drtalks-video-count">${count} video${ count === 1 ? '' : 's' }</span>` : '' }
			</div>
			<div class="drtalks-card-actions">
				<button class="button drtalks-add-expert-btn"
					data-slug="${slug}" data-name="${name}" data-photo="${photo}"
					${ added ? 'disabled' : '' }>
					${ added ? 'Already added' : 'Add' }
				</button>
			</div>
		</div>`;
	}

	function attachExpertSearchListeners() {
		expertSearchResults.querySelectorAll( '.drtalks-add-expert-btn' ).forEach( btn => {
			btn.addEventListener( 'click', function () {
				const slug  = this.dataset.slug;
				const name  = this.dataset.name;
				const photo = this.dataset.photo;
				const self  = this;
				hideError( addExpertError );
				self.textContent = 'Adding…';
				setListLoading( expertSearchResults );
				ajax( 'drtalks_add_expert', { expert_slug: slug, expert_name: name, expert_photo: photo } )
					.then( res => {
						clearListLoading( expertSearchResults );
						if ( ! res.success ) {
							self.disabled = false;
							self.textContent = 'Add';
							showError( addExpertError, res.data || 'Could not add expert.' );
							return;
						}
						self.textContent = 'Already added';
						self.disabled = true;
						expertSearchInput.value = '';
						setInnerHtml( expertSearchResults, '' );
						state.expertSlugs.push( res.data );
						renderExpertCards();
						renderSelectedVideos(); // hide this expert's videos from section 1

						// Initial batch already synced server-side — load WP posts.
						replaceExpertVideos( slug );

						if ( res.data.sync_status === 'partial' || res.data.sync_status === 'pending' ) {
							// Cron is finishing the rest in the background — poll.
							pollSyncStatus( slug );
						}
					} )
					.catch( () => {
						clearListLoading( expertSearchResults );
						self.disabled = false;
						self.textContent = 'Add';
					} );
			} );
		} );
	}

	// --- Section 2: Poll sync status -----------------------------------------

	const syncPollers = {};

	function pollSyncStatus( expertSlug ) {
		if ( syncPollers[ expertSlug ] ) {
			return;
		}
		const indicator = document.getElementById( 'drtalks-sync-' + expertSlug );
		if ( indicator ) {
			indicator.textContent = ' Syncing…';
			indicator.className = 'drtalks-sync-indicator syncing';
		}

		let pollCount = 0;
		const maxPolls = 120; // stop after 10 minutes (120 × 5s) — large experts sync in many batches

		syncPollers[ expertSlug ] = setInterval( function () {
			pollCount++;
			if ( pollCount > maxPolls ) {
				clearInterval( syncPollers[ expertSlug ] );
				delete syncPollers[ expertSlug ];
				if ( indicator ) {
					indicator.textContent = ' Sync timed out';
					indicator.className = 'drtalks-sync-indicator error';
				}
				return;
			}

			ajax( 'drtalks_get_sync_status', { expert_slug: expertSlug } )
				.then( res => {
					if ( ! res.success ) {
						return;
					}
					const s = res.data.status;
					if ( s === 'done' || s === 'error' ) {
						clearInterval( syncPollers[ expertSlug ] );
						delete syncPollers[ expertSlug ];
						if ( indicator ) {
							indicator.textContent = s === 'done' ? ' Synced ✓' : ' Sync error';
							indicator.className = 'drtalks-sync-indicator ' + s;
						}
						if ( s === 'done' ) {
							// Update the synced count on the expert card.
							const ex = state.expertSlugs.find( e => e.slug === expertSlug );
							if ( ex && typeof res.data.synced_count !== 'undefined' ) {
								ex.synced_count = res.data.synced_count;
								renderExpertCards();
							}
							replaceExpertVideos( expertSlug );
						}
					}
				} );
		}, 5000 );
	}

	function buildVideoEntry( v, expertSlug, expertName ) {
		return {
			slug          : v.slug,
			title         : v.title,
			thumbnail_url : v.thumbnail_url,
			expert_slug   : expertSlug,
			expert_name   : expertName || v.expert_name || '',
			wp_post_url   : v.wp_post_url || '',
			drtalks_url   : v.drtalks_url || ( 'https://drtalks.com/videos/' + encodeURIComponent( v.slug || '' ) ),
		};
	}

	// Adds videos not already in state (used while waiting for sync).
	function mergeExpertVideosFromApi( expertSlug, expertName ) {
		ajax( 'drtalks_load_expert_videos', { expert_slug: expertSlug, per_page: 100, page: 1 } )
			.then( vRes => {
				if ( ! vRes.success || ! vRes.data.videos?.length ) {
					return;
				}
				const hiddenSlugs = new Set( state.hiddenVideos.map( h => h.slug ) );
				const existing    = new Set( state.expertVideos.map( v => v.slug ) );
				vRes.data.videos.forEach( v => {
					if ( ! existing.has( v.slug ) && ! hiddenSlugs.has( v.slug ) ) {
						state.expertVideos.push( buildVideoEntry( v, expertSlug, expertName ) );
					}
				} );
				renderExpertVideos();
			} );
	}

	// Replaces all videos for an expert with fresh data (used after sync completes).
	function replaceExpertVideos( expertSlug ) {
		ajax( 'drtalks_load_expert_videos', { expert_slug: expertSlug, per_page: 100, page: 1 } )
			.then( vRes => {
				if ( ! vRes.success ) {
					return;
				}
				const hiddenSlugs = new Set( state.hiddenVideos.map( h => h.slug ) );
				state.expertVideos = state.expertVideos.filter( v => v.expert_slug !== expertSlug );
				( vRes.data.videos || [] ).forEach( v => {
					if ( ! hiddenSlugs.has( v.slug ) ) {
						state.expertVideos.push( buildVideoEntry( v, expertSlug, v.expert_name ) );
					}
				} );
				renderExpertVideos();
			} );
	}

	// --- Section 2: Expert video list ----------------------------------------

	function renderExpertVideos() {
		const hiddenSlugs = new Set( state.hiddenVideos.map( h => h.slug ) );
		const visible     = state.expertVideos.filter( v => ! hiddenSlugs.has( v.slug ) );

		updateSectionCount( 'drtalks-expert-videos-count', visible.length );

		if ( ! visible.length ) {
			setInnerHtml( expertVideosEl, '<p class="drtalks-empty-state">No videos yet. Sync may still be in progress.</p>' );
			return;
		}
		const html = visible.map( v => renderExpertVideoCard( v ) ).join( '' );
		setInnerHtml( expertVideosEl, html );
		attachExpertVideoListeners();
	}

	function renderExpertVideoCard( v ) {
		const slug = escHtml( v.slug || '' );
		return renderVideoCard( v, `<button class="button drtalks-hide-video-btn" data-slug="${slug}">Hide</button>` );
	}

	function attachExpertVideoListeners() {
		expertVideosEl.querySelectorAll( '.drtalks-hide-video-btn' ).forEach( btn => {
			btn.addEventListener( 'click', function () {
				const slug = this.dataset.slug;
				this.disabled = true;
				ajax( 'drtalks_hide_video', { slug } )
					.then( res => {
						if ( ! res.success ) {
							this.disabled = false;
							return;
						}
						state.hiddenVideos.push( res.data );
						renderExpertVideos();
						renderHiddenVideos();
					} );
			} );
		} );
	}

	// --- Section 2: Hidden videos --------------------------------------------

	function renderHiddenVideos() {
		if ( ! state.hiddenVideos.length ) {
			setInnerHtml( hiddenVideosEl, '<p class="drtalks-empty-state">No hidden videos.</p>' );
			return;
		}
		const html = state.hiddenVideos.map( v => renderHiddenVideoCard( v ) ).join( '' );
		setInnerHtml( hiddenVideosEl, html );
		attachHiddenVideoListeners();
	}

	function renderHiddenVideoCard( v ) {
		const slug  = escHtml( v.slug || '' );
		const title = escHtml( v.title || slug );
		const thumb = escHtml( v.thumbnail_url || '' );
		return `<div class="drtalks-video-card drtalks-hidden-card" data-slug="${slug}">
			${ thumb ? `<img src="${thumb}" class="drtalks-thumb" alt="">` : '<div class="drtalks-thumb-placeholder"></div>' }
			<div class="drtalks-card-body">
				<strong class="drtalks-card-title">${title}</strong>
			</div>
			<div class="drtalks-card-actions">
				<button class="button drtalks-unhide-video-btn" data-slug="${slug}">Unhide</button>
			</div>
		</div>`;
	}

	function attachHiddenVideoListeners() {
		hiddenVideosEl.querySelectorAll( '.drtalks-unhide-video-btn' ).forEach( btn => {
			btn.addEventListener( 'click', function () {
				const slug = this.dataset.slug;
				this.disabled = true;
				this.textContent = 'Restoring…';
				ajax( 'drtalks_unhide_video', { slug } )
					.then( res => {
						if ( ! res.success ) {
							this.disabled = false;
							this.textContent = 'Unhide';
							alert( res.data || 'Could not restore video.' );
							return;
						}
						// Move from hidden to visible.
						state.hiddenVideos = state.hiddenVideos.filter( h => h.slug !== slug );
						state.expertVideos.push( {
							slug          : res.data.slug,
							title         : res.data.title,
							thumbnail_url : res.data.thumbnail_url,
							expert_slug   : res.data.expert_slug || '',
							expert_name   : res.data.expert_name || '',
							wp_post_url   : res.data.wp_post_url || '',
							drtalks_url   : res.data.drtalks_url || ( 'https://drtalks.com/videos/' + encodeURIComponent( res.data.slug || '' ) ),
						} );
						renderExpertVideos();
						renderHiddenVideos();
					} );
			} );
		} );
	}

	// --- Global sync status bar -----------------------------------------------

	const globalSyncStatusEl = document.getElementById( 'drtalks-global-sync-status' );
	const globalSyncDotEl    = globalSyncStatusEl && globalSyncStatusEl.querySelector( '.drtalks-sync-status-dot' );
	const globalSyncTextEl   = globalSyncStatusEl && globalSyncStatusEl.querySelector( '.drtalks-sync-status-text' );
	let   globalSyncTimer    = null;

	function formatSyncTime( unixTs ) {
		const d   = new Date( unixTs * 1000 );
		const now = new Date();
		const isSameDay = d.toDateString() === now.toDateString();

		const tomorrow = new Date( now );
		tomorrow.setDate( tomorrow.getDate() + 1 );
		const isTomorrow = d.toDateString() === tomorrow.toDateString();

		const time = d.toLocaleTimeString( [], { hour: 'numeric', minute: '2-digit' } );
		if ( isSameDay ) return time;
		if ( isTomorrow ) return 'tomorrow at ' + time;
		return d.toLocaleDateString( [], { month: 'short', day: 'numeric' } ) + ' at ' + time;
	}

	function renderGlobalSyncStatus( data ) {
		if ( ! globalSyncStatusEl ) return;

		const lastPart = data.last
			? ' &mdash; last ran at ' + formatSyncTime( data.last )
			: '';

		globalSyncStatusEl.className = 'drtalks-global-sync-status';

		let text = '';
		if ( data.state === 'running' ) {
			globalSyncStatusEl.classList.add( 'is-running' );
			text = 'Sync in progress, started at ' + formatSyncTime( data.since ) + lastPart;
		} else if ( data.state === 'scheduled' ) {
			globalSyncStatusEl.classList.add( 'is-scheduled' );
			text = 'Next sync at ' + formatSyncTime( data.next ) + lastPart;
		} else if ( data.state === 'manual' ) {
			globalSyncStatusEl.classList.add( 'is-manual' );
			text = 'Manual sync only' + ( data.last ? lastPart : '' );
		} else {
			globalSyncStatusEl.classList.add( 'is-idle' );
			text = data.last ? 'Idle' + lastPart : 'No sync scheduled yet.';
		}

		globalSyncTextEl.innerHTML = text;
	}

	function loadGlobalSyncStatus() {
		ajax( 'drtalks_global_sync_status', {} ).then( res => {
			if ( ! res || ! res.success ) return;
			renderGlobalSyncStatus( res.data );

			// If running, poll every 8s; otherwise refresh every 60s.
			clearTimeout( globalSyncTimer );
			const interval = res.data.state === 'running' ? 8000 : 60000;
			globalSyncTimer = setTimeout( loadGlobalSyncStatus, interval );
		} ).catch( () => {
			clearTimeout( globalSyncTimer );
			globalSyncTimer = setTimeout( loadGlobalSyncStatus, 30000 );
		} );
	}


	loadGlobalSyncStatus();

	// --- Initial render ------------------------------------------------------

	updateArchiveUI();
	updateTemplateStyleCards();
	renderSelectedVideos();
	renderExpertCards(); // calls updateExpertSearchVisibility internally

	// Start any in-progress syncs polling.
	state.expertSlugs.forEach( e => {
		if ( e.sync_status === 'pending' ) {
			pollSyncStatus( e.slug );
		}
	} );

} )();
