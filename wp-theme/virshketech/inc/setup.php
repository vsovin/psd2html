<?php
/**
 * Первичная настройка темы: служебные страницы, записи аудиторий, модули.
 * Идемпотентно; вызывается из admin_init (один раз) и вручную кнопкой.
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

define( 'VTK_SETUP_FLAG', 'vtk_setup_done' );

add_action( 'after_switch_theme', 'vtk_run_setup' );
add_action( 'admin_init', 'vtk_maybe_setup' );

/** При активации темы — сразу; позже (смена слагов CPT) — по кнопке в настройках. */
function vtk_maybe_setup() {
if ( get_option( VTK_SETUP_FLAG ) ) {
return;
}
vtk_run_setup();
}

function vtk_run_setup() {
if ( ! current_user_can( 'switch_themes' ) && ! is_admin() ) {
return;
}

vtk_register_cpts();

// --- Служебные страницы ---
$pages = array(
'calculator' => array( 'title' => 'Калькулятор комплектации', 'blocks' => "<!-- wp:virshketech/calculator /-->\n<div class=\"wp-block-virshketech-calculator\"></div>\n<!-- wp:paragraph --><p>Не нашли нужную опцию? Напишите нам — соберём нестандартную комплектацию.</p><!-- /wp:paragraph -->" ),
'order'      => array( 'title' => 'Оформить заказ', 'blocks' => "<!-- wp:heading --><h1 class=\"wp-block-heading\">Оформить заказ</h1><!-- /wp:heading -->\n<!-- wp:paragraph --><p>Ваши контакты и конфигурация из калькулятора — в одной заявке.</p><!-- /wp:paragraph -->\n<!-- wp:virshketech/order-form /-->\n<div class=\"wp-block-virshketech-order-form\"></div>" ),
'thanks'     => array( 'title' => 'Спасибо за заявку', 'blocks' => "<!-- wp:heading --><h1 class=\"wp-block-heading\">Заявка отправлена</h1><!-- /wp:heading -->\n<!-- wp:paragraph --><p>Мы свяжемся с вами в рабочее время в течение 2 часов. Если вопрос срочный — позвоните нам.</p><!-- /wp:paragraph -->" ),
);
foreach ( $pages as $slug => $d ) {
if ( get_page_by_path( $slug ) ) {
continue;
}
wp_insert_post( array(
'post_type'   => 'page',
'post_status' => 'publish',
'post_name'   => $slug,
'post_title'  => $d['title'],
'post_content' => $d['blocks'],
) );
}

// --- Посадочные страницы аудиторий (TODO: тексты — черновые, уточнить у заказчика) ---
$audiences = array(
'cnc-pro' => array(
'title'    => 'ТФО-160 для профессиональных ЧПУ',
'hero'     => 'Четвёртая ось для Fanuc, Siemens, Mazak, Okuma, Haas, GSK',
'subtitle' => 'Подключение блоком киоск или к импульсным драйверам стойки. Ограниченный, но надёжный функционал без вмешательства в ЧПУ-ядро.',
'price'    => 370000,
'method'   => "Подключение:\n— через блок киоск (аналог внешнего привода оси);\n— либо к импульсным выходам стойки (Step/Dir).\n\nСтойка остаётся «как есть»: мы не трогаем её параметры и сервисные функции.",
'features' => array(
array( 'text' => 'Поворот на заданный угол', 'available' => true ),
array( 'text' => 'Нарезание зубчатых колёс', 'available' => true ),
array( 'text' => 'Нарезание резьбы без передачи параметров в стойку', 'available' => true ),
array( 'text' => 'Полноценная осевая интерполяция G-code', 'available' => false ),
),
'order' => 1,
),
'cnc-lite' => array(
'title'    => 'ТФО-160 для ЧПУ начального уровня',
'hero'     => 'Полный функционал для Mach3, DDCS, LinuxCNC, Inctra, Pumotix',
'subtitle' => 'Подключение реле, StepDir или ModBus. Четвёртая ось работает как «родная» — со всеми циклами.',
'price'    => 340000,
'method'   => "Подключение (на выбор):\n— релейное;\n— StepDir (импульсы Step/Dir);\n— ModBus.\n\nОсь видна системе ЧПУ как обычная четвёртая ось: доступны все постпроцессоры и циклы.",
'features' => array(
array( 'text' => 'Поворот на заданный угол', 'available' => true ),
array( 'text' => 'Нарезание зубчатых колёс', 'available' => true ),
array( 'text' => 'Нарезание резьбы', 'available' => true ),
array( 'text' => 'Осевая интерполяция (XYZ+U)', 'available' => true ),
array( 'text' => 'Индексация и непрерывное деление', 'available' => true ),
),
'order' => 2,
),
'manual' => array(
'title'    => 'ТФО-160 для ручных станков',
'hero'     => 'Четвёртая ось на ручной токарный станок',
'subtitle' => 'Полный функционал с ручным управлением. При установке сервомоторов доступны автоматические циклы.',
'price'    => 370000,
'method'   => "Механический монтаж на станину/планшайбу станка.\nУправление — маховиком/пультом ТФО-160.\nОпция: установка моторов — тогда доступны автоматические циклы обработки.",
'features' => array(
array( 'text' => 'Ручной поворот на заданный угол', 'available' => true ),
array( 'text' => 'Деление при фрезеровании пазов/граней', 'available' => true ),
array( 'text' => 'Нарезание резьбы и зубчатых колёс', 'available' => true ),
array( 'text' => 'Автоматические циклы (с моторами)', 'available' => true ),
),
'order' => 3,
),
);
foreach ( $audiences as $slug => $a ) {
$exists = get_page_by_path( $slug, OBJECT, 'audience_page' );
$id     = $exists ? $exists->ID : wp_insert_post( array(
'post_type'   => 'audience_page',
'post_status' => 'publish',
'post_name'   => $slug,
'post_title'  => $a['title'],
'menu_order'  => $a['order'],
) );
if ( ! $id || is_wp_error( $id ) ) {
continue;
}
update_post_meta( $id, '_vtk_slug', $slug );
update_post_meta( $id, '_vtk_hero_title', $a['hero'] );
update_post_meta( $id, '_vtk_hero_subtitle', $a['subtitle'] );
update_post_meta( $id, '_vtk_features', $a['features'] );
update_post_meta( $id, '_vtk_connection_method', $a['method'] );
update_post_meta( $id, '_vtk_price', $a['price'] );
update_post_meta( $id, '_vtk_available', true );
}

// --- Модули калькулятора ---
$modules = array(
'Mодуль автоматизации токарного патрона' => array( 120000, 'm-chuck', 'Автоматический зажим/разжим заготовки. Цикл без участия оператора.' ),
'Автозажим тисков'                       => array( 95000,  'm-vise',  'Пневмо/электропривод зажима тисков по команде цикла.' ),
'Автоматическая задняя бабка'            => array( 140000, 'm-tailstock', 'Автоподжим задней бабки, синхронизированный с программой.' ),
'Робот для подачи заготовок'             => array( 350000, 'm-robot', 'Загрузчик заготовок: серия деталей без оператора.' ),
);
foreach ( $modules as $title => $m ) {
$dup = get_page_by_title( $title, OBJECT, 'calc_module' );
if ( $dup ) {
continue;
}
$id = wp_insert_post( array(
'post_type'    => 'calc_module',
'post_status'  => 'publish',
'post_title'   => $title,
'post_content' => $m[2],
) );
if ( $id && ! is_wp_error( $id ) ) {
update_post_meta( $id, '_vtk_module_price', $m[0] );
update_post_meta( $id, '_vtk_svg_id', $m[1] );
update_post_meta( $id, '_vtk_active', true );
}
}

flush_rewrite_rules( false );
update_option( VTK_SETUP_FLAG, gmdate( 'c' ) );
}
