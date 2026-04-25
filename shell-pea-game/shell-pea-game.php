<?php
/**
 * Plugin Name:       Shell Pea Game
 * Plugin URI:        https://example.com/shell-pea-game
 * Description:       A classic three-shell-and-a-pea game embedded via the [shell_game] shortcode. Bet your virtual coins, watch the shuffle, and try to follow the pea.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Shell Pea Game Contributors
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       shell-pea-game
 * Domain Path:       /languages
 *
 * @package ShellPeaGame
 */

defined( 'ABSPATH' ) || exit;

define( 'SPG_VERSION', '1.0.0' );
define( 'SPG_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPG_URL', plugin_dir_url( __FILE__ ) );

require_once SPG_DIR . 'includes/class-spg-settings.php';

/**
 * Boot the plugin.
 *
 * @return void
 */
function spg_init() {
	if ( is_admin() ) {
		new SPG_Settings();
	}

	add_shortcode( 'shell_game', 'spg_render_shortcode' );
	add_action( 'wp_enqueue_scripts', 'spg_maybe_enqueue_assets' );
}
add_action( 'plugins_loaded', 'spg_init' );

/**
 * Detect whether the current request will render the shortcode.
 *
 * Checks the queried post content for the [shell_game] shortcode.
 *
 * @return bool
 */
function spg_post_has_shortcode() {
	if ( ! is_singular() ) {
		return false;
	}

	$post = get_post();
	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	return has_shortcode( $post->post_content, 'shell_game' );
}

/**
 * Conditionally enqueue the front-end assets when the shortcode is present.
 *
 * @return void
 */
function spg_maybe_enqueue_assets() {
	if ( ! spg_post_has_shortcode() ) {
		return;
	}

	spg_enqueue_assets();
}

/**
 * Register and enqueue the JS/CSS plus localize data.
 *
 * Also exposed so that the shortcode callback can call it directly when the
 * shortcode is rendered outside of normal post content (e.g. via do_shortcode
 * inside a template).
 *
 * @return void
 */
function spg_enqueue_assets() {
	if ( wp_script_is( 'spg-shell-game', 'enqueued' ) ) {
		return;
	}

	wp_enqueue_style(
		'spg-shell-game',
		SPG_URL . 'assets/shell-game.css',
		array(),
		SPG_VERSION
	);

	wp_enqueue_script(
		'spg-shell-game',
		SPG_URL . 'assets/shell-game.js',
		array(),
		SPG_VERSION,
		true
	);

	$defaults = spg_get_default_settings();

	wp_localize_script(
		'spg-shell-game',
		'SPGData',
		array(
			'currency_name' => $defaults['currency_name'],
			'starting_bank' => $defaults['starting_bank'],
			'shuffle_speed' => $defaults['shuffle_speed'],
		)
	);
}

/**
 * Pull the saved settings (with sane defaults) used by the front-end.
 *
 * @return array{currency_name:string,starting_bank:int,shuffle_speed:string}
 */
function spg_get_default_settings() {
	$currency = get_option( 'spg_currency_name', 'Coins' );
	$bank     = get_option( 'spg_starting_bank', 100 );
	$speed    = get_option( 'spg_shuffle_speed', 'medium' );

	return array(
		'currency_name' => sanitize_text_field( (string) $currency ),
		'starting_bank' => spg_clamp_bank( absint( $bank ) ),
		'shuffle_speed' => spg_sanitize_speed( (string) $speed ),
	);
}

/**
 * Clamp the starting bank to the allowed range.
 *
 * @param int $value Raw integer value.
 * @return int
 */
function spg_clamp_bank( $value ) {
	$value = (int) $value;
	if ( $value < 50 ) {
		$value = 50;
	}
	if ( $value > 10000 ) {
		$value = 10000;
	}
	return $value;
}

/**
 * Sanitize the shuffle speed value to one of the allowed strings.
 *
 * @param string $value Raw speed string.
 * @return string
 */
function spg_sanitize_speed( $value ) {
	$value   = strtolower( sanitize_text_field( $value ) );
	$allowed = array( 'slow', 'medium', 'fast' );
	if ( ! in_array( $value, $allowed, true ) ) {
		return 'medium';
	}
	return $value;
}

