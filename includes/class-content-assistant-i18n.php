<?php
/**
 * لایهٔ دوزبانه (فارسی / انگلیسی) افزونه.
 *
 * همهٔ رشته‌های رابط کاربری — PHP و JS — از این کاتالوگ خوانده می‌شوند؛
 * بنابراین هر بخش همیشه هم ترجمهٔ فارسی و هم انگلیسی دارد، مستقل از
 * locale وردپرس و بدون نیاز به فایل‌های .mo.
 *
 * @package ContentAssistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ترجمه، جهت‌دهی (RTL/LTR) و تحویل رشته‌ها به JS.
 */
class Content_Assistant_I18n {

	/**
	 * زبان انتخابی: auto | fa | en.
	 *
	 * @return string
	 */
	public static function setting() {
		$v = Content_Assistant_Settings::get_one( 'ui_language' );
		return in_array( $v, array( 'fa', 'en', 'auto' ), true ) ? $v : 'auto';
	}

	/**
	 * زبان فعال رابط کاربری.
	 *
	 * @return string fa|en
	 */
	public static function lang() {
		$set = self::setting();
		if ( 'fa' === $set || 'en' === $set ) {
			return $set;
		}
		$locale = function_exists( 'get_locale' ) ? get_locale() : 'en_US';
		return ( 0 === strpos( strtolower( (string) $locale ), 'fa' ) ) ? 'fa' : 'en';
	}

	/**
	 * آیا زبان فعال راست‌به‌چپ است؟
	 *
	 * @return bool
	 */
	public static function is_rtl() {
		return 'fa' === self::lang();
	}

	/**
	 * جهت متن برای attributeهای dir.
	 *
	 * @return string rtl|ltr
	 */
	public static function dir() {
		return self::is_rtl() ? 'rtl' : 'ltr';
	}

