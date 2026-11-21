<?php
/**
 * موتور تولید محتوا: ساخت پست کامل، بهبود پست موجود، پیشنهادهای دستیار.
 *
 * @package ContentAssistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * تولید متن با مدل و اعمال روی پست‌ها.
 */
class Content_Assistant_Generator {

	/**
	 * زمینهٔ عمومی سایت برای پرامپت‌ها.
	 *
	 * @return string
	 */
	public static function site_context() {
		$parts = array(
			sprintf( 'نام سایت: %s', get_bloginfo( 'name' ) ),
			sprintf( 'توضیح سایت: %s', get_bloginfo( 'description' ) ),
			sprintf( 'آدرس: %s', home_url() ),
		);
		return implode( "\n", $parts );
	}

	/**
	 * پرامپت سیستم مشترک.
	 *
	 * @param string $role نقش (مثل "کارشناس سئو").
	 * @return string
	 */
	public static function system_prompt( $role = 'کارشناس حرفه‌ای تولید محتوای فارسی و سئو' ) {
		$settings = Content_Assistant_Settings::get();
		$lang     = ( 'en' === $settings['content_language'] ) ? 'English' : 'فارسی';

		return implode(
			"\n",
			array(
				sprintf( 'تو یک %s هستی.', $role ),
				'همهٔ خروجی‌های تو باید به زبان ' . $lang . ' باشد.',
				self::site_context(),
				'قواعد مهم:',
				'- هرگز از عباراتی مثل "به عنوان یک هوش مصنوعی" استفاده نکن.',
				'- محتوای اصلی، کاربردی و بدون حشو بنویس؛ جملات کوتاه و روان.',
				'- ساختار HTML مجاز در محتوا: p, h2, h3, ul, ol, li, strong, em, blockquote, a, br. هرگز h1 تولید نکن (عنوان پست خودش h1 است).',
				'- فقط HTML محتوا را برگردان، نه <html> یا <body>.',
			)
		);
	}

	/**
	 * فراخوانی مدل و دریافت JSON.
	 *
	 * @param array  $messages پیام‌ها.
	 * @param array  $args     آرگومان‌های اضافی chat.
	 * @param string $context  برچسب لاگ.
	 * @return array|WP_Error ['data'=>array, 'raw'=>string]
	 */
	private static function ask_json( $messages, $args = array(), $context = '' ) {
		$args['messages'] = $messages;
		$res              = Content_Assistant_Client::chat( $args );

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$data = Content_Assistant_Client::extract_json( $res['content'] );
		if ( null === $data ) {
			Content_Assistant_Logger:: log( 'error', 'خروجی مدل JSON نبود: ' . $context, array( 'snippet' => substr( $res['content'], 0, 400 ) ) );
			return new WP_Error( 'content_assistant_bad_json', content_assistant_t( 'err_bad_json' ) );
		}

		return array(
			'data' => $data,
			'raw'  => $res['content'],
		);
	}

