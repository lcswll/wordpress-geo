/**
 * Site-wide GEO audit app: batch-scans all published content via REST,
 * then renders a sortable score table with the failed checks and the
 * AI interest per page.
 */
import { useCallback, useEffect, useRef, useState } from 'react';
import { formatNumber, makeT } from './util';
import { Skeleton } from './panels';
import { OpportunityScatter, ScoreHistogram, ScoreGauge } from './charts';

/**
 * route must be query-free; extra params go into `query` so the separator
 * stays correct on plain-permalink sites (restUrl already contains "?").
 */
export function restFetch( config, route, options, query ) {
	const base = config.restUrl + route;
	const sep = base.indexOf( '?' ) === -1 ? '?' : '&';
	return fetch( base + sep + ( query ? query + '&' : '' ) + '_locale=user', {
		credentials: 'same-origin',
		headers: Object.assign(
			{ 'X-WP-Nonce': config.nonce, Accept: 'application/json, */*;q=0.1' },
			options && options.body ? { 'Content-Type': 'application/json' } : {}
		),
		method: ( options && options.method ) || 'GET',
		body: options && options.body ? JSON.stringify( options.body ) : undefined,
	} ).then( ( r ) => {
		if ( ! r.ok ) {
			throw new Error( 'HTTP ' + r.status );
		}
		return r.json();
	} );
}

export function ScoreBar( { score, total } ) {
	const pct = total ? Math.round( ( score / total ) * 100 ) : 0;
	const color = pct >= 77 ? '#00a32a' : pct >= 46 ? '#dba617' : '#d63638';
	return (
		<span className="geoins-scorecell">
			<b style={ { color } }>{ score }/{ total }</b>
			<span className="geoins-scorecell-track">
				<span className="geoins-scorecell-fill" style={ { width: pct + '%', background: color } } />
			</span>
		</span>
	);
}

