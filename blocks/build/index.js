/**
 * DrTalks Video block — editor UI (no-build vanilla JS).
 * Uses window.wp.* globals; no JSX or compilation required.
 */
/* global wp, drtalksAdmin */
( function () {
	var el                = wp.element.createElement;
	var useState          = wp.element.useState;
	var useRef            = wp.element.useRef;
	var registerBlock     = wp.blocks.registerBlockType;
	var useBlockProps     = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var TextControl       = wp.components.TextControl;
	var ToggleControl     = wp.components.ToggleControl;
	var PanelBody         = wp.components.PanelBody;
	var Spinner           = wp.components.Spinner;
	var Button            = wp.components.Button;

	function debounce( fn, delay ) {
		var timer;
		return function () {
			var args = arguments;
			var ctx  = this;
			clearTimeout( timer );
			timer = setTimeout( function () { fn.apply( ctx, args ); }, delay );
		};
	}

	function Edit( props ) {
		var attributes    = props.attributes;
		var setAttributes = props.setAttributes;

		var videoSlug       = attributes.videoSlug;
		var videoTitle      = attributes.videoTitle;
		var videoThumbnail  = attributes.videoThumbnail;
		var showTitle       = attributes.showTitle       !== false;
		var showDescription = attributes.showDescription !== false;
		var showTranscript  = attributes.showTranscript  !== false;
		var showAuthor      = attributes.showAuthor      !== false;

		var qs         = useState( '' );
		var query      = qs[0];
		var setQuery   = qs[1];

		var rs         = useState( [] );
		var results    = rs[0];
		var setResults = rs[1];

		var ls         = useState( false );
		var loading    = ls[0];
		var setLoading = ls[1];

		var abortRef   = useRef( null );
		var blockProps = useBlockProps();

		var doSearch = debounce( function ( q ) {
			if ( ! q || q.length < 2 ) {
				setResults( [] );
				return;
			}
			if ( abortRef.current ) { abortRef.current.abort(); }
			var controller = new AbortController();
			abortRef.current = controller;
			setLoading( true );

			var body = new URLSearchParams( {
				action:   'drtalks_search_videos',
				nonce:    drtalksAdmin.nonce,
				q:        q,
				per_page: 20,
			} );

			fetch( drtalksAdmin.ajaxUrl, {
				method:  'POST',
				body:    body,
				signal:  controller.signal,
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			} )
				.then( function ( res ) { return res.json(); } )
				.then( function ( json ) {
					setLoading( false );
					if ( json.success ) {
						setResults( json.data.videos || [] );
					}
				} )
				.catch( function ( e ) {
					if ( e.name === 'AbortError' ) { return; } // New search already in flight — keep spinner.
					setLoading( false );
					console.error( e );
				} );
		}, 300 );

		function selectVideo( video ) {
			setAttributes( {
				videoSlug:      video.slug,
				videoTitle:     video.title,
				videoThumbnail: video.thumbnail_url || '',
			} );
			setResults( [] );
			setQuery( '' );
		}

		// ── Sidebar: Display Options panel (always shown) ────────────────────
		var inspector = el( InspectorControls, null,
			el( PanelBody, { title: 'Display Options', initialOpen: true },
				el( ToggleControl, {
					label:    'Show Title',
					checked:  showTitle,
					onChange: function ( v ) { setAttributes( { showTitle: v } ); },
				} ),
				el( ToggleControl, {
					label:    'Show Description',
					checked:  showDescription,
					onChange: function ( v ) { setAttributes( { showDescription: v } ); },
				} ),
				el( ToggleControl, {
					label:    'Show Transcript',
					checked:  showTranscript,
					onChange: function ( v ) { setAttributes( { showTranscript: v } ); },
				} ),
				el( ToggleControl, {
					label:    'Show About Author',
					checked:  showAuthor,
					onChange: function ( v ) { setAttributes( { showAuthor: v } ); },
				} )
			)
		);

		// ── Preview state (video already selected) ───────────────────────────
		if ( videoSlug ) {
			return el(
				'div', blockProps,
				inspector,
				el( 'div', { className: 'drtalks-block-preview' },
					videoThumbnail
						? el( 'img', {
							src:   videoThumbnail,
							alt:   videoTitle,
							style: { maxWidth: '100%', display: 'block', marginBottom: '8px' },
						} )
						: null,
					el( 'p', { style: { margin: '0 0 8px' } },
						el( 'strong', null, videoTitle || videoSlug )
					),
					el( 'div', { style: { display: 'flex', gap: '6px', flexWrap: 'wrap', fontSize: '12px', color: '#757575', marginBottom: '8px' } },
						showTitle       ? el( 'span', { style: { background: '#f0f0f0', padding: '2px 6px', borderRadius: '3px' } }, 'Title' )       : null,
						showDescription ? el( 'span', { style: { background: '#f0f0f0', padding: '2px 6px', borderRadius: '3px' } }, 'Description' ) : null,
						showTranscript  ? el( 'span', { style: { background: '#f0f0f0', padding: '2px 6px', borderRadius: '3px' } }, 'Transcript' )  : null,
						showAuthor      ? el( 'span', { style: { background: '#f0f0f0', padding: '2px 6px', borderRadius: '3px' } }, 'About Author' ) : null
					),
					el( Button, {
						variant: 'secondary',
						isSmall: true,
						onClick: function () {
							setAttributes( { videoSlug: '', videoTitle: '', videoThumbnail: '' } );
						},
					}, 'Change video' )
				)
			);
		}

		// ── Search state (no video selected yet) ─────────────────────────────
		return el(
			'div', blockProps,
			inspector,
			el( 'div', { className: 'drtalks-block-search' },
				el( TextControl, {
					label:       'Search DrTalks Videos',
					value:       query,
					onChange:    function ( v ) { setQuery( v ); doSearch( v ); },
					placeholder: 'Type a title or expert name…',
					__nextHasNoMarginBottom: true,
				} ),
				loading
					? el( 'div', {
						style: {
							display:    'flex',
							alignItems: 'center',
							gap:        '8px',
							margin:     '8px 0 0',
							color:      '#757575',
							fontSize:   '13px',
						},
					},
						el( Spinner, null ),
						el( 'span', null, 'Searching…' )
					)
					: null,
				! loading && results.length > 0
					? el( 'ul', {
						className: 'drtalks-block-results',
						style: {
							margin:     '8px 0 0',
							padding:    0,
							listStyle:  'none',
							border:     '1px solid #ddd',
							borderRadius: '3px',
							maxHeight:  '260px',
							overflowY:  'auto',
						},
					},
						results.map( function ( v ) {
							return el( 'li', {
								key:       v.slug,
								className: 'drtalks-block-result',
								onClick:   function () { selectVideo( v ); },
								style: {
									cursor:      'pointer',
									display:     'flex',
									gap:         '10px',
									alignItems:  'center',
									padding:     '6px 10px',
									borderBottom: '1px solid #f0f0f0',
								},
							},
								v.thumbnail_url
									? el( 'img', {
										src:   v.thumbnail_url,
										alt:   '',
										style: { width: 56, height: 40, objectFit: 'cover', flexShrink: 0, borderRadius: '2px' },
									} )
									: null,
								el( 'span', { style: { fontSize: '13px' } }, v.title )
							);
						} )
					)
					: null
			)
		);
	}

	registerBlock( 'drtalks/video', {
		title:       'DrTalks Video',
		category:    'embed',
		description: 'Embed a DrTalks video with transcript.',
		icon:        'video-alt3',
		supports:    { html: false },
		attributes: {
			videoSlug:       { type: 'string',  default: '' },
			videoTitle:      { type: 'string',  default: '' },
			videoThumbnail:  { type: 'string',  default: '' },
			showTitle:       { type: 'boolean', default: true },
			showDescription: { type: 'boolean', default: true },
			showTranscript:  { type: 'boolean', default: true },
			showAuthor:      { type: 'boolean', default: true },
		},
		edit: Edit,
		save: function () { return null; },
	} );
} )();
