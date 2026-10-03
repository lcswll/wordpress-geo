<?php
/**
 * "How it works" – a plain-language explainer for non-technical users:
 * what GEO is, how the plugin works, why it matters, and what results
 * are realistic. No hype, honest expectations.
 *
 * @package Wille_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap geoins-wrap geoins-learn">
	<?php GEOINS_Admin::header( 'wille-geo-learn' ); ?>
	<h1><?php esc_html_e( 'How it works – GEO explained in plain language', 'wille-geo-ai-visibility' ); ?></h1>
	<p class="geoins-intro"><?php esc_html_e( 'No jargon, no hype: what is happening, what this plugin does about it, and what results you can realistically expect.', 'wille-geo-ai-visibility' ); ?></p>

	<div class="geoins-panel">
		<h2><?php esc_html_e( 'What is happening right now?', 'wille-geo-ai-visibility' ); ?></h2>
		<p><?php esc_html_e( 'More and more people do not type their questions into Google anymore – they ask ChatGPT, Perplexity, Copilot or Claude. The AI reads a handful of websites, writes one answer, and names its sources. If your site is one of those sources, you get mentioned and linked. If not, you are invisible in that conversation – even if you rank well in classic Google.', 'wille-geo-ai-visibility' ); ?></p>
		<p><?php esc_html_e( 'Making your site a source AI systems find, understand and cite is called GEO (Generative Engine Optimization). It is the same idea as SEO, just for AI answers instead of blue links – and because far fewer sites do it yet, early movers are disproportionately visible.', 'wille-geo-ai-visibility' ); ?></p>
		<p><?php esc_html_e( 'Classic SEO does not become worthless – Google is still the biggest source of visitors for most sites. GEO comes on top: the same clear, well-structured content that AI systems love also tends to rank better in classic search. You lose nothing by optimizing for both.', 'wille-geo-ai-visibility' ); ?></p>
	</div>

	<div class="geoins-panel">
		<h2><?php esc_html_e( 'How does an AI even reach my website?', 'wille-geo-ai-visibility' ); ?></h2>
		<p><?php esc_html_e( 'AI companies send automated visitors – bots – to your site. They identify themselves by name, and the name tells you exactly why they came. Three kinds matter, and they mean very different things for your business:', 'wille-geo-ai-visibility' ); ?></p>
		<ol>
			<li><strong><?php esc_html_e( 'Agent bots', 'wille-geo-ai-visibility' ); ?></strong> – <?php esc_html_e( 'a real person just asked an AI a question, and the AI is reading your page right now to answer it. This is the most valuable signal there is: someone is interested in your topic at this very moment.', 'wille-geo-ai-visibility' ); ?></li>
			<li><strong><?php esc_html_e( 'Retrieval bots', 'wille-geo-ai-visibility' ); ?></strong> – <?php esc_html_e( 'they build the library the AI quotes from later. Being in that library decides whether you can be cited at all.', 'wille-geo-ai-visibility' ); ?></li>
			<li><strong><?php esc_html_e( 'Training bots', 'wille-geo-ai-visibility' ); ?></strong> – <?php esc_html_e( 'they collect text to train future AI models. They bring you no visitors and no citations. Whether you allow them is purely a content-rights decision – blocking them costs you nothing.', 'wille-geo-ai-visibility' ); ?></li>
		</ol>
		<p><?php esc_html_e( 'This plugin recognizes these bots on every visit and shows you in the dashboard: which AI reads which of your pages, how often, and for which topic. And when a person clicks the link to your site inside an AI answer, that visit is counted too – that is the moment AI visibility turns into a potential customer.', 'wille-geo-ai-visibility' ); ?></p>
	</div>

	<div class="geoins-panel">
		<h2><?php esc_html_e( 'What does the plugin actually do for me?', 'wille-geo-ai-visibility' ); ?></h2>
		<p><?php esc_html_e( 'Two things: it measures, and it optimizes. Everything runs on your own server, nothing is sent anywhere, and every switch in the settings explains its benefit before you flip it.', 'wille-geo-ai-visibility' ); ?></p>
		<h3><?php esc_html_e( 'It measures', 'wille-geo-ai-visibility' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'Every AI bot visit, sorted by what it means for you (agent, retrieval, training).', 'wille-geo-ai-visibility' ); ?></li>
			<li><?php esc_html_e( 'Real people arriving from AI answers – the actual payoff, per AI and per page.', 'wille-geo-ai-visibility' ); ?></li>
			<li><?php esc_html_e( 'On request, proof: impostor bots that fake a famous name are unmasked via the official IP lists of the AI companies.', 'wille-geo-ai-visibility' ); ?></li>
		</ul>
		<h3><?php esc_html_e( 'It optimizes', 'wille-geo-ai-visibility' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'A live checklist while you write: thirteen concrete, explained criteria that make a page quotable for AI (answer first, question headings, lists, numbers, freshness …).', 'wille-geo-ai-visibility' ); ?></li>
			<li><?php esc_html_e( 'Machine-readable versions of your content (llms.txt, Markdown pages, structured data) – so AI systems understand your site with as little friction as possible.', 'wille-geo-ai-visibility' ); ?></li>
			<li><?php esc_html_e( 'Control over who may read your content: allow the bots that cite you, block the ones that only take – with a clear warning before you block something that would cost you visibility.', 'wille-geo-ai-visibility' ); ?></li>
			<li><?php esc_html_e( 'Speed: new and updated content is announced to search engines within minutes (IndexNow) – the sooner it is indexed, the sooner an AI can cite it.', 'wille-geo-ai-visibility' ); ?></li>
		</ul>
	</div>

	<div class="geoins-panel">
		<h2><?php esc_html_e( 'What can I realistically expect? An honest answer', 'wille-geo-ai-visibility' ); ?></h2>
		<p><?php esc_html_e( 'Be skeptical of anyone promising you a flood of customers from AI search. Here is the honest picture:', 'wille-geo-ai-visibility' ); ?></p>
		<ul>
			<li><strong><?php esc_html_e( 'Volume: still small, growing fast.', 'wille-geo-ai-visibility' ); ?></strong> <?php esc_html_e( 'For most sites, visitors from AI answers are today a low single-digit percentage of what Google sends – but that share has been climbing steeply every quarter. The point of starting now is being established as a source before your competitors are.', 'wille-geo-ai-visibility' ); ?></li>
			<li><strong><?php esc_html_e( 'Quality: unusually high.', 'wille-geo-ai-visibility' ); ?></strong> <?php esc_html_e( 'Someone who clicks through from an AI answer has already read a summary and wants the depth – studies and shop data consistently show these visitors convert noticeably better than average search traffic. Fewer visitors, but warmer ones.', 'wille-geo-ai-visibility' ); ?></li>
			<li><strong><?php esc_html_e( 'Invisible impact: bigger than the click numbers.', 'wille-geo-ai-visibility' ); ?></strong> <?php esc_html_e( 'Many people read the AI answer that cites you and never click – but they saw your name as the source. That is brand visibility no statistic can fully capture; the citation itself is the win.', 'wille-geo-ai-visibility' ); ?></li>
			<li><strong><?php esc_html_e( 'Timeline: weeks to months, not days.', 'wille-geo-ai-visibility' ); ?></strong> <?php esc_html_e( 'Retrieval bots need to find and re-read your improved pages first. Expect first measurable effects after a few weeks and a fair verdict after three to six months – the dashboard shows you the trend the whole way.', 'wille-geo-ai-visibility' ); ?></li>
			<li><strong><?php esc_html_e( 'The honest limit: content decides.', 'wille-geo-ai-visibility' ); ?></strong> <?php esc_html_e( 'No tool can force an AI to cite you. AI systems cite pages that answer a real question clearly, concretely and credibly. This plugin makes exactly that measurable and systematically easier – it removes every technical obstacle and shows you what works. The substance still has to come from you.', 'wille-geo-ai-visibility' ); ?></li>
		</ul>
		<p><?php esc_html_e( 'A realistic example: a specialist site with 50 solid articles that adopts the GEO checklist should expect its first AI citations and a small but steadily growing stream of high-intent visitors within a few months – think of it as opening a second, still-small door to your business that gets wider every month, not as replacing Google.', 'wille-geo-ai-visibility' ); ?></p>
	</div>

	<div class="geoins-panel">
		<h2><?php esc_html_e( 'Where do I start? Five steps, 30 minutes', 'wille-geo-ai-visibility' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Check the GEO status panel on the statistics page – fix anything that is not green. That removes the technical blockers.', 'wille-geo-ai-visibility' ); ?></li>
			<li><?php esc_html_e( 'Open your five most important pages and set a focus term for each: the question or topic this page should be THE answer for.', 'wille-geo-ai-visibility' ); ?></li>
			<li><?php esc_html_e( 'Work through the GEO checklist on those pages – especially: answer the core question directly in the first paragraph, and turn headings into real questions.', 'wille-geo-ai-visibility' ); ?></li>
			<li><?php esc_html_e( 'Pin your best pages as key content, so llms.txt leads AI agents straight to them.', 'wille-geo-ai-visibility' ); ?></li>
			<li><?php esc_html_e( 'Then let it run. Check the dashboard weekly (or enable the email report) and invest where AI interest is already showing – that is where citations are closest.', 'wille-geo-ai-visibility' ); ?></li>
		</ol>
		<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wille-geo' ) ); ?>"><?php esc_html_e( 'Open the statistics dashboard', 'wille-geo-ai-visibility' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wille-geo-settings' ) ); ?>"><?php esc_html_e( 'Open the settings', 'wille-geo-ai-visibility' ); ?></a>
			</p>
		<?php else : ?>
			<p><?php esc_html_e( 'Open any post in the editor – the “GEO check” panel in the sidebar walks you through exactly these steps while you write.', 'wille-geo-ai-visibility' ); ?></p>
		<?php endif; ?>
	</div>
</div>
