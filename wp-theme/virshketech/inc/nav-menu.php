<?php
/**
 * Автоменю: виртуальные пункты «Посадочные страницы» (audience_page по слагам)
 * + ссылки на служебные страницы. Нужен, чтобы в Appearance > Menus можно было
 * добавить пилюли аудиторий и /calculator/ даже до создания пунктов вручную.
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

add_filter( 'wp_nav_menu_objects', 'vtk_append_audience_items', 10, 2 );
function vtk_append_audience_items( $items, $args ) {
// Только для основного меню; дублируем поведение header.html.
if ( 'primary' !== $args->theme_location ) {
return $items;
}

$existing = array();
foreach ( $items as $i ) {
if ( isset( $i->object_id ) ) {
$existing[ $i->object_type . ':' . $i->object_id ] = true;
}
}

$cur = vtk_current_audience_key();
$map = array(
'pro'    => array( 'cnc-pro',  'У меня стойка Fanuc/Siemens' ),
'lite'   => array( 'cnc-lite', 'У меня Mach3/DDCS' ),
'manual' => array( 'manual',   'У меня ручной станок' ),
);

foreach ( $map as $key => $d ) {
$id = vtk_audience_id_by_slug( $d[0] );
if ( ! $id || isset( $existing[ 'post_type:' . $id ] ) ) {
continue;
}
$item              = new stdClass();
$item->ID          = 'vtk-aud-' . $key;
$item->db_id       = 0;
$item->object      = 'audience_page';
$item->object_id   = $id;
$item->type        = 'post_type';
$item->menu_item_parent = 0;
$item->title       = $d[1];
$item->url         = get_permalink( $id );
$item->classes     = array( 'pill' );
$item->current     = ( $cur === $key );
$item->class_names = trim( implode( ' ', $item->classes ) . ( $item->current ? ' active current-menu-item' : '' ) );
$items[]           = $item;
}

return $items;
}

// Класс .active для текущего пункта — фронтенд-пилюли полагаются на него.
add_filter( 'nav_menu_css_class', 'vtk_nav_active_class', 10, 4 );
function vtk_nav_active_class( $classes, $item, $args, $nav_args ) {
if ( isset( $item->current ) && $item->current && ! in_array( 'active', $classes, true ) ) {
$classes[] = 'active';
}
return $classes;
}
