/**
 * Non-chart panels: KPI cards, AI filter, icon bar lists, tables, status,
 * loading skeleton, empty state.
 */
import { COLORS, formatNumber, delta } from './util';
import { Sparkline } from './charts';
import { BrandIcon, iconForSource } from './icons';

/**
 * Per-AI filter chips. Companies are shown when they have data in the
 * current range (or are currently selected). `data` must be an UNFILTERED
 * response snapshot (App caches one), otherwise selecting a chip would
 * remove all the others. Alphabetical order keeps chip positions stable
 * across async refetches – no buttons jumping under the pointer.
 */
export function FilterBar( { config, data, active, onChange, t } ) {
	const totals = {};
	const add = ( slug, count ) => {
		const meta = config.sources && config.sources[ slug ];
		if ( ! meta ) {
			return;
		}
		totals[ meta.company ] = ( totals[ meta.company ] || 0 ) + count;
	};
	// botSources is the uncapped list; topBots (top 15) is the pre-1.5 fallback.
	( ( data && ( data.botSources || data.topBots ) ) || [] ).forEach( ( b ) => add( b.source, b.count ) );
	( ( data && data.referrals ) || [] ).forEach( ( r ) => add( r.source, r.count ) );

	const labelOf = ( key ) => ( config.companies[ key ] && config.companies[ key ].label ) || key;
	const keys = Object.keys( totals ).sort( ( a, b ) => labelOf( a ).localeCompare( labelOf( b ) ) );
	if ( active && keys.indexOf( active ) === -1 && config.companies[ active ] ) {
		keys.push( active );
	}

	if ( ! keys.length ) {
		return null;
	}

	return (
		<div className="geoins-filterbar" role="group" aria-label={ t( 'filterLabel', 'Filter by AI' ) }>
			<span className="geoins-filterlabel">{ t( 'filterLabel', 'Filter by AI' ) }:</span>
			<button
				type="button"
				className={ 'geoins-chip' + ( active ? '' : ' is-active' ) }
				aria-pressed={ ! active }
				onClick={ () => onChange( null ) }
			>
				{ t( 'filterAll', 'All AIs' ) }
			</button>
			{ keys.map( ( key ) => {
				const company = config.companies[ key ];
				if ( ! company ) {
					return null;
				}
				return (
					<button
						key={ key }
						type="button"
						className={ 'geoins-chip' + ( active === key ? ' is-active' : '' ) }
						aria-pressed={ active === key }
						onClick={ () => onChange( active === key ? null : key ) }
					>
						<BrandIcon icon={ company.icon } label={ company.label } size={ 14 } />
						{ company.label }
					</button>
				);
			} ) }
		</div>
	);
}

/**
 * Horizontal bar list with brand icons (top bots, referral sources).
 */
export function IconBarList( { rows, config, colorFor, t, showImpostors } ) {
	if ( ! rows.length ) {
		return <p className="geoins-empty">{ t( 'noData', 'No data yet.' ) }</p>;
	}
	const max = rows[ 0 ].count || 1;
	return (
		<div className="geoins-iconbars">
			{ rows.map( ( row ) => (
				<div key={ row.source } className="geoins-iconbar-row" title={ row.label }>
					<BrandIcon icon={ iconForSource( config, row.source ) } label={ row.label } size={ 18 } />
					<span className="geoins-iconbar-label">{ row.label }</span>
					<span className="geoins-iconbar-track">
						<span
							className="geoins-iconbar-fill"
							style={ {
								width: Math.max( 2, Math.round( ( row.count / max ) * 100 ) ) + '%',
								'--geoins-bar': colorFor( row ),
							} }
						/>
					</span>
					<span className="geoins-iconbar-count">
						{ formatNumber( row.count ) }
						{ showImpostors && row.failed > 0 && (
							<span className="geoins-iconbar-failed" title={ t( 'spoofed', 'impostors' ) }>
								✕{ formatNumber( row.failed ) }
							</span>
						) }
					</span>
				</div>
			) ) }
		</div>
	);
}

function DeltaChip( { now, prev, t } ) {
	const d = delta( now, prev );
	if ( ! d ) {
		return null;
	}
	if ( d.kind === 'new' ) {
		return <span className="geoins-delta is-up">▲ { t( 'newLabel', 'new' ) }</span>;
	}
	if ( d.kind === 'flat' ) {
		return <span className="geoins-delta">±0%</span>;
	}
	return (
		<span className={ 'geoins-delta ' + ( d.kind === 'up' ? 'is-up' : 'is-down' ) }>
			{ d.kind === 'up' ? '▲ +' : '▼ ' }
			{ d.pct }%
		</span>
	);
}

