<?php
/**
 * تنظیمات افزونه: صفحهٔ تنظیمات با تب‌ها + گزینه‌ها.
 *
 * @package ContentAssistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ثبت گزینه‌ها، منوی تنظیمات و رندر صفحه.
 */
class Content_Assistant_Settings {

	const OPTION = 'content_assistant_settings';

	/**
	 * کلید رمزنگاری برای API Key (مبتنی بر SALT های وردپرس).
	 *
	 * @return string
	 */
	private static function get_encryption_key() {
		$salt = defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : 'default_salt_change_me';
		return substr( hash( 'sha256', $salt . 'content_assistant_api_key', true ), 0, 32 );
	}

	/**
	 * رمزنگاری داده.
	 *
	 * @param string $data داده.
	 * @return string base64 encoded.
	 */
	private static function encrypt( $data ) {
		if ( '' === $data ) {
			return '';
		}
		$key     = self::get_encryption_key();
		$iv      = random_bytes( 16 );
		$cipher  = openssl_encrypt( $data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		return base64_encode( $iv . $cipher );
	}

	/**
	 * رمزگشایی داده.
	 *
	 * @param string $data base64 encoded data.
	 * @return string
	 */
	private static function decrypt( $data ) {
		if ( '' === $data ) {
			return '';
		}
		$key    = self::get_encryption_key();
		$raw    = base64_decode( $data );
		if ( false === $raw || strlen( $raw ) < 16 ) {
			// داده قدیمی بدون رمزنگاری - برای سازگاری عقب‌گشت.
			return $data;
		}
		$iv     = substr( $raw, 0, 16 );
		$cipher = substr( $raw, 16 );
		$decrypted = openssl_decrypt( $cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		return false === $decrypted ? '' : $decrypted;
	}

	/**
	 * پیش‌فرض‌های تنظیمات.
	 *
	 * @return array
	 */
	public static function defaults() {
		$post_types = self::editable_post_types();
		return array(
			'api_key'                 => '',
			'base_url'                => 'https://api.gapgpt.app/v1',
			'model'                   => 'gpt-4o',
			'temperature'             => '0.7',
			'max_tokens'              => '4096',
			'content_language'        => 'fa',
			'ui_language'             => 'auto',
			'default_post_status'     => 'draft',
			'image_model'             => 'gpt-image-2',
			'enable_editor_assistant' => 1,
			'enable_admin_bar_button' => 1,
			'assistant_post_types'    => array_keys( $post_types ),
			'bulk_delay'              => 2,
		);
	}

	/**
	 * پست‌تایپ‌های قابل ویرایش (show_ui).
	 *
	 * @return array نگاشت slug => برچسب.
	 */
	public static function editable_post_types() {
		$types = get_post_types( array( 'show_ui' => true ), 'objects' );
		$out   = array();
		foreach ( $types as $slug => $obj ) {
			if ( in_array( $slug, array( 'attachment' ), true ) ) {
				continue;
			}
			$out[ $slug ] = $obj->labels->singular_name ? $obj->labels->singular_name : $slug;
		}
		return $out;
	}

	/**
	 * تنظیمات با ادغام پیش‌فرض‌ها (API Key رمزگشایی شده).
	 *
	 * @return array
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$merged = wp_parse_args( $stored, self::defaults() );
		// رمزگشایی API Key در صورت وجود.
		if ( isset( $merged['api_key'] ) && '' !== $merged['api_key'] ) {
			$merged['api_key'] = self::decrypt( $merged['api_key'] );
		}
		return $merged;
	}

	/**
	 * خواندن یک تنظیم (API Key رمزگشایی شده).
	 *
	 * @param string $key کلید.
	 * @return mixed
	 */
	public static function get_one( $key ) {
		$s = self::get();
		return isset( $s[ $key ] ) ? $s[ $key ] : null;
	}

	/**
	 * ذخیرهٔ تنظیمات (API Key رمزنگاری شده).
	 *
	 * @param array $new داده.
	 * @return array ذخیره‌شده.
	 */
	public static function update( $new ) {
		$current = self::get(); // این متد رمزگشایی شده برمی‌گرداند.
		$merged  = wp_parse_args( is_array( $new ) ? $new : array(), $current );

		// رمزنگاری API Key اگر تغییر کرده باشد.
		if ( isset( $new['api_key'] ) ) {
			$in_key = trim( (string) $new['api_key'] );
			if ( '' !== $in_key && $in_key !== $current['api_key'] ) {
				$merged['api_key'] = self::encrypt( $in_key );
			} else {
				// اگر خالی ارسال شده، کلید قبلی را حفظ کن (رمزنگاری شده).
				$stored = get_option( self::OPTION, array() );
				if ( isset( $stored['api_key'] ) ) {
					$merged['api_key'] = $stored['api_key'];
				}
			}
		}

		update_option( self::OPTION, $merged, false );
		return $merged;
	}

	/**
	 * راه‌اندازی hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_switch_language' ) );
	}

	/**
	 * سوییچر فوری زبان رابط کاربری: ?content_assistant_lang=fa|en|auto
	 */
	public static function maybe_switch_language() {
		if ( ! isset( $_GET['content_assistant_lang'] ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$lang = sanitize_key( wp_unslash( $_GET['content_assistant_lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $lang, array( 'fa', 'en', 'auto' ), true ) ) {
			return;
		}
		check_admin_referer( 'content_assistant_switch_lang' );

		self::update( array( 'ui_language' => $lang ) );

		$back = wp_get_referer();
		if ( ! $back ) {
			$back = admin_url( 'admin.php?page=content-assistant' );
		}
		wp_safe_redirect( remove_query_arg( array( 'content_assistant_lang', '_wpnonce' ), $back ) );
		exit;
	}

	/**
	 * ثبت گزینه برای Settings API.
	 */
	public static function register_setting() {
		register_setting(
			'content_assistant_settings_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	/**
	 * پاکسازی داده‌های فرم تنظیمات.
	 *
	 * @param array $input دادهٔ خام.
	 * @return array
	 */
	public static function sanitize( $input ) {
		// خواندن مقدار ذخیره شده (رمزنگاری شده) و رمزگشایی برای مقایسه.
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$current = wp_parse_args( $stored, self::defaults() );
		// رمزگشایی کلید فعلی برای مقایسه.
		if ( isset( $current['api_key'] ) && '' !== $current['api_key'] ) {
			$current['api_key'] = self::decrypt( $current['api_key'] );
		}

		$out = $current;

		if ( ! is_array( $input ) ) {
			return $out;
		}

		// کلید API: رمزنگاری و ذخیره.
		if ( isset( $input['api_key'] ) ) {
			$in_key = trim( (string) $input['api_key'] );
			if ( '' !== $in_key && $in_key !== $current['api_key'] ) {
				$out['api_key'] = self::encrypt( sanitize_text_field( $in_key ) );
			}
			// اگر خالی ارسال شده، کلید قبلی حفظ می‌شود.
		}

		if ( isset( $input['base_url'] ) ) {
			$url = esc_url_raw( trim( (string) $input['base_url'] ) );
			if ( $url ) {
				$out['base_url'] = $url;
			}
		}
		if ( isset( $input['model'] ) ) {
			$out['model'] = sanitize_text_field( $input['model'] );
		}
		if ( isset( $input['image_model'] ) ) {
			$out['image_model'] = sanitize_text_field( $input['image_model'] );
		}
		if ( isset( $input['temperature'] ) ) {
			$out['temperature'] = (string) min( 2, max( 0, (float) $input['temperature'] ) );
		}
		if ( isset( $input['max_tokens'] ) ) {
			$out['max_tokens'] = (string) min( 32000, max( 16, (int) $input['max_tokens'] ) );
		}
		if ( isset( $input['content_language'] ) ) {
			$cl = sanitize_key( $input['content_language'] );
			if ( in_array( $cl, array( 'fa', 'en' ), true ) ) {
				$out['content_language'] = $cl;
			}
		}
		if ( isset( $input['ui_language'] ) ) {
			$ul = sanitize_key( $input['ui_language'] );
			if ( in_array( $ul, array( 'auto', 'fa', 'en' ), true ) ) {
				$out['ui_language'] = $ul;
			}
		}
		if ( isset( $input['default_post_status'] ) ) {
			$st = sanitize_key( $input['default_post_status'] );
			if ( in_array( $st, array( 'draft', 'pending', 'publish', 'private' ), true ) ) {
				$out['default_post_status'] = $st;
			}
		}
		if ( isset( $input['bulk_delay'] ) ) {
			$out['bulk_delay'] = (string) min( 30, max( 0, (int) $input['bulk_delay'] ) );
		}

		$out['enable_editor_assistant'] = ! empty( $input['enable_editor_assistant'] ) ? 1 : 0;
		$out['enable_admin_bar_button'] = ! empty( $input['enable_admin_bar_button'] ) ? 1 : 0;

		if ( isset( $input['assistant_post_types'] ) && is_array( $input['assistant_post_types'] ) ) {
			$allowed = array_keys( self::editable_post_types() );
			$sel     = array_intersect( array_map( 'sanitize_key', $input['assistant_post_types'] ), $allowed );
			$out['assistant_post_types'] = array_values( $sel );
		}

		return $out;
	}

	/**
	 * آیا این صفحه از صفحات خود افزونه است؟
	 *
	 * @return bool
	 */
	public static function is_our_screen() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		return $screen && ( 'toplevel_page_content-assistant' === $screen->id || 0 === strpos( (string) $screen->id, 'content_assistant_page_' ) );
	}

	/**
	 * هدر مشترک صفحات افزونه: عنوان + سوییچر زبان + نسخه.
	 *
	 * @param string $title کلید عنوان.
	 * @param string $desc  کلید توضیح (اختیاری).
	 */
	public static function render_header( $title, $desc = '' ) {
		$current = Content_Assistant_I18n::setting();
		$langs   = array(
			'auto' => content_assistant_te( 'ui_lang_auto' ),
			'fa'   => 'فارسی',
			'en'   => 'English',
		);
		?>
		<div class="content-assistant-pagehead">
			<div class="content-assistant-pagehead-main">
				<span class="content-assistant-logo dashicons dashicons-lightbulb" aria-hidden="true"></span>
				<div class="content-assistant-pagehead-text">
					<h1 class="content-assistant-pagetitle">
						<?php echo esc_html( content_assistant_t( $title ) ); ?>
						<span class="content-assistant-version">v<?php echo esc_html( CONTENT_ASSISTANT_VERSION ); ?></span>
					</h1>
					<p class="content-assistant-pagesub"><?php echo esc_html( content_assistant_te( 'app_tagline' ) ); ?> — <a href="https://ahangaryan.ir" target="_blank" rel="noopener noreferrer">ahangaryan.ir</a></p>
				</div>
			</div>
			<div class="content-assistant-pagehead-side">
				<nav class="content-assistant-langswitch" aria-label="<?php echo esc_attr( content_assistant_te( 'ui_language_label' ) ); ?>">
					<?php foreach ( $langs as $code => $label ) : ?>
						<a class="content-assistant-langswitch-opt <?php echo esc_attr( $current === $code ? 'is-active' : '' ); ?>"
							href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'content_assistant_lang', $code ), 'content_assistant_switch_lang' ) ); ?>"
							lang="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</nav>
			</div>
		</div>
		<?php
		if ( $desc ) {
			echo '<p class="content-assistant-pagedesc">' . esc_html( content_assistant_t( $desc ) ) . '</p>';
		}
	}

	/**
	 * فوتر مشترک: هویت نویسنده.
	 */
	public static function render_footer() {
		?>
		<div class="content-assistant-footer">
			<div class="content-assistant-footer-brand">
				<span class="content-assistant-footer-name">Hamid Ahangaryan</span>
				<span class="content-assistant-footer-sep" aria-hidden="true">•</span>
				<a href="https://ahangaryan.ir" target="_blank" rel="noopener noreferrer">ahangaryan.ir</a>
			</div>
			<div class="content-assistant-footer-note">
				<?php echo esc_html( content_assistant_te( 'company_built' ) ); ?>
				<span class="content-assistant-footer-sep" aria-hidden="true">•</span>
				<?php echo esc_html( content_assistant_te( 'version_label' ) ); ?> <?php echo esc_html( CONTENT_ASSISTANT_VERSION ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * منوی تنظیمات.
	 */
	public static function register_menu() {
		add_menu_page(
			content_assistant_te( 'app_name' ),
			content_assistant_te( 'app_name' ),
			'manage_options',
			'content-assistant',
			array( __CLASS__, 'render_page' ),
			'dashicons-lightbulb',
			58
		);
		add_submenu_page( 'content-assistant', content_assistant_te( 'menu_schedule' ), content_assistant_te( 'menu_schedule' ), 'manage_options', 'content-assistant-schedule', array( 'Content_Assistant_Scheduler', 'render_page' ) );
		add_submenu_page( 'content-assistant', content_assistant_te( 'menu_bulk' ), content_assistant_te( 'menu_bulk' ), 'manage_options', 'content-assistant-bulk', array( 'Content_Assistant_Bulk_Editor', 'render_page' ) );
		add_submenu_page( 'content-assistant', content_assistant_te( 'menu_logs' ), content_assistant_te( 'menu_logs' ), 'manage_options', 'content-assistant-logs', array( __CLASS__, 'render_logs_page' ) );
		add_submenu_page( 'content-assistant', content_assistant_te( 'menu_quick' ), content_assistant_te( 'menu_quick' ), 'manage_options', 'content-assistant-quick', array( __CLASS__, 'render_quick_page' ) );
	}

	/**
	 * صفحهٔ تست سریع: تولید فوری یک پست از موضوع/درخواست و انتشار آن.
	 */
	public static function render_quick_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$types = self::editable_post_types();
		?>
		<div class="wrap content-assistant-wrap" dir="<?php echo esc_attr( Content_Assistant_I18n::dir() ); ?>">
			<?php self::render_header( 'quick_title', 'quick_desc' ); ?>

			<div class="content-assistant-card content-assistant-card--lg">
				<table class="form-table content-assistant-form" role="presentation">
					<tr>
						<th scope="row"><label for="qt_post_type"><?php echo esc_html( content_assistant_te( 'label_post_type' ) ); ?></label></th>
						<td>
							<select id="qt_post_type" class="content-assistant-select">
								<?php foreach ( $types as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label . ' (' . $slug . ')' ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="qt_topic"><?php echo esc_html( content_assistant_te( 'quick_topic' ) ); ?></label></th>
						<td><input type="text" class="regular-text content-assistant-input" id="qt_topic" placeholder="<?php echo esc_attr( content_assistant_te( 'quick_topic_ph' ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="qt_instruction"><?php echo esc_html( content_assistant_te( 'quick_instruction' ) ); ?></label></th>
						<td>
							<textarea id="qt_instruction" class="large-text content-assistant-input" rows="3" placeholder="<?php echo esc_attr( content_assistant_te( 'quick_instruction_ph' ) ); ?>"></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="qt_words"><?php echo esc_html( content_assistant_te( 'label_word_count' ) ); ?></label></th>
						<td><input type="number" id="qt_words" value="600" min="100" max="5000" step="50" class="small-text content-assistant-input" /></td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( content_assistant_te( 'col_status' ) ); ?></th>
						<td><label class="content-assistant-check"><input type="checkbox" id="qt_publish" checked /> <?php echo esc_html( content_assistant_te( 'quick_publish' ) ); ?></label></td>
					</tr>
				</table>
				<p class="content-assistant-actions">
					<button type="button" class="button button-primary button-hero content-assistant-btn" id="qt-run"><?php echo esc_html( content_assistant_te( 'quick_run' ) ); ?></button>
					<span id="qt-status" class="content-assistant-status" role="status" aria-live="polite"></span>
				</p>
				<div id="qt-result"></div>
			</div>

			<?php self::render_footer(); ?>
		</div>
		<?php
	}

	/**
	 * رندر صفحهٔ تنظیمات.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s    = self::get();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = array(
			'general'   => content_assistant_te( 'tab_general' ),
			'assistant' => content_assistant_te( 'tab_assistant' ),
		);
		?>
		<div class="wrap content-assistant-wrap" dir="<?php echo esc_attr( Content_Assistant_I18n::dir() ); ?>">
			<?php self::render_header( 'settings_title' ); ?>

			<?php settings_errors(); ?>

			<nav class="content-assistant-tabs">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a class="content-assistant-tab <?php echo esc_attr( $tab === $slug ? 'is-active' : '' ); ?>"
						href="<?php echo esc_url( admin_url( 'admin.php?page=content-assistant&tab=' . $slug ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php" class="content-assistant-settings-form">
				<?php settings_fields( 'content_assistant_settings_group' ); ?>
				<input type="hidden" name="content_assistant_settings[tab]" value="<?php echo esc_attr( $tab ); ?>" />

				<!-- Hidden fields to preserve values when switching tabs -->
				<input type="hidden" name="content_assistant_settings[api_key]" value="<?php echo esc_attr( $s['api_key'] ); ?>" />
				<input type="hidden" name="content_assistant_settings[base_url]" value="<?php echo esc_attr( $s['base_url'] ); ?>" />
				<input type="hidden" name="content_assistant_settings[model]" value="<?php echo esc_attr( $s['model'] ); ?>" />
				<input type="hidden" name="content_assistant_settings[image_model]" value="<?php echo esc_attr( $s['image_model'] ); ?>" />
				<input type="hidden" name="content_assistant_settings[temperature]" value="<?php echo esc_attr( $s['temperature'] ); ?>" />
				<input type="hidden" name="content_assistant_settings[max_tokens]" value="<?php echo esc_attr( $s['max_tokens'] ); ?>" />
				<input type="hidden" name="content_assistant_settings[content_language]" value="<?php echo esc_attr( $s['content_language'] ); ?>" />
				<input type="hidden" name="content_assistant_settings[ui_language]" value="<?php echo esc_attr( $s['ui_language'] ); ?>" />
				<input type="hidden" name="content_assistant_settings[default_post_status]" value="<?php echo esc_attr( $s['default_post_status'] ); ?>" />
				<input type="hidden" name="content_assistant_settings[bulk_delay]" value="<?php echo esc_attr( $s['bulk_delay'] ); ?>" />
				<input type="hidden" name="content_assistant_settings[enable_editor_assistant]" value="<?php echo esc_attr( $s['enable_editor_assistant'] ? '1' : '0' ); ?>" />
				<input type="hidden" name="content_assistant_settings[enable_admin_bar_button]" value="<?php echo esc_attr( $s['enable_admin_bar_button'] ? '1' : '0' ); ?>" />
				<?php if ( is_array( $s['assistant_post_types'] ) ) : ?>
					<?php foreach ( $s['assistant_post_types'] as $pt ) : ?>
						<input type="hidden" name="content_assistant_settings[assistant_post_types][]" value="<?php echo esc_attr( $pt ); ?>" />
					<?php endforeach; ?>
				<?php endif; ?>

				<div class="content-assistant-card content-assistant-card--lg">
					<?php
					if ( 'assistant' === $tab ) {
						self::render_assistant_tab( $s );
					} else {
						self::render_general_tab( $s );
					}
					?>
				</div>

				<div class="content-assistant-submitbar">
					<?php submit_button( content_assistant_te( 'save_settings' ), 'primary large', 'submit', false ); ?>
				</div>
			</form>

			<?php if ( 'general' === $tab ) : ?>
				<div class="content-assistant-card content-assistant-card--lg content-assistant-about" id="content-assistant-about">
					<h2 class="content-assistant-card-title"><?php echo esc_html( content_assistant_te( 'about_title' ) ); ?></h2>
					<p class="content-assistant-about-text"><?php echo esc_html( content_assistant_te( 'about_desc' ) ); ?></p>
					<p class="content-assistant-about-text content-assistant-about-muted"><?php echo esc_html( content_assistant_te( 'support_desc' ) ); ?></p>
				</div>
			<?php endif; ?>

			<?php self::render_footer(); ?>
		</div>
		<?php
	}

	/**
	 * تب عمومی.
	 *
	 * @param array $s تنظیمات.
	 */
	private static function render_general_tab( $s ) {
		$masked = '';
		if ( ! empty( $s['api_key'] ) ) {
			$len    = strlen( $s['api_key'] );
			$masked = str_repeat( '•', max( 0, $len - 4 ) ) . substr( $s['api_key'], -4 );
		}
		?>
		<h2 class="content-assistant-card-title"><?php echo esc_html( content_assistant_te( 'tab_general' ) ); ?></h2>

		<div class="content-assistant-field-row content-assistant-field-row--key">
			<span class="content-assistant-keystatus <?php echo esc_attr( $masked ? 'is-ok' : 'is-warn' ); ?>">
				<?php echo esc_html( $masked ? content_assistant_te( 'api_key_set' ) : content_assistant_te( 'api_key_missing' ) ); ?>
			</span>
		</div>

		<table class="form-table content-assistant-form" role="presentation">
			<tr>
				<th scope="row"><label for="content_assistant_api_key"><?php echo esc_html( content_assistant_te( 'api_key_label' ) ); ?></label></th>
				<td>
					<div class="content-assistant-input-wrap">
						<input type="password" class="regular-text content-assistant-input" id="content_assistant_api_key" autocomplete="off"
							value="" placeholder="<?php echo esc_attr( $masked ? $masked : 'gpg-…' ); ?>" readonly />
						<button type="button" class="content-assistant-reveal" data-target="content_assistant_api_key" aria-label="<?php echo esc_attr( content_assistant_te( 'details' ) ); ?>">👁</button>
					</div>
					<p class="description">
						<?php
						if ( $masked ) {
							printf(
								/* translators: %s: masked key */
								esc_html( content_assistant_t( 'api_key_current' ) ),
								'<code>' . esc_html( $masked ) . '</code>'
							);
						} else {
							esc_html( content_assistant_te( 'api_key_desc' ) );
						}
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="content_assistant_base_url"><?php echo esc_html( content_assistant_te( 'base_url_label' ) ); ?></label></th>
				<td>
					<input type="url" class="regular-text content-assistant-input" id="content_assistant_base_url" name="content_assistant_settings[base_url]" value="<?php echo esc_attr( $s['base_url'] ); ?>" dir="ltr" />
					<p class="description"><?php echo esc_html( content_assistant_te( 'base_url_desc' ) ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="content_assistant_model"><?php echo esc_html( content_assistant_te( 'model_label' ) ); ?></label></th>
				<td>
					<input type="text" class="regular-text content-assistant-input" id="content_assistant_model" name="content_assistant_settings[model]" value="<?php echo esc_attr( $s['model'] ); ?>" list="content_assistant_model_list" dir="ltr" />
					<datalist id="content_assistant_model_list">
						<option value="gpt-4o"></option>
						<option value="gpt-5"></option>
						<option value="gemini-2.5-pro"></option>
						<option value="qwen-3.5"></option>
					</datalist>
					<p class="description"><?php echo esc_html( content_assistant_te( 'model_desc' ) ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="content_assistant_image_model"><?php echo esc_html( content_assistant_te( 'image_model_label' ) ); ?></label></th>
				<td>
					<input type="text" class="regular-text content-assistant-input" id="content_assistant_image_model" name="content_assistant_settings[image_model]" value="<?php echo esc_attr( $s['image_model'] ); ?>" dir="ltr" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="content_assistant_temperature"><?php echo esc_html( content_assistant_te( 'temperature_label' ) ); ?></label></th>
				<td>
					<input type="number" step="0.1" min="0" max="2" class="small-text content-assistant-input" id="content_assistant_temperature" name="content_assistant_settings[temperature]" value="<?php echo esc_attr( $s['temperature'] ); ?>" dir="ltr" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="content_assistant_max_tokens"><?php echo esc_html( content_assistant_te( 'max_tokens_label' ) ); ?></label></th>
				<td>
					<input type="number" step="1" min="16" max="32000" class="small-text content-assistant-input" id="content_assistant_max_tokens" name="content_assistant_settings[max_tokens]" value="<?php echo esc_attr( $s['max_tokens'] ); ?>" dir="ltr" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="content_assistant_language"><?php echo esc_html( content_assistant_te( 'content_lang_label' ) ); ?></label></th>
				<td>
					<select id="content_assistant_language" class="content-assistant-select" name="content_assistant_settings[content_language]">
						<option value="fa" <?php selected( $s['content_language'], 'fa' ); ?>><?php echo esc_html( content_assistant_te( 'lang_fa' ) ); ?></option>
						<option value="en" <?php selected( $s['content_language'], 'en' ); ?>><?php echo esc_html( content_assistant_te( 'lang_en' ) ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="content_assistant_ui_language"><?php echo esc_html( content_assistant_te( 'ui_language_label' ) ); ?></label></th>
				<td>
					<select id="content_assistant_ui_language" class="content-assistant-select" name="content_assistant_settings[ui_language]">
						<option value="auto" <?php selected( $s['ui_language'], 'auto' ); ?>><?php echo esc_html( content_assistant_te( 'ui_lang_auto' ) ); ?></option>
						<option value="fa" <?php selected( $s['ui_language'], 'fa' ); ?>><?php echo esc_html( content_assistant_te( 'lang_fa' ) ); ?></option>
						<option value="en" <?php selected( $s['ui_language'], 'en' ); ?>><?php echo esc_html( content_assistant_te( 'lang_en' ) ); ?></option>
					</select>
					<p class="description"><?php echo esc_html( content_assistant_te( 'ui_language_desc' ) ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="content_assistant_status"><?php echo esc_html( content_assistant_te( 'default_status_label' ) ); ?></label></th>
				<td>
					<select id="content_assistant_status" class="content-assistant-select" name="content_assistant_settings[default_post_status]">
						<option value="draft" <?php selected( $s['default_post_status'], 'draft' ); ?>><?php echo esc_html( content_assistant_te( 'status_draft' ) ); ?></option>
						<option value="pending" <?php selected( $s['default_post_status'], 'pending' ); ?>><?php echo esc_html( content_assistant_te( 'status_pending' ) ); ?></option>
						<option value="publish" <?php selected( $s['default_post_status'], 'publish' ); ?>><?php echo esc_html( content_assistant_te( 'status_publish' ) ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="content_assistant_bulk_delay"><?php echo esc_html( content_assistant_te( 'bulk_delay_label' ) ); ?></label></th>
				<td>
					<input type="number" step="1" min="0" max="30" class="small-text content-assistant-input" id="content_assistant_bulk_delay" name="content_assistant_settings[bulk_delay]" value="<?php echo esc_attr( $s['bulk_delay'] ); ?>" dir="ltr" />
					<p class="description"><?php echo esc_html( content_assistant_te( 'bulk_delay_desc' ) ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html( content_assistant_te( 'test_connection_label' ) ); ?></th>
				<td>
					<button type="button" class="button content-assistant-btn" id="content-assistant-test-connection"><?php echo esc_html( content_assistant_te( 'test_connection_btn' ) ); ?></button>
					<span id="content-assistant-test-result" class="content-assistant-test-result" role="status" aria-live="polite"></span>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * تب دستیار ویرایشگر.
	 *
	 * @param array $s تنظیمات.
	 */
	private static function render_assistant_tab( $s ) {
		$types = self::editable_post_types();
		$sel   = is_array( $s['assistant_post_types'] ) ? $s['assistant_post_types'] : array();
		?>
		<h2 class="content-assistant-card-title"><?php echo esc_html( content_assistant_te( 'tab_assistant' ) ); ?></h2>

		<table class="form-table content-assistant-form" role="presentation">
			<tr>
				<th scope="row"><?php echo esc_html( content_assistant_te( 'editor_assistant_label' ) ); ?></th>
				<td>
					<label class="content-assistant-switch">
						<input type="checkbox" name="content_assistant_settings[enable_editor_assistant]" value="1" <?php checked( ! empty( $s['enable_editor_assistant'] ) ); ?> />
						<span class="content-assistant-switch-track" aria-hidden="true"></span>
						<span><?php echo esc_html( content_assistant_te( 'editor_assistant_desc' ) ); ?></span>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html( content_assistant_te( 'admin_bar_label' ) ); ?></th>
				<td>
					<label class="content-assistant-switch">
						<input type="checkbox" name="content_assistant_settings[enable_admin_bar_button]" value="1" <?php checked( ! empty( $s['enable_admin_bar_button'] ) ); ?> />
						<span class="content-assistant-switch-track" aria-hidden="true"></span>
						<span><?php echo esc_html( content_assistant_te( 'admin_bar_desc' ) ); ?></span>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html( content_assistant_te( 'assistant_post_types_label' ) ); ?></th>
				<td>
					<div class="content-assistant-checkgrid">
						<?php foreach ( $types as $slug => $label ) : ?>
							<label class="content-assistant-check">
								<input type="checkbox" name="content_assistant_settings[assistant_post_types][]" value="<?php echo esc_attr( $slug ); ?>"
									<?php checked( in_array( $slug, $sel, true ) ); ?> />
								<?php echo esc_html( $label . ' (' . $slug . ')' ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * صفحهٔ لاگ و مصرف.
	 */
	public static function render_logs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$usage = Content_Assistant_Logger::usage();
		$logs  = array_reverse( Content_Assistant_Logger::logs() );

		// کارت‌های آماری.
		$tiles = array(
			array( 'total_calls', isset( $usage['calls'] ) ? number_format_i18n( $usage['calls'] ) : '0' ),
			array( 'prompt_tokens', isset( $usage['prompt_tokens'] ) ? number_format_i18n( $usage['prompt_tokens'] ) : '0' ),
			array( 'completion_tokens', isset( $usage['completion_tokens'] ) ? number_format_i18n( $usage['completion_tokens'] ) : '0' ),
			array( 'posts_generated', isset( $usage['posts_generated'] ) ? number_format_i18n( $usage['posts_generated'] ) : '0' ),
			array( 'images_generated', isset( $usage['images_generated'] ) ? number_format_i18n( $usage['images_generated'] ) : '0' ),
			array( 'last_call', ! empty( $usage['last_call'] ) ? $usage['last_call'] : '—' ),
		);
		?>
		<div class="wrap content-assistant-wrap" dir="<?php echo esc_attr( Content_Assistant_I18n::dir() ); ?>">
			<?php self::render_header( 'logs_title' ); ?>

			<div class="content-assistant-tiles">
				<?php foreach ( $tiles as $tile ) : ?>
					<div class="content-assistant-tile">
						<span class="content-assistant-tile-label"><?php echo esc_html( content_assistant_te( $tile[0] ) ); ?></span>
						<span class="content-assistant-tile-value" dir="ltr"><?php echo esc_html( $tile[1] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="content-assistant-card">
				<h2 class="content-assistant-card-title"><?php echo esc_html( content_assistant_te( 'daily_usage' ) ); ?></h2>
				<?php if ( empty( $usage['by_date'] ) ) : ?>
					<p class="content-assistant-empty"><?php echo esc_html( content_assistant_te( 'usage_empty' ) ); ?></p>
				<?php else : ?>
					<table class="widefat striped content-assistant-table">
						<thead>
							<tr>
								<th><?php echo esc_html( content_assistant_te( 'col_date' ) ); ?></th>
								<th><?php echo esc_html( content_assistant_te( 'col_calls' ) ); ?></th>
								<th><?php echo esc_html( content_assistant_te( 'prompt_tokens' ) ); ?></th>
								<th><?php echo esc_html( content_assistant_te( 'completion_tokens' ) ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_reverse( $usage['by_date'], true ) as $date => $row ) : ?>
								<tr>
									<td dir="ltr"><?php echo esc_html( $date ); ?></td>
									<td><?php echo esc_html( isset( $row['calls'] ) ? $row['calls'] : 0 ); ?></td>
									<td><?php echo esc_html( isset( $row['prompt_tokens'] ) ? number_format_i18n( $row['prompt_tokens'] ) : 0 ); ?></td>
									<td><?php echo esc_html( isset( $row['completion_tokens'] ) ? number_format_i18n( $row['completion_tokens'] ) : 0 ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<div class="content-assistant-card">
				<h2 class="content-assistant-card-title content-assistant-card-title--bar">
					<?php echo esc_html( content_assistant_te( 'event_log' ) ); ?>
					<a class="button button-small content-assistant-btn content-assistant-btn--danger-ghost" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=content-assistant-logs&content_assistant_clear_logs=1' ), 'content_assistant_clear_logs' ) ); ?>"
						onclick="return confirm('<?php echo esc_js( content_assistant_te( 'clear_log_confirm' ) ); ?>');"><?php echo esc_html( content_assistant_te( 'clear_log' ) ); ?></a>
				</h2>
				<?php if ( isset( $_GET['content_assistant_clear_logs'] ) && check_admin_referer( 'content_assistant_clear_logs' ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
					<?php Content_Assistant_Logger::clear_logs(); ?>
					<div class="notice notice-success is-dismissible"><p><?php echo esc_html( content_assistant_te( 'log_cleared' ) ); ?></p></div>
					<?php $logs = array(); ?>
				<?php endif; ?>
				<?php if ( empty( $logs ) ) : ?>
					<p class="content-assistant-empty"><?php echo esc_html( content_assistant_te( 'logs_empty' ) ); ?></p>
				<?php else : ?>
					<table class="widefat striped content-assistant-log-table content-assistant-table">
						<thead>
							<tr>
								<th style="width:160px"><?php echo esc_html( content_assistant_te( 'col_time' ) ); ?></th>
								<th style="width:90px"><?php echo esc_html( content_assistant_te( 'col_type' ) ); ?></th>
								<th><?php echo esc_html( content_assistant_te( 'col_message' ) ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $logs as $row ) : ?>
								<tr class="content-assistant-log-<?php echo esc_attr( $row['type'] ); ?>">
									<td dir="ltr"><?php echo esc_html( $row['time'] ); ?></td>
									<td><code><?php echo esc_html( $row['type'] ); ?></code></td>
									<td>
										<?php echo esc_html( $row['message'] ); ?>
										<?php if ( ! empty( $row['context'] ) ) : ?>
											<details><summary><?php echo esc_html( content_assistant_te( 'details' ) ); ?></summary><code dir="ltr"><?php echo esc_html( wp_json_encode( $row['context'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) ); ?></code></details>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<?php self::render_footer(); ?>
		</div>
		<?php
	}
}
