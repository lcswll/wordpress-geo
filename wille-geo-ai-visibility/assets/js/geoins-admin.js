/**
 * Wille GEO admin JS: settings quick actions + classic-editor GEO check
 * meta box. The statistics dashboard is a React app (geoins-dashboard.js,
 * source in src/dashboard/).
 * Vanilla JS, no dependencies.
 */
( function () {
	'use strict';

	var data = window.geoinsData || {};

	/** Element with an optional class and text content (never parsed as HTML). */
	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( text !== undefined && text !== null ) {
			node.textContent = String( text );
		}
		return node;
	}

	/* ------------------------------------------------------------------ *
	 * Settings: bot quick actions
	 * ------------------------------------------------------------------ */

	var blockTraining = document.getElementById( 'geoins-block-training' );
	var unblockAll = document.getElementById( 'geoins-unblock-all' );

	if ( blockTraining ) {
		blockTraining.addEventListener( 'click', function () {
			document.querySelectorAll( '.geoins-botgroup.is-safe input[type="checkbox"]' ).forEach( function ( box ) {
				box.checked = true;
			} );
		} );
	}
	if ( unblockAll ) {
		unblockAll.addEventListener( 'click', function () {
			document.querySelectorAll( '.geoins-botgroup input[type="checkbox"]' ).forEach( function ( box ) {
				box.checked = false;
			} );
		} );
	}

	/* ------------------------------------------------------------------ *
	 * GEO check meta box (classic editor)
	 * ------------------------------------------------------------------ */

	var box = document.getElementById( 'geoins-analysis-results' );

	function renderAnalysis( d ) {
		var pct = d.total ? Math.round( ( d.score / d.total ) * 100 ) : 0;
		var bar = el( 'div', 'geoins-scorebar' );
		var fill = el( 'span' );
		var list = el( 'ul' );
		fill.style.width = pct + '%';
		bar.appendChild( fill );
		d.checks.forEach( function ( c ) {
			var item = el( 'li', 'geoins-check ' + ( c.pass ? 'is-pass' : 'is-fail' ) );
			var label = el( 'span', '', c.label );
			if ( ! c.pass ) {
				label.appendChild( el( 'span', 'geoins-check-benefit', c.benefit ) );
			}
			item.appendChild( el( 'span', 'geoins-mark', c.pass ? '✓' : '✕' ) );
			item.appendChild( label );
			list.appendChild( item );
		} );
		box.replaceChildren(
			el( 'p', 'geoins-score', d.score + ' / ' + d.total + ' ' + ( data.i18n ? data.i18n.passed : '' ) ),
			bar,
			list
		);
	}

	if ( box ) {
		var body = new FormData();
		body.append( 'action', 'geoins_analyze' );
		body.append( 'nonce', box.getAttribute( 'data-nonce' ) );
		body.append( 'post_id', box.getAttribute( 'data-post' ) );
		fetch( ( window.ajaxurl || ( data && data.ajaxUrl ) ), { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( res ) {
				if ( res && res.success ) {
					renderAnalysis( res.data );
				} else {
					box.innerHTML = '';
				}
			} )
			.catch( function () {
				box.innerHTML = '';
			} );
	}
} )();
