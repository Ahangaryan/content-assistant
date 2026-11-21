<?php
/**
 * ویرایش گروهی: پیدا کردن پست‌های بدون محتوا/عنوان/سئو و اصلاح دسته‌جمعی.
 *
 * @package ContentAssistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * صفحهٔ عملیات گروهی + منطق اجرای هر آیتم.
 */
class Content_Assistant_Bulk_Editor {

	/**
	 * hooks.
	 */
	public static function init() {
		// منو در Settings::register_menu ثبت شده.
	}

	/**
	 * پست‌های مطابق با فیلتر.
	 *
	 * @param array $args post_type, filter, limit, offset, search.
	 * @return array
	 */
	public static function query_posts( $args ) {
		$pt     = isset( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : 'post';
		$filter = isset( $args['filter'] ) ? sanitize_key( $args['filter'] ) : 'no_content';
		$limit  = isset( $args['limit'] ) ? min( 200, max( 1, (int) $args['limit'] ) ) : 50;
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
		$search = isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '';

		if ( ! post_type_exists( $pt ) ) {
			return array();
		}

		$q = array(
			'post_type'      => $pt,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);

		if ( '' !== $search ) {
			$q['s'] = $search;
		}

		switch ( $filter ) {
			case 'no_content':
				// محتوا خالی یا فقط فضای خالی.
				add_filter( 'posts_where', array( __CLASS__, 'where_empty_content' ) );
				$posts = get_posts( $q );
				remove_filter( 'posts_where', array( __CLASS__, 'where_empty_content' ) );
				return $posts;

			case 'no_title':
				add_filter( 'posts_where', array( __CLASS__, 'where_empty_title' ) );
				$posts = get_posts( $q );
				remove_filter( 'posts_where', array( __CLASS__, 'where_empty_title' ) );
				return $posts;

			case 'no_excerpt':
				$q['has_excerpt'] = false;
				return get_posts( $q );

			case 'no_thumbnail':
				$q['meta_query'] = array(
					array(
						'key'     => '_thumbnail_id',
						'compare' => 'NOT EXISTS',
					),
				);
				return get_posts( $q );

			case 'no_seo_desc':
				self::$seo_keys = array( '_yoast_wpseo_metadesc', '_rank_math_description', '_aioseo_description', '_content_assistant_meta_description' );
				add_filter( 'posts_where', array( __CLASS__, 'where_no_seo_desc' ) );
				$posts = get_posts( $q );
				remove_filter( 'posts_where', array( __CLASS__, 'where_no_seo_desc' ) );
				return $posts;

			case 'all':
			default:
				return get_posts( $q );
		}
	}

	/**
	 * کلیدهای سئو برای کلایری فیلتر.
	 *
	 * @var array
	 */
	protected static $seo_keys = array();

	/**
	 * WHERE: محتوای خالی.
	 *
	 * @param string $where کلاوز where.
	 * @return string
	 */
	public static function where_empty_content( $where ) {
		global $wpdb;
		$where .= " AND ( {$wpdb->posts}.post_content = '' OR TRIM({$wpdb->posts}.post_content) = '' )";
		return $where;
	}

	/**
	 * WHERE: عنوان خالی.
	 *
	 * @param string $where کلاوز where.
	 * @return string
	 */
	public static function where_empty_title( $where ) {
		global $wpdb;
		$where .= " AND ( {$wpdb->posts}.post_title = '' OR TRIM({$wpdb->posts}.post_title) = '' )";
		return $where;
	}

	/**
	 * WHERE: بدون توضیح متا در هیچ افزونهٔ سئو.
	 *
	 * @param string $where کلاوز where.
	 * @return string
	 */
	public static function where_no_seo_desc( $where ) {
		global $wpdb;
		$keys   = self::$seo_keys ? self::$seo_keys : array( '_content_assistant_meta_description' );
		$not_in = implode( "','", array_map( 'esc_sql', $keys ) );
		$where .= " AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = {$wpdb->posts}.ID AND pm.meta_key IN ('{$not_in}') AND pm.meta_value <> '' )";
		return $where;
	}

	/**
	 * اجرای یک عملیات روی یک پست (از سمت REST، تک‌تک توسط JS صدا زده می‌شود).
	 *
	 * @param int    $post_id شناسه.
	 * @param string $op      fix_title|write_content|improve_seo|fix_all.
	 * @param array  $args    instruction, mode.
	 * @return array|WP_Error
	 */
	public static function process_item( $post_id, $op, $args = array() ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'content_assistant_no_post', content_assistant_t( 'err_no_post' ) );
		}

