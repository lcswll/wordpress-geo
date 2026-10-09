/**
 * First-run panel: replaces the empty statistics with results the site owner gets right away
 * (GEO status, content analysis of the existing posts, one-click email alert) while the
 * plugin waits for the first AI visit.
 */
import { useEffect, useState } from 'react';
import { formatNumber } from './util';
import { restFetch, ScoreBar } from './audit';

function Step( { state, title, children } ) {
	return (
		<li className={ 'geoins-start-step geoins-start-' + state }>
			<span className={ 'geoins-light geoins-light-' + state } aria-hidden="true" />
			<div>
				<strong>{ title }</strong>
				{ children }
			</div>
		</li>
	);
}

function ContentStep( { config, t } ) {
	const total = config.checkLabels ? Object.keys( config.checkLabels ).length : 13;
	const [ scan, setScan ] = useState( { cached: 0, total: 0, running: true } );
	const [ rows, setRows ] = useState( null );
	const [ average, setAverage ] = useState( null );
	const [ failed, setFailed ] = useState( false );

	useEffect( () => {
		let cancelled = false;
		const load = () =>
			Promise.all( [
				restFetch( config, 'geoins/v1/audit', null, 'orderby=score&order=asc&per_page=5' ),
				restFetch( config, 'geoins/v1/audit/summary' ),
			] ).then( ( [ table, summary ] ) => {
				if ( cancelled ) {
					return;
				}
				let n = 0;
				let sum = 0;
				( summary.distribution || [] ).forEach( ( d ) => {
					n += d.n;
					sum += d.score * d.n;
				} );
				setAverage( n ? Math.round( ( sum / n ) * 10 ) / 10 : null );
				setRows( ( table.rows || [] ).filter( ( r ) => r.score < total ).slice( 0, 3 ) );
			} );

		// Scans in batches (results are cached per post, so this is cheap on later visits).
		const step = ( prevCached ) => {
			restFetch( config, 'geoins/v1/audit/run', { method: 'POST', body: { batch: 25 } } )
				.then( ( json ) => {
					if ( cancelled ) {
						return;
					}
					setScan( { cached: json.cached, total: json.total, running: json.cached < json.total } );
					if ( json.cached < json.total && json.analyzed > 0 && json.cached > prevCached ) {
						step( json.cached );
					} else {
						load().catch( () => ! cancelled && setFailed( true ) );
					}
				} )
				.catch( () => ! cancelled && setFailed( true ) );
		};
		step( -1 );
		return () => {
			cancelled = true;
		};
	}, [ config, total ] );

	if ( failed ) {
		return (
			<Step state="warn" title={ t( 'startContentTitle', 'Your content, checked for AI search' ) }>
				<p>{ t( 'loadError', 'Could not load statistics.' ) } <a href={ config.auditUrl }>{ t( 'startAuditAll', 'Open the full GEO analysis' ) }</a></p>
			</Step>
		);
	}
	if ( scan.running || null === rows ) {
		return (
			<Step state="wait" title={ t( 'startContentTitle', 'Your content, checked for AI search' ) }>
				<p>
					{ t( 'auditScanning', 'Analyzing content … %1$s of %2$s done' )
						.replace( '%1$s', formatNumber( scan.cached ) )
						.replace( '%2$s', formatNumber( scan.total ) ) }
				</p>
			</Step>
		);
	}
	if ( ! scan.total ) {
		return (
			<Step state="warn" title={ t( 'startContentTitle', 'Your content, checked for AI search' ) }>
				<p>{ t( 'auditEmpty', 'No published content to audit yet.' ) }</p>
			</Step>
		);
	}
	return (
		<Step state={ rows.length ? 'warn' : 'ok' } title={ t( 'startContentTitle', 'Your content, checked for AI search' ) }>
			{ null !== average && (
				<p>
					{ t( 'startContentScore', '%1$s pages analyzed, average GEO score %2$s of %3$s.' )
						.replace( '%1$s', formatNumber( scan.total ) )
						.replace( '%2$s', String( average ).replace( '.', config.decimalPoint || '.' ) )
						.replace( '%3$s', String( total ) ) }
				</p>
			) }
			{ rows.length > 0 ? (
				<>
					<p>{ t( 'startContentWeakest', 'These pages have the most room for improvement:' ) }</p>
					<ul className="geoins-start-pages">
						{ rows.map( ( r ) => (
							<li key={ r.id }>
								<a href={ r.edit }>{ r.title || '#' + r.id }</a>
								<ScoreBar score={ r.score } total={ total } />
							</li>
						) ) }
					</ul>
				</>
			) : (
				<p>{ t( 'auditPerfect', 'All checks passed' ) }</p>
			) }
			<p><a href={ config.auditUrl }>{ t( 'startAuditAll', 'Open the full GEO analysis' ) }</a></p>
		</Step>
	);
}

export default function StartPanel( { data, config, t } ) {
	// Blocking problems (site hidden, robots.txt, blocked citation bots) vs. optional features not switched on yet.
	const BLOCKING = [ 'public', 'robots_virtual', 'citation_bots' ];
	const rowsAll = data.status || [];
	const blocking = rowsAll.filter( ( r ) => 'ok' !== r.status && ( 'bad' === r.status || BLOCKING.indexOf( r.id ) !== -1 ) ).length;
	const optional = rowsAll.filter( ( r ) => 'ok' !== r.status ).length - blocking;
	return (
		<div className="geoins-panel geoins-panel-wide geoins-start">
			<h2>{ t( 'emptyTitle', 'Waiting for the first AI visit' ) }</h2>
			<p className="geoins-benefit">{ t( 'noData', 'No data yet. AI accesses appear here as soon as a known AI bot or an AI-referred visitor reaches your site.' ) }</p>
			<p className="geoins-benefit">{ t( 'startIntro', 'Until then, here is what Wille GEO already found on your site:' ) }</p>
			<ul className="geoins-start-steps">
				<Step state={ blocking ? 'bad' : 'ok' } title={ t( 'statusTitle', 'GEO status of your site' ) }>
					<p>
						{ blocking
							? t( 'startStatusBlocking', 'Something keeps AI systems away from your site – see the red and yellow points in the GEO status below.' )
							: t( 'startStatusOk', 'Your site is open to AI crawlers: nothing blocks ChatGPT, Perplexity or Claude.' ) }
					</p>
					{ optional > 0 && (
						<p>
							{ t( 'startStatusOptional', 'Optional: %s more features in the GEO status below can improve your visibility.' ).replace( '%s', formatNumber( optional ) ) }
						</p>
					) }
				</Step>
				<ContentStep config={ config } t={ t } />
				<Step state={ config.alertsEmail ? 'ok' : 'wait' } title={ t( 'startAlertsTitle', 'Notification for the first AI visit' ) }>
					{ config.alertsEmail ? (
						<p>{ t( 'startAlertsOn', 'You will get an email as soon as a new AI reads your site for the first time.' ) }</p>
					) : (
						<>
							<p>{ t( 'startAlertsOff', 'Get an email as soon as a new AI reads your site for the first time – at most one email per day.' ) }</p>
							{ config.enableAlertsUrl && (
								<a className="button" href={ config.enableAlertsUrl }>
									{ t( 'startAlertsButton', 'Turn on email notifications' ) }
								</a>
							) }
						</>
					) }
				</Step>
			</ul>
			{ config.learnUrl && (
				<p>
					<a href={ config.learnUrl }>{ t( 'learnLink', 'New here? How it all works, in plain language.' ) }</a>
				</p>
			) }
		</div>
	);
}
