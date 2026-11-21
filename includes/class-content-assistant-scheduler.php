<?php
/**
 * زمان‌بندی تولید روزانه برای هر پست‌تایپ با WP-Cron.
 *
 * @package ContentAssistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * صف تولید خودکار: تعداد روزانه + فاصلهٔ زمانی یا زمان‌های انتخابی + موضوعات.
 */
class Content_Assistant_Scheduler {

	const CRON_HOOK   = 'content_assistant_schedule_tick';
	const SCHED_OPTION = 'content_assistant_schedules';
	const STATE_OPTION = 'content_assistant_schedule_state';

	/**
	 * hooks.
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'tick' ) );
		add_action( 'admin_post_content_assistant_save_schedules', array( __CLASS__, 'save_page' ) );
		add_action( 'admin_post_content_assistant_generate_now', array( __CLASS__, 'manual_generate' ) );
	}

	/**
	 * برنامهٔ cron پنج‌دقیقه‌ای.
	 *
	 * @param array $schedules برنامه‌ها.
	 * @return array
	 */
	public static function cron_schedules( $schedules ) {
		$schedules['content_assistant_5min'] = array(
			'interval' => 300,
			'display'  => content_assistant_t( 'cron_5min' ),
		);
		return $schedules;
	}

	/**
	 * ثبت رویداد cron هنگام فعال‌سازی.
	 */
	public static function schedule_event() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'content_assistant_5min', self::CRON_HOOK );
		}
	}

	/**
	 * حذف رویداد cron.
	 */
	public static function unschedule_event() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * همهٔ برنامه‌های ذخیره‌شده.
	 *
	 * @return array
	 */
	public static function get_all() {
		$all = get_option( self::SCHED_OPTION, array() );
		return is_array( $all ) ? $all : array();
	}

	/**
	 * برنامهٔ یک پست‌تایپ.
	 *
	 * @param string $post_type اسلاگ.
	 * @return array|null
	 */
	public static function get( $post_type ) {
		$all = self::get_all();
		return isset( $all[ $post_type ] ) ? $all[ $post_type ] : null;
	}

	/**
	 * برنامه‌های فعال.
	 *
	 * @return array
	 */
	public static function active() {
		$out = array();
		foreach ( self::get_all() as $pt => $cfg ) {
			if ( ! empty( $cfg['enabled'] ) && ! empty( $cfg['count'] ) ) {
				$out[ $pt ] = $cfg;
			}
		}
		return $out;
	}

	/**
	 * پاکسازی برنامهٔ یک پست‌تایپ.
	 *
	 * @param array $input دادهٔ خام فرم.
	 * @return array برنامه‌های پاک‌شده.
	 */
	public static function sanitize_all( $input ) {
		$out = array();
		if ( ! is_array( $input ) ) {
			return $out;
		}
		$valid_types = array_keys( Content_Assistant_Settings::editable_post_types() );

		foreach ( $input as $post_type => $cfg ) {
			$pt = sanitize_key( $post_type );
			if ( ! in_array( $pt, $valid_types, true ) || ! is_array( $cfg ) ) {
				continue;
			}

			$count = isset( $cfg['count'] ) ? min( 48, max( 0, (int) $cfg['count'] ) ) : 0;
			$enabled = ! empty( $cfg['enabled'] ) && $count > 0 ? 1 : 0;

			$mode = isset( $cfg['mode'] ) && 'times' === $cfg['mode'] ? 'times' : 'intervals';

			// زمان‌های انتخابی: "08:00,12:30,18:00".
			$times_raw  = isset( $cfg['times'] ) ? sanitize_text_field( $cfg['times'] ) : '';
			$times      = array();
			foreach ( preg_split( '/[\s,;]+/', $times_raw ) as $t ) {
				$t = trim( $t );
				if ( preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', $t, $m ) ) {
					$times[] = sprintf( '%02d:%s', (int) $m[1], $m[2] );
				}
			}
			$times = array_slice( array_unique( $times ), 0, 48 );
			sort( $times );

			$w_start = self::sanitize_hhmm( isset( $cfg['window_start'] ) ? $cfg['window_start'] : '08:00' );
			$w_end   = self::sanitize_hhmm( isset( $cfg['window_end'] ) ? $cfg['window_end'] : '22:00' );

			// موضوعات: هر خط یک موضوع.
			$topics_raw = isset( $cfg['topics'] ) ? (string) $cfg['topics'] : '';
			$topics     = array();
			foreach ( preg_split( '/\r\n|\r|\n/', wp_kses_post( $topics_raw ) ) as $line ) {
				$line = trim( wp_strip_all_tags( $line ) );
				if ( '' !== $line ) {
					$topics[] = $line;
				}
			}
			$topics = array_slice( $topics, 0, 100 );

			$status = isset( $cfg['status'] ) ? sanitize_key( $cfg['status'] ) : Content_Assistant_Settings::get_one( 'default_post_status' );
			if ( ! in_array( $status, array( 'draft', 'pending', 'publish', 'private' ), true ) ) {
				$status = 'draft';
			}

			$cats = array();
			if ( ! empty( $cfg['category_ids'] ) && is_array( $cfg['category_ids'] ) ) {
				$cats = array_slice( array_filter( array_map( 'intval', $cfg['category_ids'] ) ), 0, 20 );
			}

			$out[ $pt ] = array(
				'enabled'       => $enabled,
				'count'         => $count,
				'mode'          => $mode,
				'times'         => $times,
				'window_start'  => $w_start,
				'window_end'    => $w_end,
				'topics'        => $topics,
				'words'         => isset( $cfg['words'] ) ? min( 5000, max( 150, (int) $cfg['words'] ) ) : 600,
				'status'        => $status,
				'category_ids'  => $cats,
				'extra_prompt'  => isset( $cfg['extra_prompt'] ) ? sanitize_textarea_field( $cfg['extra_prompt'] ) : '',
				'generate_image'=> ! empty( $cfg['generate_image'] ) ? 1 : 0,
			);
		}

		return $out;
	}

	/**
	 * اعتبارسنجی HH:MM.
	 *
	 * @param string $v مقدار.
	 * @return string
	 */
	private static function sanitize_hhmm( $v ) {
		$v = trim( sanitize_text_field( (string) $v ) );
		if ( preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', $v, $m ) ) {
			return sprintf( '%02d:%s', (int) $m[1], $m[2] );
		}
		return '08:00';
	}

	/**
	 * اسلات‌های امروز یک برنامه را برمی‌گرداند: ['08:00', '10:00', …].
	 *
	 * @param array $cfg برنامه.
	 * @return array
	 */
	public static function slots_for_today( $cfg ) {
		$count = max( 0, (int) $cfg['count'] );

		if ( 'times' === $cfg['mode'] ) {
			$times = is_array( $cfg['times'] ) ? $cfg['times'] : array();
			if ( $times ) {
				// اگر تعداد زمان‌ها کمتر از تعداد روزانه بود، با فاصله تکمیل می‌شود.
				if ( count( $times ) < $count ) {
					$more  = self::interval_slots( $cfg['window_start'], $cfg['window_end'], $count - count( $times ) );
					$times = array_slice( array_unique( array_merge( $times, $more ) ), 0, $count );
					sort( $times );
				}
				return array_slice( $times, 0, $count );
			}
			// اگر زمانی وارد نشده، به حالت بازه رجوع کن.
			$mode = 'intervals';
		}

		return self::interval_slots( $cfg['window_start'], $cfg['window_end'], $count );
	}

	/**
	 * اسلات‌های یکنواخت بین دو زمان.
	 *
	 * @param string $start شروع HH:MM.
	 * @param string $end   پایان HH:MM.
	 * @param int    $count تعداد.
	 * @return array
	 */
	private static function interval_slots( $start, $end, $count ) {
		if ( $count <= 0 ) {
			return array();
		}
		$s = ( (int) substr( $start, 0, 2 ) ) * 60 + (int) substr( $start, 3, 2 );
		$e = ( (int) substr( $end, 0, 2 ) ) * 60 + (int) substr( $end, 3, 2 );
		if ( $e <= $s ) {
			$e = $s + 1; // جلوگیری از تقسیم بر صفر؛ کل روز بازه محسوب می‌شود.
		}
		$slots = array();
		if ( 1 === $count ) {
			$slots[] = sprintf( '%02d:%02d', intdiv( $s, 60 ) % 24, $s % 60 );
			return $slots;
		}
		for ( $i = 0; $i < $count; $i++ ) {
			$min     = (int) round( $s + ( ( $e - $s ) / ( $count - 1 ) ) * $i );
			$min     = min( 1439, $min );
			$slots[] = sprintf( '%02d:%02d', intdiv( $min, 60 ), $min % 60 );
		}
		return array_unique( $slots );
	}

	/**
	 * وضعیت اجرای امروز: کدام اسلات‌ها انجام شده‌اند.
	 *
	 * @return array
	 */
	private static function get_state() {
		$state  = get_option( self::STATE_OPTION, array() );
		$state  = is_array( $state ) ? $state : array();
		$today  = current_time( 'Y-m-d' );

		if ( empty( $state['date'] ) || $state['date'] !== $today ) {
			$state = array(
				'date' => $today,
				'done' => array(), // post_type => [ '08:00', ... ]
			);
		}
		return $state;
	}

	/**
	 * ذخیرهٔ وضعیت.
	 *
	 * @param array $state وضعیت.
	 */
	private static function save_state( $state ) {
		update_option( self::STATE_OPTION, $state, false );
	}

	/**
	 * ضربهٔ cron: بررسی اسلات‌های سررسیدشده و تولید.
	 */
	public static function tick() {
		$active = self::active();
		if ( ! $active ) {
			return;
		}

		// اگر کلید API تنظیم نشده، کاری نکن.
		if ( '' === trim( (string) Content_Assistant_Settings::get_one( 'api_key' ) ) ) {
			return;
		}

		$now_hm = current_time( 'H:i' );
		$state  = self::get_state();

		foreach ( $active as $pt => $cfg ) {
			if ( ! post_type_exists( $pt ) ) {
				continue;
			}

			$slots  = self::slots_for_today( $cfg );
			$done   = isset( $state['done'][ $pt ] ) ? (array) $state['done'][ $pt ] : array();

			foreach ( $slots as $slot ) {
				if ( in_array( $slot, $done, true ) ) {
					continue;
				}
				if ( $slot > $now_hm ) {
					continue; // هنوز موعدش نرسیده.
				}

				// اسلات را ابتدا علامت می‌زنیم تا در صورت خطا دوباره صف نشود.
				$state['done'][ $pt ][] = $slot;
				self::save_state( $state );

				$topic = self::next_topic( $cfg, $pt );
				$args  = array(
					'topic'        => $topic,
					'status'       => $cfg['status'],
					'words'        => $cfg['words'],
					'extra_prompt' => $cfg['extra_prompt'],
					'category_ids' => $cfg['category_ids'],
				);

				$result = Content_Assistant_Generator::create_post( $pt, $args );

				if ( is_wp_error( $result ) ) {
					Content_Assistant_Logger::log( 'schedule', sprintf( 'خطا در تولید خودکار %s (اسلات %s): %s', $pt, $slot, $result->get_error_message() ) );
				} else {
					Content_Assistant_Logger::log( 'schedule', sprintf( 'تولید خودکار %s در %s: «%s»', $pt, $slot, $result['title'] ), array( 'post_id' => $result['post_id'] ) );

					// تصویر شاخص اختیاری.
					if ( ! empty( $cfg['generate_image'] ) && ! empty( $result['image_prompt'] ) ) {
						self::generate_featured_image( $result['post_id'], $result['image_prompt'] );
					}
				}

				break; // در هر ضربه فقط یک پست، برای جلوگیری از فشار به API.
			}
		}
	}

	/**
	 * انتخاب موضوع بعدی (چرخشی روی فهرست موضوعات).
	 *
	 * @param array  $cfg برنامه.
	 * @param string $pt  پست‌تایپ.
	 * @return string
	 */
	private static function next_topic( $cfg, $pt ) {
		$topics = isset( $cfg['topics'] ) && is_array( $cfg['topics'] ) ? $cfg['topics'] : array();
		if ( ! $topics ) {
			return '';
		}
		$state = self::get_state();
		$idx   = isset( $state['topic_idx'][ $pt ] ) ? (int) $state['topic_idx'][ $pt ] : 0;
		$topic = $topics[ $idx % count( $topics ) ];

		$state['topic_idx'][ $pt ] = $idx + 1;
		self::save_state( $state );

		return $topic;
	}

	/**
	 * تولید و افزودن تصویر شاخص از آدرس/دادهٔ base64.
	 *
	 * @param int    $post_id شناسهٔ پست.
	 * @param string $prompt  پرامپت تصویر.
	 * @return void
	 */
	public static function generate_featured_image( $post_id, $prompt ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$res = Content_Assistant_Client::image( $prompt, '1024x1024' );
		if ( is_wp_error( $res ) ) {
			Content_Assistant_Logger::log( 'error', 'خطای تولید تصویر شاخص: ' . $res->get_error_message(), array( 'post_id' => $post_id ) );
			return;
		}

		$filename = 'content-assistant-' . $post_id . '-' . wp_generate_password( 8, false ) . '.png';

		if ( ! empty( $res['b64'] ) ) {
			$upload = wp_upload_bits( $filename, null, base64_decode( $res['b64'] ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( ! empty( $upload['error'] ) ) {
				Content_Assistant_Logger::log( 'error', 'خطای ذخیرهٔ تصویر: ' . $upload['error'] );
				return;
			}
			$file = $upload['file'];
		} elseif ( ! empty( $res['url'] ) ) {
			$tmp = download_url( $res['url'], 120 );
			if ( is_wp_error( $tmp ) ) {
				Content_Assistant_Logger::log( 'error', 'خطای دانلود تصویر: ' . $tmp->get_error_message() );
				return;
			}
			$file = $tmp;
		} else {
			return;
		}

		$attach_id = media_handle_sideload(
			array(
				'name'     => $filename,
				'tmp_name' => $file,
			),
			$post_id,
			sprintf( 'تصویر شاخص تولیدشده با Content Assistant از پرامپت: %s', $prompt )
		);

		if ( is_wp_error( $attach_id ) ) {
			Content_Assistant_Logger::log( 'error', 'خطای افزودن تصویر به کتابخانه: ' . $attach_id->get_error_message() );
			return;
		}

		set_post_thumbnail( $post_id, $attach_id );
		Content_Assistant_Logger::track_generated( 'images' );
		Content_Assistant_Logger::log( 'generation', 'تصویر شاخص برای پست #' . $post_id . ' تولید شد.', array( 'attach_id' => $attach_id ) );
	}

	/**
	 * ذخیرهٔ فرم صفحهٔ زمان‌بندی.
	 */
	public static function save_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( content_assistant_te( 'unauthorized' ) );
		}
		check_admin_referer( 'content_assistant_save_schedules' );

		$raw   = isset( $_POST['content_assistant_sched'] ) ? wp_unslash( $_POST['content_assistant_sched'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$clean = self::sanitize_all( $raw );

		update_option( self::SCHED_OPTION, $clean, false );
		self::schedule_event(); // مطمئن شو cron ثبت است.

		Content_Assistant_Logger::log( 'info', 'تنظیمات زمان‌بندی تولید ذخیره شد.', array( 'post_types' => array_keys( $clean ) ) );

		wp_safe_redirect( add_query_arg( array( 'page' => 'content-assistant-schedule', 'content_assistant_saved' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * تولید دستیِ فوری یک پست‌تایپ (دکمهٔ «تولید آزمایشی»).
	 */
	public static function manual_generate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( content_assistant_te( 'unauthorized' ) );
		}
		check_admin_referer( 'content_assistant_generate_now' );

		$pt  = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$cfg = self::get( $pt );

		if ( ! $pt || ! post_type_exists( $pt ) ) {
			wp_die( content_assistant_te( 'bad_post_type' ) );
		}

		$args = array(
			'topic'        => $cfg ? self::next_topic( $cfg, $pt ) : '',
			'status'       => ( $cfg && ! empty( $cfg['status'] ) ) ? $cfg['status'] : Content_Assistant_Settings::get_one( 'default_post_status' ),
			'words'        => ( $cfg && ! empty( $cfg['words'] ) ) ? $cfg['words'] : 600,
			'extra_prompt' => ( $cfg && ! empty( $cfg['extra_prompt'] ) ) ? $cfg['extra_prompt'] : '',
			'category_ids' => ( $cfg && ! empty( $cfg['category_ids'] ) ) ? $cfg['category_ids'] : array(),
		);

		$result = Content_Assistant_Generator::create_post( $pt, $args );

		if ( is_wp_error( $result ) ) {
			Content_Assistant_Logger::log( 'error', 'تولید دستی ناموفق: ' . $result->get_error_message() );
			wp_safe_redirect( add_query_arg( array( 'page' => 'content-assistant-schedule', 'content_assistant_err' => rawurlencode( $result->get_error_message() ) ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( $cfg && ! empty( $cfg['generate_image'] ) && ! empty( $result['image_prompt'] ) ) {
			self::generate_featured_image( $result['post_id'], $result['image_prompt'] );
		}

		wp_safe_redirect( get_edit_post_link( $result['post_id'], 'raw' ) );
		exit;
	}

	/**
	 * صفحهٔ زمان‌بندی تولید.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$types      = Content_Assistant_Settings::editable_post_types();
		$all        = self::get_all();
		$state      = self::get_state();
		$state      = isset( $state['done'] ) ? $state['done'] : array();
		$categories = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) );
		$categories = is_wp_error( $categories ) ? array() : $categories;
		?>
		<div class="wrap content-assistant-wrap" dir="<?php echo esc_attr( Content_Assistant_I18n::dir() ); ?>">
			<?php Content_Assistant_Settings::render_header( 'schedule_title', 'schedule_desc' ); ?>

			<?php if ( isset( $_GET['content_assistant_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( content_assistant_te( 'schedule_saved' ) ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['content_assistant_err'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( wp_kses_post( urldecode( sanitize_text_field( wp_unslash( $_GET['content_assistant_err'] ) ) ) ) ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'content_assistant_save_schedules' ); ?>
				<input type="hidden" name="action" value="content_assistant_save_schedules" />

				<?php foreach ( $types as $pt => $label ) : ?>
					<?php
					$cfg  = isset( $all[ $pt ] ) ? wp_parse_args(
						$all[ $pt ],
						array(
							'enabled'        => 0,
							'count'          => 0,
							'mode'           => 'intervals',
							'times'          => array(),
							'window_start'   => '08:00',
							'window_end'     => '22:00',
							'topics'         => array(),
							'words'          => 600,
							'status'         => 'draft',
							'category_ids'   => array(),
							'extra_prompt'   => '',
							'generate_image' => 0,
						)
					) : array(
						'enabled'        => 0,
						'count'          => 0,
						'mode'           => 'intervals',
						'times'          => array(),
						'window_start'   => '08:00',
						'window_end'     => '22:00',
						'topics'         => array(),
						'words'          => 600,
						'status'         => 'draft',
						'category_ids'   => array(),
						'extra_prompt'   => '',
						'generate_image' => 0,
					);
					$done_today = isset( $state[ $pt ] ) ? count( (array) $state[ $pt ] ) : 0;
					$slots      = $cfg['enabled'] ? self::slots_for_today( $cfg ) : array();
					?>
					<div class="content-assistant-card content-assistant-card--lg content-assistant-sched-card <?php echo esc_attr( empty( $cfg['enabled'] ) ? 'is-off' : 'is-on' ); ?>">
						<div class="content-assistant-sched-head">
							<label class="content-assistant-sched-title">
								<input type="checkbox" name="content_assistant_sched[<?php echo esc_attr( $pt ); ?>][enabled]" value="1" <?php checked( ! empty( $cfg['enabled'] ) ); ?> />
								<span class="content-assistant-sched-name"><?php echo esc_html( $label ); ?></span>
								<code class="content-assistant-sched-slug"><?php echo esc_html( $pt ); ?></code>
							</label>
							<div class="content-assistant-sched-meta">
								<span class="content-assistant-badge">
									<strong><?php echo esc_html( sprintf( content_assistant_t( 'today_progress' ), (int) $done_today, (int) $cfg['count'] ) ); ?></strong>
								</span>
								<?php if ( $slots ) : ?>
									<span class="content-assistant-slots">
										<span class="content-assistant-slots-label"><?php echo esc_html( content_assistant_te( 'slots_label' ) ); ?></span>
										<?php foreach ( $slots as $sl ) : ?>
											<code class="content-assistant-slot <?php echo esc_attr( in_array( $sl, (array) $state[ $pt ], true ) ? 'is-done' : '' ); ?>"><?php echo esc_html( $sl ); ?></code>
										<?php endforeach; ?>
									</span>
								<?php endif; ?>
								<?php if ( $cfg['enabled'] ) : ?>
									<a class="button button-small content-assistant-btn"
										href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=content_assistant_generate_now&post_type=' . $pt ), 'content_assistant_generate_now' ) ); ?>"
										onclick="return confirm('<?php echo esc_js( content_assistant_te( 'generate_test_confirm' ) ); ?>');">
										<?php echo esc_html( content_assistant_te( 'generate_test' ) ); ?>
									</a>
								<?php endif; ?>
							</div>
						</div>

						<table class="form-table content-assistant-form" role="presentation">
							<tr>
								<th scope="row"><label for="sched-count-<?php echo esc_attr( $pt ); ?>"><?php echo esc_html( content_assistant_te( 'posts_per_day' ) ); ?></label></th>
								<td>
									<input type="number" min="0" max="48" class="small-text content-assistant-input" id="sched-count-<?php echo esc_attr( $pt ); ?>"
										name="content_assistant_sched[<?php echo esc_attr( $pt ); ?>][count]"
										value="<?php echo esc_attr( $cfg['count'] ); ?>" dir="ltr" />
								</td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html( content_assistant_te( 'scheduling_mode' ) ); ?></th>
								<td>
									<label class="content-assistant-radio">
										<input type="radio" name="content_assistant_sched[<?php echo esc_attr( $pt ); ?>][mode]" value="intervals"
											<?php checked( $cfg['mode'], 'intervals' ); ?> onchange="this.closest('.content-assistant-card').querySelector('.content-assistant-times').style.display='none';this.closest('.content-assistant-card').querySelector('.content-assistant-window').style.display='';" />
										<?php echo esc_html( content_assistant_te( 'mode_intervals' ) ); ?>
									</label>
									<label class="content-assistant-radio content-assistant-radio--block">
										<input type="radio" name="content_assistant_sched[<?php echo esc_attr( $pt ); ?>][mode]" value="times"
											<?php checked( $cfg['mode'], 'times' ); ?> onchange="this.closest('.content-assistant-card').querySelector('.content-assistant-times').style.display='';this.closest('.content-assistant-card').querySelector('.content-assistant-window').style.display='none';" />
										<?php echo esc_html( content_assistant_te( 'mode_times' ) ); ?>
									</label>

									<div class="content-assistant-window content-assistant-inline2" style="<?php echo 'times' === $cfg['mode'] ? 'display:none;' : ''; ?>">
										<label><?php echo esc_html( content_assistant_te( 'from' ) ); ?>
											<input type="time" class="content-assistant-input" name="content_assistant_sched[<?php echo esc_attr( $pt ); ?>][window_start]" value="<?php echo esc_attr( $cfg['window_start'] ); ?>" dir="ltr" />
										</label>
										<label><?php echo esc_html( content_assistant_te( 'to' ) ); ?>
											<input type="time" class="content-assistant-input" name="content_assistant_sched[<?php echo esc_attr( $pt ); ?>][window_end]" value="<?php echo esc_attr( $cfg['window_end'] ); ?>" dir="ltr" />
										</label>
									</div>

									<div class="content-assistant-times" style="<?php echo 'intervals' === $cfg['mode'] ? 'display:none;' : ''; ?>">
										<input type="text" class="large-text content-assistant-input" dir="ltr" placeholder="08:00,12:30,18:00"
											name="content_assistant_sched[<?php echo esc_attr( $pt ); ?>][times]"
											value="<?php echo esc_attr( implode( ',', (array) $cfg['times'] ) ); ?>" />
										<p class="description"><?php echo esc_html( content_assistant_te( 'times_desc' ) ); ?></p>
									</div>
								</td>
							</tr>
							<tr>
								<th scope="row"><label><?php echo esc_html( content_assistant_te( 'topics_label' ) ); ?></label></th>
								<td>
									<textarea class="large-text content-assistant-input" rows="4" name="content_assistant_sched[<?php echo esc_attr( $pt ); ?>][topics]"><?php echo esc_textarea( implode( "\n", (array) $cfg['topics'] ) ); ?></textarea>
									<p class="description"><?php echo esc_html( content_assistant_te( 'topics_desc' ) ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html( content_assistant_te( 'details' ) ); ?></th>
								<td>
									<label><?php echo esc_html( content_assistant_te( 'content_words' ) ); ?>
										<input type="number" min="150" max="5000" class="small-text content-assistant-input" name="content_assistant_sched[<?php echo esc_attr( $pt ); ?>][words]" value="<?php echo esc_attr( $cfg['words'] ); ?>" dir="ltr" />
									</label>
									<br />
									<label class="content-assistant-blockfield"><?php echo esc_html( content_assistant_te( 'post_status' ) ); ?>
										<select class="content-assistant-select" name="content_assistant_sched[<?php echo esc_attr( $pt ); ?>][status]">
											<option value="draft" <?php selected( $cfg['status'], 'draft' ); ?>><?php echo esc_html( content_assistant_te( 'status_draft' ) ); ?></option>
											<option value="pending" <?php selected( $cfg['status'], 'pending' ); ?>><?php echo esc_html( content_assistant_te( 'status_pending' ) ); ?></option>
											<option value="publish" <?php selected( $cfg['status'], 'publish' ); ?>><?php echo esc_html( content_assistant_te( 'status_publish' ) ); ?></option>
										</select>
									</label>
									<br />
									<label class="content-assistant-blockfield"><?php echo esc_html( content_assistant_te( 'categories_optional' ) ); ?>
										<select class="content-assistant-select content-assistant-select--multi" name="content_assistant_sched[<?php echo esc_attr( $pt ); ?>][category_ids][]" multiple size="4">
											<?php foreach ( $categories as $cat ) : ?>
												<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( in_array( (int) $cat->term_id, array_map( 'intval', (array) $cfg['category_ids'] ), true ) ); ?>>
													<?php echo esc_html( $cat->name ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</label>
									<label class="content-assistant-check content-assistant-blockfield">
										<input type="checkbox" name="content_assistant_sched[<?php echo esc_attr( $pt ); ?>][generate_image]" value="1" <?php checked( ! empty( $cfg['generate_image'] ) ); ?> />
										<?php echo esc_html( content_assistant_te( 'generate_featured_image' ) ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html( content_assistant_te( 'extra_prompt_label' ) ); ?></th>
								<td>
									<textarea class="large-text content-assistant-input" rows="2" name="content_assistant_sched[<?php echo esc_attr( $pt ); ?>][extra_prompt]"><?php echo esc_textarea( $cfg['extra_prompt'] ); ?></textarea>
									<p class="description"><?php echo esc_html( content_assistant_te( 'extra_prompt_desc' ) ); ?></p>
								</td>
							</tr>
						</table>
					</div>
				<?php endforeach; ?>

				<div class="content-assistant-submitbar">
					<?php submit_button( content_assistant_te( 'save_schedule' ), 'primary large', 'submit', false ); ?>
				</div>
			</form>

			<?php Content_Assistant_Settings::render_footer(); ?>
		</div>
		<?php
	}
}
