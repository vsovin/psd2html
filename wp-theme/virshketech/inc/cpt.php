<?php
/**
 * Кастомные типы записей: audience_page, calc_module, order_request.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'vtk_register_cpts' );
function vtk_register_cpts() {

	// Посадочные страницы аудиторий (cnc-pro / cnc-lite / manual).
	register_post_type( 'audience_page', array(
		'labels' => array(
			'name'               => __( 'Страницы аудиторий', 'virshketech' ),
			'singular_name'      => __( 'Страница аудитории', 'virshketech' ),
			'add_new_item'       => __( 'Добавить страницу аудитории', 'virshketech' ),
			'edit_item'          => __( 'Редактировать страницу аудитории', 'virshketech' ),
			'search_items'       => __( 'Поиск по страницам аудиторий', 'virshketech' ),
			'menu_name'          => __( 'Аудитории', 'virshketech' ),
		),
		'public'       => true,
		'has_archive'  => false,
		'menu_icon'    => 'dashicons-buddicons-activity',
		'menu_position'=> 21,
		'show_in_rest' => true, // Gutenberg + REST
		'rewrite'      => false, // чистые URL /cnc-pro/ и т.п. строит inc/rewrites.php
		'supports'     => array( 'title', 'editor', 'page-attributes' ),
	) );

	// Модули калькулятора.
	register_post_type( 'calc_module', array(
		'labels' => array(
			'name'          => __( 'Модули калькулятора', 'virshketech' ),
			'singular_name' => __( 'Модуль калькулятора', 'virshketech' ),
			'add_new_item'  => __( 'Добавить модуль', 'virshketech' ),
			'menu_name'     => __( 'Модули', 'virshketech' ),
		),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => 'virshketech',
		'menu_icon'    => 'dashicons-calculator',
		'show_in_rest' => true,
		'supports'     => array( 'title', 'editor', 'page-attributes' ), // editor = описание
	) );

	// Заявки с формы заказа.
	register_post_type( 'order_request', array(
		'labels' => array(
			'name'               => __( 'Заявки', 'virshketech' ),
			'singular_name'      => __( 'Заявка', 'virshketech' ),
			'add_new_item'       => __( 'Добавить заявку вручную', 'virshketech' ),
			'all_items'          => __( 'Все заявки', 'virshketech' ),
			'menu_name'          => __( 'Заявки', 'virshketech' ),
		),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => 'virshketech',
		'show_in_rest' => true,
		'supports'     => array( 'title' ), // title = Имя + компания
		'delete_with_user' => false,
	) );
}

/**
 * register_post_meta — поля CPT доступны в REST (Gutenberg-метабоксы через
 * @wordpress/data используют именно show_in_rest) и редактируются на экране записи.
 */
add_action( 'init', 'vtk_register_meta' );
function vtk_register_meta() {

	// --- audience_page ---
	vtk_meta( 'audience_page', '_vtk_slug', 'string' );                                        // URL-сегмент (cnc-pro и т.п.)
	vtk_meta( 'audience_page', '_vtk_hero_title', 'string' );                                  // Заголовок hero
	vtk_meta( 'audience_page', '_vtk_hero_subtitle', 'string' );                               // Подзаголовок
	vtk_meta( 'audience_page', '_vtk_features', 'array', array() );                            // Repeater функций: [{text, available}]
	vtk_meta( 'audience_page', '_vtk_connection_method', 'string' );                           // Метод подключения (textarea)
	vtk_meta( 'audience_page', '_vtk_price', 'integer', 0 );                                   // Стоимость решения
	vtk_meta( 'audience_page', '_vtk_gallery', 'array', array() );                             // Примеры подключения (gallery): ID вложений
	vtk_meta( 'audience_page', '_vtk_available', 'boolean', true );                            // Отображать на главной

	// --- calc_module ---
	vtk_meta( 'calc_module', '_vtk_module_price', 'integer', 0 );                              // Цена модуля
	vtk_meta( 'calc_module', '_vtk_svg_id', 'string' );                                        // SVG-идентификатор подсветки
	vtk_meta( 'calc_module', '_vtk_active', 'boolean', true );                                 // Активен

	// --- order_request ---
	vtk_meta( 'order_request', '_vtk_req_name', 'string' );
	vtk_meta( 'order_request', '_vtk_req_company', 'string' );
	vtk_meta( 'order_request', '_vtk_req_phone', 'string' );
	vtk_meta( 'order_request', '_vtk_req_email', 'string' );
	vtk_meta( 'order_request', '_vtk_req_machine', 'string' );
	vtk_meta( 'order_request', '_vtk_req_config', 'string' );
	vtk_meta( 'order_request', '_vtk_req_total', 'integer', 0 );
	vtk_meta( 'order_request', '_vtk_req_status', 'string', 'new' );                           // new|in_work|done
	vtk_meta( 'order_request', '_vtk_req_date', 'string' );                                    // datetime ISO
}

/**
 * Мини-хелпер регистрации метаполя со схемой для REST.
 *
 * @param string $type    Post type.
 * @param string $key     Meta key.
 * @param string $schema  json-schema тип (string|integer|boolean|array|object).
 * @param mixed  $default Значение по умолчанию.
 */
function vtk_meta( $type, $key, $schema, $default = '' ) {
	$rest = array( 'schema' => array( 'type' => $schema ) );
	if ( 'array' === $schema ) {
		$rest['schema']['items'] = array( 'type' => array( 'string', 'number', 'boolean', 'object', 'null' ) );
	}
	register_post_meta( $type, $key, array(
		'type'          => $schema,
		'single'        => true,
		'default'       => $default,
		'show_in_rest'  => $rest,
		'auth_callback' => static function () {
			return current_user_can( 'edit_posts' );
		},
	) );
}
