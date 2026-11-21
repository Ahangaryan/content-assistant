<?php
/**
 * کلاینت API Content Assistant (سازگار با OpenAI).
 *
 * @package ContentAssistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * فراخوانی‌های HTTP به API Content Assistant.
 */
class Content_Assistant_Client {

	/**
	 * تنظیمات با مقادیر پیش‌فرض.
	 *
	 * @return array
	 */
	public static function settings() {
		return Content_Assistant_Settings::get();
	}

	/**
	 * بررسی و اعمال نرخ محدودیت (Rate Limiting).
	 *
	 * @return bool|WP_Error true در صورت مجاز بودن، WP_Error در صورت محدودیت.
	 */
	private static function check_rate_limit() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return true; // کاربران مهمان بررسی نمی‌شوند.
		}

		$limit_key = 'content_assistant_rate_limit_' . $user_id;
		$current   = (int) get_transient( $limit_key );

		// حداکثر 30 درخواست در دقیقه برای هر کاربر.
		$max_requests = 30;
		$window       = 60; // ثانیه.

		if ( $current >= $max_requests ) {
			return new WP_Error(
				'content_assistant_rate_limited',
				content_assistant_t( 'err_rate_limited' ),
				array( 'retry_after' => $window )
			);
		}

		// افزایش شمارنده.
		if ( false === $current ) {
			set_transient( $limit_key, 1, $window );
		} else {
			set_transient( $limit_key, $current + 1, $window );
		}

		return true;
	}

	/**
	 * فراخوانی chat/completions.
	 *
	 * @param array $args messages, model, temperature, max_tokens, json.
	 * @return array|WP_Error ['content'=>string, 'usage'=>array, 'model'=>string]
	 */
	public static function chat( $args ) {
		// بررسی نرخ محدودیت.
		$rate_check = self::check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$s       = self::settings();
		$api_key = isset( $s['api_key'] ) ? trim( (string) $s['api_key'] ) : '';

		if ( '' === $api_key ) {
			return new WP_Error( 'content_assistant_no_key', content_assistant_t( 'err_no_api_key' ) );
		}

		$model = ! empty( $args['model'] ) ? sanitize_text_field( $args['model'] ) : sanitize_text_field( $s['model'] );

		$body = array(
			'model'    => $model,
			'messages' => isset( $args['messages'] ) ? $args['messages'] : array(),
		);
		if ( isset( $args['temperature'] ) ) {
			$body['temperature'] = (float) $args['temperature'];
		} elseif ( isset( $s['temperature'] ) && '' !== $s['temperature'] ) {
			$body['temperature'] = (float) $s['temperature'];
		}
		if ( isset( $args['max_tokens'] ) ) {
			$body['max_tokens'] = (int) $args['max_tokens'];
		} elseif ( ! empty( $s['max_tokens'] ) ) {
			$body['max_tokens'] = (int) $s['max_tokens'];
		}

		$endpoint = untrailingslashit( $s['base_url'] ) . '/chat/completions';

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 180,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			Content_Assistant_Logger::log( 'error', 'خطای ارتباط با API: ' . $response->get_error_message(), array( 'endpoint' => $endpoint ) );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : substr( $raw, 0, 300 );
			Content_Assistant_Logger::log( 'error', 'API error (code ' . $code . '): ' . $msg, array( 'endpoint' => $endpoint, 'response' => $raw ) );
			return new WP_Error( 'content_assistant_api_error', content_assistant_t( 'err_api', array( $code, $msg ) ), array( 'status' => $code ) );
		}

		$content = '';
		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			$content = (string) $data['choices'][0]['message']['content'];
		}

		$usage = isset( $data['usage'] ) && is_array( $data['usage'] ) ? $data['usage'] : null;
		Content_Assistant_Logger::track_api_call( $usage, 'chat' );

		if ( '' === trim( $content ) ) {
			return new WP_Error( 'content_assistant_empty', content_assistant_t( 'err_empty_response' ) );
		}

		return array(
			'content' => $content,
			'usage'   => $usage,
			'model'   => isset( $data['model'] ) ? $data['model'] : $model,
		);
	}

	/**
	 * تولید تصویر با images/generations. آدرس فایل خروجی را برمی‌گرداند.
	 *
	 * @param string $prompt متن.
	 * @param string $size   اندازه.
	 * @return array|WP_Error ['url'=>string]
	 */
	public static function image( $prompt, $size = '1024x1024' ) {
		// بررسی نرخ محدودیت.
		$rate_check = self::check_rate_limit();
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$s       = self::settings();
		$api_key = isset( $s['api_key'] ) ? trim( (string) $s['api_key'] ) : '';

		if ( '' === $api_key ) {
			return new WP_Error( 'content_assistant_no_key', content_assistant_t( 'err_no_api_key' ) );
		}

		$body = array(
			'model'  => ! empty( $s['image_model'] ) ? $s['image_model'] : 'gpt-image-2',
			'prompt' => sanitize_textarea_field( $prompt ),
			'size'   => sanitize_text_field( $size ),
			'n'      => 1,
		);

		$endpoint = untrailingslashit( $s['base_url'] ) . '/images/generations';

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 300,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			Content_Assistant_Logger::log( 'error', 'خطای ارتباط با API تصویر: ' . $response->get_error_message() );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : substr( $raw, 0, 300 );
			Content_Assistant_Logger::log( 'error', 'Image API error (code ' . $code . '): ' . $msg, array( 'response' => $raw ) );
			return new WP_Error( 'content_assistant_api_error', content_assistant_t( 'err_image_api', array( $code, $msg ) ) );
		}

		$usage = isset( $data['usage'] ) && is_array( $data['usage'] ) ? $data['usage'] : null;
		Content_Assistant_Logger::track_api_call( $usage, 'image' );

		if ( empty( $data['data'][0] ) ) {
			return new WP_Error( 'content_assistant_empty', content_assistant_t( 'err_empty_image' ) );
		}

		$item = $data['data'][0];

		// ممکن است b64_json برگردد؛ در این حالت آدرس نداریم.
		if ( ! empty( $item['b64_json'] ) ) {
			return array(
				'b64'    => (string) $item['b64_json'],
				'url'    => '',
				'prompt' => $body['prompt'],
			);
		}

		return array(
			'url'    => isset( $item['url'] ) ? (string) $item['url'] : '',
			'b64'    => '',
			'prompt' => $body['prompt'],
		);
	}

	/**
	 * تست اتصال و اعتبار کلید: درخواست کوچک چت.
	 *
	 * @return array|WP_Error ['ok'=>bool, 'message'=>string, 'model'=>string]
	 */
	public static function test_connection() {
		$result = self::chat(
			array(
				'model'       => '',
				'max_tokens'  => 16,
				'messages'    => array(
					array(
						'role'    => 'user',
						'content' => 'ping — فقط کلمه pong را برگردان.',
					),
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'ok'      => true,
			'message' => content_assistant_t( 'test_ok' ),
			'model'   => $result['model'],
			'sample'  => substr( $result['content'], 0, 80 ),
		);
	}

	/**
	 * استخراج JSON معتبر از پاسخ مدل (با تحمل کد-فنس و متن اضافه).
	 *
	 * @param string $text خروجی مدل.
	 * @return array|null
	 */
	public static function extract_json( $text ) {
		$text = trim( (string) $text );

		// حذف کد-فنس ```json ... ```.
		if ( preg_match( '/```(?:json)?\s*([\s\S]*?)\s*```/', $text, $m ) ) {
			$text = trim( $m[1] );
		}

		$decoded = json_decode( $text, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		// تلاش برای یافتن اولین { تا آخرین }.
		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );
		if ( false !== $start && false !== $end && $end > $start ) {
			$decoded = json_decode( substr( $text, $start, $end - $start + 1 ), true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return null;
	}
}
