<?php
/**
 * پنل هوشمند: دکمهٔ سراسری نوار مدیریت + متا‌باکس ویرایشگر کلاسیک.
 *
 * @package ContentAssistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * رفتار پنل هوشمند در کل محیط وردپرس.
 */
class Content_Assistant_Assistant {

	/**
	 * hooks.
	 */
	public static function init() {
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar_button' ), 100 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_metabox' ) );
	}

	/**
	 * آیا پنل هوشمند برای این کاربر/پست‌تایپ فعال است؟
	 *
	 * @param string $post_type پست‌تایپ.
	 * @return bool
	 */
	public static function enabled_for( $post_type = '' ) {
		if ( ! Content_Assistant_Settings::get_one( 'enable_editor_assistant' ) ) {
			return false;
		}
		if ( $post_type ) {
			$allowed = (array) Content_Assistant_Settings::get_one( 'assistant_post_types' );
			if ( $allowed && ! in_array( $post_type, $allowed, true ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * دکمهٔ نوار مدیریت — در همهٔ صفحات وردپرس.
	 *
	 * @param WP_Admin_Bar $bar نوار.
	 */
	public static function admin_bar_button( $bar ) {
		if ( ! Content_Assistant_Settings::get_one( 'enable_admin_bar_button' ) ) {
			return;
		}
		if ( ! is_admin() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		// در صفحهٔ ویرایش: باز کردن پنل؛ در غیر این صورت: رفتن به ویرایش گروهی.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$is_edit = $screen && in_array( $screen->base, array( 'post', 'post-new' ), true );

		$bar->add_node(
			array(
				'id'    => 'content-assistant-smart-panel',
				'title' => '<span class="ab-icon dashicons dashicons-lightbulb" aria-hidden="true"></span><span class="ab-label">' . content_assistant_te( 'assistant_menu' ) . '</span>',
				'href'  => $is_edit ? '#' : admin_url( 'admin.php?page=content-assistant-bulk' ),
				'meta'  => array(
					'title' => content_assistant_te( 'assistant_title_attr' ),
				),
			)
		);

		if ( $is_edit ) {
			$bar->add_node(
				array(
					'id'     => 'content-assistant-smart-panel-open',
					'parent' => 'content-assistant-smart-panel',
					'title'  => content_assistant_te( 'open_panel' ),
					'href'   => '#',
					'meta'   => array(
						'class' => 'content-assistant-open-panel',
					),
				)
			);
		}

		$bar->add_node(
			array(
				'id'     => 'content-assistant-smart-panel-bulk',
				'parent' => 'content-assistant-smart-panel',
				'title'  => content_assistant_te( 'menu_bulk' ),
				'href'   => admin_url( 'admin.php?page=content-assistant-bulk' ),
			)
		);
		$bar->add_node(
			array(
				'id'     => 'content-assistant-smart-panel-schedule',
				'parent' => 'content-assistant-smart-panel',
				'title'  => content_assistant_te( 'menu_schedule' ),
				'href'   => admin_url( 'admin.php?page=content-assistant-schedule' ),
			)
		);
		$bar->add_node(
			array(
				'id'     => 'content-assistant-smart-panel-settings',
				'parent' => 'content-assistant-smart-panel',
				'title'  => content_assistant_te( 'menu_settings' ),
				'href'   => admin_url( 'admin.php?page=content-assistant' ),
			)
		);
	}

	/**
	 * واگذاری اسکریپت‌ها/استایل‌ها.
	 *
	 * @param string $hook هوک صفحه.
	 */
	public static function enqueue( $hook ) {
		// CSS عمومی در همهٔ صفحات ادمین (برای نوار مدیریت و پنل‌ها).
		wp_enqueue_style( 'content-assistant-admin', CONTENT_ASSISTANT_PLUGIN_URL . 'assets/css/admin.css', array(), CONTENT_ASSISTANT_VERSION );

		// صفحات خود افزونه.
		$our_screens = array( 'toplevel_page_content-assistant', 'content_assistant_page_content-assistant-schedule', 'content_assistant_page_content-assistant-bulk', 'content_assistant_page_content-assistant-logs', 'content_assistant_page_content-assistant-quick' );
		if ( in_array( $hook, $our_screens, true ) ) {
			wp_enqueue_script( 'wp-api-fetch' );
			wp_enqueue_script( 'content-assistant-bulk', CONTENT_ASSISTANT_PLUGIN_URL . 'assets/js/bulk.js', array( 'wp-api-fetch' ), CONTENT_ASSISTANT_VERSION, true );
			Content_Assistant_I18n::localize( 'content-assistant-bulk' );
		}
		if ( 'content_assistant_page_content-assistant-quick' === $hook ) {
			wp_enqueue_script( 'wp-api-fetch' );
			wp_enqueue_script( 'content-assistant-quick', CONTENT_ASSISTANT_PLUGIN_URL . 'assets/js/quick-test.js', array( 'wp-api-fetch' ), CONTENT_ASSISTANT_VERSION, true );
			Content_Assistant_I18n::localize( 'content-assistant-quick' );
		}
		if ( 'toplevel_page_content-assistant' === $hook ) {
			wp_enqueue_script( 'content-assistant-settings', CONTENT_ASSISTANT_PLUGIN_URL . 'assets/js/settings.js', array( 'wp-api-fetch' ), CONTENT_ASSISTANT_VERSION, true );
			Content_Assistant_I18n::localize( 'content-assistant-settings' );
		}

		// صفحهٔ ویرایش پست: دستیار داخل ویرایشگر.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $screen->base, array( 'post', 'post-new' ), true ) && self::enabled_for( $screen->post_type ) ) {
			wp_enqueue_script( 'wp-api-fetch' );
			wp_enqueue_script( 'content-assistant-editor', CONTENT_ASSISTANT_PLUGIN_URL . 'assets/js/editor-assistant.js', array( 'wp-api-fetch', 'wp-element', 'wp-components', 'wp-edit-post', 'wp-plugins', 'wp-data', 'wp-blocks', 'wp-block-editor', 'wp-i18n' ), CONTENT_ASSISTANT_VERSION, true );
			Content_Assistant_I18n::localize( 'content-assistant-editor' );
			wp_localize_script(
				'content-assistant-editor',
				'contentAssistantEditorConfig',
				array(
					'isBlockEditor' => $screen->is_block_editor( null ),
					'postType'      => $screen->post_type,
					'postId'        => isset( $_GET['post'] ) ? (int) $_GET['post'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				)
			);

			// متا‌باکس فقط برای ویرایشگر کلاسیک.
			if ( ! $screen->is_block_editor( null ) ) {
				wp_enqueue_script( 'content-assistant-classic', CONTENT_ASSISTANT_PLUGIN_URL . 'assets/js/classic-assistant.js', array( 'wp-api-fetch' ), CONTENT_ASSISTANT_VERSION, true );
				Content_Assistant_I18n::localize( 'content-assistant-classic' );
			}
		}
	}

	/**
	 * متا‌باکس ویرایشگر کلاسیک.
	 */
	public static function register_metabox() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
			return;
		}
		if ( $screen->is_block_editor( null ) ) {
			return;
		}
		if ( ! self::enabled_for( $screen->post_type ) ) {
			return;
		}

		add_meta_box(
			'content-assistant-smart-panel-box',
			content_assistant_te( 'assistant_menu' ),
			array( __CLASS__, 'render_metabox' ),
			$screen->post_type,
			'side',
			'high'
		);
	}

	/**
	 * رندر محتوای متا‌باکس (توسط JS پر می‌شود).
	 */
	public static function render_metabox() {
		echo '<div id="content-assistant-classic-root" dir="' . esc_attr( Content_Assistant_I18n::dir() ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '">';
		echo '<p class="description">' . esc_html( content_assistant_te( 'loading_assistant' ) ) . '</p>';
		echo '</div>';
	}
}