		$instruction = isset( $args['instruction'] ) ? sanitize_textarea_field( $args['instruction'] ) : '';
		$mode        = isset( $args['mode'] ) && 'append' === $args['mode'] ? 'append' : 'replace';
		$applied     = array();

		$needs_title   = in_array( $op, array( 'fix_title', 'fix_all' ), true ) || '' === trim( $post->post_title );
		$needs_content = in_array( $op, array( 'write_content', 'fix_all' ), true ) || '' === trim( $post->post_content );
		$needs_seo     = in_array( $op, array( 'improve_seo', 'fix_all' ), true );

		// ۱) عنوان.
		if ( $needs_title ) {
			$res = Content_Assistant_Generator::assistant(
				$post_id,
				'suggest_title',
				array(
					'instruction' => $instruction,
					'count'       => 1,
				)
			);
			if ( ! is_wp_error( $res ) && ! empty( $res['options'][0] ) ) {
				$r = wp_update_post(
					array(
						'ID'         => $post_id,
						'post_title' => sanitize_text_field( $res['options'][0] ),
					),
					true
				);
				if ( ! is_wp_error( $r ) ) {
					$applied[] = 'title';
				}
			}
		}

		// ۲) محتوا.
		if ( $needs_content ) {
			$res = Content_Assistant_Generator::assistant(
				$post_id,
				'write_content',
				array(
					'instruction' => $instruction,
					'mode'        => $mode,
				)
			);
			if ( ! is_wp_error( $res ) && 'replace_html' === $res['type'] ) {
				$content = $res['value'];
				if ( 'append' === $mode && '' !== trim( $post->post_content ) ) {
					$content = rtrim( $post->post_content ) . "\n\n" . $content;
				}
				$r = wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => $content,
					),
					true
				);
				if ( ! is_wp_error( $r ) ) {
					$applied[] = 'content';
					update_post_meta( $post_id, '_content_assistant_bulk_has_content', time() );
				}
			}
		}

		// ۳) سئو.
		if ( $needs_seo ) {
			$res = Content_Assistant_Generator::assistant( $post_id, 'improve_seo', array( 'instruction' => $instruction ) );
			if ( ! is_wp_error( $res ) && 'seo_report' === $res['type'] ) {
				if ( '' !== $res['meta_description'] || '' !== $res['focus_keyword'] ) {
					Content_Assistant_Generator::update_seo_meta(
						$post_id,
						array(
							'description' => $res['meta_description'],
							'focus'       => $res['focus_keyword'],
						)
					);
					$applied[] = 'seo_meta';
				}
				// اگر خلاصه خالی است، از توضیح متا پر شود.
				$p = get_post( $post_id );
				if ( '' === trim( $p->post_excerpt ) && '' !== $res['meta_description'] ) {
					wp_update_post(
						array(
							'ID'         => $post_id,
							'post_excerpt' => sanitize_textarea_field( $res['meta_description'] ),
						)
					);
					$applied[] = 'excerpt';
				}
			}
		}

		// حتی اگر چیزی اعمال نشد، پست «بازدیدشده» علامت می‌خورد تا دوباره صف نشود.
		update_post_meta( $post_id, '_content_assistant_bulk_checked', time() );

		Content_Assistant_Logger::log( 'bulk', sprintf( 'عملیات %s روی پست #%d انجام شد (%s).', $op, $post_id, $applied ? implode( '، ', $applied ) : 'بدون تغییر' ), array( 'post_id' => $post_id ) );

		return array(
			'post_id' => $post_id,
			'applied' => $applied,
			'title'   => get_the_title( $post_id ),
		);
	}

	/**
	 * صفحهٔ ویرایش گروهی.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$types = Content_Assistant_Settings::editable_post_types();
		?>
		<div class="wrap content-assistant-wrap" dir="<?php echo esc_attr( Content_Assistant_I18n::dir() ); ?>">
			<?php Content_Assistant_Settings::render_header( 'bulk_title', 'bulk_desc' ); ?>

			<div class="content-assistant-card content-assistant-card--lg">
				<h2 class="content-assistant-card-title"><?php echo esc_html( content_assistant_te( 'bulk_step1' ) ); ?></h2>
				<table class="form-table content-assistant-form" role="presentation">
					<tr>
						<th scope="row"><label for="gb_post_type"><?php echo esc_html( content_assistant_te( 'label_post_type' ) ); ?></label></th>
						<td>
							<select id="gb_post_type" class="content-assistant-select">
								<?php foreach ( $types as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label . ' (' . $slug . ')' ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gb_filter"><?php echo esc_html( content_assistant_te( 'filter_label' ) ); ?></label></th>
						<td>
							<select id="gb_filter" class="content-assistant-select">
								<option value="no_content"><?php echo esc_html( content_assistant_te( 'filter_no_content' ) ); ?></option>
								<option value="no_title"><?php echo esc_html( content_assistant_te( 'filter_no_title' ) ); ?></option>
								<option value="no_excerpt"><?php echo esc_html( content_assistant_te( 'filter_no_excerpt' ) ); ?></option>
								<option value="no_thumbnail"><?php echo esc_html( content_assistant_te( 'filter_no_thumbnail' ) ); ?></option>
								<option value="no_seo_desc"><?php echo esc_html( content_assistant_te( 'filter_no_seo_desc' ) ); ?></option>
								<option value="all"><?php echo esc_html( content_assistant_te( 'filter_all' ) ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gb_search"><?php echo esc_html( content_assistant_te( 'search_in_title' ) ); ?></label></th>
						<td><input type="text" class="regular-text content-assistant-input" id="gb_search" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="gb_limit"><?php echo esc_html( content_assistant_te( 'max_count' ) ); ?></label></th>
						<td><input type="number" id="gb_limit" value="10" min="1" max="200" class="small-text content-assistant-input" dir="ltr" /></td>
					</tr>
				</table>
				<p class="content-assistant-actions">
					<button type="button" class="button content-assistant-btn" id="gb-scan"><?php echo esc_html( content_assistant_te( 'find_posts' ) ); ?></button>
				</p>
				<div id="gb-scan-result"></div>
			</div>

			<div class="content-assistant-card content-assistant-card--lg">
				<h2 class="content-assistant-card-title"><?php echo esc_html( content_assistant_te( 'bulk_step2' ) ); ?></h2>
				<table class="form-table content-assistant-form" role="presentation">
					<tr>
						<th scope="row"><label for="gb_op"><?php echo esc_html( content_assistant_te( 'operation' ) ); ?></label></th>
						<td>
							<select id="gb_op" class="content-assistant-select">
								<option value="write_content"><?php echo esc_html( content_assistant_te( 'op_write_content' ) ); ?></option>
								<option value="fix_title"><?php echo esc_html( content_assistant_te( 'op_fix_title' ) ); ?></option>
								<option value="improve_seo"><?php echo esc_html( content_assistant_te( 'op_improve_seo' ) ); ?></option>
								<option value="fix_all"><?php echo esc_html( content_assistant_te( 'op_fix_all' ) ); ?></option>
							</select>
						</td>
					</tr>
					<tr id="gb-mode-row">
						<th scope="row"><?php echo esc_html( content_assistant_te( 'content_mode' ) ); ?></th>
						<td>
							<label class="content-assistant-radio"><input type="radio" name="gb_mode" value="replace" checked /> <?php echo esc_html( content_assistant_te( 'mode_replace' ) ); ?></label>
							<label class="content-assistant-radio"><input type="radio" name="gb_mode" value="append" /> <?php echo esc_html( content_assistant_te( 'mode_append' ) ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gb_instruction"><?php echo esc_html( content_assistant_te( 'special_instruction' ) ); ?></label></th>
						<td>
							<textarea id="gb_instruction" class="large-text content-assistant-input" rows="2" placeholder="<?php echo esc_attr( content_assistant_te( 'special_instruction_ph' ) ); ?>"></textarea>
						</td>
					</tr>
				</table>
				<p class="content-assistant-actions">
					<button type="button" class="button button-primary button-hero content-assistant-btn" id="gb-run" disabled><?php echo esc_html( content_assistant_te( 'start_process' ) ); ?></button>
					<button type="button" class="button content-assistant-btn content-assistant-btn--danger" id="gb-cancel" disabled><?php echo esc_html( content_assistant_te( 'stop' ) ); ?></button>
					<span id="gb-progress" class="content-assistant-status" role="status" aria-live="polite"></span>
				</p>
				<div class="content-assistant-progress-bar" style="display:none;"><div id="gb-progress-fill"></div></div>
				<div id="gb-log"></div>
			</div>

			<?php Content_Assistant_Settings::render_footer(); ?>
		</div>

		<script>
		window.contentAssistantBulkConfig = {
			delay: <?php echo (int) Content_Assistant_Settings::get_one( 'bulk_delay' ); ?> * 1000
		};
		</script>
		<?php
	}
}