	/**
	 * ساخت یک پست کامل برای زمان‌بندی/ایجاد.
	 *
	 * @param string $post_type اسلاگ پست‌تایپ.
	 * @param array  $args      topic, status, words, extra_prompt, category_ids.
	 * @return array|WP_Error ['post_id'=>int, 'title'=>string]
	 */
	public static function create_post( $post_type, $args = array() ) {
		$settings  = Content_Assistant_Settings::get();
		$topic     = isset( $args['topic'] ) ? sanitize_textarea_field( $args['topic'] ) : '';
		$status    = ! empty( $args['status'] ) ? sanitize_key( $args['status'] ) : $settings['default_post_status'];
		$words     = isset( $args['words'] ) ? max( 100, (int) $args['words'] ) : 600;
		$extra     = isset( $args['extra_prompt'] ) ? sanitize_textarea_field( $args['extra_prompt'] ) : '';
		$type_obj  = get_post_type_object( $post_type );
		$type_name = $type_obj ? $type_obj->labels->singular_name : $post_type;

		$topic_line = $topic
			? 'موضوع این پست: ' . $topic . "\n" . 'دقیقاً دربارهٔ همین موضوع بنویس و ذره‌ای از آن منحرف نشو؛ در عنوان، خلاصه و محتوا باید همین موضوع اصلی باشد.'
			: 'خودت یک موضوع مرتبط، جذاب و پرجستجو برای این سایت انتخاب کن.';

		$user = implode(
			"\n",
			array(
				'یک پست کامل برای پست‌تایپ «' . $type_name . '» بنویس.',
				$topic_line,
				'حدود ' . $words . ' کلمه محتوای اصلی بنویس.',
				$extra ? 'نکات تکمیلی: ' . $extra : '',
				'خروجی را فقط و فقط به صورت JSON با ساختار زیر برگردان:',
				"{\n  \"title\": \"عنوان جذاب و سئوشده\",\n  \"content\": \"<p>محتوای HTML…</p>\",\n  \"excerpt\": \"خلاصهٔ حداکثر ۳۰ کلمه‌ای\",\n  \"slug\": \"url-friendly-slug\",\n  \"focus_keyword\": \"کلیدواژهٔ اصلی\",\n  \"meta_description\": \"توضیح متا حداکثر ۱۵۵ کاراکتر\",\n  \"tags\": [\"برچسب۱\", \"برچسب۲\"],\n  \"image_prompt\": \"پرامپت انگلیسی برای تولید تصویر شاخص مناسب این پست\"\n}",
				$topic ? 'یادآوری پایانی: موضوع اصلی و تنها محور این پست «' . $topic . '» است؛ هیچ موضوع دیگری را جایگزین نکن.' : '',
			)
		);

		$result = self::ask_json(
			array(
				array( 'role' => 'system', 'content' => self::system_prompt() ),
				array( 'role' => 'user', 'content' => $user ),
			),
			// دمای پایین‌تر برای تولید پست: پایبندی دقیق‌تر به موضوع.
			array( 'temperature' => 0.3 ),
			'create_post:' . $post_type
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$d = $result['data'];

		$postarr = array(
			'post_type'    => $post_type,
			'post_status'  => in_array( $status, array( 'draft', 'pending', 'publish', 'private' ), true ) ? $status : 'draft',
			'post_title'   => isset( $d['title'] ) ? sanitize_text_field( $d['title'] ) : '',
			'post_content' => isset( $d['content'] ) ? self::sanitize_content_html( $d['content'] ) : '',
			'post_excerpt' => isset( $d['excerpt'] ) ? sanitize_textarea_field( $d['excerpt'] ) : '',
			'post_name'    => isset( $d['slug'] ) ? sanitize_title( $d['slug'] ) : '',
		);

		if ( '' === $postarr['post_title'] ) {
			return new WP_Error( 'content_assistant_no_title', content_assistant_t( 'err_no_title' ) );
		}

		$post_id = wp_insert_post( $postarr, true );

		if ( is_wp_error( $post_id ) ) {
			Content_Assistant_Logger::log( 'error', 'خطا در ایجاد پست: ' . $post_id->get_error_message(), array( 'post_type' => $post_type ) );
			return $post_id;
		}

		// سئو: Yoast / RankMath / AIOSEO.
		self::update_seo_meta(
			$post_id,
			array(
				'description' => isset( $d['meta_description'] ) ? $d['meta_description'] : '',
				'focus'       => isset( $d['focus_keyword'] ) ? $d['focus_keyword'] : '',
			)
		);

		// برچسب‌ها.
		if ( ! empty( $d['tags'] ) && is_array( $d['tags'] ) && in_array( 'tag', (array) get_object_taxonomies( $post_type ), true ) ) {
			$tags = array_slice( array_filter( array_map( 'sanitize_text_field', $d['tags'] ) ), 0, 10 );
			if ( $tags ) {
				wp_set_post_tags( $post_id, $tags );
			}
		}

		// دسته‌های انتخابی کاربر.
		if ( ! empty( $args['category_ids'] ) && is_array( $args['category_ids'] ) && in_array( 'category', (array) get_object_taxonomies( $post_type ), true ) ) {
			wp_set_post_categories( $post_id, array_map( 'intval', $args['category_ids'] ) );
		}

		Content_Assistant_Logger::track_generated( 'posts' );
		Content_Assistant_Logger::log( 'generation', sprintf( 'پست «%s» برای %s تولید شد.', $postarr['post_title'], $post_type ), array( 'post_id' => $post_id ) );

		return array(
			'post_id' => $post_id,
			'title'   => $postarr['post_title'],
			'image_prompt' => isset( $d['image_prompt'] ) ? sanitize_textarea_field( $d['image_prompt'] ) : '',
		);
	}

	/**
	 * پاکسازی HTML محتوا: حذف تگ‌های خطرناک باقی‌مانده.
	 *
	 * @param string $html محتوا.
	 * @return string
	 */
	public static function sanitize_content_html( $html ) {
		$allowed = '<p><h2><h3><h4><ul><ol><li><strong><em><blockquote><a><br><figure><figcaption><img><table><thead><tbody><tr><th><td><dl><dt><dd><code><pre><hr><span>';
		$html    = wp_kses_post( $html );
		$html    = wp_kses( $html, $allowed );
		// حذف h1 — عنوان پست خودش h1 است.
		$html = preg_replace( '/<h1[^>]*>.*?<\/h1>/is', '', $html );
		return trim( $html );
	}

	/**
	 * به‌روزرسانی متادیتای سئو بسته به افزونهٔ نصب‌شده.
	 *
	 * @param int    $post_id شناسه.
	 * @param array  $data    description, focus.
	 * @return void
	 */
	public static function update_seo_meta( $post_id, $data ) {
		$desc = isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '';
		$focus = isset( $data['focus'] ) ? sanitize_text_field( $data['focus'] ) : '';

		if ( '' !== $desc ) {
			if ( defined( 'WPSEO_VERSION' ) ) {                 // Yoast.
				update_post_meta( $post_id, '_yoast_wpseo_metadesc', $desc );
			}
			if ( class_exists( 'RankMath' ) ) {                  // RankMath.
				update_post_meta( $post_id, '_rank_math_description', $desc );
			}
			if ( defined( 'AIOSEO_VERSION' ) ) {                 // All in One SEO.
				update_post_meta( $post_id, '_aioseo_description', $desc );
			}
			// همیشه در متای اختصاصی نگه می‌داریم تا قالب‌های سفارشی هم بخوانند.
			update_post_meta( $post_id, '_content_assistant_meta_description', $desc );
		}
		if ( '' !== $focus ) {
			if ( defined( 'WPSEO_VERSION' ) ) {
				update_post_meta( $post_id, '_yoast_wpseo_focuskw', $focus );
			}
			if ( class_exists( 'RankMath' ) ) {
				update_post_meta( $post_id, '_rank_math_focus_keyword', $focus );
			}
			update_post_meta( $post_id, '_content_assistant_focus_keyword', $focus );
		}
	}

	/**
	 * زمینهٔ یک پست برای پرامپت (متن ساده).
	 *
	 * @param WP_Post $post پست.
	 * @return string
	 */
	public static function post_context( $post ) {
		$content = trim( wp_strip_all_tags( $post->post_content ) );
		if ( function_exists( 'mb_substr' ) && mb_strlen( $content ) > 4000 ) {
			$content = mb_substr( $content, 0, 4000 ) . '…';
		}

		$lines = array(
			sprintf( 'عنوان فعلی: %s', $post->post_title ),
			sprintf( 'خلاصه فعلی: %s', $post->post_excerpt ? $post->post_excerpt : '(خالی)' ),
			sprintf( 'محتوای فعلی (%d کاراکتر):', strlen( $content ) ),
			$content ? $content : '(خالی)',
		);

		$meta_desc = get_post_meta( $post->ID, '_content_assistant_meta_description', true );
		if ( ! $meta_desc && defined( 'WPSEO_VERSION' ) ) {
			$meta_desc = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
		}
		$lines[] = sprintf( 'توضیح متای فعلی: %s', $meta_desc ? $meta_desc : '(خالی)' );

		$terms = get_the_terms( $post->ID, 'category' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$lines[] = 'دسته‌ها: ' . implode( '، ', wp_list_pluck( $terms, 'name' ) );
		}

		return implode( "\n", $lines );
	}

	/**
	 * پیشنهاد دستیار برای یک قسمت از پست.
	 *
	 * اکشن‌ها:
	 *  - suggest_title: ۳ گزینه عنوان بهتر
	 *  - write_content: تولید/بازنویسی محتوای کامل (یا ادامه)
	 *  - improve_seo: تحلیل + اصلاح متادیتای سئو
	 *  - improve_excerpt: پیشنهاد خلاصه
	 *  - improve_block: بازنویسی یک بلوک/پاراگراف انتخابی
	 *  - custom: دستور دلخواه کاربر دربارهٔ قسمتی مشخص
	 *
	 * @param int    $post_id شناسهٔ پست.
	 * @param string $action  اکشن.
	 * @param array  $params  instruction, field, field_label, field_value, count.
	 * @return array|WP_Error
	 */
	public static function assistant( $post_id, $action, $params = array() ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'content_assistant_no_post', content_assistant_t( 'err_no_post' ) );
		}