export function KpiCards( { totals, prev, series, catLabels, t } ) {
	const cards = [
		{ key: 'agent', value: totals.agent, old: prev.agent },
		{ key: 'retrieval', value: totals.retrieval, old: prev.retrieval },
		{ key: 'search', value: totals.search, old: prev.search },
		{ key: 'training', value: totals.training, old: prev.training },
		{ key: 'referral', value: totals.referrals, old: prev.referrals },
	];
	return (
		<div className="geoins-cards" aria-live="polite">
			{ cards.map( ( c ) => (
				<div key={ c.key } className="geoins-card geoins-card-accent" style={ { '--geoins-accent': COLORS[ c.key ] } }>
					<div className="geoins-num">
						{ formatNumber( c.value ) } <DeltaChip now={ c.value } prev={ c.old } t={ t } />
					</div>
					<div className="geoins-lbl">{ catLabels[ c.key ] || c.key }</div>
					<Sparkline series={ series } dataKey={ c.key } color={ COLORS[ c.key ] } />
				</div>
			) ) }
		</div>
	);
}

/** sprintf-lite for the localized alert templates. */
function fill( template, values ) {
	let out = template;
	values.forEach( ( value, i ) => {
		out = out.split( '%' + ( i + 1 ) + '$s' ).join( String( value ) );
	} );
	return out.indexOf( '%s' ) !== -1 && values.length ? out.split( '%s' ).join( String( values[ 0 ] ) ) : out;
}

function alertMessage( alert, t ) {
	const data = alert.data || {};
	switch ( alert.type ) {
		case 'new_referral':
			return fill( t( 'alertNewRef', 'First human visitors from %s! Your content is being cited there.' ), [ data.label || '?' ] );
		case 'new_bot':
			return data.relevant
				? fill( t( 'alertNewBot', '%s crawled your site for the first time – a citation-relevant AI is now reading you.' ), [ data.label || '?' ] )
				: fill( t( 'alertNewTrain', '%s (training crawler) visited your site for the first time.' ), [ data.label || '?' ] );
		case 'spike':
			return fill( t( 'alertSpike', 'AI visitor spike: %1$s visitors from AI answers yesterday (recent average: %2$s/day).' ), [ data.count || 0, data.avg || 0 ] );
	}
	return '';
}

/**
 * Citation alerts strip: unread alerts with a mark-all-read action.
 */
export function AlertsBar( { alerts, config, t, onRead } ) {
	const unread = ( alerts || [] ).filter( ( a ) => ! a.read );
	if ( ! unread.length ) {
		return null;
	}

	const markRead = () => {
		const base = config.restUrl + 'geoins/v1/alerts/read';
		fetch( base + ( base.indexOf( '?' ) === -1 ? '?' : '&' ) + '_locale=user', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': config.nonce },
		} ).catch( () => {} );
		onRead();
	};

	return (
		<div className="geoins-alerts" role="status">
			<div className="geoins-alerts-head">
				<strong>🔔 { t( 'alertsTitle', 'What happened' ) }</strong>
				<button type="button" className="button-link geoins-alerts-read" onClick={ markRead }>
					{ t( 'markRead', 'Mark all as read' ) }
				</button>
			</div>
			<ul>
				{ unread.slice( 0, 6 ).map( ( alert ) => (
					<li key={ alert.id } className={ 'geoins-alert geoins-alert-' + alert.type }>
						{ alertMessage( alert, t ) }
						<span className="geoins-alert-date">{ new Date( alert.time * 1000 ).toLocaleDateString() }</span>
					</li>
				) ) }
			</ul>
		</div>
	);
}

/**
 * AI radar: AI-sounding user agents that are not in the registry.
 */