/**
 * Render the [shell_game] shortcode.
 *
 * Inline attributes override the saved settings when present.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function spg_render_shortcode( $atts ) {
	$defaults = spg_get_default_settings();

	$atts = shortcode_atts(
		array(
			'currency'      => $defaults['currency_name'],
			'starting_bank' => $defaults['starting_bank'],
			'shuffle_speed' => $defaults['shuffle_speed'],
		),
		(array) $atts,
		'shell_game'
	);

	$currency      = sanitize_text_field( (string) $atts['currency'] );
	$starting_bank = spg_clamp_bank( absint( $atts['starting_bank'] ) );
	$shuffle_speed = spg_sanitize_speed( (string) $atts['shuffle_speed'] );

	// Make sure the assets are loaded even when the shortcode runs outside the main loop.
	spg_enqueue_assets();

	// Re-localize with the (possibly overridden) inline values for this instance.
	$instance_id = wp_unique_id( 'spg-instance-' );

	$config = array(
		'currency_name' => $currency,
		'starting_bank' => $starting_bank,
		'shuffle_speed' => $shuffle_speed,
	);

	ob_start();
	?>
	<div class="spg-game" id="<?php echo esc_attr( $instance_id ); ?>" data-spg-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
		<div class="spg-header">
			<div class="spg-stats">
				<div class="spg-stat">
					<span class="spg-stat-label"><?php echo esc_html( $currency ); ?></span>
					<span class="spg-stat-value spg-bank">0</span>
				</div>
				<div class="spg-stat">
					<span class="spg-stat-label"><?php echo esc_html__( 'Round', 'shell-pea-game' ); ?></span>
					<span class="spg-stat-value spg-round">1</span>
				</div>
				<div class="spg-stat">
					<span class="spg-stat-label"><?php echo esc_html__( 'High Score', 'shell-pea-game' ); ?></span>
					<span class="spg-stat-value spg-highscore">0</span>
				</div>
			</div>
		</div>

		<div class="spg-bet-controls" aria-label="<?php echo esc_attr__( 'Place your bet', 'shell-pea-game' ); ?>">
			<span class="spg-bet-label"><?php echo esc_html__( 'Bet:', 'shell-pea-game' ); ?></span>
			<button type="button" class="spg-bet-btn" data-bet="10">10</button>
			<button type="button" class="spg-bet-btn" data-bet="25">25</button>
			<button type="button" class="spg-bet-btn" data-bet="50">50</button>
			<button type="button" class="spg-bet-btn" data-bet="all"><?php echo esc_html__( 'All-in', 'shell-pea-game' ); ?></button>
			<button type="button" class="spg-shuffle-btn" disabled><?php echo esc_html__( 'Shuffle', 'shell-pea-game' ); ?></button>
		</div>

		<div class="spg-board" role="group" aria-label="<?php echo esc_attr__( 'Shells', 'shell-pea-game' ); ?>">
			<div class="spg-shell-slot" data-slot="0">
				<button type="button" class="spg-shell" data-shell="0" aria-label="<?php echo esc_attr__( 'Shell 1', 'shell-pea-game' ); ?>">
					<?php echo spg_shell_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
				</button>
				<div class="spg-pea" aria-hidden="true">
					<?php echo spg_pea_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
				</div>
			</div>
			<div class="spg-shell-slot" data-slot="1">
				<button type="button" class="spg-shell" data-shell="1" aria-label="<?php echo esc_attr__( 'Shell 2', 'shell-pea-game' ); ?>">
					<?php echo spg_shell_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
				</button>
				<div class="spg-pea" aria-hidden="true">
					<?php echo spg_pea_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
				</div>
			</div>
			<div class="spg-shell-slot" data-slot="2">
				<button type="button" class="spg-shell" data-shell="2" aria-label="<?php echo esc_attr__( 'Shell 3', 'shell-pea-game' ); ?>">
					<?php echo spg_shell_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
				</button>
				<div class="spg-pea" aria-hidden="true">
					<?php echo spg_pea_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG markup. ?>
				</div>
			</div>
		</div>

		<div class="spg-status" aria-live="polite"><?php echo esc_html__( 'Place your bet to begin.', 'shell-pea-game' ); ?></div>

		<div class="spg-footer">
			<button type="button" class="spg-reset-btn"><?php echo esc_html__( 'Reset Game', 'shell-pea-game' ); ?></button>
			<button type="button" class="spg-mute-btn" aria-pressed="false"><?php echo esc_html__( 'Sound: On', 'shell-pea-game' ); ?></button>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Inline SVG markup for a single walnut-toned shell.
 *
 * Kept small and self-contained so we never need an external image asset.
 *
 * @return string
 */
function spg_shell_svg() {
	return '<svg class="spg-shell-svg" viewBox="0 0 120 100" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
		. '<defs>'
		. '<radialGradient id="spg-shell-grad" cx="50%" cy="35%" r="65%">'
		. '<stop offset="0%" stop-color="#C4845A"/>'
		. '<stop offset="55%" stop-color="#8B5E3C"/>'
		. '<stop offset="100%" stop-color="#6B3F1F"/>'
		. '</radialGradient>'
		. '<filter id="spg-shell-shadow" x="-20%" y="-20%" width="140%" height="140%">'
		. '<feGaussianBlur in="SourceAlpha" stdDeviation="2"/>'
		. '<feOffset dx="0" dy="3" result="offsetblur"/>'
		. '<feComponentTransfer><feFuncA type="linear" slope="0.45"/></feComponentTransfer>'
		. '<feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge>'
		. '</filter>'
		. '</defs>'
		. '<path d="M10 90 Q10 25 60 15 Q110 25 110 90 Q60 100 10 90 Z" fill="url(#spg-shell-grad)" stroke="#5A341A" stroke-width="1.5" filter="url(#spg-shell-shadow)"/>'
		. '<path d="M30 35 Q60 20 90 35" stroke="#C4845A" stroke-width="1.2" fill="none" opacity="0.55"/>'
		. '<path d="M22 55 Q60 40 98 55" stroke="#C4845A" stroke-width="1" fill="none" opacity="0.4"/>'
		. '<ellipse cx="60" cy="92" rx="48" ry="4" fill="#3F2611" opacity="0.55"/>'
		. '</svg>';
}

/**
 * Inline SVG markup for the bright green pea.
 *
 * @return string
 */
function spg_pea_svg() {
	return '<svg class="spg-pea-svg" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
		. '<defs>'
		. '<radialGradient id="spg-pea-grad" cx="35%" cy="30%" r="70%">'
		. '<stop offset="0%" stop-color="#A6E07F"/>'
		. '<stop offset="60%" stop-color="#4CAF50"/>'
		. '<stop offset="100%" stop-color="#2E7D32"/>'
		. '</radialGradient>'
		. '</defs>'
		. '<circle cx="20" cy="20" r="14" fill="url(#spg-pea-grad)" stroke="#1B5E20" stroke-width="0.8"/>'
		. '<ellipse cx="14" cy="14" rx="4" ry="2.5" fill="#FFFFFF" opacity="0.75"/>'
		. '</svg>';
}