	/**
	 * کاتالوگ کامل رشته‌ها: کلید => [fa, en].
	 *
	 * @return array
	 */
	public static function catalog() {
$zwnj = "\u{200C}"; // نیم‌فاصله — برای «هوش مصنوعی» در صورت نیاز.

	return array(
		/* -- هویت و عمومی -- */
		'app_name'        => array( 'fa' => 'هوش‌یار', 'en' => 'Hoshyar' ),
		'app_short'       => array( 'fa' => 'هوش مصنوعی', 'en' => 'AI' ),
		'app_tagline'     => array( 'fa' => 'تولید محتوا، ویرایش گروهی و پنل هوشمند', 'en' => 'Content generation, bulk editing & smart panel' ),
		'company_built'   => array( 'fa' => 'ساخته و پشتیبانی: Hamid Ahangaryan', 'en' => 'Built & supported by Hamid Ahangaryan' ),
		'about_title'     => array( 'fa' => 'دربارهٔ این افزونه', 'en' => 'About this plugin' ),
		'about_desc'      => array(
			'fa' => 'هوش مصنوعی برای وردپرس یک افزونهٔ دوزبانه (فارسی/انگلیسی) است که وردپرس را به API سازگار با OpenAI وصل می‌کند: تولید خودکار محتوا، زمان‌بندی روزانه، ویرایش گروهی، پنل هوشمند در ویرایشگر و بهبود سئو.',
			'en' => 'AI for WordPress is a bilingual (Persian/English) plugin that connects WordPress to OpenAI-compatible APIs: automatic content generation, daily scheduling, bulk editing, a smart panel in the editor, and SEO improvements.',
		),
		'support_desc'    => array(
			'fa' => 'برای پشتیبانی، پیشنهاد ویژگی جدید یا سفارش توسعه به ahangaryan.ir مراجعه کنید.',
			'en' => 'For support, feature requests or custom development, visit ahangaryan.ir.',
		),
		'version_label'   => array( 'fa' => 'نسخه', 'en' => 'Version' ),

		/* -- منو -- */
		'menu_settings'   => array( 'fa' => 'تنظیمات', 'en' => 'Settings' ),
		'menu_schedule'   => array( 'fa' => 'زمان‌بندی تولید', 'en' => 'Generation Schedule' ),
		'menu_bulk'       => array( 'fa' => 'ویرایش گروهی', 'en' => 'Bulk Editing' ),
		'menu_logs'       => array( 'fa' => 'لاگ و مصرف', 'en' => 'Logs & Usage' ),
		'menu_quick'      => array( 'fa' => 'تست سریع', 'en' => 'Quick Test' ),

			/* -- تنظیمات -- */
			'settings_title'       => array( 'fa' => 'تنظیمات پنل هوشمند', 'en' => 'Smart Panel Settings' ),
			'ui_language_label'    => array( 'fa' => 'زبان رابط کاربری', 'en' => 'Interface language' ),
			'ui_language_desc'     => array(
				'fa' => 'به‌صورت پیش‌فرض از زبان وردپرس پیروی می‌کند؛ می‌توانید آن را تغییر دهید. سوییچر بالای صفحه هم فوراً زبان را عوض می‌کند.',
				'en' => 'Follows the WordPress site language by default; you can override it here. The switcher at the top changes it instantly.',
			),
			'ui_lang_auto'         => array( 'fa' => 'خودکار (زبان وردپرس)', 'en' => 'Auto (site language)' ),
			'tab_general'          => array( 'fa' => 'عمومی و API', 'en' => 'General & API' ),
			'tab_assistant'        => array( 'fa' => 'پنل هوشمند', 'en' => 'Smart Panel' ),
			'settings_saved'       => array( 'fa' => 'تنظیمات ذخیره شد.', 'en' => 'Settings saved.' ),
			'api_key_label'        => array( 'fa' => 'کلید API گپ جی‌پی‌تی', 'en' => 'GapGPT API key' ),
			'api_key_current'      => array(
				'fa' => 'کلید فعلی: %s — برای تغییر، کلید جدید را وارد کنید. کلید در پنل توسعه‌دهندگان گپ جی‌پی‌تی ساخته می‌شود.',
				'en' => 'Current key: %s — enter a new key to replace it. Keys are created in the GapGPT developer panel.',
			),
			'api_key_desc'         => array(
				'fa' => 'کلید را از پنل توسعه‌دهندگان گپ جی‌پی‌تی بگیرید (فقط یک بار نمایش داده می‌شود).',
				'en' => 'Get your key from the GapGPT developer panel (shown only once).',
			),
			'api_key_set'          => array( 'fa' => 'کلید تنظیم شد ✓', 'en' => 'Key configured ✓' ),
			'api_key_missing'      => array( 'fa' => 'کلید تنظیم نشده', 'en' => 'Key not set' ),
			'base_url_label'       => array( 'fa' => 'آدرس API', 'en' => 'API URL' ),
			'base_url_desc'        => array( 'fa' => 'پیش‌فرض: https://api.gapgpt.app/v1', 'en' => 'Default: https://api.gapgpt.app/v1' ),
			'model_label'          => array( 'fa' => 'مدل پیش‌فرض', 'en' => 'Default model' ),
			'model_desc'           => array(
				'fa' => 'هر مدل سازگار OpenAI که در صفحهٔ مدل‌ها موجود است. نام دقیق را وارد کنید.',
				'en' => 'Any OpenAI-compatible model available on the models page. Enter the exact name.',
			),
			'image_model_label'    => array( 'fa' => 'مدل تولید تصویر', 'en' => 'Image model' ),
			'temperature_label'    => array( 'fa' => 'دما (Temperature)', 'en' => 'Temperature' ),
			'max_tokens_label'     => array( 'fa' => 'حداکثر توکن خروجی', 'en' => 'Max output tokens' ),
			'content_lang_label'   => array( 'fa' => 'زبان محتوا', 'en' => 'Content language' ),
			'lang_fa'              => array( 'fa' => 'فارسی', 'en' => 'Persian' ),
			'lang_en'              => array( 'fa' => 'انگلیسی', 'en' => 'English' ),
			'default_status_label' => array( 'fa' => 'وضعیت پیش‌فرض پست‌های تولیدشده', 'en' => 'Default status of generated posts' ),
			'status_draft'         => array( 'fa' => 'پیش‌نویس (پیشنهادی — ابتدا بازبینی کنید)', 'en' => 'Draft (recommended — review first)' ),
			'status_pending'       => array( 'fa' => 'در انتظار بررسی', 'en' => 'Pending review' ),
			'status_publish'       => array( 'fa' => 'انتشار خودکار', 'en' => 'Auto-publish' ),
			'bulk_delay_label'     => array( 'fa' => 'تأخیر بین درخواست‌های گروهی (ثانیه)', 'en' => 'Delay between bulk requests (seconds)' ),
			'bulk_delay_desc'      => array(
				'fa' => 'برای جلوگیری از فشار به API هنگام ویرایش گروهی.',
				'en' => 'Protects the API from overload during bulk editing.',
			),
			'test_connection_label' => array( 'fa' => 'تست اتصال', 'en' => 'Connection test' ),
			'test_connection_btn'  => array( 'fa' => 'اتصال به API را تست کن', 'en' => 'Test API connection' ),
'editor_assistant_label' => array( 'fa' => 'پنل هوشمند در ویرایشگر', 'en' => 'Smart panel in editor' ),
		'editor_assistant_desc'  => array(
			'fa' => 'در ویرایشگر بلوک: پنل کناری «پنل هوشمند». در ویرایشگر کلاسیک: متا‌باکس. هر فیلد قابل شناسایی و ویرایش است.',
			'en' => 'Block editor: the "Smart Panel" sidebar. Classic editor: a meta box. Every field can be detected and edited.',
		),
		'admin_bar_label'      => array( 'fa' => 'دکمهٔ پنل هوشمند در نوار مدیریت', 'en' => 'Smart panel button in admin bar' ),
		'admin_bar_desc'       => array(
			'fa' => 'در صفحهٔ ویرایش، پنل هوشمند را باز می‌کند؛ در صفحات دیگر به صفحهٔ ویرایش گروهی می‌رود.',
			'en' => 'Opens the smart panel on the edit screen; goes to Bulk Editing elsewhere.',
		),
		'assistant_post_types_label' => array( 'fa' => 'پست‌تایپ‌های دارای پنل هوشمند', 'en' => 'Post types with smart panel' ),
			'save_settings'        => array( 'fa' => 'ذخیرهٔ تنظیمات', 'en' => 'Save Settings' ),

			/* -- تست سریع -- */
			'quick_title'        => array( 'fa' => 'تست سریع تولید پست', 'en' => 'Quick post generation test' ),
			'quick_desc'         => array(
				'fa' => 'موضوع و درخواستتان را بنویسید، نوع پست را انتخاب کنید؛ پست تولید و در صورت تیک «انتشار» بلافاصله منتشر می‌شود. تولید محتوا ممکن است ۳۰ ثانیه تا ۲ دقیقه طول بکشد.',
				'en' => 'Write your topic and request, choose a post type; the post is generated and — if “Publish” is checked — published immediately. Generation may take 30 seconds to 2 minutes.',
			),
			'label_post_type'    => array( 'fa' => 'نوع پست', 'en' => 'Post type' ),
			'quick_topic'        => array( 'fa' => 'موضوع پست', 'en' => 'Post topic' ),
			'quick_topic_ph'     => array( 'fa' => 'مثلاً: مقایسهٔ بهترین لپ‌تاپ‌های دانشجویی در ۱۴۰۴', 'en' => 'e.g. Best student laptops compared in 2025' ),
			'quick_instruction'  => array( 'fa' => 'درخواست / نکات ویژه', 'en' => 'Request / special notes' ),
			'quick_instruction_ph' => array(
				'fa' => 'مثلاً: لحن صمیمی باشد؛ برای مبتدیان بنویس؛ حتماً جدول مقایسه اضافه کن…',
				'en' => 'e.g. Friendly tone; write for beginners; add a comparison table…',
			),
			'label_word_count'   => array( 'fa' => 'تعداد کلمات', 'en' => 'Word count' ),
			'quick_publish'      => array( 'fa' => 'انتشار فوری پس از تولید', 'en' => 'Publish immediately after generation' ),
			'quick_run'          => array( 'fa' => 'تولید و انتشار', 'en' => 'Generate & publish' ),

			/* -- لاگ و مصرف -- */
			'logs_title'       => array( 'fa' => 'لاگ و مصرف هوش مصنوعی برای وردپرس', 'en' => 'AI for WordPress Logs & Usage' ),
			'usage_summary'    => array( 'fa' => 'خلاصهٔ مصرف', 'en' => 'Usage summary' ),
			'total_calls'      => array( 'fa' => 'کل فراخوانی API', 'en' => 'Total API calls' ),
			'prompt_tokens'    => array( 'fa' => 'توکن ورودی', 'en' => 'Prompt tokens' ),
			'completion_tokens' => array( 'fa' => 'توکن خروجی', 'en' => 'Completion tokens' ),
			'posts_generated'  => array( 'fa' => 'پست‌های تولیدشده', 'en' => 'Generated posts' ),
			'images_generated' => array( 'fa' => 'تصاویر تولیدشده', 'en' => 'Generated images' ),
			'last_call'        => array( 'fa' => 'آخرین فراخوانی', 'en' => 'Last call' ),
			'daily_usage'      => array( 'fa' => 'مصرف روزانه', 'en' => 'Daily usage' ),
			'usage_empty'      => array( 'fa' => 'هنوز داده‌ای ثبت نشده است.', 'en' => 'Nothing recorded yet.' ),
			'col_date'         => array( 'fa' => 'تاریخ', 'en' => 'Date' ),
			'col_calls'        => array( 'fa' => 'فراخوانی', 'en' => 'Calls' ),
			'event_log'        => array( 'fa' => 'لاگ رویدادها', 'en' => 'Event log' ),
			'clear_log'        => array( 'fa' => 'پاک کردن لاگ', 'en' => 'Clear log' ),
			'clear_log_confirm' => array( 'fa' => 'لاگ پاک شود؟', 'en' => 'Clear the log?' ),
			'log_cleared'      => array( 'fa' => 'لاگ پاک شد.', 'en' => 'Log cleared.' ),
			'logs_empty'       => array( 'fa' => 'لاگی ثبت نشده است.', 'en' => 'No log entries.' ),
			'col_time'         => array( 'fa' => 'زمان', 'en' => 'Time' ),
			'col_type'         => array( 'fa' => 'نوع', 'en' => 'Type' ),
			'col_message'      => array( 'fa' => 'پیام', 'en' => 'Message' ),
			'col_status'       => array( 'fa' => 'وضعیت', 'en' => 'Status' ),
			'col_id'           => array( 'fa' => 'شناسه', 'en' => 'ID' ),
			'details'          => array( 'fa' => 'جزئیات', 'en' => 'Details' ),

			/* -- زمان‌بندی -- */
			'schedule_title'     => array( 'fa' => 'زمان‌بندی تولید محتوا', 'en' => 'Content generation schedule' ),
			'schedule_saved'     => array( 'fa' => 'تنظیمات زمان‌بندی ذخیره شد.', 'en' => 'Schedule settings saved.' ),
			'schedule_desc'      => array(
				'fa' => 'برای هر پست‌تایپ یک برنامهٔ روزانه بسازید: تعداد پست در روز، با فاصلهٔ منظم بین دو ساعت یا در زمان‌های دلخواه، با موضوعات قابل تعیین. اجرا با cron وردپرس (هر ۵ دقیقه) انجام می‌شود؛ پست‌ها با وضعیت انتخابی ساخته می‌شوند.',
				'en' => 'Build a daily plan for each post type: posts per day, evenly spaced within a time window or at chosen times, with optional topics. Runs via WP-Cron (every 5 minutes); posts are created with the selected status.',
			),
			'today_progress'     => array( 'fa' => 'امروز: %1$d از %2$d', 'en' => 'Today: %1$d of %2$d' ),
			'slots_label'        => array( 'fa' => 'اسلات‌ها:', 'en' => 'Slots:' ),
			'generate_test'      => array( 'fa' => 'تولید آزمایشی', 'en' => 'Generate test post' ),
			'generate_test_confirm' => array( 'fa' => 'یک پست آزمایشی همین حالا تولید شود؟', 'en' => 'Generate a test post right now?' ),
			'posts_per_day'      => array( 'fa' => 'تعداد پست در روز', 'en' => 'Posts per day' ),
			'scheduling_mode'    => array( 'fa' => 'نحوهٔ زمان‌بندی', 'en' => 'Scheduling mode' ),
			'mode_intervals'     => array( 'fa' => 'با فاصلهٔ منظم در یک بازهٔ زمانی', 'en' => 'Evenly spaced within a time window' ),
			'mode_times'         => array( 'fa' => 'در زمان‌های انتخابی', 'en' => 'At chosen times' ),
			'from'               => array( 'fa' => 'از', 'en' => 'From' ),
			'to'                 => array( 'fa' => 'تا', 'en' => 'To' ),
			'times_desc'         => array(
				'fa' => 'زمان‌ها را با کاما جدا کنید. اگر کمتر از تعداد پست وارد شود، باقی با فاصلهٔ منظم تکمیل می‌شود.',
				'en' => 'Separate times with commas. If fewer than the post count, the rest are spaced evenly.',
			),
			'topics_label'       => array( 'fa' => 'موضوعات (هر خط یک موضوع)', 'en' => 'Topics (one per line)' ),
			'topics_desc'        => array(
				'fa' => 'موضوعات به‌صورت چرخشی استفاده می‌شود. خالی بگذارید تا خود مدل موضوع انتخاب کند.',
				'en' => 'Topics are used cyclically. Leave empty to let the model pick.',
			),
			'content_words'      => array( 'fa' => 'تعداد کلمات محتوا', 'en' => 'Content word count' ),
			'post_status'        => array( 'fa' => 'وضعیت پست', 'en' => 'Post status' ),
			'categories_optional' => array( 'fa' => 'دسته‌ها (اختیاری)', 'en' => 'Categories (optional)' ),
			'generate_featured_image' => array( 'fa' => 'پس از تولید پست، تصویر شاخص هم ساخته شود', 'en' => 'Also generate a featured image after the post' ),
			'extra_prompt_label' => array( 'fa' => 'نکات تکمیلی برای مدل', 'en' => 'Extra notes for the model' ),
			'extra_prompt_desc'  => array(
				'fa' => 'مثلاً: لحن صمیمی داشته باشد / به محصولات فروشگاه لینک دهد / از عکس در متن استفاده نکند.',
				'en' => 'e.g. friendly tone / link to store products / no images in the text.',
			),
			'save_schedule'      => array( 'fa' => 'ذخیرهٔ زمان‌بندی', 'en' => 'Save schedule' ),
			'cron_5min'          => array( 'fa' => 'هر ۵ دقیقه (هوش مصنوعی)', 'en' => 'Every 5 minutes (AI)' ),
			'unauthorized'       => array( 'fa' => 'دسترسی غیرمجاز.', 'en' => 'Access denied.' ),
			'bad_post_type'      => array( 'fa' => 'پست‌تایپ نامعتبر.', 'en' => 'Invalid post type.' ),

			/* -- ویرایش گروهی -- */
			'bulk_title'        => array( 'fa' => 'ویرایش گروهی با هوش مصنوعی برای وردپرس', 'en' => 'Bulk editing with AI for WordPress' ),
			'bulk_desc'         => array(
				'fa' => 'پست‌هایی را با فیلتر پیدا کنید و به‌صورت دسته‌جمعی اصلاح کنید: نوشتن محتوا برای پست‌های خالی، درست کردن عناوین، بهبود سئو. برای هر پست‌تایپی که در وردپرس تعریف شده باشد کار می‌کند.',
				'en' => 'Find posts with a filter and fix them in bulk: write content for empty posts, fix titles, improve SEO. Works for any post type defined in WordPress.',
			),
			'bulk_step1'        => array( 'fa' => '۱. انتخاب و فیلتر', 'en' => '1. Select & filter' ),
			'bulk_step2'        => array( 'fa' => '۲. عملیات', 'en' => '2. Actions' ),
			'filter_label'      => array( 'fa' => 'فیلتر', 'en' => 'Filter' ),
			'filter_no_content' => array( 'fa' => 'پست‌های بدون محتوا', 'en' => 'Posts without content' ),
			'filter_no_title'   => array( 'fa' => 'پست‌های بدون عنوان', 'en' => 'Posts without a title' ),
			'filter_no_excerpt' => array( 'fa' => 'پست‌های بدون خلاصه', 'en' => 'Posts without an excerpt' ),
			'filter_no_thumbnail' => array( 'fa' => 'پست‌های بدون تصویر شاخص', 'en' => 'Posts without a featured image' ),
			'filter_no_seo_desc' => array( 'fa' => 'پست‌های بدون توضیح متا (سئو)', 'en' => 'Posts without a meta description (SEO)' ),
			'filter_all'        => array( 'fa' => 'همهٔ پست‌ها', 'en' => 'All posts' ),
			'search_in_title'   => array( 'fa' => 'جستجو در عنوان (اختیاری)', 'en' => 'Search in title (optional)' ),
			'max_count'         => array( 'fa' => 'حداکثر تعداد', 'en' => 'Maximum count' ),
			'find_posts'        => array( 'fa' => 'پیدا کردن پست‌ها', 'en' => 'Find posts' ),
			'operation'         => array( 'fa' => 'عملیات', 'en' => 'Action' ),
			'op_write_content'  => array( 'fa' => 'تولید محتوا (برای پست‌های خالی)', 'en' => 'Write content (for empty posts)' ),
			'op_fix_title'      => array( 'fa' => 'درست کردن/بهتر کردن عنوان', 'en' => 'Fix/improve title' ),
			'op_improve_seo'    => array( 'fa' => 'بهبود سئو (توضیح متا، کلیدواژه، خلاصه)', 'en' => 'Improve SEO (meta description, keyword, excerpt)' ),
			'op_fix_all'        => array( 'fa' => 'همه (عنوان + محتوا + سئو)', 'en' => 'Everything (title + content + SEO)' ),
			'content_mode'      => array( 'fa' => 'روش تولید محتوا', 'en' => 'Content mode' ),
			'mode_replace'      => array( 'fa' => 'جایگزینی کامل محتوا', 'en' => 'Replace content entirely' ),
			'mode_append'       => array( 'fa' => 'افزودن به انتهای محتوای فعلی', 'en' => 'Append to existing content' ),
			'special_instruction' => array( 'fa' => 'دستور ویژه به مدل (اختیاری)', 'en' => 'Special instruction for the model (optional)' ),
			'special_instruction_ph' => array(
				'fa' => 'مثلاً: لحن تبلیغاتی نباشد؛ به قیمت محصول اشاره کن؛ سوال متداول اضافه کن…',
				'en' => 'e.g. avoid a salesy tone; mention the price; add an FAQ…',
			),
			'start_process'     => array( 'fa' => 'شروع پردازش', 'en' => 'Start processing' ),
			'stop'              => array( 'fa' => 'توقف', 'en' => 'Stop' ),
			'untitled'          => array( 'fa' => '(بدون عنوان)', 'en' => '(no title)' ),

/* -- خطاهای سرویس -- */
		'err_no_api_key'    => array( 'fa' => 'کلید API تنظیم نشده است؛ ابتدا آن را در تنظیمات وارد کنید.', 'en' => 'The API key is not set; configure it in Settings first.' ),
		'err_api'           => array( 'fa' => 'خطای API (کد %1$d): %2$s', 'en' => 'API error (code %1$d): %2$s' ),
		'err_empty_response' => array( 'fa' => 'پاسخ API خالی بود.', 'en' => 'The API response was empty.' ),
		'err_image_api'     => array( 'fa' => 'خطای API تصویر (کد %1$d): %2$s', 'en' => 'Image API error (code %1$d): %2$s' ),
		'err_empty_image'   => array( 'fa' => 'پاسخ API تصویر خالی بود.', 'en' => 'The image API response was empty.' ),
		'test_ok'           => array( 'fa' => 'اتصال با موفقیت برقرار شد.', 'en' => 'Connection established successfully.' ),
		'err_bad_action'    => array( 'fa' => 'اکشن نامعتبر است.', 'en' => 'Invalid action.' ),
		'err_bad_request'   => array( 'fa' => 'درخواست نامعتبر است.', 'en' => 'Invalid request.' ),
		'err_no_post'       => array( 'fa' => 'پست یافت نشد.', 'en' => 'Post not found.' ),
		'err_bad_field'     => array( 'fa' => 'نام فیلد نامعتبر است.', 'en' => 'Invalid field name.' ),
		'err_field_unsupported' => array( 'fa' => 'فیلد پشتیبانی نمی‌شود.', 'en' => 'Field not supported.' ),
		'err_bad_post_type' => array( 'fa' => 'پست‌تایپ نامعتبر است.', 'en' => 'Invalid post type.' ),
		'err_topic_required' => array( 'fa' => 'موضوع پست را وارد کنید.', 'en' => 'Enter a post topic.' ),
		'err_no_publish_cap' => array( 'fa' => 'شما دسترسی انتشار ندارید.', 'en' => 'You do not have permission to publish.' ),
		'err_bad_json'      => array( 'fa' => 'خروجی مدل قابل تحلیل نبود؛ دوباره تلاش کنید.', 'en' => 'The model output could not be parsed; try again.' ),
		'err_no_title'      => array( 'fa' => 'مدل عنوانی تولید نکرد.', 'en' => 'The model produced no title.' ),
		'err_no_suggestion' => array( 'fa' => 'پیشنهادی تولید نشد.', 'en' => 'No suggestion was produced.' ),
		'err_no_content'    => array( 'fa' => 'محتوایی تولید نشد.', 'en' => 'No content was produced.' ),
		'err_no_excerpt'    => array( 'fa' => 'خلاصه‌ای تولید نشد.', 'en' => 'No excerpt was produced.' ),
		'err_no_instruction' => array( 'fa' => 'دستوری وارد نشده است.', 'en' => 'No instruction was provided.' ),
		'err_no_response'   => array( 'fa' => 'پاسخی تولید نشد.', 'en' => 'No response was produced.' ),
		'err_rate_limited'  => array( 'fa' => 'تعداد درخواست‌ها زیاد است. لطفاً چند لحظه صبر کنید.', 'en' => 'Too many requests. Please wait a moment.' ),

			/* -- فیلدهای پست -- */
			'field_title'         => array( 'fa' => 'عنوان', 'en' => 'Title' ),
			'field_content'       => array( 'fa' => 'محتوا', 'en' => 'Content' ),
			'field_excerpt'       => array( 'fa' => 'خلاصه', 'en' => 'Excerpt' ),
			'field_slug'          => array( 'fa' => 'نامک (اسلاگ)', 'en' => 'Slug' ),
			'field_meta_desc'     => array( 'fa' => 'توضیح متا (سئو)', 'en' => 'Meta description (SEO)' ),
			'field_focus_keyword' => array( 'fa' => 'کلیدواژهٔ اصلی سئو', 'en' => 'Main SEO keyword' ),
			'field_meta'          => array( 'fa' => 'متا: %s', 'en' => 'Meta: %s' ),

/* -- دستیار -- */
		'assistant_menu'       => array( 'fa' => 'هوش‌یار', 'en' => 'Hoshyar' ),
		'assistant_title_attr' => array( 'fa' => 'هوش‌یار — دستیار هوش مصنوعی وردپرس', 'en' => 'Hoshyar — WordPress AI assistant' ),
		'open_panel'           => array( 'fa' => 'باز کردن پنل هوشمند', 'en' => 'Open smart panel' ),
		'loading_assistant'    => array( 'fa' => 'در حال بارگذاری پنل هوشمند…', 'en' => 'Loading smart panel…' ),
		'assistant_desc'       => array(
			'fa' => 'پنل هوشمند — برای هر قسمت از پست پیشنهاد بگیرید؛ هیچ تغییری بدون تأیید شما اعمال نمی‌شود.',
			'en' => 'Smart panel — get suggestions for any part of a post; nothing is applied without your confirmation.',
		),

			/* -- رشته‌های JS -- */
			'js_searching'          => array( 'fa' => 'در حال جستجو…', 'en' => 'Searching…' ),
			'js_no_posts_found'     => array( 'fa' => 'پستی با این فیلتر پیدا نشد. ✅', 'en' => 'No posts matched this filter. ✅' ),
			'js_posts_found'        => array( 'fa' => '%d پست پیدا شد:', 'en' => '%d posts found:' ),
			'js_error'              => array( 'fa' => 'خطا: %s', 'en' => 'Error: %s' ),
			'js_progress'           => array( 'fa' => '%1$d از %2$d — موفق: %3$d / خطا: %4$d', 'en' => '%1$d of %2$d — success: %3$d / errors: %4$d' ),
			'js_log_success'        => array( 'fa' => '#%1$d «%2$s» — اعمال: %3$s', 'en' => '#%1$d “%2$s” — applied: %3$s' ),
			'js_no_changes'         => array( 'fa' => 'بدون تغییر', 'en' => 'no changes' ),
			'js_connection_error'   => array( 'fa' => 'خطای ارتباط: %s', 'en' => 'Connection error: %s' ),
			'js_done'               => array( 'fa' => 'پردازش تمام شد. ', 'en' => 'Processing finished. ' ),
			'js_stopped'            => array( 'fa' => 'پردازش متوقف شد. ', 'en' => 'Processing stopped. ' ),
			'js_summary'            => array( 'fa' => 'موفق: %1$d — خطا: %2$d', 'en' => 'Success: %1$d — errors: %2$d' ),
			'js_generating'         => array( 'fa' => 'در حال تولید…', 'en' => 'Generating…' ),
			'js_quick_running'      => array(
				'fa' => 'در حال تولید پست با مدل… این کار ممکن است ۳۰ ثانیه تا ۲ دقیقه طول بکشد.',
				'en' => 'Generating the post with the model… this may take 30 seconds to 2 minutes.',
			),
			'js_invalid_response'   => array( 'fa' => 'پاسخ نامعتبر از سرور دریافت شد.', 'en' => 'Invalid response from the server.' ),
			'js_post_published'     => array( 'fa' => '✅ پست منتشر شد.', 'en' => '✅ Post published.' ),
			'js_post_draft_created' => array( 'fa' => '✅ پست به‌صورت پیش‌نویس ساخته شد.', 'en' => '✅ Post created as a draft.' ),
			'js_view_post'          => array( 'fa' => 'مشاهده پست', 'en' => 'View post' ),
			'js_edit_in_wp'         => array( 'fa' => 'ویرایش در وردپرس', 'en' => 'Edit in WordPress' ),
			'js_status_prefix'      => array( 'fa' => 'وضعیت:', 'en' => 'Status:' ),
			'js_id_prefix'          => array( 'fa' => 'شناسه:', 'en' => 'ID:' ),
			'js_unknown_error'      => array( 'fa' => 'خطای ناشناخته در ارتباط با سرور.', 'en' => 'Unknown error while contacting the server.' ),
			'js_testing'            => array( 'fa' => 'در حال تست…', 'en' => 'Testing…' ),
			'js_model_prefix'       => array( 'fa' => 'مدل:', 'en' => 'Model:' ),
			'js_applying'           => array( 'fa' => 'در حال پردازش با هوش مصنوعی…', 'en' => 'Working with AI…' ),
			'js_confirm_apply'      => array( 'fa' => 'این پیشنهاد اعمال شود؟', 'en' => 'Apply this suggestion?' ),
			'js_apply_confirm'      => array( 'fa' => 'اعمال با تأیید من', 'en' => 'Apply with my confirmation' ),
			'js_dismiss'            => array( 'fa' => 'رد پیشنهاد', 'en' => 'Dismiss suggestion' ),
			'js_applied_ok'         => array( 'fa' => 'اعمال شد. فراموش نکنید پست را ذخیره کنید.', 'en' => 'Applied. Don’t forget to save the post.' ),
			'js_pick_option'        => array( 'fa' => 'یک گزینه را برای اعمال انتخاب کنید:', 'en' => 'Pick an option to apply:' ),
			'js_confirm_title'      => array( 'fa' => 'عنوان «%s» اعمال شود؟', 'en' => 'Apply the title “%s”?' ),
			'js_close'              => array( 'fa' => 'بستن', 'en' => 'Close' ),
			'js_ready_to_apply'     => array( 'fa' => 'پیشنهاد آماده است — برای اعمال تأیید کنید:', 'en' => 'The suggestion is ready — confirm to apply:' ),
			'js_seo_score'          => array( 'fa' => 'امتیاز سئو:', 'en' => 'SEO score:' ),
			'js_issues'             => array( 'fa' => 'مشکلات:', 'en' => 'Issues:' ),
			'js_suggestions'        => array( 'fa' => 'پیشنهادات:', 'en' => 'Suggestions:' ),
			'js_meta_desc_suggested' => array( 'fa' => 'توضیح متای پیشنهادی:', 'en' => 'Suggested meta description:' ),
			'js_apply_meta'         => array( 'fa' => 'اعمال توضیح متا', 'en' => 'Apply meta description' ),
			'js_confirm_meta'       => array( 'fa' => 'توضیح متا اعمال شود؟', 'en' => 'Apply the meta description?' ),
			'js_focus_keyword'      => array( 'fa' => 'کلیدواژهٔ اصلی:', 'en' => 'Main keyword:' ),
			'js_apply_keyword'      => array( 'fa' => 'اعمال کلیدواژه', 'en' => 'Apply keyword' ),
			'js_confirm_keyword'    => array( 'fa' => 'کلیدواژه اعمال شود؟', 'en' => 'Apply the keyword?' ),
			'js_content_fixes_note' => array( 'fa' => 'مدل برای محتوا هم اصلاحیه دارد (در صورت تأیید جایگزین می‌شود):', 'en' => 'The model also suggests content fixes (replaces on confirmation):' ),
			'js_apply_content_fix'  => array( 'fa' => 'اعمال اصلاحیهٔ محتوا', 'en' => 'Apply content fix' ),
			'js_confirm_content_fix' => array( 'fa' => 'اصلاحیهٔ محتوا اعمال شود؟', 'en' => 'Apply the content fix?' ),
			'js_assistant_suggestion' => array( 'fa' => 'پیشنهاد هوش‌یار:', 'en' => 'Hoshyar suggestion:' ),
			'js_target_field'       => array( 'fa' => 'فیلد هدف + دستور ویژه', 'en' => 'Target field + special instruction' ),
			'js_run_on_field'       => array( 'fa' => 'اجرای دستور روی فیلد انتخابی', 'en' => 'Run instruction on selected field' ),
			'js_btn_title_suggest'  => array( 'fa' => 'پیشنهاد عنوان', 'en' => 'Suggest titles' ),
			'js_btn_write_content'  => array( 'fa' => 'تولید محتوا', 'en' => 'Write content' ),
			'js_btn_seo'            => array( 'fa' => 'بهبود سئو', 'en' => 'Improve SEO' ),
			'js_btn_excerpt'        => array( 'fa' => 'پیشنهاد خلاصه', 'en' => 'Suggest excerpt' ),
			'js_btn_rewrite_block'  => array( 'fa' => 'بازنویسی بلوک انتخابی', 'en' => 'Rewrite selected block' ),
			'js_instruction_ph_classic' => array(
				'fa' => 'مثلاً: «عنوان را جذاب‌تر کن»، «این پاراگراف را سئوتر بنویس»، «خلاصه بنویس»…',
				'en' => 'e.g. “Make the title catchier”, “Rewrite this paragraph for SEO”, “Write an excerpt”…',
			),
			'js_opt_main_content'   => array( 'fa' => 'محتوای اصلی', 'en' => 'Main content' ),
			'js_selected_block'     => array( 'fa' => 'بلوک انتخابی', 'en' => 'Selected block' ),
			'js_whole_post'         => array( 'fa' => 'کل پست', 'en' => 'Whole post' ),
			'js_es_panel_title'     => array( 'fa' => 'هوش‌یار', 'en' => 'Hoshyar' ),
			'js_es_quick_actions'   => array( 'fa' => 'عملیات سریع', 'en' => 'Quick actions' ),
			'js_es_instruction_label' => array( 'fa' => 'دستور ویژه', 'en' => 'Special instruction' ),
			'js_es_result_title'    => array( 'fa' => 'نتیجه', 'en' => 'Result' ),
			'js_applies_to_block'   => array( 'fa' => 'روی بلوک انتخابی اعمال می‌شود.', 'en' => 'Applied to the selected block.' ),
			'js_applies_to_post'    => array( 'fa' => 'روی کل پست اعمال می‌شود.', 'en' => 'Applied to the whole post.' ),
			'js_es_instruction_title' => array( 'fa' => 'دستور ویژه (برای فیلد انتخابی یا کل پست)', 'en' => 'Special instruction (for the selected field or the whole post)' ),
			'js_es_instruction_ph'  => array(
				'fa' => 'مثلاً: «لحن متن را رسمی‌تر کن» یا «سئوی این قسمت را بهتر کن»…',
				'en' => 'e.g. “Make the tone more formal” or “Improve the SEO of this part”…',
			),
			'js_es_run_instruction' => array( 'fa' => 'اجرای دستور', 'en' => 'Run instruction' ),
			'js_es_fields_title'    => array( 'fa' => 'فیلدهای شناسایی‌شدهٔ پست', 'en' => 'Detected post fields' ),
			'js_es_refresh_fields'  => array( 'fa' => 'بروزرسانی فهرست فیلدها', 'en' => 'Refresh field list' ),
			'js_es_refresh_hint'    => array( 'fa' => 'دکمهٔ بروزرسانی را بزنید تا فیلدها شناسایی شوند.', 'en' => 'Hit refresh to detect the fields.' ),
			'js_es_run_on_selected' => array( 'fa' => 'دستور روی فیلد انتخابی', 'en' => 'Run on selected field' ),
			'js_es_proposal'        => array( 'fa' => 'پیشنهاد (برای اعمال تأیید کنید):', 'en' => 'Suggestion (confirm to apply):' ),
			'js_apply'              => array( 'fa' => 'اعمال پیشنهاد', 'en' => 'Apply suggestion' ),
		);
	}

