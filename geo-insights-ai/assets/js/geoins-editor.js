/**
 * GEO Insights – live GEO checks in the block editor sidebar.
 * No build step: plain wp.element calls.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.plugins || ! wp.data || ! wp.element ) {
		return;
	}

	var config = window.geoinsEditor || {};
	var el = wp.element.createElement;
	var Panel =
		( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	if ( ! Panel ) {
		return;
	}

	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var useSelect = wp.data.useSelect;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useEntityProp = wp.coreData && wp.coreData.useEntityProp;

	function textOf( html ) {
		return html
			.replace( /<[^>]*>/g, ' ' )
			.replace( /&[a-z#0-9]+;/gi, ' ' )
			.replace( /\s+/g, ' ' )
			.trim();
	}

	function words( text ) {
		return text ? text.split( /\s+/ ).filter( Boolean ).length : 0;
	}

	function runChecks( content, title, keyword ) {
		var html = content.replace( /<!--[\s\S]*?-->/g, '' );
		var text = textOf( html );

		var paragraphs = [];
		var m;
		var pRe = /<p[^>]*>([\s\S]*?)<\/p>/gi;
		while ( ( m = pRe.exec( html ) ) ) {
			var pText = textOf( m[ 1 ] );
			if ( pText ) {
				paragraphs.push( pText );
			}
		}

		var questionHeadings = 0;
		var hRe = /<h[23][^>]*>([\s\S]*?)<\/h[23]>/gi;
		while ( ( m = hRe.exec( html ) ) ) {
			if ( /\?\s*$/.test( textOf( m[ 1 ] ) ) ) {
				questionHeadings++;
			}
		}

		// Site host punycode-normalized via the URL parser, so IDN domains
		// compare the same way as link hostnames (which URL() normalizes).
		var siteHost = ( config.siteHost || '' ).toLowerCase();
		try {
			siteHost = new URL( 'https://' + siteHost + '/' ).hostname;
		} catch ( e ) { /* keep raw value */ }

		var externalLinks = 0;
		var internalLinks = 0;
		var aRe = /<a[^>]+href=["']([^"']+)["']/gi;
		while ( ( m = aRe.exec( html ) ) ) {
			var href = m[ 1 ];
			if ( /^https?:\/\//i.test( href ) ) {
				try {
					var host = new URL( href ).hostname.toLowerCase();
					if ( host && host !== siteHost ) {
						externalLinks++;
					} else if ( host ) {
						internalLinks++;
					}
				} catch ( e ) { /* ignore malformed URL */ }
			} else if ( href.charAt( 0 ) === '/' && href.charAt( 1 ) !== '/' ) {
				internalLinks++;
			}
		}

		var imagesMissingAlt = 0;
		var imgRe = /<img\b[^>]*>/gi;
		while ( ( m = imgRe.exec( html ) ) ) {
			if ( ! /\balt\s*=\s*["'][^"']*\S[^"']*["']/i.test( m[ 0 ] ) ) {
				imagesMissingAlt++;
			}
		}

		var longParagraphs = paragraphs.filter( function ( p ) {
			return words( p ) > 150;
		} ).length;
		var firstWords = words( paragraphs[ 0 ] || '' );
		var numberGroups = ( text.match( /\d[\d.,%]*/g ) || [] ).length;

		var kwRaw = ( keyword || '' ).trim();
		var kw = ( kwRaw || title || '' ).trim().toLowerCase().replace( /\s+/g, ' ' );
		var titleNorm = ( title || '' ).toLowerCase().replace( /\s+/g, ' ' );

		return {
			keyword_set: kwRaw !== '',
			keyword_in_title: kw !== '' && titleNorm.indexOf( kw ) !== -1,
			answer_first: firstWords >= 20 && firstWords <= 120,
			question_headings: questionHeadings > 0,
			lists_tables: /<(ul|ol|table)[\s>]/i.test( html ),
			short_paragraphs: paragraphs.length > 0 && longParagraphs === 0,
			numbers: numberGroups >= 3,
			external_links: externalLinks > 0,
			internal_links: internalLinks > 0,
			image_alt: imagesMissingAlt === 0,
			word_count: words( text ) >= 300,
			freshness: true, // Being edited right now.
			author_bio: !! config.authorBio
		};
	}

	function GeoInsightsPanel() {
		var post = useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			var core = select( 'core' );
			var content = editor.getEditedPostContent() || '';

			// Expand synced patterns (<!-- wp:block {"ref":N} /-->) so the
			// live checks see the same links/images the server-side analysis
			// sees after do_blocks(). Two passes cover one nesting level;
			// useSelect re-runs automatically once the records resolve.
			var blockRe = /<!--\s*wp:block\s+(\{[^}]*\})\s*\/-->/g;
			for ( var pass = 0; pass < 2; pass++ ) {
				if ( content.indexOf( 'wp:block' ) === -1 ) {
					break;
				}
				content = content.replace( blockRe, function ( m0, json ) {
					try {
						var ref = JSON.parse( json ).ref;
						if ( ! ref ) {
							return '';
						}
						var rec = core.getEditedEntityRecord( 'postType', 'wp_block', ref );
						var c = rec && rec.content;
						if ( typeof c === 'string' ) {
							return c;
						}
						return ( c && typeof c.raw === 'string' ) ? c.raw : '';
					} catch ( e ) {
						return '';
					}
				} );
			}

			return {
				type: editor.getCurrentPostType(),
				id: editor.getCurrentPostId(),
				content: content,
				title: editor.getEditedPostAttribute( 'title' ) || ''
			};
		}, [] );

		var metaPair = useEntityProp ? useEntityProp( 'postType', post.type, 'meta', post.id ) : [ {}, function () {} ];
		var meta = metaPair[ 0 ] || {};
		var setMeta = metaPair[ 1 ];
		var keyword = meta._geoins_keyword || '';
		var pinned = !! meta._geoins_llms_pin;

		// Debounced analysis: recompute at most ~3×/second while typing.
		var resultsPair = useState( function () {
			return runChecks( post.content || '', post.title, keyword );
		} );
		var results = resultsPair[ 0 ];
		var setResults = resultsPair[ 1 ];
		useEffect(
			function () {
				var handle = window.setTimeout( function () {
					setResults( runChecks( post.content || '', post.title, keyword ) );
				}, 300 );
				return function () {
					window.clearTimeout( handle );
				};
			},
			[ post.content, post.title, keyword ]
		);
		var order = config.order || [];
		var score = 0;
		order.forEach( function ( id ) {
			if ( results[ id ] ) {
				score++;
			}
		} );
		var pct = order.length ? Math.round( ( score / order.length ) * 100 ) : 0;

		var items = order.map( function ( id ) {
			var def = ( config.checks || {} )[ id ] || { label: id, benefit: '' };
			var pass = !! results[ id ];
			return el(
				'li',
				{ key: id, className: 'geoins-check ' + ( pass ? 'is-pass' : 'is-fail' ) },
				el( 'span', { className: 'geoins-mark' }, pass ? '✓' : '✕' ),
				el(
					'span',
					null,
					def.label,
					! pass && def.benefit
						? el( 'span', { className: 'geoins-check-benefit' }, def.benefit )
						: null
				)
			);
		} );

		return el(
			Panel,
			{ name: 'geoins-panel', title: config.i18n.panelTitle, icon: 'visibility' },
			el( TextControl, {
				label: config.i18n.focusTerm,
				value: keyword,
				onChange: function ( value ) {
					setMeta( Object.assign( {}, meta, { _geoins_keyword: value } ) );
				},
				help: config.i18n.focusHelp,
				__nextHasNoMarginBottom: true
			} ),
			ToggleControl
				? el( ToggleControl, {
					label: config.i18n.pinLabel,
					checked: pinned,
					onChange: function ( value ) {
						setMeta( Object.assign( {}, meta, { _geoins_llms_pin: !! value } ) );
					},
					help: config.i18n.pinHelp,
					__nextHasNoMarginBottom: true
				} )
				: null,
			el( 'p', { className: 'geoins-score' }, score + ' / ' + order.length + ' ' + config.i18n.passed ),
			el(
				'div',
				{ className: 'geoins-scorebar' },
				el( 'span', { style: { width: pct + '%' } } )
			),
			el( 'ul', { className: 'geoins-editor-checks' }, items )
		);
	}

	wp.plugins.registerPlugin( 'geo-insights', {
		render: GeoInsightsPanel
	} );
} )( window.wp );
