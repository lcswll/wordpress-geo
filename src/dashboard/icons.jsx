/**
 * Brand icons for AI sources.
 *
 * Real brand marks come from Simple Icons (CC0-licensed collection,
 * https://simpleicons.org – trademarks remain their owners' property and
 * are used for identification only). Brands that had their icons removed
 * from that set at the owner's request (OpenAI, Microsoft, Amazon …)
 * get a monogram badge in their brand color instead – we do not bundle
 * logos we have no license to redistribute.
 */
import {
	siAnthropic,
	siClaude,
	siPerplexity,
	siGoogle,
	siGooglegemini,
	siMeta,
	siApple,
	siBytedance,
	siDuckduckgo,
	siMistralai,
	siHuawei,
	siDeepseek,
	siX,
} from 'simple-icons';

const SIMPLE = {
	anthropic: siAnthropic,
	claude: siClaude,
	perplexity: siPerplexity,
	google: siGoogle,
	gemini: siGooglegemini,
	meta: siMeta,
	apple: siApple,
	bytedance: siBytedance,
	duckduckgo: siDuckduckgo,
	mistral: siMistralai,
	huawei: siHuawei,
	deepseek: siDeepseek,
	x: siX,
};

// Monogram fallbacks in (approximate) brand colors.
const MONO = {
	openai: { letter: 'O', bg: '#10A37F' },
	microsoft: { letter: 'M', bg: '#0078D4' },
	bing: { letter: 'B', bg: '#0078D4' },
	copilot: { letter: 'C', bg: '#0078D4' },
	amazon: { letter: 'A', bg: '#FF9900' },
	commoncrawl: { letter: 'C', bg: '#5A6472' },
	you: { letter: 'Y', bg: '#6E56CF' },
	ai2: { letter: 'A', bg: '#F0529C' },
	cohere: { letter: 'C', bg: '#39594D' },
	diffbot: { letter: 'D', bg: '#14B8A6' },
	timpi: { letter: 'T', bg: '#4F46E5' },
	webz: { letter: 'W', bg: '#0EA5E9' },
};

/**
 * One brand icon. Renders a Simple Icons SVG path, a colored monogram
 * badge, or (unknown icon key) a neutral monogram from the label.
 */
export function BrandIcon( { icon, label, size } ) {
	const px = size || 16;
	const simple = SIMPLE[ icon ];

	if ( simple ) {
		return (
			<svg
				className="geoins-brandicon"
				width={ px }
				height={ px }
				viewBox="0 0 24 24"
				role="img"
				aria-hidden="true"
				focusable="false"
			>
				<path d={ simple.path } fill={ '#' + simple.hex } />
			</svg>
		);
	}

	const mono = MONO[ icon ] || { letter: ( label || '?' ).charAt( 0 ).toUpperCase(), bg: '#8c8f94' };
	return (
		<span
			className="geoins-brandicon geoins-brandicon-mono"
			style={ { width: px, height: px, background: mono.bg, fontSize: Math.round( px * 0.62 ), lineHeight: px + 'px' } }
			aria-hidden="true"
		>
			{ mono.letter }
		</span>
	);
}

/** Resolve the icon key for a source slug from the localized directory. */
export function iconForSource( config, slug ) {
	const meta = config.sources && config.sources[ slug ];
	return meta ? meta.icon : '';
}
