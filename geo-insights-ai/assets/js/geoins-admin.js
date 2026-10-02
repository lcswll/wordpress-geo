/**
 * GEO Insights admin JS: settings quick actions + classic-editor GEO check
 * meta box. The statistics dashboard is a React app (geoins-dashboard.js,
 * source in src/dashboard/).
 * Vanilla JS, no dependencies.
 */
( function () {
	'use strict';

	var data = window.geoinsData || {};

	function esc( s ) {
		var d = document.createElement( 'div' );
		d.textContent = String( s === null || s === undefined ? '' : s );
		return d.innerHTML;
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
		var html = '<p class="geoins-score">' + esc( d.score + ' / ' + d.total + ' ' + ( data.i18n ? data.i18n.passed : '' ) ) + '</p>' +
			'<div class="geoins-scorebar"><span style="width:' + pct + '%"></span></div><ul>';
		d.checks.forEach( function ( c ) {
			html += '<li class="geoins-check ' + ( c.pass ? 'is-pass' : 'is-fail' ) + '">' +
				'<span class="geoins-mark">' + ( c.pass ? '✓' : '✕' ) + '</span>' +
				'<span>' + esc( c.label ) +
				( c.pass ? '' : '<span class="geoins-check-benefit">' + esc( c.benefit ) + '</span>' ) +
				'</span></li>';
		} );
		box.innerHTML = html + '</ul>';
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
