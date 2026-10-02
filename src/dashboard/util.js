/**
 * Shared helpers for the GEO Insights React dashboard.
 */

export const COLORS = {
	agent: '#2271b1',
	retrieval: '#00a32a',
	search: '#8c8f94',
	training: '#dba617',
	referral: '#7c3aed',
};

export const SERIES_KEYS = [ 'agent', 'retrieval', 'search', 'training', 'referral' ];

const nf = new Intl.NumberFormat();

export function formatNumber( n ) {
	return nf.format( n || 0 );
}

/**
 * Translation helper: strings are localized in PHP (wp_localize_script)
 * so they flow through the plugin's normal POT/de_DE pipeline; the second
 * argument is only the English fallback for broken installs.
 */
export function makeT( i18n ) {
	return ( key, fallback ) => ( i18n && i18n[ key ] ) || fallback;
}

/** Percentage delta vs. the previous period; null when both are zero. */
export function delta( now, prev ) {
	now = now || 0;
	prev = prev || 0;
	if ( ! now && ! prev ) {
		return null;
	}
	if ( ! prev ) {
		return { kind: 'new' };
	}
	const pct = Math.round( ( ( now - prev ) / prev ) * 100 );
	return { kind: pct > 0 ? 'up' : pct < 0 ? 'down' : 'flat', pct };
}

/** "2026-08-28" -> "08-28" for axis ticks. */
export function shortDate( d ) {
	return typeof d === 'string' ? d.slice( 5 ) : d;
}
