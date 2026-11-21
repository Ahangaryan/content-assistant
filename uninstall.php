<?php
/**
 * حذف کامل داده‌های افزونه هنگام Uninstall.
 *
 * @package ContentAssistant
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// حذف برنامهٔ cron.
wp_clear_scheduled_hook( 'content_assistant_schedule_tick' );

// حذف گزینه‌ها.
delete_option( 'content_assistant_settings' );
delete_option( 'content_assistant_schedules' );
delete_option( 'content_assistant_schedule_state' );
delete_option( 'content_assistant_usage' );
delete_option( 'content_assistant_log' );

// متادیتاهای افزونه روی پست‌ها.
delete_post_meta_by_key( '_content_assistant_meta_description' );
delete_post_meta_by_key( '_content_assistant_focus_keyword' );
delete_post_meta_by_key( '_content_assistant_bulk_has_content' );
delete_post_meta_by_key( '_content_assistant_bulk_checked' );
