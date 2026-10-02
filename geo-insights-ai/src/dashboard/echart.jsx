/**
 * Thin React wrapper around Apache ECharts (tree-shaken via echarts/core).
 * One instance per chart, resize-aware, disposes on unmount, honors
 * prefers-reduced-motion.
 */
import { useEffect, useMemo, useRef } from 'react';
import * as echarts from 'echarts/core';
import {
	LineChart,
	BarChart,
	PieChart,
	ScatterChart,
	TreemapChart,
	SankeyChart,
	HeatmapChart,
	GaugeChart,
} from 'echarts/charts';
import {
	GridComponent,
	TooltipComponent,
	LegendComponent,
	DataZoomComponent,
	VisualMapComponent,
	TitleComponent,
	MarkLineComponent,
} from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use( [
	LineChart,
	BarChart,
	PieChart,
	ScatterChart,
	TreemapChart,
	SankeyChart,
	HeatmapChart,
	GaugeChart,
	GridComponent,
	TooltipComponent,
	LegendComponent,
	DataZoomComponent,
	VisualMapComponent,
	TitleComponent,
	MarkLineComponent,
	CanvasRenderer,
] );

export const FONT = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';

export const TOOLTIP = {
	backgroundColor: '#fff',
	borderColor: '#dcdcde',
	borderWidth: 1,
	padding: [ 8, 10 ],
	textStyle: { color: '#1d2327', fontSize: 12, fontFamily: FONT },
	extraCssText: 'box-shadow:0 4px 12px rgba(16,24,40,.08);border-radius:8px;',
};

function reducedMotion() {
	try {
		return window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	} catch ( e ) {
		return false;
	}
}

export { echarts };

export default function EChart( { option, height, onClick, className } ) {
	const ref = useRef( null );
	const inst = useRef( null );

	useEffect( () => {
		if ( ! ref.current ) {
			return undefined;
		}
		inst.current = echarts.init( ref.current, null, { renderer: 'canvas' } );
		const ro = new ResizeObserver( () => {
			if ( inst.current ) {
				inst.current.resize();
			}
		} );
		ro.observe( ref.current );
		return () => {
			ro.disconnect();
			if ( inst.current ) {
				inst.current.dispose();
				inst.current = null;
			}
		};
	}, [] );

	const full = useMemo(
		() => ( {
			animation: ! reducedMotion(),
			animationDuration: 500,
			animationEasing: 'cubicOut',
			textStyle: { fontFamily: FONT },
			...option,
		} ),
		[ option ]
	);

	useEffect( () => {
		if ( inst.current ) {
			inst.current.setOption( full, { notMerge: true } );
		}
	}, [ full ] );

	useEffect( () => {
		const chart = inst.current;
		if ( ! chart ) {
			return undefined;
		}
		chart.off( 'click' );
		if ( onClick ) {
			chart.on( 'click', onClick );
		}
		return () => chart.off( 'click' );
	}, [ onClick ] );

	return <div ref={ ref } className={ 'geoins-echart' + ( className ? ' ' + className : '' ) } style={ { width: '100%', height: height || 260 } } />;
}
