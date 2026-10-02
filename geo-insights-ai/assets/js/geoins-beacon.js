/**
 * GEO Insights referral beacon: counts visitors arriving from AI answers
 * even when the page is served from a full-page cache (no PHP runs).
 *
 * Privacy: runs only for logged-out visitors, sends only a source slug
 * and the page path when the visit demonstrably came from a known AI
 * (referrer domain or utm_source). No cookies, no IPs, no fingerprinting.
 */
( function () {
	'use strict';

	var cfg = window.geoinsBeacon;
	if ( ! cfg || ! cfg.endpoint || ! cfg.sources ) {
		return;
	}

	function refHost() {
		try {
			return document.referrer ? new URL( document.referrer ).hostname.toLowerCase().replace( /^www\./, '' ) : '';
		} catch ( e ) {
			return '';
		}
	}

	function matchSource() {
		var host = refHost();
		var utm = '';
		try {
			utm = ( new URLSearchParams( window.location.search ).get( 'utm_source' ) || '' ).toLowerCase().trim();
		} catch ( e ) { /* very old browser: utm matching skipped */ }

		if ( host && cfg.host && host === cfg.host.toLowerCase().replace( /^www\./, '' ) ) {
			host = ''; // Internal navigation.
		}

		var slugs = Object.keys( cfg.sources );
		for ( var i = 0; i < slugs.length; i++ ) {
			var s = cfg.sources[ slugs[ i ] ];
			if ( host ) {
				for ( var d = 0; d < s.d.length; d++ ) {
					if ( host === s.d[ d ] || host.slice( -( s.d[ d ].length + 1 ) ) === '.' + s.d[ d ] ) {
						return slugs[ i ];
					}
				}
			}
			if ( utm && s.u.indexOf( utm ) !== -1 ) {
				return slugs[ i ];
			}
		}
		return null;
	}

	var source = matchSource();
	if ( ! source ) {
		return;
	}

	var path = window.location.pathname || '/';

	// Once per browser session and page: reloads/back-navigation don't double count.
	var guard = 'geoins-b:' + source + ':' + path;
	try {
		if ( window.sessionStorage.getItem( guard ) ) {
			return;
		}
		window.sessionStorage.setItem( guard, '1' );
	} catch ( e ) { /* storage blocked: server-side dedupe still applies */ }

	var payload = JSON.stringify( { source: source, path: path } );
	try {
		if ( navigator.sendBeacon ) {
			navigator.sendBeacon( cfg.endpoint, new Blob( [ payload ], { type: 'application/json' } ) );
			return;
		}
	} catch ( e ) { /* fall through to fetch */ }
	if ( window.fetch ) {
		window.fetch( cfg.endpoint, {
			method: 'POST',
			credentials: 'omit',
			keepalive: true,
			headers: { 'Content-Type': 'application/json' },
			body: payload
		} ).catch( function () {} );
	}
} )();
