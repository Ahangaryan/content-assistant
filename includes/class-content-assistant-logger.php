<?php
/**
 * لاگ درخواست‌ها و آمار مصرف توکن.
 *
 * @package ContentAssistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ثبت رویدادها در گزینهٔ content_assistant_log و شمارش مصرف در content_assistant_usage.
 * از transient برای بافر کردن نوشتن‌ها استفاده می‌شود تا فشار روی دیتابیس کم شود.
 */
class Content_Assistant_Logger {

	const LOG_OPTION    = 'content_assistant_log';
	const USAGE_OPTION  = 'content_assistant_usage';
	const LOG_MAX_ITEMS = 500;
	const BUFFER_SIZE   = 10; // تعداد لاگ‌ها قبل از فلش شدن.
	const BUFFER_TRANSIENT = 'content_assistant_log_buffer';

	/**
	 * ثبت یک رویداد (بافرشده).
	 *
	 * @param string $type    نوع: error|info|generation|bulk|assistant|schedule.
	 * @param string $message پیام.
	 * @param array  $context اطلاعات تکمیلی.
	 */
	public static function log( $type, $message, $context = array() ) {
		$entry = array(
			'time'    => current_time( 'mysql' ),
			'type'    => sanitize_key( $type ),
			'message' => sanitize_textarea_field( $message ),
			'context' => self::sanitize_context( $context ),
		);

		// خواندن بافر از transient.
		$buffer = get_transient( self::BUFFER_TRANSIENT );
		if ( ! is_array( $buffer ) ) {
			$buffer = array();
		}

		$buffer[] = $entry;

		// اگر بافر پر شد، فلش کن.
		if ( count( $buffer ) >= self::BUFFER_SIZE ) {
			self::flush_buffer( $buffer );
			delete_transient( self::BUFFER_TRANSIENT );
		} else {
			// بافر را در transient ذخیره (منقضی می‌شود در 1 ساعت).
			set_transient( self::BUFFER_TRANSIENT, $buffer, HOUR_IN_SECONDS );
			// همچنین یک هک برای فلش در shutdown رجیستر می‌کنیم.
			self::register_shutdown_flush();
		}
	}

	/**
	 * فلش بافر به دیتابیس.
	 *
	 * @param array $buffer بافر لاگ‌ها.
	 */
	private static function flush_buffer( $buffer ) {
		if ( empty( $buffer ) ) {
			return;
		}

		$log = get_option( self::LOG_OPTION, array() );
		$log = is_array( $log ) ? $log : array();

		$log = array_merge( $log, $buffer );

		if ( count( $log ) > self::LOG_MAX_ITEMS ) {
			$log = array_slice( $log, -1 * self::LOG_MAX_ITEMS );
		}

		update_option( self::LOG_OPTION, $log, false );
	}

	/**
	 * ثبت یکباره فلش در shutdown.
	 */
	private static function register_shutdown_flush() {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;
		add_action( 'shutdown', array( __CLASS__, 'shutdown_flush' ) );
	}

	/**
	 * فلش بافر در پایان اجرا.
	 */
	public static function shutdown_flush() {
		$buffer = get_transient( self::BUFFER_TRANSIENT );
		if ( is_array( $buffer ) && $buffer ) {
			self::flush_buffer( $buffer );
			delete_transient( self::BUFFER_TRANSIENT );
		}
	}

	/**
	 * زوراً فلش کردن بافر (برای استفاده در ادمین).
	 */
	public static function force_flush() {
		$buffer = get_transient( self::BUFFER_TRANSIENT );
		if ( is_array( $buffer ) && $buffer ) {
			self::flush_buffer( $buffer );
			delete_transient( self::BUFFER_TRANSIENT );
		}
	}

	/**
	 * پاکسازی context از مقادیر بزرگ.
	 *
	 * @param mixed $context داده.
	 * @return mixed
	 */
	private static function sanitize_context( $context ) {
		if ( ! is_array( $context ) ) {
			return sanitize_text_field( (string) $context );
		}
		$clean = array();
		foreach ( $context as $k => $v ) {
			$key = sanitize_key( $k );
			if ( is_array( $v ) ) {
				$v = wp_json_encode( array_slice( $v, 0, 20 ) );
			}
			$v = (string) $v;
			if ( strlen( $v ) > 1000 ) {
				$v = substr( $v, 0, 1000 ) . '…';
			}
			$clean[ $key ] = sanitize_text_field( $v );
		}
		return $clean;
	}

