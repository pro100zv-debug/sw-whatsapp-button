<?php
/**
 * Plugin Name:       SW WhatsApp Button
 * Description:       Floating, responsive WhatsApp button. Number, message, colors and position in Settings → SW WhatsApp.
 * Version:           1.6.0
 * Author:            SergioWeb
 * Author URI:        https://sergioweb.es/
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * Text Domain:       sw-whatsapp
 * Domain Path:       /languages
 * Update URI:        https://github.com/pro100zv-debug/sw-whatsapp-button
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Updates from GitHub ---------- */

if ( is_readable( __DIR__ . '/plugin-update-checker/plugin-update-checker.php' ) ) {
	require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

	$sw_whatsapp_updater = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/pro100zv-debug/sw-whatsapp-button/',
		__FILE__,
		'sw-whatsapp-button'
	);
	$sw_whatsapp_updater->setBranch( 'main' );
	// Only releases that already have the zip attached (built by GitHub Actions).
	$sw_whatsapp_updater->getVcsApi()->enableReleaseAssets(
		'/sw-whatsapp-button\.zip($|[?&#])/i',
		\YahnisElsts\PluginUpdateChecker\v5p7\Vcs\Api::REQUIRE_RELEASE_ASSETS
	);
}

final class SW_WhatsApp_Button {

	const OPT        = 'sw_whatsapp';
	const SLUG       = 'sw-whatsapp';
	const DOMAIN     = 'sw-whatsapp';
	const DB_OPT     = 'sw_whatsapp_db';
	const DB_VERSION = '1.4.1';

	/**
	 * Bundled translations. Any regional variant (es_MX, es_AR, ru_UA…)
	 * falls back to the base file of its language.
	 */
	const BUNDLED_LANGS = array(
		'es' => 'es_ES',
		'ru' => 'ru_RU',
	);

	public static function defaults() {
		return array(
			'enabled'     => 1,
			'phone'       => '',
			'message'     => '',
			'label'       => '',
			'bg'          => '#25D366',
			'fg'          => '#FFFFFF',
			'bg_hover'    => '',
			'pulse_color' => '',
			'pulse_hover' => '',
			'position'    => 'right',
			'offset_x'    => 20,
			'offset_y'    => 20,
			'size'        => 60,
			'desktop'     => 1,
			'mobile'      => 1,
			'pulse'       => 1,
			'new_tab'     => 1,
			'icon'        => 'whatsapp',
			'icon_url'    => '',
			'hide_ids'    => '',
			'hide_woo'    => 1,
		);
	}

	public static function version() {
		$data = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
		return $data['Version'];
	}

