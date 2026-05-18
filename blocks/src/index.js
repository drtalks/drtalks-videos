/**
 * DrTalks Video block — editor UI
 *
 * Server-side rendered; only the edit() function runs in the block editor.
 */
/* global drtalksAdmin */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { TextControl, Spinner } from '@wordpress/components';
import { useState, useCallback, useRef } from '@wordpress/element';
import metadata from './block.json';

function debounce( fn, delay ) {
	let timer;
	return ( ...args ) => {
		clearTimeout( timer );
		timer = setTimeout( () => fn( ...args ), delay );
	};
}

function Edit( { attributes, setAttributes } ) {
	const { videoSlug, videoTitle, videoThumbnail } = attributes;
	const [ query, setQuery ]     = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const abortRef = useRef( null );

	const blockProps = useBlockProps();

	const handleSearch = useCallback(
		debounce( async ( q ) => {
			if ( abortRef.current ) abortRef.current.abort();
			const controller = new AbortController();
			abortRef.current = controller;

			setLoading( true );

			try {
				const body = new URLSearchParams( {
					action:   'drtalks_search_videos',
					nonce:    drtalksAdmin.nonce,
					q,
					per_page: 20,
				} );

				const res = await fetch( drtalksAdmin.ajaxUrl, {
					method: 'POST',
					body,
					signal: controller.signal,
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				} );
				const json = await res.json();
				if ( json.success ) {
					setResults( json.data.videos || [] );
				}
			} catch ( e ) {
				if ( e.name !== 'AbortError' ) console.error( e );
			} finally {
				setLoading( false );
			}
		}, 300 ),
		[]
	);

	function selectVideo( video ) {
		setAttributes( {
			videoSlug:      video.slug,
			videoTitle:     video.title,
			videoThumbnail: video.thumbnail_url || '',
		} );
		setResults( [] );
		setQuery( video.title );
	}

	return (
		<div { ...blockProps }>
			{ videoSlug ? (
				<div className="drtalks-block-preview">
					{ videoThumbnail && (
						<img
							src={ videoThumbnail }
							alt={ videoTitle }
							style={ { maxWidth: '100%', display: 'block' } }
						/>
					) }
					<p><strong>{ videoTitle || videoSlug }</strong></p>
					<button
						className="drtalks-block-change"
						onClick={ () => setAttributes( { videoSlug: '', videoTitle: '', videoThumbnail: '' } ) }
					>
						Change video
					</button>
				</div>
			) : (
				<div className="drtalks-block-search">
					<TextControl
						label="Search DrTalks Videos"
						value={ query }
						onChange={ ( v ) => { setQuery( v ); handleSearch( v ); } }
						placeholder="Type to search…"
					/>
					{ loading && <Spinner /> }
					{ results.length > 0 && (
						<ul className="drtalks-block-results">
							{ results.map( ( v ) => (
								<li
									key={ v.slug }
									className="drtalks-block-result"
									onClick={ () => selectVideo( v ) }
									style={ { cursor: 'pointer', display: 'flex', gap: '8px', alignItems: 'center', padding: '4px 0' } }
								>
									{ v.thumbnail_url && (
										<img src={ v.thumbnail_url } alt="" style={ { width: 60, flexShrink: 0 } } />
									) }
									<span>{ v.title }</span>
								</li>
							) ) }
						</ul>
					) }
				</div>
			) }
		</div>
	);
}

registerBlockType( metadata.name, {
	...metadata,
	edit: Edit,
	// save returns null — server-side rendered.
	save: () => null,
} );