export default function AuditApp( { config } ) {
	const t = makeT( config.i18n );
	const total = config.checkLabels ? Object.keys( config.checkLabels ).length : 13;

	const [ scan, setScan ] = useState( { cached: 0, total: 0, running: true } );
	const [ table, setTable ] = useState( null );
	const [ summary, setSummary ] = useState( null );
	const [ query, setQuery ] = useState( { orderby: 'score', order: 'asc', type: '', page: 1 } );
	const [ error, setError ] = useState( false );
	const scanning = useRef( false );

	const loadSummary = useCallback( () => {
		return restFetch( config, 'geoins/v1/audit/summary' )
			.then( ( json ) => setSummary( json ) )
			.catch( () => setSummary( null ) ); // Charts are optional; the table still works.
	}, [ config ] );

	const loadTable = useCallback( ( q ) => {
		return restFetch(
			config,
			'geoins/v1/audit',
			undefined,
			'orderby=' + q.orderby + '&order=' + q.order + '&type=' + q.type + '&page=' + q.page + '&per_page=50'
		).then( ( json ) => {
			setTable( json );
			setScan( ( prev ) => ( { ...prev, cached: json.cached, total: json.total } ) );
			return json;
		} );
	}, [ config ] );

	// Scan loop: analyze batches until everything is cached, then load.
	// Terminates when a batch makes no progress (cached must advance), so
	// a stuck server can never spin the loop forever.
	const runScan = useCallback( ( force ) => {
		if ( scanning.current ) {
			return;
		}
		scanning.current = true;
		setScan( ( prev ) => ( { ...prev, running: true } ) );

		const finish = ( failed ) => {
			scanning.current = false;
			setScan( ( prev ) => ( { ...prev, running: false } ) );
			if ( failed ) {
				setError( true );
				return;
			}
			const fresh = { orderby: 'score', order: 'asc', type: '', page: 1 };
			setQuery( fresh );
			loadTable( fresh ).catch( () => setError( true ) );
			loadSummary();
		};

		const step = ( prevCached, isFirst ) => {
			restFetch( config, 'geoins/v1/audit/run', {
				method: 'POST',
				body: { batch: 25, force: !! ( isFirst && force ) },
			} )
				.then( ( json ) => {
					setScan( { cached: json.cached, total: json.total, running: json.cached < json.total } );
					const more = json.cached < json.total && json.analyzed > 0;
					if ( more && json.cached > prevCached ) {
						step( json.cached, false );
					} else {
						finish( more ); // more-but-no-progress = server stuck.
					}
				} )
				.catch( () => finish( true ) );
		};
		step( -1, true );
	}, [ config, loadTable, loadSummary ] );

	useEffect( () => {
		runScan( false );
	}, [ runScan ] );

	const changeQuery = ( patch ) => {
		const next = { ...query, ...patch };
		if ( undefined === patch.page ) {
			next.page = 1;
		}
		setQuery( next );
		loadTable( next ).catch( () => setError( true ) );
	};

	const sortBy = ( col ) => {
		if ( query.orderby === col ) {
			changeQuery( { order: query.order === 'asc' ? 'desc' : 'asc' } );
		} else {
			changeQuery( { orderby: col, order: col === 'score' ? 'asc' : 'desc' } );
		}
	};

	const arrow = ( col ) => ( query.orderby === col ? ( query.order === 'asc' ? ' ↑' : ' ↓' ) : '' );

	if ( error ) {
		return (
			<div className="notice notice-error inline">
				<p>{ t( 'loadError', 'Could not load statistics.' ) }</p>
			</div>
		);
	}

	if ( scan.running || ! table ) {
		const label = t( 'auditScanning', 'Analyzing content … %1$s of %2$s done' )
			.replace( '%1$s', formatNumber( scan.cached ) )
			.replace( '%2$s', formatNumber( scan.total ) );
		const pct = scan.total ? Math.round( ( scan.cached / scan.total ) * 100 ) : 0;
		return (
			<div className="geoins-app">
				<div className="geoins-panel">
					<p className="geoins-scan-label" aria-live="polite">{ label }</p>
					<div className="geoins-scan-track"><span className="geoins-scan-fill" style={ { width: pct + '%' } } /></div>
				</div>
				<Skeleton />
			</div>
		);
	}

	if ( ! table.rows.length && ! table.total ) {
		return (
			<div className="geoins-panel geoins-hero">
				<p>{ t( 'auditEmpty', 'No published content to audit yet.' ) }</p>
			</div>
		);
	}

	return (
		<div className="geoins-app">
			<div className="geoins-toolbar" role="group">
				<button
					type="button"
					className={ 'button geoins-range' + ( query.type === '' ? ' is-active' : '' ) }
					onClick={ () => changeQuery( { type: '' } ) }
				>
					{ t( 'auditAllTypes', 'All types' ) }
				</button>
				<button
					type="button"
					className={ 'button geoins-range' + ( query.type === 'post' ? ' is-active' : '' ) }
					onClick={ () => changeQuery( { type: 'post' } ) }
				>
					{ t( 'auditTypePost', 'Post' ) }
				</button>
				<button
					type="button"
					className={ 'button geoins-range' + ( query.type === 'page' ? ' is-active' : '' ) }
					onClick={ () => changeQuery( { type: 'page' } ) }
				>
					{ t( 'auditTypePage', 'Page' ) }
				</button>
				<span className="geoins-toolbar-spacer" />
				<button type="button" className="button" onClick={ () => runScan( true ) }>
					{ t( 'auditRescan', 'Re-scan' ) }
				</button>
			</div>

			{ summary && (
				<div className="geoins-grid">
					<div className="geoins-panel">
						<h2>{ t( 'gaugeTitle', 'Average GEO score' ) }</h2>
						<p className="geoins-benefit">{ t( 'gaugeHint', 'Across all scored pages.' ) }</p>
						<ScoreGauge distribution={ summary.distribution || [] } total={ summary.total || total } />
					</div>
					<div className="geoins-panel">
						<h2>{ t( 'auditHistTitle', 'Score distribution' ) }</h2>
						<ScoreHistogram distribution={ summary.distribution } total={ summary.total || total } t={ t } />
					</div>
					<div className="geoins-panel geoins-panel-wide">
						<h2>{ t( 'auditScatterTitle', 'Opportunity map' ) }</h2>
						<p className="geoins-benefit">{ t( 'auditScatterHint', 'Every dot is a page AI read in the last 30 days.' ) }</p>
						{ summary.points && summary.points.length ? (
							<OpportunityScatter points={ summary.points } total={ summary.total || total } t={ t } />
						) : (
							<p className="geoins-empty">{ t( 'auditScatterEmpty', 'No AI accesses on scored pages in the last 30 days yet.' ) }</p>
						) }
					</div>
				</div>
			) }

			<div className="geoins-panel geoins-panel-wide">
				<p className="geoins-benefit">{ t( 'auditOpportunity', 'High AI interest, low score – fix these first.' ) }</p>
				<table className="geoins-table geoins-audit-table">
					<thead>
						<tr>
							<th><button type="button" className="geoins-sort" onClick={ () => sortBy( 'title' ) }>{ t( 'auditTitleCol', 'Title' ) }{ arrow( 'title' ) }</button></th>
							<th><button type="button" className="geoins-sort" onClick={ () => sortBy( 'score' ) }>{ t( 'auditScoreCol', 'GEO score' ) }{ arrow( 'score' ) }</button></th>
							<th>{ t( 'auditFailsCol', 'Open improvements' ) }</th>
							<th style={ { textAlign: 'right' } }>{ t( 'auditHitsCol', 'AI accesses (30d)' ) }</th>
							<th><button type="button" className="geoins-sort" onClick={ () => sortBy( 'modified' ) }>{ t( 'auditModCol', 'Updated' ) }{ arrow( 'modified' ) }</button></th>
						</tr>
					</thead>
					<tbody>
						{ table.rows.map( ( row ) => (
							<tr key={ row.id }>
								<td>
									<a href={ row.edit || row.url }>{ row.title || '#' + row.id }</a>{ ' ' }
									<span className="geoins-typechip">{ row.type === 'page' ? t( 'auditTypePage', 'Page' ) : t( 'auditTypePost', 'Post' ) }</span>
								</td>
								<td><ScoreBar score={ row.score } total={ total } /></td>
								<td>
									{ row.fails.length === 0 ? (
										<span className="geoins-allpass">✓ { t( 'auditPerfect', 'All checks passed' ) }</span>
									) : (
										<>
											{ row.fails.slice( 0, 3 ).map( ( id ) => (
												<span key={ id } className="geoins-failchip" title={ ( config.checkLabels || {} )[ id ] || id }>
													{ ( config.checkLabels || {} )[ id ] || id }
												</span>
											) ) }
											{ row.fails.length > 3 && (
												<span className="geoins-morechip">+{ row.fails.length - 3 } { t( 'auditMore', 'more' ) }</span>
											) }
										</>
									) }
								</td>
								<td style={ { textAlign: 'right' } }>
									{ row.hits > 0 ? <b>{ formatNumber( row.hits ) }</b> : <span style={ { color: '#a7aaad' } }>–</span> }
								</td>
								<td>{ row.modified }</td>
							</tr>
						) ) }
					</tbody>
				</table>

				{ table.pages > 1 && (
					<p className="geoins-pager">
						<button
							type="button"
							className="button"
							disabled={ query.page <= 1 }
							onClick={ () => changeQuery( { page: query.page - 1 } ) }
						>
							← { t( 'auditPrev', 'Previous' ) }
						</button>
						<span className="geoins-pager-info">{ query.page } / { table.pages }</span>
						<button
							type="button"
							className="button"
							disabled={ query.page >= table.pages }
							onClick={ () => changeQuery( { page: query.page + 1 } ) }
						>
							{ t( 'auditNext', 'Next' ) } →
						</button>
					</p>
				) }
			</div>
		</div>
	);
}