	/**
	 * شمارش یک فراخوانی API همراه توکن‌ها (بافرشده).
	 *
	 * @param array|null $usage آرایهٔ usage از پاسخ API.
	 * @param string     $kind  نوع: chat|image.
	 */
	public static function track_api_call( $usage, $kind = 'chat' ) {
		$today = current_time( 'Y-m-d' );

		// استفاده از transient برای شمارنده‌های روزانه.
		$counter_key = 'content_assistant_usage_counter_' . $today;
		$counters    = get_transient( $counter_key );
		if ( ! is_array( $counters ) ) {
			$counters = array(
				'calls'             => 0,
				'chat_calls'        => 0,
				'image_calls'       => 0,
				'prompt_tokens'     => 0,
				'completion_tokens' => 0,
				'total_tokens'      => 0,
			);
		}

		$counters['calls']++;
		$counters[ $kind . '_calls' ]++;

		if ( is_array( $usage ) ) {
			$pt = isset( $usage['prompt_tokens'] ) ? (int) $usage['prompt_tokens'] : 0;
			$ct = isset( $usage['completion_tokens'] ) ? (int) $usage['completion_tokens'] : 0;
			$counters['prompt_tokens']     += $pt;
			$counters['completion_tokens'] += $ct;
			$counters['total_tokens']      += $pt + $ct;
		}

		set_transient( $counter_key, $counters, DAY_IN_SECONDS * 2 );

		// آپدیت last_call در آپشن اصلی (کم‌فریکوانس).
		$data = get_option( self::USAGE_OPTION, array() );
		$data = is_array( $data ) ? $data : array();
		$data['last_call'] = current_time( 'mysql' );
		update_option( self::USAGE_OPTION, $data, false );
	}

	/**
	 * همگام‌سازی شمارنده‌های transient با آپشن اصلی (در صفحهٔ لاگ فراخوانی می‌شود).
	 */
	public static function sync_counters() {
		// ۳۰ روز گذشته را چک کن.
		for ( $i = 0; $i < 30; $i++ ) {
			$date = date( 'Y-m-d', strtotime( "-{$i} days" ) );
			$counter_key = 'content_assistant_usage_counter_' . $date;
			$counters    = get_transient( $counter_key );

			if ( is_array( $counters ) ) {
				$data = get_option( self::USAGE_OPTION, array() );
				$data = is_array( $data ) ? $data : array();

				if ( ! isset( $data['by_date'] ) || ! is_array( $data['by_date'] ) ) {
					$data['by_date'] = array();
				}
				if ( ! isset( $data['by_date'][ $date ] ) ) {
					$data['by_date'][ $date ] = array( 'calls' => 0, 'prompt_tokens' => 0, 'completion_tokens' => 0 );
				}

				$data['by_date'][ $date ]['calls']             = (int) $data['by_date'][ $date ]['calls'] + $counters['calls'];
				$data['by_date'][ $date ]['prompt_tokens']     = (int) $data['by_date'][ $date ]['prompt_tokens'] + $counters['prompt_tokens'];
				$data['by_date'][ $date ]['completion_tokens'] = (int) $data['by_date'][ $date ]['completion_tokens'] + $counters['completion_tokens'];

				$data['calls']             = isset( $data['calls'] ) ? (int) $data['calls'] + $counters['calls'] : $counters['calls'];
				$data['chat_calls']        = isset( $data['chat_calls'] ) ? (int) $data['chat_calls'] + $counters['chat_calls'] : $counters['chat_calls'];
				$data['image_calls']       = isset( $data['image_calls'] ) ? (int) $data['image_calls'] + $counters['image_calls'] : $counters['image_calls'];
				$data['prompt_tokens']     = isset( $data['prompt_tokens'] ) ? (int) $data['prompt_tokens'] + $counters['prompt_tokens'] : $counters['prompt_tokens'];
				$data['completion_tokens'] = isset( $data['completion_tokens'] ) ? (int) $data['completion_tokens'] + $counters['completion_tokens'] : $counters['completion_tokens'];
				$data['total_tokens']      = isset( $data['total_tokens'] ) ? (int) $data['total_tokens'] + $counters['total_tokens'] : $counters['total_tokens'];

				update_option( self::USAGE_OPTION, $data, false );
				delete_transient( $counter_key );
			}
		}

		// نگه‌داری ۳۰ روز اخیر.
		$data = get_option( self::USAGE_OPTION, array() );
		if ( isset( $data['by_date'] ) && is_array( $data['by_date'] ) ) {
			$data['by_date'] = array_slice( $data['by_date'], -30, null, true );
			update_option( self::USAGE_OPTION, $data, false );
		}
	}

	/**
	 * شمارش یک عملیات تولید.
	 *
	 * @param string $kind posts|images.
	 * @param int    $n    تعداد.
	 */
	public static function track_generated( $kind, $n = 1 ) {
		$today = current_time( 'Y-m-d' );
		$key   = 'content_assistant_generated_' . $kind . '_' . $today;
		$count = (int) get_transient( $key );
		set_transient( $key, $count + (int) $n, DAY_IN_SECONDS * 2 );
	}

	/**
	 * آمار کامل مصرف (همگام‌سازی پیش از خواندن).
	 *
	 * @return array
	 */
	public static function usage() {
		self::sync_counters();
		$data = get_option( self::USAGE_OPTION, array() );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * کل لاگ (فلش پیش از خواندن).
	 *
	 * @return array
	 */
	public static function logs() {
		self::force_flush();
		$log = get_option( self::LOG_OPTION, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * پاک کردن لاگ.
	 */
	public static function clear_logs() {
		update_option( self::LOG_OPTION, array(), false );
		delete_transient( self::BUFFER_TRANSIENT );
	}
}
