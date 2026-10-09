<?php
/**
 * VirshkeTech — bootstrap темы.
 * Блочная тема (FSE), WordPress 6.5+.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VTK_VERSION', '1.0.0' );
define( 'VTK_DIR', get_template_directory() );
define( 'VTK_URI', get_template_directory_uri() );

require_once VTK_DIR . '/inc/cpt.php';        // CPT: audience_page, calc_module, order_request
require_once VTK_DIR . '/inc/blocks.php';     // Кастомные Gutenberg-блоки
require_once VTK_DIR . '/inc/settings.php';   // Настройки > Калькулятор + метабоксы CPT
require_once VTK_DIR . '/inc/forms.php';      // Обработка формы заказа, email-уведомления, CSV
require_once VTK_DIR . '/inc/rest-api.php';   // POST /wp-json/virshketech/v1/order, GET модулей

/**
 * Ресурсы фронтенда: CSS прототипа + JS калькулятора/формы.
 * script.js получает данные калькулятора через wp_localize_script (см. vtk_calc_data).
 */
function vtk_enqueue_assets() {
	if ( is_admin() ) {
		return;
	}

	wp_enqueue_style( 'virshketech', VTK_URI . '/assets/style.css', array(), VTK_VERSION );

	wp_enqueue_script(
		'virshketech',
		VTK_URI . '/assets/script.js',
		array(),
		VTK_VERSION,
		array( 'in_footer' => true )
	);

	// Данные для калькулятора и REST — см. inc/settings.php::vtk_calc_data().
	wp_localize_script( 'virshketech', 'VirshkeTech', vtk_calc_data() );

	if ( is_singular( 'audience_page' ) ) {
		wp_add_inline_style( 'virshketech', '.audience-hero{padding:4rem 0}' );
	}
}
add_action( 'wp_enqueue_scripts', 'vtk_enqueue_assets' );

/**
 * Ресурсы редактора Gutenberg: стили блоков в сайдбаре предпросмотра.
 */
function vtk_enqueue_editor_assets() {
	wp_enqueue_style( 'virshketech-editor', VTK_URI . '/assets/style.css', array(), VTK_VERSION );
}
add_action( 'enqueue_block_editor_assets', 'vtk_enqueue_editor_assets' );

/**
 * Поддержка темы.
 */
function vtk_theme_setup() {
	load_theme_textdomain( 'virshketech', VTK_DIR . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array( 'height' => 48, 'width' => 220, 'flexible' => true ) );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/style.css' );

	register_nav_menus( array(
		'primary' => __( 'Главное меню (Appearance > Menus)', 'virshketech' ),
		'footer'  => __( 'Меню футера', 'virshketech' ),
	) );
}
add_action( 'after_setup_theme', 'vtk_theme_setup' );

/**
 * Виджеты-сайдбар не используются в FSE, но область регистрируем на будущее.
 */
function vtk_widgets_init() {
	register_sidebar( array(
		'name'          => __( 'Сайдбар', 'virshketech' ),
		'id'            => 'sidebar-1',
		'before_widget' => '<div id="%1$s" class="widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h3 class="widget-title">',
		'after_title'   => '</h3>',
	) );
}
add_action( 'widgets_init', 'vtk_widgets_init' );

/**
 * Активация темы: создаём служебные страницы (Калькулятор, Заказ, Спасибо),
 * если их ещё нет, и flush rewrite rules для CPT.
 */
function vtk_after_switch_theme() {
	vtk_create_core_pages();
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'vtk_after_switch_theme' );

/**
 * Создаёт страницы /calculator/, /order/, /thanks/ с нужными блочами.
 * Идемпотентно.
 */
function vtk_create_core_pages() {
	$pages = array(
		'calculator' => array(
			'title'   => __( 'Калькулятор комплектации', 'virshketech' ),
			'slug'    => 'calculator',
			'content' => '<!-- wp:virshketech/calculator /-->',
		),
		'order' => array(
			'title'   => __( 'Оформить заказ', 'virshketech' ),
			'slug'    => 'order',
			'content' => '<!-- wp:virshketech/order-form /-->',
		),
		'thanks' => array(
			'title'   => __( 'Спасибо за заявку!', 'virshketech' ),
			'slug'    => 'thanks',
			'content' => "<!-- wp:heading --><h2>" . esc_html__( 'Спасибо!', 'virshketech' ) . "</h2><!-- /wp:heading -->\n<!-- wp:paragraph --><p>" . esc_html__( 'Заявка принята. Менеджер свяжется с вами в рабочее время.', 'virshketech' ) . "</p><!-- /wp:paragraph -->",
		),
	);

	foreach ( $pages as $data ) {
		$existing = get_page_by_path( $data['slug'] );
		if ( $existing instanceof WP_Post ) {
			continue;
		}
		wp_insert_post( array(
			'post_title'   => $data['title'],
			'post_name'    => $data['slug'],
			'post_content' => $data['content'],
			'post_status'  => 'publish',
			'post_type'    => 'page',
		) );
	}
}

/**
 * Помощник: получить URL служебной страницы по слагу (для кнопок/редиректов).
 */
function vtk_page_url( $slug, $fallback = '' ) {
	$page = get_page_by_path( $slug );
	return $page ? get_permalink( $page ) : $fallback;
}

/**
 * Хелпер форматирования цены «370 000 ₽» — используется в шаблонах блоков и PHP-рендеринге.
 */
function vtk_price_html( $value ) {
	$value = (int) $value;
	return number_format_i18n( $value, 0 ) . '&nbsp;&#8381;';
}