	/**
	 * ترجمهٔ یک کلید.
	 *
	 * @param string $key  کلید رشته.
	 * @param array  $args آرگومان‌های sprintf (اختیاری).
	 * @return string
	 */
	public static function t( $key, $args = array() ) {
		static $catalog = null;
		if ( null === $catalog ) {
			$catalog = self::catalog();
		}

		$lang = self::lang();
		$text = isset( $catalog[ $key ][ $lang ] ) ? $catalog[ $key ][ $lang ] : ( isset( $catalog[ $key ]['en'] ) ? $catalog[ $key ]['en'] : (string) $key );

		if ( ! empty( $args ) && false !== strpos( $text, '%' ) ) {
			$translated = @vsprintf( $text, array_values( $args ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( false !== $translated && '' !== $translated ) {
				$text = $translated;
			}
		}

		/**
		 * فیلتر خروجی ترجمه — برای سفارشی‌سازی توسط توسعه‌دهندگان.
		 *
		 * @param string $text متن ترجمه‌شده.
		 * @param string $key  کلید.
		 * @param string $lang زبان.
		 */
		return apply_filters( 'content_assistant_translation', $text, $key, $lang );
	}

	/**
	 * ترجمه + خروجی امن (esc_html).
	 *
	 * @param string $key  کلید.
	 * @param array  $args آرگومان‌ها.
	 * @return string
	 */
	public static function esc( $key, $args = array() ) {
		return esc_html( self::t( $key, $args ) );
	}

	/**
	 * ترجمهٔ مناسب attribute (esc_attr).
	 *
	 * @param string $key  کلید.
	 * @param array  $args آرگومان‌ها.
	 * @return string
	 */
	public static function attr( $key, $args = array() ) {
		return esc_attr( self::t( $key, $args ) );
	}

	/**
	 * کاتالوگ ترجمه‌شده برای تحویل به JS.
	 *
	 * @return array کلید => متن به زبان فعال.
	 */
	public static function translated_catalog() {
		$out    = array();
		$catalog = self::catalog();
		foreach ( $catalog as $key => $pair ) {
			$out[ $key ] = isset( $pair[ self::lang() ] ) ? $pair[ self::lang() ] : $pair['en'];
		}
		return $out;
	}

	/**
	 * تحویل رشته‌ها + جهت + زبان به یک اسکریپت (obj سراسری contentAssistantI18n).
	 *
	 * @param string $handle نام اسکریپت.
	 */
	public static function localize( $handle ) {
		wp_localize_script(
			$handle,
			'contentAssistantI18n',
			array(
				'lang'    => self::lang(),
				'dir'     => self::dir(),
				'strings' => self::translated_catalog(),
			)
		);
	}
}

/**
 * ترجمهٔ سراسری (کوتاه‌شده) — معادل __() با کاتالوگ دوزبانهٔ افزونه.
 *
 * @param string $key  کلید.
 * @param array  $args آرگومان‌ها.
 * @return string
 */
function content_assistant_t( $key, $args = array() ) {
	return Content_Assistant_I18n::t( $key, $args );
}

/**
 * ترجمهٔ سراسری با خروجی امن — معادل esc_html__().
 *
 * @param string $key  کلید.
 * @param array  $args آرگومان‌ها.
 * @return string
 */
function content_assistant_te( $key, $args = array() ) {
	return Content_Assistant_I18n::esc( $key, $args );
}

/**
 * ترجمهٔ سراسری برای attribute — معادل esc_attr__().
 *
 * @param string $key  کلید.
 * @param array  $args آرگومان‌ها.
 * @return string
 */
function content_assistant_ta( $key, $args = array() ) {
	return Content_Assistant_I18n::attr( $key, $args );
}