	public static function get() {
		$saved = get_option( self::OPT, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function init() {
		add_filter( 'load_textdomain_mofile', array( __CLASS__, 'mofile_fallback' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render' ), 99 );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'action_links' ) );
	}

	/* ---------- Translations ---------- */

	public static function load_textdomain() {
		load_plugin_textdomain( self::DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * If there is no file for the exact locale (e.g. es_MX), use the bundled
	 * base translation of the same language (es_ES). English is the default.
	 */
	public static function mofile_fallback( $mofile, $domain ) {
		if ( self::DOMAIN !== $domain || is_readable( $mofile ) ) {
			return $mofile;
		}
		if ( preg_match( '/' . preg_quote( self::DOMAIN, '/' ) . '-([a-z]{2,3})(?:_[A-Za-z0-9_]+)?\.mo$/', $mofile, $m ) ) {
			$lang = strtolower( $m[1] );
			if ( isset( self::BUNDLED_LANGS[ $lang ] ) ) {
				$bundled = __DIR__ . '/languages/' . self::DOMAIN . '-' . self::BUNDLED_LANGS[ $lang ] . '.mo';
				if ( is_readable( $bundled ) ) {
					return $bundled;
				}
			}
		}
		return $mofile;
	}

	/* ---------- Data migrations between versions ---------- */

	public static function maybe_upgrade() {
		$db = (string) get_option( self::DB_OPT, '0' );
		if ( version_compare( $db, self::DB_VERSION, '>=' ) ) {
			return;
		}

		$saved   = get_option( self::OPT, null );
		$changed = false;

		if ( is_array( $saved ) ) {
			// 1.3.0: old default texts are cleared; texts written by the user are kept.
			if ( version_compare( $db, '1.3.0', '<' ) ) {
				$old_defaults = array(
					'message' => 'Hola, tengo una consulta sobre: {title}',
					'label'   => '¿Hablamos?',
				);
				foreach ( $old_defaults as $key => $old ) {
					if ( isset( $saved[ $key ] ) && trim( (string) $saved[ $key ] ) === $old ) {
						$saved[ $key ] = '';
						$changed       = true;
					}
				}
			}

			// 1.4.1: if there was a custom image, select the "custom icon" option
			// so the button keeps showing exactly the same.
			if ( version_compare( $db, '1.4.1', '<' ) && ! empty( $saved['icon_url'] ) ) {
				$saved['icon'] = 'custom';
				$changed       = true;
			}

			if ( $changed ) {
				update_option( self::OPT, $saved );
			}
		}

		update_option( self::DB_OPT, self::DB_VERSION, false );
	}

	/* ---------- Admin ---------- */

	public static function menu() {
		add_options_page( 'SW WhatsApp', 'SW WhatsApp', 'manage_options', self::SLUG, array( __CLASS__, 'page' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Settings', 'sw-whatsapp' ) . '</a>' );
		return $links;
	}

	public static function register() {
		register_setting(
			self::SLUG,
			self::OPT,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	public static function sanitize( $in ) {
		$d   = self::defaults();
		$in  = is_array( $in ) ? $in : array();
		$out = array();

		foreach ( array( 'enabled', 'desktop', 'mobile', 'pulse', 'new_tab', 'hide_woo' ) as $k ) {
			$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}

		$out['phone']       = preg_replace( '/\D+/', '', (string) ( $in['phone'] ?? '' ) );
		$out['message']     = sanitize_textarea_field( $in['message'] ?? '' );
		$out['label']       = sanitize_text_field( $in['label'] ?? '' );
		$out['bg']          = sanitize_hex_color( $in['bg'] ?? '' ) ?: $d['bg'];
		$out['fg']          = sanitize_hex_color( $in['fg'] ?? '' ) ?: $d['fg'];
		$out['bg_hover']    = (string) sanitize_hex_color( $in['bg_hover'] ?? '' );
		$out['pulse_color'] = (string) sanitize_hex_color( $in['pulse_color'] ?? '' );
		$out['pulse_hover'] = (string) sanitize_hex_color( $in['pulse_hover'] ?? '' );
		$out['position']    = ( ( $in['position'] ?? '' ) === 'left' ) ? 'left' : 'right';
		$out['offset_x']    = min( 200, absint( $in['offset_x'] ?? $d['offset_x'] ) );
		$out['offset_y']    = min( 300, absint( $in['offset_y'] ?? $d['offset_y'] ) );
		$out['size']        = max( 40, min( 100, absint( $in['size'] ?? $d['size'] ) ) );
		$out['icon_url']    = esc_url_raw( $in['icon_url'] ?? '' );
		$out['icon']        = ( ( $in['icon'] ?? '' ) === 'custom' && $out['icon_url'] ) ? 'custom' : 'whatsapp';
		$ids                = array_filter( array_map( 'absint', explode( ',', (string) ( $in['hide_ids'] ?? '' ) ) ) );
		$out['hide_ids']    = implode( ',', array_unique( $ids ) );

		return $out;
	}

	public static function admin_assets( $hook ) {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		// Empty color (transparent / no change): checkerboard like in Photoshop.
		wp_add_inline_style(
			'wp-color-picker',
			'.sw-wa-settings .wp-color-result:not([style*="background-color"]){background-color:#fff;background-image:repeating-conic-gradient(#d0d0d0 0 25%,#fff 0 50%);background-size:10px 10px}'
		);
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_media();
		wp_add_inline_script(
			'wp-color-picker',
			'var swWaI18n=' . wp_json_encode( array( 'mediaTitle' => __( 'Button icon', 'sw-whatsapp' ) ) ) . ';' .
			"jQuery(function($){
				$('.sw-color').wpColorPicker();
				$('#sw-icon-pick').on('click',function(e){
					e.preventDefault();
					var f=wp.media({title:swWaI18n.mediaTitle,library:{type:'image'},multiple:false});
					f.on('select',function(){ $('#sw-icon-url').val(f.state().get('selection').first().toJSON().url); });
					f.open();
				});
				$('#sw-icon-clear').on('click',function(e){ e.preventDefault(); $('#sw-icon-url').val(''); });
				function swIcon(){ $('#sw-icon-custom').toggle($('input[name=\"sw_whatsapp[icon]\"]:checked').val()==='custom'); }
				$('input[name=\"sw_whatsapp[icon]\"]').on('change',swIcon); swIcon();
			});"
		);
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o = self::get();
		$n = self::OPT;
		?>
		<div class="wrap sw-wa-settings">
			<h1>SW WhatsApp <span style="font-size:13px;font-weight:400;color:#646970">v<?php echo esc_html( self::version() ); ?></span></h1>
			<form method="post" action="options.php">
				<?php settings_fields( self::SLUG ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable', 'sw-whatsapp' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[enabled]" value="1" <?php checked( $o['enabled'] ); ?>> <?php esc_html_e( 'Show the button on the website', 'sw-whatsapp' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="sw-phone"><?php esc_html_e( 'WhatsApp number', 'sw-whatsapp' ); ?></label></th>
						<td>
							<input id="sw-phone" type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[phone]" value="<?php echo esc_attr( $o['phone'] ); ?>" placeholder="34600111222">
							<p class="description"><?php esc_html_e( 'With country code, without “+” or spaces (they are removed automatically). Without a number the button is not shown.', 'sw-whatsapp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sw-msg"><?php esc_html_e( 'Predefined message', 'sw-whatsapp' ); ?></label></th>
						<td>
							<textarea id="sw-msg" rows="3" class="large-text" name="<?php echo esc_attr( $n ); ?>[message]" placeholder="<?php esc_attr_e( 'Hi, I have a question about: {title}', 'sw-whatsapp' ); ?>"><?php echo esc_textarea( $o['message'] ); ?></textarea>
							<p class="description">
								<?php
								printf(
									/* translators: 1: {title} variable, 2: {url} variable */
									esc_html__( 'Text already typed when the chat opens. Empty = empty chat. Variables: %1$s — page title, %2$s — page link.', 'sw-whatsapp' ),
									'<code>{title}</code>',
									'<code>{url}</code>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sw-label"><?php esc_html_e( 'Text next to the button', 'sw-whatsapp' ); ?></label></th>
						<td>
							<input id="sw-label" type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[label]" value="<?php echo esc_attr( $o['label'] ); ?>" placeholder="<?php esc_attr_e( 'Let’s talk?', 'sw-whatsapp' ); ?>">
							<p class="description"><?php esc_html_e( 'Desktop and tablet only (≥ 768px). Empty = no text.', 'sw-whatsapp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Colors', 'sw-whatsapp' ); ?></th>
						<td>
							<p><input type="text" class="sw-color" name="<?php echo esc_attr( $n ); ?>[bg]" value="<?php echo esc_attr( $o['bg'] ); ?>" data-default-color="#25D366"> <?php esc_html_e( 'Button background', 'sw-whatsapp' ); ?></p>
							<p><input type="text" class="sw-color" name="<?php echo esc_attr( $n ); ?>[fg]" value="<?php echo esc_attr( $o['fg'] ); ?>" data-default-color="#FFFFFF"> <?php esc_html_e( 'Icon', 'sw-whatsapp' ); ?></p>
							<p><input type="text" class="sw-color" name="<?php echo esc_attr( $n ); ?>[bg_hover]" value="<?php echo esc_attr( $o['bg_hover'] ); ?>" data-default-color=""> <?php esc_html_e( 'Background on hover', 'sw-whatsapp' ); ?></p>
							<p class="description"><?php esc_html_e( 'Optional. Empty (“Clear”) = the button does not change color on hover.', 'sw-whatsapp' ); ?></p>
							<p style="margin-top:14px"><input type="text" class="sw-color" name="<?php echo esc_attr( $n ); ?>[pulse_color]" value="<?php echo esc_attr( $o['pulse_color'] ); ?>" data-default-color=""> <?php esc_html_e( 'Pulse', 'sw-whatsapp' ); ?></p>
							<p><input type="text" class="sw-color" name="<?php echo esc_attr( $n ); ?>[pulse_hover]" value="<?php echo esc_attr( $o['pulse_hover'] ); ?>" data-default-color=""> <?php esc_html_e( 'Pulse on hover', 'sw-whatsapp' ); ?></p>
							<p class="description"><?php esc_html_e( 'Optional. Empty “Pulse” = same color as the button background. Empty “Pulse on hover” = no change. Only when the pulse animation is enabled (below, in Options).', 'sw-whatsapp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Icon', 'sw-whatsapp' ); ?></th>
						<td>
							<p>
								<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[icon]" value="whatsapp" <?php checked( 'custom' !== $o['icon'] ); ?>> <?php esc_html_e( 'WhatsApp (official, included)', 'sw-whatsapp' ); ?></label> &nbsp;
								<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[icon]" value="custom" <?php checked( $o['icon'], 'custom' ); ?>> <?php esc_html_e( 'Upload your own', 'sw-whatsapp' ); ?></label>
							</p>
							<p class="description"><?php esc_html_e( 'The official icon uses the “Icon” color.', 'sw-whatsapp' ); ?></p>
							<div id="sw-icon-custom" style="margin-top:10px">
								<input id="sw-icon-url" type="url" class="regular-text" name="<?php echo esc_attr( $n ); ?>[icon_url]" value="<?php echo esc_attr( $o['icon_url'] ); ?>">
								<button class="button" id="sw-icon-pick"><?php esc_html_e( 'Choose', 'sw-whatsapp' ); ?></button>
								<button class="button-link" id="sw-icon-clear"><?php esc_html_e( 'Remove', 'sw-whatsapp' ); ?></button>
								<p class="description"><?php esc_html_e( 'SVG/PNG from the media library, square with a transparent background. Shown as is (not recolored).', 'sw-whatsapp' ); ?></p>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Position', 'sw-whatsapp' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( $n ); ?>[position]">
								<option value="right" <?php selected( $o['position'], 'right' ); ?>><?php esc_html_e( 'Bottom right', 'sw-whatsapp' ); ?></option>
								<option value="left" <?php selected( $o['position'], 'left' ); ?>><?php esc_html_e( 'Bottom left', 'sw-whatsapp' ); ?></option>
							</select>
							<p>
								<?php esc_html_e( 'Side margin', 'sw-whatsapp' ); ?> <input type="number" min="0" max="200" class="small-text" name="<?php echo esc_attr( $n ); ?>[offset_x]" value="<?php echo esc_attr( $o['offset_x'] ); ?>"> px &nbsp;
								<?php esc_html_e( 'Bottom margin', 'sw-whatsapp' ); ?> <input type="number" min="0" max="300" class="small-text" name="<?php echo esc_attr( $n ); ?>[offset_y]" value="<?php echo esc_attr( $o['offset_y'] ); ?>"> px &nbsp;
								<?php esc_html_e( 'Size', 'sw-whatsapp' ); ?> <input type="number" min="40" max="100" class="small-text" name="<?php echo esc_attr( $n ); ?>[size]" value="<?php echo esc_attr( $o['size'] ); ?>"> px
							</p>
							<p class="description"><?php esc_html_e( 'On mobile the button is automatically reduced to 85%.', 'sw-whatsapp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Devices', 'sw-whatsapp' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[desktop]" value="1" <?php checked( $o['desktop'] ); ?>> <?php esc_html_e( 'Desktop (> 1024px)', 'sw-whatsapp' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[mobile]" value="1" <?php checked( $o['mobile'] ); ?>> <?php esc_html_e( 'Mobile and tablet (≤ 1024px)', 'sw-whatsapp' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Options', 'sw-whatsapp' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[pulse]" value="1" <?php checked( $o['pulse'] ); ?>> <?php esc_html_e( 'Pulse animation', 'sw-whatsapp' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[new_tab]" value="1" <?php checked( $o['new_tab'] ); ?>> <?php esc_html_e( 'Open in a new tab', 'sw-whatsapp' ); ?></label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[hide_woo]" value="1" <?php checked( $o['hide_woo'] ); ?>> <?php esc_html_e( 'Hide on WooCommerce cart and checkout', 'sw-whatsapp' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sw-hide"><?php esc_html_e( 'Hide on pages (ID)', 'sw-whatsapp' ); ?></label></th>
						<td>
							<input id="sw-hide" type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[hide_ids]" value="<?php echo esc_attr( $o['hide_ids'] ); ?>" placeholder="12, 345">
							<p class="description"><?php esc_html_e( 'Page, post or product IDs separated by commas.', 'sw-whatsapp' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="description">
					<?php
					printf(
						/* translators: 1: dataLayer code, 2: event name */
						esc_html__( 'Analytics: each click sends %1$s — create a custom event trigger %2$s in GTM.', 'sw-whatsapp' ),
						"<code>dataLayer.push({event:'whatsapp_click'})</code>",
						'<code>whatsapp_click</code>'
					);
					?>
				</p>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/* ---------- Frontend ---------- */

	private static function is_hidden( $o ) {
		if ( ! empty( $o['hide_woo'] ) && function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
			return true;
		}
		if ( $o['hide_ids'] ) {
			$ids = array_map( 'absint', explode( ',', $o['hide_ids'] ) );
			if ( in_array( (int) get_queried_object_id(), $ids, true ) ) {
				return true;
			}
		}
		return (bool) apply_filters( 'sw_whatsapp_hide', false, $o );
	}

	private static function build_url( $phone, $message ) {
		$url = 'https://wa.me/' . $phone;
		if ( '' === trim( $message ) ) {
			return $url;
		}
		global $wp;
		$current = home_url( isset( $wp->request ) && $wp->request ? trailingslashit( $wp->request ) : '/' );
		$title   = is_singular() ? get_the_title() : wp_get_document_title();
		$text    = strtr(
			$message,
			array(
				'{title}' => wp_strip_all_tags( html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) ),
				'{url}'   => $current,
			)
		);
		return $url . '?text=' . rawurlencode( $text );
	}

	private static function default_icon() {
		return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 3C7 3 3 6.6 3 11c0 2.3 1.1 4.4 2.9 5.9L5 21l4.3-2.2c.9.2 1.8.3 2.7.3 5 0 9-3.6 9-8s-4-8-9-8zm-4 9.2a1.2 1.2 0 1 1 0-2.4 1.2 1.2 0 0 1 0 2.4zm4 0a1.2 1.2 0 1 1 0-2.4 1.2 1.2 0 0 1 0 2.4zm4 0a1.2 1.2 0 1 1 0-2.4 1.2 1.2 0 0 1 0 2.4z"/></svg>';
	}

	public static function render() {
		if ( is_admin() || wp_doing_ajax() || is_feed() ) {
			return;
		}
		$o     = self::get();
		$phone = preg_replace( '/\D+/', '', (string) apply_filters( 'sw_whatsapp_phone', $o['phone'] ) );

		if ( empty( $o['enabled'] ) || ! $phone ) {
			return;
		}
		if ( empty( $o['desktop'] ) && empty( $o['mobile'] ) ) {
			return;
		}
		if ( self::is_hidden( $o ) ) {
			return;
		}

		$classes = array( 'sw-wa', 'sw-wa--' . $o['position'] );
		if ( ! empty( $o['pulse'] ) ) {
			$classes[] = 'sw-wa--pulse';
		}
		if ( empty( $o['desktop'] ) ) {
			$classes[] = 'sw-wa--no-desktop';
		}
		if ( empty( $o['mobile'] ) ) {
			$classes[] = 'sw-wa--no-mobile';
		}
		if ( ! empty( $o['bg_hover'] ) ) {
			$classes[] = 'sw-wa--hover';
		}
		if ( ! empty( $o['pulse_hover'] ) ) {
			$classes[] = 'sw-wa--pulse-hover';
		}

		$style = sprintf(
			'--sw-bg:%s;--sw-fg:%s;--sw-size:%dpx;--sw-x:%dpx;--sw-y:%dpx;',
			$o['bg'],
			$o['fg'],
			$o['size'],
			$o['offset_x'],
			$o['offset_y']
		);
		if ( ! empty( $o['bg_hover'] ) ) {
			$style .= '--sw-bg-hover:' . $o['bg_hover'] . ';';
		}
		if ( ! empty( $o['pulse_color'] ) ) {
			$style .= '--sw-pulse:' . $o['pulse_color'] . ';';
		}
		if ( ! empty( $o['pulse_hover'] ) ) {
			$style .= '--sw-pulse-hover:' . $o['pulse_hover'] . ';';
		}

		$glyph = plugins_url( 'icons/whatsapp-glyph.svg', __FILE__ );

		if ( 'custom' === $o['icon'] && $o['icon_url'] ) {
			$icon = '<img src="' . esc_url( $o['icon_url'] ) . '" alt="" width="32" height="32" loading="lazy" decoding="async">';
		} elseif ( 'chat' === $o['icon'] ) {
			// Compatibility: sites that chose the generic chat icon in older versions.
			$icon = self::default_icon();
		} else {
			$icon   = '<span class="sw-wa__glyph" aria-hidden="true"></span>';
			$style .= "--sw-glyph:url('" . esc_url( $glyph ) . "');";
		}

		$target = ! empty( $o['new_tab'] ) ? ' target="_blank"' : '';
		?>
<style id="sw-wa-css">
.sw-wa{position:fixed;z-index:9999;bottom:calc(var(--sw-y) + env(safe-area-inset-bottom,0px));right:calc(var(--sw-x) + env(safe-area-inset-right,0px));display:flex;flex-direction:row-reverse;align-items:center;gap:10px;text-decoration:none;-webkit-tap-highlight-color:transparent}
.sw-wa--left{right:auto;left:calc(var(--sw-x) + env(safe-area-inset-left,0px));flex-direction:row}
.sw-wa__btn{position:relative;flex:0 0 auto;width:var(--sw-size);height:var(--sw-size);border-radius:50%;background:var(--sw-bg);color:var(--sw-fg);display:grid;place-items:center;box-shadow:0 4px 14px rgba(0,0,0,.25);transition:transform .2s ease}
.sw-wa__btn svg,.sw-wa__btn img{width:55%;height:55%;object-fit:contain;display:block}
.sw-wa__glyph{display:block;width:55%;height:55%;background:currentColor;-webkit-mask:var(--sw-glyph) center/contain no-repeat;mask:var(--sw-glyph) center/contain no-repeat}
.sw-wa:hover .sw-wa__btn{transform:scale(1.08)}
.sw-wa--hover .sw-wa__btn{transition:transform .2s ease,background-color .2s ease}
.sw-wa--hover:hover .sw-wa__btn,.sw-wa--hover:focus-visible .sw-wa__btn{background:var(--sw-bg-hover)}
.sw-wa:focus{outline:none}
.sw-wa:focus-visible .sw-wa__btn{outline:3px solid var(--sw-bg);outline-offset:3px}
.sw-wa__label{background:#fff;color:#1f2937;font:500 14px/1.3 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;padding:8px 12px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.15);white-space:nowrap}
.sw-wa--pulse .sw-wa__btn::before{content:"";position:absolute;inset:0;border-radius:50%;pointer-events:none;color:var(--sw-pulse,var(--sw-bg));animation:sw-wa-pulse 2s ease-out infinite}
.sw-wa--pulse-hover:hover .sw-wa__btn::before,.sw-wa--pulse-hover:focus-visible .sw-wa__btn::before{color:var(--sw-pulse-hover)}
@keyframes sw-wa-pulse{0%{box-shadow:0 0 0 0 currentColor}80%,100%{box-shadow:0 0 0 calc(var(--sw-size)*.3) transparent}}
@media (max-width:767px){.sw-wa__btn{width:calc(var(--sw-size)*.85);height:calc(var(--sw-size)*.85)}.sw-wa__label{display:none}}
@media (max-width:1024px){.sw-wa--no-mobile{display:none}}
@media (min-width:1025px){.sw-wa--no-desktop{display:none}}
@media (prefers-reduced-motion:reduce){.sw-wa__btn{transition:none}.sw-wa--pulse .sw-wa__btn::before{display:none}}
@media print{.sw-wa{display:none}}
</style>
<a class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" style="<?php echo esc_attr( $style ); ?>" href="<?php echo esc_url( self::build_url( $phone, $o['message'] ) ); ?>"<?php echo $target; // phpcs:ignore ?> rel="noopener nofollow" aria-label="<?php esc_attr_e( 'Write to us on WhatsApp', 'sw-whatsapp' ); ?>">
	<span class="sw-wa__btn"><?php echo $icon; // phpcs:ignore -- escaped above / static SVG ?></span>
	<?php if ( $o['label'] ) : ?><span class="sw-wa__label"><?php echo esc_html( $o['label'] ); ?></span><?php endif; ?>
</a>
<script>document.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('.sw-wa');if(!a)return;window.dataLayer=window.dataLayer||[];window.dataLayer.push({event:'whatsapp_click',wa_page:location.pathname});});</script>
		<?php
	}

	public static function uninstall() {
		delete_option( self::OPT );
		delete_option( self::DB_OPT );
	}
}

SW_WhatsApp_Button::init();
register_uninstall_hook( __FILE__, array( 'SW_WhatsApp_Button', 'uninstall' ) );
