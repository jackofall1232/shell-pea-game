<?php
/**
 * Settings page for the Shell Pea Game plugin.
 *
 * @package ShellPeaGame
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SPG_Settings
 *
 * Registers the admin menu, settings fields and renders the settings page.
 */
class SPG_Settings {

	const OPTION_GROUP   = 'spg_settings';
	const PAGE_SLUG      = 'shell-pea-game';
	const SECTION_ID     = 'spg_main_section';
	const OPT_CURRENCY   = 'spg_currency_name';
	const OPT_BANK       = 'spg_starting_bank';
	const OPT_SPEED      = 'spg_shuffle_speed';

	/**
	 * Wire up WordPress hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register the Settings → Shell Game menu entry.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_options_page(
			__( 'Shell Pea Game', 'shell-pea-game' ),
			__( 'Shell Game', 'shell-pea-game' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register all options, the settings section and the fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPT_CURRENCY,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_currency' ),
				'default'           => 'Coins',
				'show_in_rest'      => false,
			)
		);

		register_setting(
			self::OPTION_GROUP,
			self::OPT_BANK,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_bank' ),
				'default'           => 100,
				'show_in_rest'      => false,
			)
		);

		register_setting(
			self::OPTION_GROUP,
			self::OPT_SPEED,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_speed' ),
				'default'           => 'medium',
				'show_in_rest'      => false,
			)
		);

		add_settings_section(
			self::SECTION_ID,
			__( 'Game Defaults', 'shell-pea-game' ),
			array( $this, 'render_section_intro' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			self::OPT_CURRENCY,
			__( 'Currency Name', 'shell-pea-game' ),
			array( $this, 'render_currency_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID,
			array( 'label_for' => self::OPT_CURRENCY )
		);

		add_settings_field(
			self::OPT_BANK,
			__( 'Starting Bank', 'shell-pea-game' ),
			array( $this, 'render_bank_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID,
			array( 'label_for' => self::OPT_BANK )
		);

		add_settings_field(
			self::OPT_SPEED,
			__( 'Shuffle Speed', 'shell-pea-game' ),
			array( $this, 'render_speed_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID,
			array( 'label_for' => self::OPT_SPEED )
		);
	}

	/**
	 * Sanitize the currency name to plain text.
	 *
	 * @param mixed $value Raw input.
	 * @return string
	 */
	public function sanitize_currency( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		$value = sanitize_text_field( $value );
		if ( '' === $value ) {
			$value = 'Coins';
		}
		// Hard cap so a stray paste cannot produce huge values in localized data.
		if ( strlen( $value ) > 32 ) {
			$value = substr( $value, 0, 32 );
		}
		return $value;
	}

	/**
	 * Sanitize and clamp the starting bank.
	 *
	 * @param mixed $value Raw input.
	 * @return int
	 */
	public function sanitize_bank( $value ) {
		$value = absint( $value );
		if ( $value < 50 ) {
			$value = 50;
		}
		if ( $value > 10000 ) {
			$value = 10000;
		}
		return $value;
	}

	/**
	 * Sanitize the shuffle speed select.
	 *
	 * @param mixed $value Raw input.
	 * @return string
	 */
	public function sanitize_speed( $value ) {
		$value   = is_scalar( $value ) ? strtolower( sanitize_text_field( (string) $value ) ) : '';
		$allowed = array( 'slow', 'medium', 'fast' );
		if ( ! in_array( $value, $allowed, true ) ) {
			return 'medium';
		}
		return $value;
	}

	/**
	 * Section description.
	 *
	 * @return void
	 */
	public function render_section_intro() {
		echo '<p>' . esc_html__( 'Defaults applied to every [shell_game] shortcode. Inline shortcode attributes always win.', 'shell-pea-game' ) . '</p>';
	}

	/**
	 * Currency text input.
	 *
	 * @return void
	 */
	public function render_currency_field() {
		$value = get_option( self::OPT_CURRENCY, 'Coins' );
		printf(
			'<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text" maxlength="32" />',
			esc_attr( self::OPT_CURRENCY ),
			esc_attr( $value )
		);
		echo '<p class="description">' . esc_html__( 'Label used in the bank display (e.g. Coins, Chips, Doubloons).', 'shell-pea-game' ) . '</p>';
	}

	/**
	 * Starting bank number input.
	 *
	 * @return void
	 */
	public function render_bank_field() {
		$value = (int) get_option( self::OPT_BANK, 100 );
		printf(
			'<input type="number" id="%1$s" name="%1$s" value="%2$d" min="50" max="10000" step="1" class="small-text" />',
			esc_attr( self::OPT_BANK ),
			(int) $value
		);
		echo '<p class="description">' . esc_html__( 'Player begins each game with this many units. Min 50, max 10000.', 'shell-pea-game' ) . '</p>';
	}

	/**
	 * Shuffle speed select.
	 *
	 * @return void
	 */
	public function render_speed_field() {
		$value   = get_option( self::OPT_SPEED, 'medium' );
		$choices = array(
			'slow'   => __( 'Slow', 'shell-pea-game' ),
			'medium' => __( 'Medium', 'shell-pea-game' ),
			'fast'   => __( 'Fast', 'shell-pea-game' ),
		);
		printf( '<select id="%1$s" name="%1$s">', esc_attr( self::OPT_SPEED ) );
		foreach ( $choices as $key => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $key ),
				selected( $value, $key, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Controls how quickly the shells swap positions.', 'shell-pea-game' ) . '</p>';
	}

	/**
	 * Render the settings page wrapper.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Shell Pea Game', 'shell-pea-game' ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'Shortcode', 'shell-pea-game' ); ?></h2>
			<p><?php echo esc_html__( 'Paste this shortcode into any post, page, or widget area to embed the game:', 'shell-pea-game' ); ?></p>
			<p>
				<input
					type="text"
					readonly
					value="[shell_game]"
					class="regular-text code"
					onclick="this.select();"
					aria-label="<?php echo esc_attr__( 'Shortcode', 'shell-pea-game' ); ?>"
				/>
			</p>
			<p><?php echo esc_html__( 'Override the defaults inline, e.g.:', 'shell-pea-game' ); ?></p>
			<p>
				<code>[shell_game currency="Chips" starting_bank="500" shuffle_speed="fast"]</code>
			</p>
		</div>
		<?php
	}
}
