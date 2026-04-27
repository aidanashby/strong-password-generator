<?php
/**
 * Plugin Name: Strong Password Generator
 * Plugin URI:  https://lucidrhino.design
 * Description: Adds a shortcode that generates strong, memorable passwords
 * Version:     1.0.0
 * Author:      Aidan Ashby
 * Author URI:  https://lucidrhino.design
 * Requires at least: 6.0
 * Tested up to:      6.7
 * Requires PHP:      7.4
 * Text Domain:       strong-password-generator
 * License:           GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Strong_Password_Generator {

	private array $defaults = [
		'password_count'      => 3,
		'mode'                => 'passphrase',
		// Passphrase mode
		'word_count'          => 4,
		'separator'           => '-',
		'title_case'          => true,
		'include_digit'       => true,
		'special_char'        => 'random',
		// Super strong mode
		'char_count'          => 16,
		'superstrong_upper'   => true,
		'superstrong_lower'   => true,
		'superstrong_digits'  => true,
		'superstrong_special' => true,
	];

	public function __construct() {
		add_shortcode( 'password_generator', [ $this, 'password_generator_shortcode' ] );
		add_action( 'wp_ajax_generate_passwords',        [ $this, 'generate_passwords_callback' ] );
		add_action( 'wp_ajax_nopriv_generate_passwords', [ $this, 'generate_passwords_callback' ] );
		add_action( 'admin_menu',  [ $this, 'admin_menu' ] );
		add_action( 'admin_init',  [ $this, 'admin_init' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'plugin_action_links' ] );
	}

	public function plugin_action_links( array $links ): array {
		$settings_link = '<a href="' . admin_url( 'options-general.php?page=strong-password-generator' ) . '">'
			. esc_html__( 'Settings', 'strong-password-generator' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	// -------------------------------------------------------------------------
	// Settings
	// -------------------------------------------------------------------------

	private function get_settings(): array {
		$saved = get_option( 'spg_settings', [] );
		return array_merge( $this->defaults, is_array( $saved ) ? $saved : [] );
	}

	// -------------------------------------------------------------------------
	// Word list
	// -------------------------------------------------------------------------

	private function load_words(): array {
		static $words = null;
		if ( $words !== null ) return $words;

		$path  = plugin_dir_path( __FILE__ ) . 'words.txt';
		$lines = @file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

		if ( $lines === false || count( $lines ) < 10 ) {
			wp_send_json_error( __( 'Word list unavailable.', 'strong-password-generator' ) );
			exit;
		}

		$words = array_values( array_map( 'trim', $lines ) );
		return $words;
	}

	// -------------------------------------------------------------------------
	// Password generation
	// -------------------------------------------------------------------------

	private function generate_passphrase( array $words, array $s ): string {
		$pool_size = count( $words );
		$count     = (int) $s['word_count'];

		// Draw without replacement using CSPRNG
		$indices = [];
		while ( count( $indices ) < $count ) {
			$idx = random_int( 0, $pool_size - 1 );
			if ( ! in_array( $idx, $indices, true ) ) {
				$indices[] = $idx;
			}
		}

		$selected = array_map(
			function ( $i ) use ( $words, $s ) {
				$word = $words[ $i ];
				return $s['title_case'] ? ucfirst( $word ) : $word;
			},
			$indices
		);

		$password = implode( $s['separator'], $selected );

		if ( $s['include_digit'] ) {
			$digit = (string) random_int( 0, 9 );
			$password = random_int( 0, 1 ) === 0
				? $digit . $password
				: $password . $digit;
		}

		$style = $s['special_char'];
		if ( $style === 'random' ) {
			$style = [ '!', '?', '#', '()' ][ random_int( 0, 3 ) ];
		}

		switch ( $style ) {
			case '!': $password .= '!'; break;
			case '?': $password .= '?'; break;
			case '#': $password  = '#' . $password; break;
			case '()': $password = '(' . $password . ')'; break;
		}

		return $password;
	}

	private function generate_superstrong( array $s ): string {
		// Ambiguous chars always excluded: uppercase I, O; lowercase l, o; digits 0, 1; pipe |
		$sets = [
			'upper'   => $s['superstrong_upper']   ? 'ABCDEFGHJKLMNPQRSTUVWXYZ' : '',
			'lower'   => $s['superstrong_lower']   ? 'abcdefghijkmnpqrstuvwxyz' : '',
			'digits'  => $s['superstrong_digits']  ? '23456789'                  : '',
			'special' => $s['superstrong_special'] ? '!@#$%^&*-_=+?'            : '',
		];

		$pool = implode( '', $sets );

		// Fallback if all groups disabled
		if ( $pool === '' ) {
			$sets  = [
				'upper'  => 'ABCDEFGHJKLMNPQRSTUVWXYZ',
				'lower'  => 'abcdefghijkmnpqrstuvwxyz',
				'digits' => '23456789',
			];
			$pool  = implode( '', $sets );
		}

		$length   = (int) $s['char_count'];
		$password = [];

		// Guarantee at least one character from each enabled group
		foreach ( $sets as $set ) {
			if ( $set !== '' ) {
				$password[] = $set[ random_int( 0, strlen( $set ) - 1 ) ];
			}
		}

		// Fill remainder from the full pool
		$pool_len = strlen( $pool );
		while ( count( $password ) < $length ) {
			$password[] = $pool[ random_int( 0, $pool_len - 1 ) ];
		}

		// Fisher-Yates shuffle using CSPRNG
		for ( $i = count( $password ) - 1; $i > 0; $i-- ) {
			$j = random_int( 0, $i );
			[ $password[ $i ], $password[ $j ] ] = [ $password[ $j ], $password[ $i ] ];
		}

		return implode( '', $password );
	}

	// -------------------------------------------------------------------------
	// AJAX handler
	// -------------------------------------------------------------------------

	public function generate_passwords_callback(): void {
		check_ajax_referer( 'password_generator_nonce', 'nonce' );

		$s         = $this->get_settings();
		$count     = max( 1, min( 10, (int) $s['password_count'] ) );
		$passwords = [];

		if ( $s['mode'] === 'superstrong' ) {
			for ( $i = 0; $i < $count; $i++ ) {
				$passwords[] = $this->generate_superstrong( $s );
			}
		} else {
			$words = $this->load_words();
			for ( $i = 0; $i < $count; $i++ ) {
				$passwords[] = $this->generate_passphrase( $words, $s );
			}
		}

		wp_send_json_success( $passwords );
	}

	// -------------------------------------------------------------------------
	// Shortcode
	// -------------------------------------------------------------------------

	public function password_generator_shortcode(): string {
		// Enqueue only on pages that use this shortcode
		wp_enqueue_script(
			'password-generator-js',
			plugin_dir_url( __FILE__ ) . 'js/password-generator.js',
			[ 'jquery' ],
			'1.0.0',
			true
		);
		wp_enqueue_style(
			'password-generator-css',
			plugin_dir_url( __FILE__ ) . 'css/password-generator.css',
			[],
			'1.0.0'
		);
		wp_localize_script(
			'password-generator-js',
			'passwordGenerator',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'password_generator_nonce' ),
			]
		);
		wp_localize_script(
			'password-generator-js',
			'passwordGeneratorL10n',
			[
				'generating' => __( 'Generating passwords...', 'strong-password-generator' ),
				'error'      => __( 'Error generating passwords. Please try again.', 'strong-password-generator' ),
				'copy'       => __( 'Copy password', 'strong-password-generator' ),
				'copied'     => __( 'Copied!', 'strong-password-generator' ),
			]
		);

		return '<div class="password-generator-container">
			<button id="generate-password-btn" class="generate-password-btn">'
			. esc_html__( 'Generate Strong Passwords', 'strong-password-generator' )
			. '</button>
			<div id="password-results" class="password-results" aria-live="polite"></div>
		</div>';
	}

	// -------------------------------------------------------------------------
	// Admin settings
	// -------------------------------------------------------------------------

	public function admin_menu(): void {
		add_options_page(
			__( 'Strong Password Generator Settings', 'strong-password-generator' ),
			__( 'Strong Password Generator', 'strong-password-generator' ),
			'manage_options',
			'strong-password-generator',
			[ $this, 'admin_page' ]
		);
	}

	public function admin_init(): void {
		register_setting(
			'spg_settings_group',
			'spg_settings',
			[ $this, 'sanitise_settings' ]
		);
	}

	public function sanitise_settings( $input ): array {
		if ( ! is_array( $input ) ) $input = [];

		$clean = [];

		$clean['password_count'] = max( 1, min( 10, (int) ( $input['password_count'] ?? 3 ) ) );

		$clean['mode'] = in_array( $input['mode'] ?? '', [ 'passphrase', 'superstrong' ], true )
			? $input['mode']
			: 'passphrase';

		// Passphrase settings
		$clean['word_count']  = max( 2, min( 6, (int) ( $input['word_count'] ?? 4 ) ) );
		$clean['separator']   = in_array( $input['separator'] ?? '', [ '-', '.', '_', ' ', '' ], true )
			? $input['separator'] : '-';
		$clean['title_case']    = ! empty( $input['title_case'] );
		$clean['include_digit'] = ! empty( $input['include_digit'] );
		$clean['special_char']  = in_array( $input['special_char'] ?? '', [ 'random', '!', '?', '#', '()' ], true )
			? $input['special_char'] : 'random';

		// Super strong settings
		$clean['char_count']          = max( 8, min( 64, (int) ( $input['char_count'] ?? 16 ) ) );
		$clean['superstrong_upper']   = ! empty( $input['superstrong_upper'] );
		$clean['superstrong_lower']   = ! empty( $input['superstrong_lower'] );
		$clean['superstrong_digits']  = ! empty( $input['superstrong_digits'] );
		$clean['superstrong_special'] = ! empty( $input['superstrong_special'] );

		// Ensure at least one super strong group is enabled
		if ( ! $clean['superstrong_upper'] && ! $clean['superstrong_lower']
			&& ! $clean['superstrong_digits'] && ! $clean['superstrong_special'] ) {
			$clean['superstrong_upper']  = true;
			$clean['superstrong_lower']  = true;
			$clean['superstrong_digits'] = true;
		}

		return $clean;
	}

	public function admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$s = $this->get_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Strong Password Generator Settings', 'strong-password-generator' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'spg_settings_group' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="spg_password_count">
								<?php esc_html_e( 'Passwords shown', 'strong-password-generator' ); ?>
							</label>
						</th>
						<td>
							<input type="number" id="spg_password_count"
								name="spg_settings[password_count]"
								value="<?php echo esc_attr( $s['password_count'] ); ?>"
								min="1" max="10" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Mode', 'strong-password-generator' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="spg_settings[mode]" value="passphrase"
										<?php checked( $s['mode'], 'passphrase' ); ?> />
									<?php esc_html_e( 'Passphrase (word-based)', 'strong-password-generator' ); ?>
								</label><br>
								<label>
									<input type="radio" name="spg_settings[mode]" value="superstrong"
										<?php checked( $s['mode'], 'superstrong' ); ?> />
									<?php esc_html_e( 'Super strong (random characters)', 'strong-password-generator' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
				</table>

				<div id="spg-passphrase-settings"<?php echo $s['mode'] === 'superstrong' ? ' style="display:none"' : ''; ?>>
					<h2><?php esc_html_e( 'Passphrase Settings', 'strong-password-generator' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="spg_word_count">
									<?php esc_html_e( 'Word count', 'strong-password-generator' ); ?>
								</label>
							</th>
							<td>
								<input type="number" id="spg_word_count"
									name="spg_settings[word_count]"
									value="<?php echo esc_attr( $s['word_count'] ); ?>"
									min="2" max="6" />
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="spg_separator">
									<?php esc_html_e( 'Separator', 'strong-password-generator' ); ?>
								</label>
							</th>
							<td>
								<select id="spg_separator" name="spg_settings[separator]">
									<option value="-"<?php selected( $s['separator'], '-' ); ?>>- <?php esc_html_e( '(hyphen)', 'strong-password-generator' ); ?></option>
									<option value="."<?php selected( $s['separator'], '.' ); ?>>. <?php esc_html_e( '(dot)', 'strong-password-generator' ); ?></option>
									<option value="_"<?php selected( $s['separator'], '_' ); ?>>_ <?php esc_html_e( '(underscore)', 'strong-password-generator' ); ?></option>
									<option value=" "<?php selected( $s['separator'], ' ' ); ?>><?php esc_html_e( 'space', 'strong-password-generator' ); ?></option>
									<option value=""<?php selected( $s['separator'], '' ); ?>><?php esc_html_e( 'none', 'strong-password-generator' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Title case', 'strong-password-generator' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="spg_settings[title_case]" value="1"
										<?php checked( $s['title_case'] ); ?> />
									<?php esc_html_e( 'Capitalise the first letter of each word', 'strong-password-generator' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Include digit', 'strong-password-generator' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="spg_settings[include_digit]" value="1"
										<?php checked( $s['include_digit'] ); ?> />
									<?php esc_html_e( 'Append or prepend a random digit', 'strong-password-generator' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="spg_special_char">
									<?php esc_html_e( 'Special character', 'strong-password-generator' ); ?>
								</label>
							</th>
							<td>
								<select id="spg_special_char" name="spg_settings[special_char]">
									<option value="random"<?php selected( $s['special_char'], 'random' ); ?>><?php esc_html_e( 'Random', 'strong-password-generator' ); ?></option>
									<option value="!"<?php selected( $s['special_char'], '!' ); ?>>!</option>
									<option value="?"<?php selected( $s['special_char'], '?' ); ?>>?</option>
									<option value="#"<?php selected( $s['special_char'], '#' ); ?>>#</option>
									<option value="()"<?php selected( $s['special_char'], '()' ); ?>>()</option>
								</select>
							</td>
						</tr>
					</table>
				</div>

				<div id="spg-superstrong-settings"<?php echo $s['mode'] === 'passphrase' ? ' style="display:none"' : ''; ?>>
					<h2><?php esc_html_e( 'Super Strong Settings', 'strong-password-generator' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Ambiguous characters (0, O, I, l, 1, |) are always excluded.', 'strong-password-generator' ); ?>
					</p>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="spg_char_count">
									<?php esc_html_e( 'Character count', 'strong-password-generator' ); ?>
								</label>
							</th>
							<td>
								<input type="number" id="spg_char_count"
									name="spg_settings[char_count]"
									value="<?php echo esc_attr( $s['char_count'] ); ?>"
									min="8" max="64" />
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Character types', 'strong-password-generator' ); ?></th>
							<td>
								<fieldset>
									<label>
										<input type="checkbox" name="spg_settings[superstrong_upper]" value="1"
											<?php checked( $s['superstrong_upper'] ); ?> />
										<?php esc_html_e( 'Uppercase A-Z', 'strong-password-generator' ); ?>
									</label><br>
									<label>
										<input type="checkbox" name="spg_settings[superstrong_lower]" value="1"
											<?php checked( $s['superstrong_lower'] ); ?> />
										<?php esc_html_e( 'Lowercase a-z', 'strong-password-generator' ); ?>
									</label><br>
									<label>
										<input type="checkbox" name="spg_settings[superstrong_digits]" value="1"
											<?php checked( $s['superstrong_digits'] ); ?> />
										<?php esc_html_e( 'Digits 0-9', 'strong-password-generator' ); ?>
									</label><br>
									<label>
										<input type="checkbox" name="spg_settings[superstrong_special]" value="1"
											<?php checked( $s['superstrong_special'] ); ?> />
										<?php esc_html_e( 'Special characters', 'strong-password-generator' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button(); ?>
			</form>
		</div>

		<script>
		(function () {
			var radios     = document.querySelectorAll( 'input[name="spg_settings[mode]"]' );
			var passphrase = document.getElementById( 'spg-passphrase-settings' );
			var superstr   = document.getElementById( 'spg-superstrong-settings' );

			function toggle() {
				var val = document.querySelector( 'input[name="spg_settings[mode]"]:checked' ).value;
				passphrase.style.display = val === 'passphrase' ? '' : 'none';
				superstr.style.display   = val === 'superstrong' ? '' : 'none';
			}

			radios.forEach( function ( r ) { r.addEventListener( 'change', toggle ); } );
		}());
		</script>
		<?php
	}
}

new Strong_Password_Generator();

// Auto-update via GitHub releases (Plugin Update Checker)
$spg_puc_path = plugin_dir_path( __FILE__ ) . 'includes/plugin-update-checker/plugin-update-checker.php';
if ( file_exists( $spg_puc_path ) ) {
	require_once $spg_puc_path;
	$spg_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/YOUR-GITHUB-USERNAME/strong-password-generator/',
		__FILE__,
		'strong-password-generator'
	);
	$spg_checker->setBranch( 'main' );
	$spg_checker->getVcsApi()->enableReleaseAssets();
	// Remove the "Check for updates" action link — updates surface through the
	// standard WP update system (plugins list and /wp-admin/update-core.php).
	add_filter( 'puc_manual_check_link-strong-password-generator', '__return_empty_string' );
}
