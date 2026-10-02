/**
 * GEO Insights dashboard – React root component.
 *
 * Data comes from GET /wp-json/geoins/v1/dashboard?days=N (admin-only,
 * X-WP-Nonce cookie auth), optionally filtered per AI company via the
 * sources parameter. All user-facing strings arrive translated via
 * wp_localize_script (config.i18n).
 */
import { useEffect, useState } from 'react';
import { COLORS, makeT } from './util';
import { Timeline, CategoryDonut, VerificationDonut, ReferralTrend, CompanyTreemap, SourcePageSankey, CrawlHeatmap } from './charts';
import {
	KpiCards,
	FilterBar,
	AlertsBar,
	UnknownBots,
	IconBarList,
	VerificationLine,
	MatrixTable,
	LandingsTable,
	StatusPanel,
	Skeleton,
	EmptyHero,
} from './panels';

const RANGES = [ 7, 30, 90 ];

export default function App( { config } ) {
	const t = makeT( config.i18n );
	const [ days, setDays ] = useState( 30 );
	const [ company, setCompany ] = useState( null );
	const [ data, setData ] = useState( null );
	// Last UNFILTERED response: feeds the filter chips, so selecting a
	// company does not make all other chips disappear.
	const [ chipData, setChipData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( false );

	const companySources =
		company && config.companies && config.companies[ company ]
			? config.companies[ company ].sources
			: null;

	useEffect( () => {
		let cancelled = false;
		setLoading( true );
		setError( false );

		// Separator-aware join: on plain-permalink sites rest_url() already
		// contains "?rest_route=/", so a hardcoded "?" would break the route.
		// _locale=user keeps REST-supplied strings (status panel) in the same
		// language as the rest of the admin screen.
		const base = config.restUrl + 'geoins/v1/dashboard';
		const sep = base.indexOf( '?' ) === -1 ? '?' : '&';
		const filter = companySources ? '&sources=' + encodeURIComponent( companySources.join( ',' ) ) : '';

		fetch( base + sep + 'days=' + days + filter + '&_locale=user', {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': config.nonce, Accept: 'application/json, */*;q=0.1' },
		} )
			.then( ( r ) => {
				if ( 401 === r.status || 403 === r.status ) {
					throw new Error( 'auth' );
				}
				if ( ! r.ok ) {
					throw new Error( 'HTTP ' + r.status );
				}
				return r.json();
			} )
			.then( ( json ) => {
				if ( ! cancelled ) {
					setData( json );
					if ( ! companySources ) {
						setChipData( json );
					}
					setLoading( false );
				}
			} )
			.catch( ( e ) => {
				if ( ! cancelled ) {
					setError( e && 'auth' === e.message ? 'auth' : 'load' );
					setLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ days, company, config.restUrl, config.nonce ] );

	const catLabels = config.catLabels || {};

	let exportUrl = '';
	if ( config.exportUrl ) {
		exportUrl = config.exportUrl + '&days=' + days;
		if ( companySources ) {
			exportUrl += '&sources=' + encodeURIComponent( companySources.join( ',' ) );
		}
	}

	return (
		<div className="geoins-app">
			<div className="geoins-toolbar" role="group" aria-label={ t( 'range', 'Date range' ) }>
				{ RANGES.map( ( r ) => (
					<button
						key={ r }
						type="button"
						className={ 'button geoins-range' + ( r === days ? ' is-active' : '' ) }
						aria-pressed={ r === days }
						onClick={ () => setDays( r ) }
					>
						{ r } { t( 'days', 'days' ) }
					</button>
				) ) }
				<span className="geoins-toolbar-spacer" />
				{ exportUrl && (
					<a className="button" href={ exportUrl }>
						{ t( 'exportCsv', 'Export CSV' ) }
					</a>
				) }
			</div>

			{ data && (
				<AlertsBar
					alerts={ data.alerts }
					config={ config }
					t={ t }
					onRead={ () =>
						setData( ( prev ) =>
							prev ? { ...prev, alerts: ( prev.alerts || [] ).map( ( a ) => ( { ...a, read: 1 } ) ) } : prev
						)
					}
				/>
			) }

			<FilterBar config={ config } data={ chipData || data } active={ company } onChange={ setCompany } t={ t } />

			{ loading && <Skeleton /> }

			{ ! loading && 'auth' === error && (
				<div className="notice notice-warning inline">
					<p>
						{ t( 'sessionExpired', 'Your session check expired – reload the page to continue.' ) }{ ' ' }
						<button type="button" className="button" onClick={ () => window.location.reload() }>
							{ t( 'reload', 'Reload page' ) }
						</button>
					</p>
				</div>
			) }

			{ ! loading && 'load' === error && (
				<div className="notice notice-error inline">
					<p>{ t( 'loadError', 'Could not load statistics.' ) }</p>
				</div>
			) }

			{ ! loading && ! error && data && (
				<Dashboard
					data={ data }
					days={ days }
					catLabels={ catLabels }
					t={ t }
					config={ config }
					filtered={ !! company }
					onResetFilter={ () => setCompany( null ) }
					onDismissUnknown={ ( key ) =>
						setData( ( prev ) =>
							prev
								? { ...prev, unknownBots: ( prev.unknownBots || [] ).filter( ( b ) => b.key !== key ) }
								: prev
						)
					}
				/>
			) }
		</div>
	);
}

function Dashboard( { data, days, catLabels, t, config, filtered, onResetFilter, onDismissUnknown } ) {
	const empty = ! data.totals.bots && ! data.totals.referrals;
	const botLabels = {};
	( data.topBots || [] ).forEach( ( b ) => {
		botLabels[ b.source ] = b.label;
	} );

	if ( empty && filtered ) {
		return (
			<div className="geoins-panel geoins-hero">
				<p>{ t( 'filterNoData', 'No data for this AI in the selected range.' ) }</p>
				<button type="button" className="button" onClick={ onResetFilter }>
					{ t( 'resetFilter', 'Show all AIs' ) }
				</button>
			</div>
		);
	}

	return (
		<>
			{ empty ? (
				<EmptyHero t={ t } learnUrl={ config.learnUrl } />
			) : (
				<KpiCards
					totals={ data.totals }
					prev={ data.totalsPrev || {} }
					series={ data.series }
					catLabels={ catLabels }
					t={ t }
				/>
			) }

			<div className="geoins-grid">
				{ ! empty && (
					<div className="geoins-panel geoins-panel-wide">
						<h2>{ t( 'timelineTitle', 'AI accesses per day' ) }</h2>
						<p className="geoins-benefit">{ t( 'timelineHint', 'Agent + Retrieval = citation-relevant: an AI read your page to answer a real question. Training only feeds models.' ) }</p>
						<Timeline series={ data.series } catLabels={ catLabels } days={ days } />
					</div>
				) }

				{ ! empty && (
					<div className="geoins-panel">
						<h2>{ t( 'botsTitle', 'Which AI bots?' ) }</h2>
						<CategoryDonut totals={ data.totals } catLabels={ catLabels } centerLabel={ t( 'hits', 'accesses' ) } />
						<IconBarList
							rows={ ( data.topBots || [] ).slice( 0, 8 ) }
							config={ config }
							colorFor={ ( row ) => COLORS[ row.category ] || COLORS.training }
							t={ t }
							showImpostors
						/>
						{ data.verification && data.verification.enabled ? (
							<>
								<h3 className="geoins-subhead">{ t( 'verifyTitle', 'Bot identity' ) }</h3>
								<VerificationDonut verification={ data.verification } t={ t } />
							</>
						) : (
							<VerificationLine verification={ data.verification } t={ t } />
						) }
					</div>
				) }

				{ ! empty && (
					<div className="geoins-panel">
						<h2>{ t( 'referralsTitle', 'Human visitors from AI answers' ) }</h2>
						<p className="geoins-benefit">{ t( 'referralsHint', 'Real people who clicked your link inside ChatGPT, Perplexity & co. This is the payoff of GEO.' ) }</p>
						{ data.totals.referrals > 0 && <ReferralTrend series={ data.series } t={ t } /> }
						<IconBarList
							rows={ ( data.referrals || [] ).slice( 0, 8 ) }
							config={ config }
							colorFor={ () => COLORS.referral }
							t={ t }
						/>
					</div>
				) }

				{ ! empty && (
					<div className="geoins-panel">
						<h2>{ t( 'treemapTitle', 'AI companies: share of accesses' ) }</h2>
						<p className="geoins-benefit">{ t( 'treemapHint', 'Area = accesses in the selected range. Click a company to zoom into its bots.' ) }</p>
						<CompanyTreemap botSources={ data.botSources || data.topBots || [] } labels={ botLabels } config={ config } />
					</div>
				) }

				{ ! empty && (
					<div className="geoins-panel">
						<h2>{ t( 'heatmapTitle', 'When do AIs read your site?' ) }</h2>
						<p className="geoins-benefit">{ t( 'heatmapHint', 'Bot accesses of the last 7 days by weekday and hour.' ) }</p>
						{ data.heatmap && data.heatmap.length ? (
							<CrawlHeatmap heatmap={ data.heatmap } weekdays={ config.weekdays } t={ t } />
						) : (
							<p className="geoins-empty">{ t( 'heatmapEmpty', 'No bot accesses in the last 7 days yet.' ) }</p>
						) }
					</div>
				) }

				{ ! empty && (
					<div className="geoins-panel geoins-panel-wide">
						<h2>{ t( 'matrixTitle', 'Terms & pages: which AI reads what?' ) }</h2>
						<p className="geoins-benefit">{ t( 'matrixHint', 'AI crawlers do not transmit search queries. This matrix maps every access to the focus term of the page – the honest, practical equivalent.' ) }</p>
						<MatrixTable rows={ data.matrix || [] } config={ config } t={ t } />
					</div>
				) }

				{ ! empty && data.matrix && data.matrix.length > 0 && (
					<div className="geoins-panel geoins-panel-wide">
						<h2>{ t( 'sankeyTitle', 'Flows: which AI reads which page' ) }</h2>
						<p className="geoins-benefit">{ t( 'sankeyHint', 'Left: AI bots, right: your pages. Band width = accesses.' ) }</p>
						<SourcePageSankey matrix={ data.matrix } />
					</div>
				) }

				{ ! empty && (
					<div className="geoins-panel geoins-panel-wide">
						<h2>{ t( 'landingsTitle', 'AI visitors: landing pages' ) }</h2>
						<LandingsTable rows={ data.landings || [] } t={ t } />
					</div>
				) }

				<UnknownBots
					bots={ data.unknownBots }
					config={ config }
					t={ t }
					onDismiss={ onDismissUnknown }
				/>

				<div className="geoins-panel geoins-panel-wide">
					<h2>{ t( 'statusTitle', 'GEO status of your site' ) }</h2>
					<StatusPanel rows={ data.status || [] } />
				</div>
			</div>
		</>
	);
}
