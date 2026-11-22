<?php
/**
 * Plugin Name:          هوش مصنوعی برای وردپرس
 * Plugin URI:           https://ahangaryan.ir
 * Description:          تولید محتوای زمان‌بندی‌شدهٔ روزانه برای هر پست‌تایپ، ویرایش گروهی، پنل هوشمند داخل ویرایشگر و بهبود سئو. رابط دوزبانه (فارسی/انگلیسی).
 * Version:              1.2.0
 * Author:               Hamid Ahangaryan
 * Author URI:           https://ahangaryan.ir
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          content-assistant
 * Requires at least:    5.8
 * Requires PHP:         7.4
 *
 * @package ContentAssistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CONTENT_ASSISTANT_VERSION', '1.2.0' );
define( 'CONTENT_ASSISTANT_PLUGIN_FILE', __FILE__ );
define( 'CONTENT_ASSISTANT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CONTENT_ASSISTANT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once CONTENT_ASSISTANT_PLUGIN_DIR . 'includes/class-content-assistant-i18n.php';
require_once CONTENT_ASSISTANT_PLUGIN_DIR . 'includes/class-content-assistant-logger.php';
require_once CONTENT_ASSISTANT_PLUGIN_DIR . 'includes/class-content-assistant-client.php';
require_once CONTENT_ASSISTANT_PLUGIN_DIR . 'includes/class-content-assistant-settings.php';
require_once CONTENT_ASSISTANT_PLUGIN_DIR . 'includes/class-content-assistant-generator.php';
require_once CONTENT_ASSISTANT_PLUGIN_DIR . 'includes/class-content-assistant-scheduler.php';
require_once CONTENT_ASSISTANT_PLUGIN_DIR . 'includes/class-content-assistant-bulk-editor.php';
require_once CONTENT_ASSISTANT_PLUGIN_DIR . 'includes/class-content-assistant-assistant.php';
require_once CONTENT_ASSISTANT_PLUGIN_DIR . 'includes/class-content-assistant-rest.php';

/**
 * فعال‌سازی: برنامهٔ cron اختصاصی + مقداردهی اولیهٔ گزینه‌ها.
 */
function content_assistant_activate() {
	Content_Assistant_Scheduler::schedule_event();

	if ( false === get_option( 'content_assistant_settings', false ) ) {
		add_option( 'content_assistant_settings', Content_Assistant_Settings::defaults() );
	}
	if ( false === get_option( 'content_assistant_schedules', false ) ) {
		add_option( 'content_assistant_schedules', array() );
	}
	if ( false === get_option( 'content_assistant_usage', false ) ) {
		add_option( 'content_assistant_usage', array() );
	}
	if ( false === get_option( 'content_assistant_log', false ) ) {
		add_option( 'content_assistant_log', array() );
	}
}
register_activation_hook( __FILE__, 'content_assistant_activate' );

/**
 * غیرفعال‌سازی: حذف رویداد cron.
 */
function content_assistant_deactivate() {
	Content_Assistant_Scheduler::unschedule_event();
}
register_deactivation_hook( __FILE__, 'content_assistant_deactivate' );

/**
 * بارگذاری افزونه: راه‌اندازی کلاس‌ها.
 */
function content_assistant_boot() {
	load_plugin_textdomain( 'content-assistant', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	Content_Assistant_Settings::init();
	Content_Assistant_Scheduler::init();
	Content_Assistant_Bulk_Editor::init();
	Content_Assistant_Assistant::init();
	Content_Assistant_REST::init();
}
add_action( 'plugins_loaded', 'content_assistant_boot' );
