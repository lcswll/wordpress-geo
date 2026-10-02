/**
 * Chart components for the GEO Insights dashboard – Apache ECharts.
 */
import { useMemo, useCallback } from 'react';
import EChart, { echarts, TOOLTIP } from './echart';
import { COLORS, SERIES_KEYS, formatNumber, shortDate } from './util';

const AXIS_LABEL = { color: '#787c82', fontSize: 11 };
const SPLIT = { lineStyle: { color: '#f0f0f1' } };
const AXIS_LINE = { lineStyle: { color: '#dcdcde' } };

function rgba( hex, alpha ) {
	const n = parseInt( hex.slice( 1 ), 16 );
	return 'rgba(' + ( ( n >> 16 ) & 255 ) + ',' + ( ( n >> 8 ) & 255 ) + ',' + ( n & 255 ) + ',' + alpha + ')';
}

function vGradient( hex, top, bottom ) {
	return new echarts.graphic.LinearGradient( 0, 0, 0, 1, [
		{ offset: 0, color: rgba( hex, top ) },
		{ offset: 1, color: rgba( hex, bottom ) },
	] );
}

function esc( s ) {
	return String( s === null || s === undefined ? '' : s ).replace( /[&<>"]/g, ( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ] ) );
}

function scoreColor( score, total ) {
	const pct = total ? score / total : 0;
	return pct >= 0.77 ? '#00a32a' : pct >= 0.46 ? '#dba617' : '#d63638';
}

/* ------------------------------------------------------------------ */

/** Tiny sparkline inside the KPI cards. */
export function Sparkline( { series, dataKey, color } ) {
	const option = useMemo( () => ( {
		animation: false,
		grid: { left: 0, right: 0, top: 2, bottom: 0 },
		xAxis: { type: 'category', show: false, data: series.map( ( d ) => d.date ), boundaryGap: false },
		yAxis: { type: 'value', show: false },
		series: [ {
			type: 'line',
			smooth: true,
			symbol: 'none',
			silent: true,
			lineStyle: { width: 1.5, color },
			areaStyle: { color: vGradient( color, 0.35, 0.02 ) },
			data: series.map( ( d ) => d[ dataKey ] || 0 ),
		} ],
	} ), [ series, dataKey, color ] );
	return (
		<div className="geoins-spark" aria-hidden="true">
			<EChart option={ option } height={ 34 } />
		</div>
	);
}

/** Main stacked timeline with legend toggling and a zoom slider on long ranges. */
export function Timeline( { series, catLabels, days } ) {
	const option = useMemo( () => ( {
		tooltip: {
			...TOOLTIP,
			trigger: 'axis',
			axisPointer: { type: 'line', lineStyle: { color: '#c3c4c7' } },
			formatter: ( params ) => {
				const rows = params.filter( ( p ) => p.value > 0 );
				const total = params.reduce( ( a, p ) => a + ( p.value || 0 ), 0 );
				return '<div style="font-weight:600;margin-bottom:4px">' + esc( params[ 0 ] ? params[ 0 ].axisValue : '' ) + '</div>' +
					rows.map( ( p ) => '<div style="display:flex;gap:8px;justify-content:space-between"><span>' + p.marker + esc( p.seriesName ) + '</span><b>' + formatNumber( p.value ) + '</b></div>' ).join( '' ) +
					'<div style="border-top:1px solid #f0f0f1;margin-top:4px;padding-top:4px;text-align:right;color:#787c82">Σ ' + formatNumber( total ) + '</div>';
			},
		},
		legend: { bottom: 0, icon: 'circle', itemWidth: 8, itemHeight: 8, textStyle: { color: '#3c434a', fontSize: 12 } },
		grid: { left: 42, right: 12, top: 14, bottom: days >= 30 ? 74 : 36 },
		xAxis: {
			type: 'category',
			boundaryGap: false,
			data: series.map( ( d ) => d.date ),
			axisLabel: { ...AXIS_LABEL, formatter: shortDate },
			axisTick: { show: false },
			axisLine: AXIS_LINE,
		},
		yAxis: { type: 'value', minInterval: 1, axisLabel: AXIS_LABEL, splitLine: SPLIT },
		dataZoom: days >= 30
			? [
				{ type: 'inside', zoomOnMouseWheel: false, moveOnMouseMove: true },
				{ type: 'slider', height: 22, bottom: 30, borderColor: '#dcdcde', fillerColor: 'rgba(34,113,177,.12)', handleStyle: { color: '#2271b1' }, textStyle: AXIS_LABEL, brushSelect: false },
			]
			: [],
		series: SERIES_KEYS.map( ( key ) => ( {
			name: ( catLabels && catLabels[ key ] ) || key,
			type: 'line',
			stack: 'total',
			smooth: true,
			symbol: 'none',
			lineStyle: { width: 1, color: COLORS[ key ] },
			itemStyle: { color: COLORS[ key ] },
			areaStyle: { color: vGradient( COLORS[ key ], 0.7, 0.18 ) },
			emphasis: { focus: 'series' },
			data: series.map( ( d ) => d[ key ] || 0 ),
		} ) ),
	} ), [ series, catLabels, days ] );

	return <EChart option={ option } height={ days >= 90 ? 330 : 290 } />;
}

function donut( data, centerText, centerSub ) {
	return {
		tooltip: { ...TOOLTIP, trigger: 'item', formatter: ( p ) => p.marker + esc( p.name ) + ' <b>' + formatNumber( p.value ) + '</b> (' + p.percent + '%)' },
		legend: { bottom: 0, icon: 'circle', itemWidth: 8, itemHeight: 8, textStyle: { color: '#3c434a', fontSize: 12 } },
		title: {
			text: centerText,
			subtext: centerSub,
			left: 'center',
			top: '34%',
			textStyle: { fontSize: 20, fontWeight: 600, color: '#1d2327' },
			subtextStyle: { fontSize: 11, color: '#787c82' },
			itemGap: 2,
		},
		series: [ {
			type: 'pie',
			radius: [ '50%', '74%' ],
			center: [ '50%', '44%' ],
			avoidLabelOverlap: true,
			label: { show: false },
			itemStyle: { borderRadius: 6, borderColor: '#fff', borderWidth: 2 },
			emphasis: { scale: true, scaleSize: 4 },
			data,
		} ],
	};
}

/** Donut: bot accesses by class, total in the center. */
export function CategoryDonut( { totals, catLabels, centerLabel } ) {
	const option = useMemo( () => {
		const data = [ 'agent', 'retrieval', 'search', 'training' ]
			.map( ( key ) => ( { name: ( catLabels && catLabels[ key ] ) || key, value: totals[ key ] || 0, itemStyle: { color: COLORS[ key ] } } ) )
			.filter( ( d ) => d.value > 0 );
		return data.length ? donut( data, formatNumber( totals.bots ), centerLabel ) : null;
	}, [ totals, catLabels, centerLabel ] );
	return option ? <EChart option={ option } height={ 210 } /> : null;
}

/** Verified / impostors / unchecked share of bot hits. */
export function VerificationDonut( { verification, t } ) {
	const option = useMemo( () => {
		const data = [
			{ name: t( 'verified', 'verified' ), value: verification.verified || 0, itemStyle: { color: '#00a32a' } },
			{ name: t( 'spoofed', 'impostors' ), value: verification.failed || 0, itemStyle: { color: '#d63638' } },
			{ name: t( 'unchecked', 'unchecked' ), value: verification.unchecked || 0, itemStyle: { color: '#c3c4c7' } },
		].filter( ( d ) => d.value > 0 );
		if ( ! data.length ) {
			return null;
		}
		const total = data.reduce( ( a, d ) => a + d.value, 0 );
		const pct = Math.round( ( ( verification.verified || 0 ) / total ) * 100 );
		return donut( data, pct + '%', t( 'verified', 'verified' ) );
	}, [ verification, t ] );
	return option ? <EChart option={ option } height={ 190 } /> : null;
}

/** AI visitors per day with a 7-day moving average. */
export function ReferralTrend( { series, t } ) {
	const option = useMemo( () => {
		if ( ! series || series.length < 2 ) {
			return null;
		}
		const avg = series.map( ( d, i ) => {
			const win = series.slice( Math.max( 0, i - 6 ), i + 1 );
			return Math.round( ( win.reduce( ( a, x ) => a + ( x.referral || 0 ), 0 ) / win.length ) * 10 ) / 10;
		} );
		return {
			tooltip: { ...TOOLTIP, trigger: 'axis' },
			grid: { left: 34, right: 10, top: 10, bottom: 24 },
			xAxis: { type: 'category', boundaryGap: false, data: series.map( ( d ) => d.date ), axisLabel: { ...AXIS_LABEL, fontSize: 10, formatter: shortDate }, axisTick: { show: false }, axisLine: AXIS_LINE },
			yAxis: { type: 'value', minInterval: 1, axisLabel: { ...AXIS_LABEL, fontSize: 10 }, splitLine: SPLIT },
			series: [
				{ name: t( 'visitors', 'visitors' ), type: 'line', smooth: true, symbol: 'none', lineStyle: { width: 1.5, color: '#c4b5fd' }, itemStyle: { color: '#c4b5fd' }, data: series.map( ( d ) => d.referral || 0 ) },
				{ name: t( 'movingAvg', '7-day average' ), type: 'line', smooth: true, symbol: 'none', lineStyle: { width: 2.5, color: COLORS.referral }, itemStyle: { color: COLORS.referral }, areaStyle: { color: vGradient( COLORS.referral, 0.18, 0 ) }, data: avg },
			],
		};
	}, [ series, t ] );
	return option ? <EChart option={ option } height={ 160 } /> : null;
}

/**
 * Opportunity map: AI interest (x) vs. GEO score (y). Bottom-right =
 * heavily read but weak pages = fix first. Click a dot to edit.
 */
export function OpportunityScatter( { points, total, t } ) {
	const option = useMemo( () => ( {
		tooltip: {
			...TOOLTIP,
			trigger: 'item',
			formatter: ( p ) => '<div style="font-weight:600;margin-bottom:4px;max-width:260px">' + esc( p.data.name ) + '</div>' +
				'<div>' + esc( t( 'auditScoreCol', 'GEO score' ) ) + ': <b>' + p.value[ 1 ] + '/' + total + '</b></div>' +
				'<div>' + esc( t( 'auditHitsCol', 'AI accesses (30d)' ) ) + ': <b>' + formatNumber( p.value[ 0 ] ) + '</b></div>',
		},
		grid: { left: 40, right: 20, top: 16, bottom: 40 },
		xAxis: { type: 'value', name: t( 'auditHitsCol', 'AI accesses (30d)' ), nameLocation: 'middle', nameGap: 26, nameTextStyle: AXIS_LABEL, minInterval: 1, axisLabel: AXIS_LABEL, splitLine: SPLIT, axisLine: AXIS_LINE },
		yAxis: { type: 'value', min: 0, max: total, minInterval: 1, axisLabel: AXIS_LABEL, splitLine: SPLIT },
		series: [ {
			type: 'scatter',
			cursor: 'pointer',
			data: points.map( ( p ) => ( {
				value: [ p.hits, p.score ],
				name: p.title,
				edit: p.edit,
				symbolSize: 10 + Math.min( 28, Math.sqrt( p.hits ) * 2.2 ),
				itemStyle: { color: rgba( scoreColor( p.score, total ), 0.8 ), borderColor: scoreColor( p.score, total ), borderWidth: 1 },
			} ) ),
			emphasis: { scale: 1.3 },
			markLine: {
				silent: true,
				symbol: 'none',
				lineStyle: { color: '#dba617', type: 'dashed' },
				label: { show: false },
				data: [ { yAxis: Math.round( total / 2 ) } ],
			},
		} ],
	} ), [ points, total, t ] );

	const onClick = useCallback( ( params ) => {
		if ( params && params.data && params.data.edit ) {
			window.location.href = params.data.edit;
		}
	}, [] );

	return <EChart option={ option } height={ 280 } onClick={ onClick } />;
}

/** How many pages sit at each score. */
export function ScoreHistogram( { distribution, total, t } ) {
	const option = useMemo( () => ( {
		tooltip: { ...TOOLTIP, trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: ( ps ) => esc( t( 'auditScoreCol', 'GEO score' ) ) + ' ' + ps[ 0 ].axisValue + '/' + total + ': <b>' + formatNumber( ps[ 0 ].value ) + '</b> ' + esc( t( 'auditPages', 'pages' ) ) },
		grid: { left: 34, right: 10, top: 12, bottom: 26 },
		xAxis: { type: 'category', data: distribution.map( ( d ) => d.score ), axisLabel: AXIS_LABEL, axisTick: { show: false }, axisLine: AXIS_LINE },
		yAxis: { type: 'value', minInterval: 1, axisLabel: AXIS_LABEL, splitLine: SPLIT },
		series: [ {
			type: 'bar',
			barMaxWidth: 28,
			itemStyle: { borderRadius: [ 5, 5, 0, 0 ] },
			data: distribution.map( ( d ) => ( { value: d.n, itemStyle: { color: rgba( scoreColor( d.score, total ), 0.85 ) } } ) ),
		} ],
	} ), [ distribution, total, t ] );
	return <EChart option={ option } height={ 190 } />;
}

/** Site-wide average score as a gauge. */
export function ScoreGauge( { distribution, total } ) {
	const option = useMemo( () => {
		const n = distribution.reduce( ( a, d ) => a + d.n, 0 );
		if ( ! n ) {
			return null;
		}
		const avg = distribution.reduce( ( a, d ) => a + d.score * d.n, 0 ) / n;
		const color = scoreColor( avg, total );
		return {
			series: [ {
				type: 'gauge',
				min: 0,
				max: total,
				startAngle: 210,
				endAngle: -30,
				radius: '100%',
				center: [ '50%', '62%' ],
				progress: { show: true, roundCap: true, width: 14, itemStyle: { color } },
				axisLine: { roundCap: true, lineStyle: { width: 14, color: [ [ 1, '#f2f4f7' ] ] } },
				pointer: { show: false },
				axisTick: { show: false },
				splitLine: { show: false },
				axisLabel: { show: false },
				detail: { valueAnimation: true, offsetCenter: [ 0, '-4%' ], fontSize: 28, fontWeight: 600, color: '#1d2327', formatter: ( v ) => v.toFixed( 1 ) + ' / ' + total },
				data: [ { value: Math.round( avg * 10 ) / 10 } ],
			} ],
		};
	}, [ distribution, total ] );
	return option ? <EChart option={ option } height={ 190 } /> : null;
}

/** Treemap: AI companies → their bots, sized by accesses. */
export function CompanyTreemap( { botSources, labels, config } ) {
	const option = useMemo( () => {
		const companies = {};
		( botSources || [] ).forEach( ( b ) => {
			const meta = config.sources && config.sources[ b.source ];
			const key = meta ? meta.company : 'other';
			const label = ( config.companies && config.companies[ key ] && config.companies[ key ].label ) || key;
			if ( ! companies[ key ] ) {
				companies[ key ] = { name: label, children: [] };
			}
			companies[ key ].children.push( { name: ( labels && labels[ b.source ] ) || b.source, value: b.count } );
		} );
		const data = Object.keys( companies ).map( ( k ) => companies[ k ] );
		if ( ! data.length ) {
			return null;
		}
		return {
			tooltip: { ...TOOLTIP, formatter: ( p ) => esc( p.treePathInfo.map( ( x ) => x.name ).filter( Boolean ).join( ' › ' ) ) + ' <b>' + formatNumber( p.value ) + '</b>' },
			series: [ {
				type: 'treemap',
				data,
				leafDepth: 1,
				roam: false,
				nodeClick: 'zoomToNode',
				width: '100%',
				height: '88%',
				top: 0,
				breadcrumb: { show: true, bottom: 0, height: 20, itemStyle: { color: '#f2f4f7', textStyle: { color: '#3c434a', fontSize: 11 } } },
				label: { show: true, formatter: '{b}', fontSize: 12, color: '#fff' },
				upperLabel: { show: true, height: 22, fontSize: 12, color: '#fff' },
				itemStyle: { borderColor: '#fff', borderWidth: 2, gapWidth: 2, borderRadius: 4 },
				levels: [
					{ itemStyle: { borderWidth: 0, gapWidth: 4 }, upperLabel: { show: false } },
					{ colorSaturation: [ 0.35, 0.6 ], itemStyle: { borderColorSaturation: 0.65, gapWidth: 2, borderWidth: 2 } },
				],
			} ],
		};
	}, [ botSources, labels, config ] );
	return option ? <EChart option={ option } height={ 260 } /> : null;
}

/** Sankey: AI bots (left) → pages they read (right). */
export function SourcePageSankey( { matrix } ) {
	const option = useMemo( () => {
		const nodes = {};
		const links = [];
		( matrix || [] ).slice( 0, 8 ).forEach( ( row ) => {
			const page = 'P:' + ( row.title || row.url || '' );
			nodes[ page ] = { name: page, itemStyle: { color: '#2271b1' } };
			( row.sources || [] ).slice( 0, 6 ).forEach( ( s ) => {
				const src = 'S:' + s.label;
				nodes[ src ] = { name: src, itemStyle: { color: '#7c3aed' } };
				links.push( { source: src, target: page, value: s.count } );
			} );
		} );
		const data = Object.keys( nodes ).map( ( k ) => nodes[ k ] );
		if ( ! links.length ) {
			return null;
		}
		const short = ( s ) => ( s.length > 34 ? s.slice( 0, 32 ) + '…' : s );
		return {
			tooltip: {
				...TOOLTIP,
				trigger: 'item',
				formatter: ( p ) => ( p.dataType === 'edge'
					? esc( p.data.source.slice( 2 ) ) + ' → ' + esc( p.data.target.slice( 2 ) ) + ' <b>' + formatNumber( p.data.value ) + '</b>'
					: esc( p.name.slice( 2 ) ) + ' <b>' + formatNumber( p.value ) + '</b>' ),
			},
			series: [ {
				type: 'sankey',
				left: 8,
				right: 210,
				top: 8,
				bottom: 8,
				nodeWidth: 14,
				nodeGap: 10,
				nodeAlign: 'justify',
				draggable: false,
				emphasis: { focus: 'adjacency' },
				data,
				links,
				label: { formatter: ( p ) => short( p.name.slice( 2 ) ), fontSize: 11, color: '#3c434a' },
				lineStyle: { color: 'gradient', curveness: 0.5, opacity: 0.35 },
				itemStyle: { borderWidth: 0, borderRadius: 3 },
			} ],
		};
	}, [ matrix ] );
	const height = option ? Math.max( 240, Math.min( 460, option.series[ 0 ].data.length * 24 ) ) : 0;
	return option ? <EChart option={ option } height={ height } /> : null;
}

/** Heatmap: bot accesses by weekday × hour (site timezone). */
export function CrawlHeatmap( { heatmap, weekdays, t } ) {
	const option = useMemo( () => {
		if ( ! heatmap || ! heatmap.length ) {
			return null;
		}
		const days = ( weekdays && weekdays.length === 7 ) ? weekdays : [ 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su' ];
		const max = heatmap.reduce( ( a, d ) => Math.max( a, d[ 2 ] ), 0 );
		return {
			tooltip: { ...TOOLTIP, formatter: ( p ) => esc( days[ p.value[ 1 ] ] ) + ' ' + p.value[ 0 ] + ':00 – <b>' + formatNumber( p.value[ 2 ] ) + '</b>' },
			grid: { left: 40, right: 10, top: 8, bottom: 52 },
			xAxis: { type: 'category', data: Array.from( { length: 24 }, ( _, i ) => i ), axisLabel: { ...AXIS_LABEL, interval: 2 }, axisTick: { show: false }, axisLine: AXIS_LINE, name: t( 'hourLabel', 'Hour' ), nameLocation: 'middle', nameGap: 22, nameTextStyle: AXIS_LABEL, splitArea: { show: false } },
			yAxis: { type: 'category', data: days, inverse: true, axisLabel: AXIS_LABEL, axisTick: { show: false }, axisLine: { show: false } },
			visualMap: { min: 0, max: Math.max( 1, max ), show: false, inRange: { color: [ '#eef4fa', '#9cc3e6', '#2271b1', '#0a4b78' ] } },
			series: [ {
				type: 'heatmap',
				data: heatmap.map( ( d ) => [ d[ 1 ], d[ 0 ], d[ 2 ] ] ),
				itemStyle: { borderColor: '#fff', borderWidth: 2, borderRadius: 3 },
				emphasis: { itemStyle: { shadowBlur: 6, shadowColor: 'rgba(0,0,0,.2)' } },
			} ],
		};
	}, [ heatmap, weekdays, t ] );
	return option ? <EChart option={ option } height={ 240 } /> : null;
}