export function UnknownBots( { bots, config, t, onDismiss } ) {
	if ( ! bots || ! bots.length ) {
		return null;
	}

	const dismiss = ( key ) => {
		const base = config.restUrl + 'geoins/v1/unknown-bots/dismiss';
		fetch( base + ( base.indexOf( '?' ) === -1 ? '?' : '&' ) + '_locale=user', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': config.nonce, 'Content-Type': 'application/json' },
			body: JSON.stringify( { key } ),
		} ).catch( () => {} );
		onDismiss( key );
	};

	return (
		<div className="geoins-panel geoins-panel-wide">
			<h2>📡 { t( 'unknownTitle', 'AI radar: unrecognized AI-like crawlers' ) }</h2>
			<p className="geoins-benefit">{ t( 'unknownHint', 'These user agents sound like AI systems but are not in the registry yet.' ) }</p>
			<table className="geoins-table">
				<thead>
					<tr>
						<th>{ t( 'unknownUa', 'User agent' ) }</th>
						<th style={ { textAlign: 'right' } }>{ t( 'hits', 'accesses' ) }</th>
						<th>{ t( 'lastSeen', 'Last seen' ) }</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					{ bots.slice( 0, 10 ).map( ( bot ) => (
						<tr key={ bot.key }>
							<td><code className="geoins-ua">{ bot.ua }</code></td>
							<td style={ { textAlign: 'right' } }><b>{ formatNumber( bot.count ) }</b></td>
							<td>{ bot.last ? new Date( bot.last * 1000 ).toLocaleDateString() : '' }</td>
							<td style={ { textAlign: 'right' } }>
								<button type="button" className="button-link geoins-dismiss" onClick={ () => dismiss( bot.key ) }>
									{ t( 'dismiss', 'Dismiss' ) }
								</button>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}

export function VerificationLine( { verification, t } ) {
	if ( ! verification ) {
		return null;
	}
	if ( ! verification.enabled ) {
		return <p className="description">{ t( 'verifyOff', 'Identity verification is off.' ) }</p>;
	}
	return (
		<p className="description">
			<span style={ { color: '#00a32a' } }>✓ { formatNumber( verification.verified ) } { t( 'verified', 'verified' ) }</span>
			{ ' · ' }
			<span style={ { color: '#d63638' } }>✕ { formatNumber( verification.failed ) } { t( 'spoofed', 'impostors' ) }</span>
			{ ' · ' }
			{ formatNumber( verification.unchecked ) } { t( 'unchecked', 'unchecked' ) }
		</p>
	);
}

export function MatrixTable( { rows, config, t } ) {
	if ( ! rows.length ) {
		return <p className="geoins-empty">{ t( 'noData', 'No data yet.' ) }</p>;
	}
	return (
		<table className="geoins-table">
			<thead>
				<tr>
					<th>{ t( 'term', 'Term' ) }</th>
					<th>{ t( 'page', 'Page' ) }</th>
					<th>{ t( 'ai', 'AI accesses' ) }</th>
					<th style={ { textAlign: 'right' } }>Σ</th>
				</tr>
			</thead>
			<tbody>
				{ rows.map( ( r, i ) => (
					<tr key={ i }>
						<td>
							<span className="geoins-keyword">{ r.keyword }</span>
						</td>
						<td>
							{ r.edit ? (
								<a href={ r.edit }>{ r.title }</a>
							) : r.url ? (
								<a href={ r.url } target="_blank" rel="noopener noreferrer">
									{ r.title }
								</a>
							) : (
								<span>{ r.title }</span>
							) }
						</td>
						<td>
							{ r.sources.map( ( s, j ) => (
								<span key={ j } className="geoins-srcchip">
									{ s.slug && <BrandIcon icon={ iconForSource( config, s.slug ) } label={ s.label } size={ 12 } /> }
									{ s.label } <b>{ formatNumber( s.count ) }</b>
								</span>
							) ) }
						</td>
						<td style={ { textAlign: 'right' } }>
							<b>{ formatNumber( r.count ) }</b>
						</td>
					</tr>
				) ) }
			</tbody>
		</table>
	);
}

export function LandingsTable( { rows, t } ) {
	if ( ! rows.length ) {
		return <p className="geoins-empty">{ t( 'noData', 'No data yet.' ) }</p>;
	}
	return (
		<table className="geoins-table">
			<thead>
				<tr>
					<th>{ t( 'page', 'Page' ) }</th>
					<th>{ t( 'from', 'AI source' ) }</th>
					<th style={ { textAlign: 'right' } }>{ t( 'visitors', 'visitors' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ rows.map( ( r, i ) => (
					<tr key={ i }>
						<td>
							{ r.url ? (
								<a href={ r.url } target="_blank" rel="noopener noreferrer">
									{ r.title }
								</a>
							) : (
								<span>{ r.title }</span>
							) }
						</td>
						<td>{ r.source }</td>
						<td style={ { textAlign: 'right' } }>
							<b>{ formatNumber( r.count ) }</b>
						</td>
					</tr>
				) ) }
			</tbody>
		</table>
	);
}

export function StatusPanel( { rows } ) {
	return (
		<div>
			{ rows.map( ( r ) => (
				<div key={ r.id } className="geoins-status-row">
					<span className={ 'geoins-light geoins-light-' + r.status } />
					<div>
						<strong>{ r.label }</strong>
						<p className="geoins-status-note">{ r.note }</p>
					</div>
				</div>
			) ) }
		</div>
	);
}

export function Skeleton() {
	return (
		<div className="geoins-skeleton" aria-hidden="true">
			<div className="geoins-cards">
				{ [ 0, 1, 2, 3, 4 ].map( ( i ) => (
					<div key={ i } className="geoins-card geoins-sk-block" style={ { height: 96 } } />
				) ) }
			</div>
			<div className="geoins-sk-block" style={ { height: 300, marginTop: 16 } } />
			<div className="geoins-sk-grid">
				<div className="geoins-sk-block" style={ { height: 220 } } />
				<div className="geoins-sk-block" style={ { height: 220 } } />
			</div>
		</div>
	);
}
