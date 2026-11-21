<?php
/**
 * REST API افزونه: پل میان دستیار JS و منطق PHP.
 *
 * @package ContentAssistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * روت‌های /content-assistant/v1/*.
 */
class Content_Assistant_REST {

	const NS = 'content-assistant/v1';

	/**
	 * hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * ثبت روت‌ها.
	 */
	public static function routes() {
		register_rest_route(
			self::NS,
			'/test-connection',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'api_test_connection' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			self::NS,
			'/assistant',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'api_assistant' ),
				'permission_callback' => array( __CLASS__, 'can_edit_post_arg' ),
			)
		);

		register_rest_route(
			self::NS,
			'/apply-field',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'api_apply_field' ),
				'permission_callback' => array( __CLASS__, 'can_edit_post_arg' ),
			)
		);

		register_rest_route(
			self::NS,
			'/fields',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'api_fields' ),
				'permission_callback' => array( __CLASS__, 'can_edit_post_arg' ),
			)
		);

		register_rest_route(
			self::NS,
			'/bulk-scan',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'api_bulk_scan' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			self::NS,
			'/bulk-process',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'api_bulk_process' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			self::NS,
			'/quick-test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'api_quick_test' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * بررسی دسترسی ویرایش پست از پارامتر body/query.
	 *
	 * @param WP_REST_Request $req درخواست.
	 * @return bool
	 */
	public static function can_edit_post_arg( $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		if ( ! $post_id ) {
			return current_user_can( 'edit_posts' );
		}
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * POST /test-connection.
	 *
	 * @return WP_REST_Response
	 */
	public static function api_test_connection() {
		$res = Content_Assistant_Client::test_connection();
		if ( is_wp_error( $res ) ) {
			return new WP_REST_Response(
				array(
					'ok'      => false,
					'message' => $res->get_error_message(),
				),
				200
			);
		}
		return new WP_REST_Response( $res, 200 );
	}

	/**
	 * POST /assistant — پیشنهاد دستیار برای قسمتی از پست.
	 *
	 * @param WP_REST_Request $req درخواست.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function api_assistant( $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		$action  = sanitize_key( (string) $req->get_param( 'action' ) );

		$allowed = array( 'suggest_title', 'write_content', 'improve_excerpt', 'improve_seo', 'improve_block', 'custom' );
		if ( ! in_array( $action, $allowed, true ) ) {
			return new WP_Error( 'content_assistant_bad_action', content_assistant_t( 'err_bad_action' ), array( 'status' => 400 ) );
		}

		$params = array(
			'instruction' => (string) $req->get_param( 'instruction' ),
			'field'       => (string) $req->get_param( 'field' ),
			'field_label' => (string) $req->get_param( 'field_label' ),
			'field_value' => (string) $req->get_param( 'field_value' ),
			'count'       => (int) $req->get_param( 'count' ),
			'mode'        => (string) $req->get_param( 'mode' ),
		);

		$result = Content_Assistant_Generator::assistant( $post_id, $action, $params );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'error' => $result->get_error_message(),
				),
				200
			);
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * POST /apply-field — اعمال مقدار پیشنهادی روی فیلد (با تأیید کاربر در سمت کلاینت).
	 *
	 * @param WP_REST_Request $req درخواست.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function api_apply_field( $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		$field   = sanitize_key( (string) $req->get_param( 'field' ) );
		$value   = (string) $req->get_param( 'value' );

		if ( ! $post_id || '' === $field ) {
			return new WP_Error( 'content_assistant_bad_request', content_assistant_t( 'err_bad_request' ), array( 'status' => 400 ) );
		}

		// meta:xxx را با دو نقطه حفظ می‌کنیم — sanitize_key حذفش می‌کند؛ بازسازی:
		$raw_field = (string) $req->get_param( 'field' );
		if ( 0 === strpos( $raw_field, 'meta:' ) ) {
			$field = 'meta:' . sanitize_text_field( substr( $raw_field, 5 ) );
		}

		$result = Content_Assistant_Generator::apply_field( $post_id, $field, $value );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'error' => $result->get_error_message(),
				),
				200
			);
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * GET /fields — فهرست فیلدهای قابل ویرایش پست.
	 *
	 * @param WP_REST_Request $req درخواست.
	 * @return WP_REST_Response
	 */
	public static function api_fields( $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		return new WP_REST_Response( array( 'fields' => Content_Assistant_Generator::detect_fields( $post_id ) ), 200 );
	}

	/**
	 * POST /bulk-scan — پیدا کردن پست‌ها با فیلتر.
	 *
	 * @param WP_REST_Request $req درخواست.
	 * @return WP_REST_Response
	 */
	public static function api_bulk_scan( $req ) {
		$posts = Content_Assistant_Bulk_Editor::query_posts(
			array(
				'post_type' => $req->get_param( 'post_type' ),
				'filter'    => $req->get_param( 'filter' ),
				'limit'     => $req->get_param( 'limit' ),
				'offset'    => $req->get_param( 'offset' ),
				'search'    => $req->get_param( 'search' ),
			)
		);

		$items = array();
		foreach ( $posts as $p ) {
			$items[] = array(
				'id'           => $p->ID,
				'title'        => $p->post_title ? $p->post_title : content_assistant_t( 'untitled' ),
				'status'       => $p->post_status,
				'edit_link'    => get_edit_post_link( $p->ID, 'raw' ),
				'has_thumbnail'=> has_post_thumbnail( $p->ID ),
			);
		}

		return new WP_REST_Response( array( 'items' => $items ), 200 );
	}

	/**
	 * POST /bulk-process — اجرای عملیات روی یک پست.
	 *
	 * @param WP_REST_Request $req درخواست.
	 * @return WP_REST_Response
	 */
	public static function api_bulk_process( $req ) {
		$post_id = (int) $req->get_param( 'post_id' );
		$op      = sanitize_key( (string) $req->get_param( 'op' ) );

		$allowed = array( 'fix_title', 'write_content', 'improve_seo', 'fix_all' );
		if ( ! in_array( $op, $allowed, true ) ) {
			return new WP_REST_Response( array( 'error' => content_assistant_t( 'err_bad_action' ) ), 200 );
		}

		$result = Content_Assistant_Bulk_Editor::process_item(
			$post_id,
			$op,
			array(
				'instruction' => (string) $req->get_param( 'instruction' ),
				'mode'        => (string) $req->get_param( 'mode' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'error' => $result->get_error_message(), 'post_id' => $post_id ), 200 );
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * POST /quick-test — تست سریع: تولید یک پست از موضوع/درخواست کاربر و انتشار آن.
	 *
	 * @param WP_REST_Request $req درخواست.
	 * @return WP_REST_Response
	 */
	public static function api_quick_test( $req ) {
		$post_type = sanitize_key( (string) $req->get_param( 'post_type' ) );
		$topic     = sanitize_textarea_field( (string) $req->get_param( 'topic' ) );
		$instruction = sanitize_textarea_field( (string) $req->get_param( 'instruction' ) );
		$words     = (int) $req->get_param( 'words' );
		$publish   = '0' === (string) $req->get_param( 'publish' ) ? false : true;

		if ( ! post_type_exists( $post_type ) || 'attachment' === $post_type ) {
			return new WP_REST_Response( array( 'error' => content_assistant_t( 'err_bad_post_type' ) ), 200 );
		}
		if ( '' === $topic ) {
			return new WP_REST_Response( array( 'error' => content_assistant_t( 'err_topic_required' ) ), 200 );
		}
		if ( ! current_user_can( 'publish_posts' ) ) {
			return new WP_REST_Response( array( 'error' => content_assistant_t( 'err_no_publish_cap' ) ), 200 );
		}

		$result = Content_Assistant_Generator::create_post(
			$post_type,
			array(
				'topic'        => $topic,
				'status'       => $publish ? 'publish' : 'draft',
				'words'        => $words > 0 ? $words : 600,
				'extra_prompt' => $instruction,
			)
		);

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'error' => $result->get_error_message() ), 200 );
		}

		$post_id = (int) $result['post_id'];

		return new WP_REST_Response(
			array(
				'post_id'   => $post_id,
				'title'     => $result['title'],
				'status'    => get_post_status( $post_id ),
				'url'       => get_permalink( $post_id ),
				'edit_link' => get_edit_post_link( $post_id, 'raw' ),
			),
			200
		);
	}
}