		$instruction    = isset( $params['instruction'] ) ? sanitize_textarea_field( $params['instruction'] ) : '';
		$field          = isset( $params['field'] ) ? sanitize_key( $params['field'] ) : '';
		$field_label    = isset( $params['field_label'] ) ? sanitize_text_field( $params['field_label'] ) : $field;
		$field_value    = isset( $params['field_value'] ) ? (string) $params['field_value'] : '';
		$count          = isset( $params['count'] ) ? min( 10, max( 1, (int) $params['count'] ) ) : 3;
		$context        = self::post_context( $post );
		$extra_context  = '';

		if ( '' !== $field_value ) {
			$fv = function_exists( 'mb_substr' ) ? mb_substr( $field_value, 0, 2500 ) : substr( $field_value, 0, 2500 );
			$extra_context = sprintf( "محتوای فعلی قسمت «%s»:\n%s\n", $field_label, $fv );
		}

		$system = self::system_prompt();

		switch ( $action ) {
			case 'suggest_title':
				$instruction = $instruction ? 'الگوی مدنظر کاربر: ' . $instruction : 'عنوان کوتاه، جذاب و سئوشده بنویس.';
				$user = implode(
					"\n",
					array(
						$context,
						$extra_context,
						$instruction,
						sprintf( '%d گزینهٔ عنوان بهتر پیشنهاد بده.', $count ),
						'خروجی JSON: { "titles": ["عنوان۱", "عنوان۲", …] }',
					)
				);
				$res = self::ask_json(
					array(
						array( 'role' => 'system', 'content' => $system ),
						array( 'role' => 'user', 'content' => $user ),
					),
					array(),
					'assistant:suggest_title'
				);
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				$titles = isset( $res['data']['titles'] ) && is_array( $res['data']['titles'] ) ? array_slice( array_values( array_filter( array_map( 'sanitize_text_field', $res['data']['titles'] ) ) ), 0, $count ) : array();
				if ( ! $titles ) {
					return new WP_Error( 'content_assistant_empty_result', content_assistant_t( 'err_no_suggestion' ) );
				}
				return array(
					'type'    => 'options',
					'field'   => 'post_title',
					'options' => $titles,
				);

			case 'write_content':
				$mode = isset( $params['mode'] ) && 'append' === $params['mode'] ? 'append' : 'replace';
				$user = implode(
					"\n",
					array(
						$context,
						$extra_context,
						$mode === 'append'
							? 'ادامهٔ محتوای فعلی را بنویس؛ متن جدید باید بی‌درز به انتهای محتوای فعلی بچسبد و تکراری نباشد.'
							: 'محتوای کامل و جامع این پست را از ابتدا بنویس.',
						$instruction ? 'نکات کاربر: ' . $instruction : '',
						'خروجی JSON: { "content": "<p>…</p>" }',
					)
				);
				$res = self::ask_json(
					array(
						array( 'role' => 'system', 'content' => $system ),
						array( 'role' => 'user', 'content' => $user ),
					),
					array(),
					'assistant:write_content'
				);
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				$html = isset( $res['data']['content'] ) ? self::sanitize_content_html( $res['data']['content'] ) : '';
				if ( '' === $html ) {
					return new WP_Error( 'content_assistant_empty_result', content_assistant_t( 'err_no_content' ) );
				}
				return array(
					'type'  => 'replace_html',
					'field' => 'post_content',
					'value' => $html,
				);

			case 'improve_excerpt':
				$user = implode(
					"\n",
					array(
						$context,
						$extra_context,
						$instruction ? 'نکات کاربر: ' . $instruction : '',
						'یک خلاصهٔ جذاب حداکثر ۳۰ کلمه‌ای برای این پست بنویس.',
						'خروجی JSON: { "excerpt": "…" }',
					)
				);
				$res = self::ask_json(
					array(
						array( 'role' => 'system', 'content' => $system ),
						array( 'role' => 'user', 'content' => $user ),
					),
					array(),
					'assistant:improve_excerpt'
				);
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				$excerpt = isset( $res['data']['excerpt'] ) ? sanitize_textarea_field( $res['data']['excerpt'] ) : '';
				if ( '' === $excerpt ) {
					return new WP_Error( 'content_assistant_empty_result', content_assistant_t( 'err_no_excerpt' ) );
				}
				return array(
					'type'  => 'replace_text',
					'field' => 'post_excerpt',
					'value' => $excerpt,
				);

			case 'improve_seo':
				$user = implode(
					"\n",
					array(
						'پست زیر را از نظر سئو تحلیل کن:',
						$context,
						$extra_context,
						$instruction ? 'نکات کاربر: ' . $instruction : '',
						'خروجی JSON:',
						'{',
						'  "score": عدد_۰_تا_۱۰۰,',
						'  "issues": ["مشکل۱", "مشکل۲"],',
						'  "suggestions": ["پیشنهاد۱", "پیشنهاد۲"],',
						'  "title": "عنوان بهینه‌شده (یا همان فعلی اگر خوب است)",',
						'  "meta_description": "توضیح متای بهینه حداکثر ۱۵۵ کاراکتر",',
						'  "focus_keyword": "کلیدواژهٔ اصلی",',
						'  "content_fixes_html": "اگر اصلاح محتوا لازم است HTML کامل محتوای اصلاح‌شده، وگرنه خالی"',
						'}',
					)
				);
				$res = self::ask_json(
					array(
						array( 'role' => 'system', 'content' => 'کارشناس سئوی سایت ' . get_bloginfo( 'name' ) . '. خروجی فقط JSON است.' ),
						array( 'role' => 'user', 'content' => $user ),
					),
					array(),
					'assistant:improve_seo'
				);
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				$d      = $res['data'];
				$fixes  = isset( $d['content_fixes_html'] ) ? self::sanitize_content_html( $d['content_fixes_html'] ) : '';
				return array(
					'type'     => 'seo_report',
					'field'    => 'seo',
					'score'    => isset( $d['score'] ) ? (int) $d['score'] : null,
					'issues'   => isset( $d['issues'] ) && is_array( $d['issues'] ) ? array_map( 'sanitize_text_field', $d['issues'] ) : array(),
					'suggestions' => isset( $d['suggestions'] ) && is_array( $d['suggestions'] ) ? array_map( 'sanitize_text_field', $d['suggestions'] ) : array(),
					'title'    => isset( $d['title'] ) ? sanitize_text_field( $d['title'] ) : '',
					'meta_description' => isset( $d['meta_description'] ) ? sanitize_textarea_field( $d['meta_description'] ) : '',
					'focus_keyword'    => isset( $d['focus_keyword'] ) ? sanitize_text_field( $d['focus_keyword'] ) : '',
					'content_fixes_html' => $fixes,
				);

			case 'improve_block':
				$user = implode(
					"\n",
					array(
						$context,
						$extra_context,
						$instruction ? 'نکات کاربر: ' . $instruction : 'این قسمت را روان‌تر، سئوشده و جذاب‌تر بازنویسی کن؛ معنا حفظ شود.',
						'خروجی JSON: { "html": "<p>…</p>" }',
					)
				);
				$res = self::ask_json(
					array(
						array( 'role' => 'system', 'content' => $system ),
						array( 'role' => 'user', 'content' => $user ),
					),
					array(),
					'assistant:improve_block'
				);
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				$html = isset( $res['data']['html'] ) ? self::sanitize_content_html( $res['data']['html'] ) : '';
				if ( '' === $html ) {
					return new WP_Error( 'content_assistant_empty_result', content_assistant_t( 'err_no_suggestion' ) );
				}
				return array(
					'type'  => 'replace_html',
					'field' => $field ? $field : 'block',
					'value' => $html,
				);

			case 'custom':
			default:
				if ( '' === $instruction ) {
					return new WP_Error( 'content_assistant_no_instruction', content_assistant_t( 'err_no_instruction' ) );
				}
				$user = implode(
					"\n",
					array(
						$context,
						$extra_context,
						'درخواست کاربر: ' . $instruction,
						'خروجی JSON: { "text": "پاسخ نهایی برای قسمت انتخابی (متن ساده یا HTML مجاز)", "advice": "در صورت نیاز راهنمای کوتاه سئو/ویرایش" }',
					)
				);
				$res = self::ask_json(
					array(
						array( 'role' => 'system', 'content' => $system ),
						array( 'role' => 'user', 'content' => $user ),
					),
					array(),
					'assistant:custom'
				);
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				$text = isset( $res['data']['text'] ) ? (string) $res['data']['text'] : '';
				if ( '' === trim( $text ) ) {
					return new WP_Error( 'content_assistant_empty_result', content_assistant_t( 'err_no_response' ) );
				}
				return array(
					'type'         => 'suggestion',
					'field'        => $field ? $field : '',
					'field_label'  => $field_label,
					'value'        => wp_kses( $text, Content_Assistant_Generator::allowed_suggestion_html() ),
					'advice'       => isset( $res['data']['advice'] ) ? sanitize_textarea_field( $res['data']['advice'] ) : '',
					'should_apply' => ! empty( $params['direct_apply'] ),
				);
		}
	}

	/**
	 * تگ‌های مجاز در پیشنهادهای دستیار.
	 *
	 * @return string
	 */
	public static function allowed_suggestion_html() {
		return '<p><h2><h3><h4><ul><ol><li><strong><em><blockquote><a><br><code><pre>';
	}

	/**
	 * اعمال نتیجهٔ دستیار روی پست (سمت سرور، برای فیلدهای سفارشی).
	 *
	 * @param int    $post_id شناسه.
	 * @param string $field   کلید فیلد: post_title|post_excerpt|post_content|meta:کلید.
	 * @param string $value   مقدار.
	 * @return array|WP_Error
	 */
	public static function apply_field( $post_id, $field, $value ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'content_assistant_no_post', content_assistant_t( 'err_no_post' ) );
		}

		if ( 'post_title' === $field ) {
			$r = wp_update_post( array( 'ID' => $post_id, 'post_title' => sanitize_text_field( $value ) ), true );
		} elseif ( 'post_excerpt' === $field ) {
			$r = wp_update_post( array( 'ID' => $post_id, 'post_excerpt' => sanitize_textarea_field( $value ) ), true );
		} elseif ( 'post_content' === $field ) {
			$r = wp_update_post( array( 'ID' => $post_id, 'post_content' => self::sanitize_content_html( $value ) ), true );
		} elseif ( 0 === strpos( $field, 'meta:' ) ) {
			$key = substr( $field, 5 );
			if ( '' === $key || strlen( $key ) > 190 ) {
				return new WP_Error( 'content_assistant_bad_field', content_assistant_t( 'err_bad_field' ) );
			}
			if ( 'content_assistant_meta_description' === $key ) {
				self::update_seo_meta( $post_id, array( 'description' => $value ) );
			} else {
				update_post_meta( $post_id, $key, sanitize_textarea_field( $value ) );
			}
			$r = 0;
		} elseif ( 'post_name' === $field ) {
			$r = wp_update_post( array( 'ID' => $post_id, 'post_name' => sanitize_title( $value ) ), true );
		} else {
			return new WP_Error( 'content_assistant_bad_field', content_assistant_t( 'err_field_unsupported' ) );
		}

		if ( is_wp_error( $r ) ) {
			return $r;
		}

		return array( 'ok' => true, 'field' => $field );
	}

	/**
	 * فهرست فیلدهای قابل ویرایش یک پست (برای پنل دستیار).
	 *
	 * @param int $post_id شناسه.
	 * @return array
	 */
	public static function detect_fields( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$fields   = array();
		$fields[] = array( 'key' => 'post_title', 'label' => content_assistant_t( 'field_title' ), 'kind' => 'text', 'value' => $post->post_title );
		$fields[] = array( 'key' => 'post_content', 'label' => content_assistant_t( 'field_content' ), 'kind' => 'html', 'value' => function_exists( 'mb_substr' ) ? mb_substr( $post->post_content, 0, 1500 ) : substr( $post->post_content, 0, 1500 ) );
		$fields[] = array( 'key' => 'post_excerpt', 'label' => content_assistant_t( 'field_excerpt' ), 'kind' => 'text', 'value' => $post->post_excerpt );
		$fields[] = array( 'key' => 'post_name', 'label' => content_assistant_t( 'field_slug' ), 'kind' => 'text', 'value' => $post->post_name );

		// متادیتای سئو.
		$desc = get_post_meta( $post_id, '_content_assistant_meta_description', true );
		if ( ! $desc && defined( 'WPSEO_VERSION' ) ) {
			$desc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
		}
		$fields[] = array( 'key' => 'meta:_content_assistant_meta_description', 'label' => content_assistant_t( 'field_meta_desc' ), 'kind' => 'text', 'value' => (string) $desc );

		$focus = get_post_meta( $post_id, '_content_assistant_focus_keyword', true );
		if ( ! $focus && defined( 'WPSEO_VERSION' ) ) {
			$focus = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
		}
		$fields[] = array( 'key' => 'meta:_content_assistant_focus_keyword', 'label' => content_assistant_t( 'field_focus_keyword' ), 'kind' => 'text', 'value' => (string) $focus );

		// سایر متاهای عمومی (غیرمحافظت‌شده).
		$metas = get_post_meta( $post_id );
		$skip  = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_attached_file', '_wp_attachment_metadata', '_wp_page_template', 'classic-editor-screen', 'classic-editor-restore' );
		$count = 0;
		foreach ( (array) $metas as $key => $vals ) {
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			if ( 0 === strpos( $key, '_yoast' ) || 0 === strpos( $key, '_rank_math' ) || 0 === strpos( $key, '_aioseo' ) || 0 === strpos( $key, '_content-assistant' ) ) {
				continue; // قبلاً اضافه شد.
			}
			// متاهای محافظت‌شده را هم نمایش می‌دهیم ولی ویرایش آن‌ها فقط از مسیر مجاز انجام می‌شود.
			$val = isset( $vals[0] ) ? (string) $vals[0] : '';
			if ( is_serialized( $val ) ) {
				continue;
			}
			$fields[] = array(
				'key'   => 'meta:' . $key,
				'label' => content_assistant_t( 'field_meta', array( $key ) ),
				'kind'  => 'text',
				'value' => function_exists( 'mb_substr' ) ? mb_substr( $val, 0, 500 ) : substr( $val, 0, 500 ),
			);
			if ( ++$count >= 30 ) {
				break;
			}
		}

		return $fields;
	}
}
