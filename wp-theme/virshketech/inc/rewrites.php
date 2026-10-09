<?php
/**
 * Чистые URL посадочных страниц аудиторий: /cnc-pro/, /cnc-lite/, /manual/.
 *
 * audience_page регистрируется с 'rewrite' => false; сегменты первого уровня
 * резолвятся через public-параметр ?typepage=slug — WP корректно ставит
 * is_singular, канонический URL и подсветку меню без кастомных rewrite-правил.
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/** Служебные страницы сайта (слаг => путь от корня). */
function vtk_service_pages() {
return array(
'calculator' => '/calculator/',
'order'      => '/order/',
'thanks'     => '/thanks/',
);
}

/** URL служебной страницы: страница WP по слагу или фолбэк-путь. */
if ( ! function_exists( 'vtk_page_url' ) ) {
function vtk_page_url( $slug, $fallback = '' ) {
if ( ! $fallback ) {
$svc      = vtk_service_pages();
$fallback = home_url( isset( $svc[ $slug ] ) ? $svc[ $slug ] : '/' );
}
$page = get_page_by_path( $slug );
return $page ? get_permalink( $page ) : $fallback;
}
}

add_filter( 'query_vars', 'vtk_add_typepage_var' );
function vtk_add_typepage_var( $vars ) {
$vars[] = 'typepage';
return $vars;
}

/**
 * Инъекция правил до сборки кэша .htaccess/front-controller:
 * односегментный slug аудитории -> index.php?typepage=slug.
 */
add_action( 'init', 'vtk_register_clean_routes' );
function vtk_register_clean_routes() {
global $wp_rewrite;

$reserved  = array_keys( vtk_service_pages() );
$aud_slugs = get_posts(
array(
'post_type'      => 'audience_page',
'post_status'    => 'publish',
'posts_per_page' => 100,
'fields'         => 'slugs',
)
);
$slugs = array_values( array_diff( (array) $aud_slugs, $reserved ) );

$rules = array();
foreach ( $slugs as $slug ) {
$rules[ '^' . preg_quote( $slug, '/' ) . '/?$' ] = 'index.php?typepage=' . $slug;
}

if ( $rules ) {
$wp_rewrite->non_verbose_rules = $rules + (array) $wp_rewrite->non_verbose_rules;
$wp_rewrite->rules             = $rules + (array) $wp_rewrite->rules;
}
}

// Пересобрать правила после создания/переименования записи аудитории.
add_action( 'save_post_audience_page', 'vtk_schedule_rewrite_flush' );
function vtk_schedule_rewrite_flush() {
if ( ! wp_next_scheduled( 'vtk_flush_rewrites' ) ) {
wp_schedule_single_event( time() + 2, 'vtk_flush_rewrites' );
}
}

add_action( 'vtk_flush_rewrites', 'vtk_do_flush_rewrites' );
function vtk_do_flush_rewrites() {
vtk_register_cpts();
flush_rewrite_rules( false );
}

/**
 * main query: ?typepage=slug -> конкретная запись audience_page.
 * parse_request позволяет подменить query_vars до выполнения запроса.
 */
add_action( 'parse_request', 'vtk_handle_typepage' );
function vtk_handle_typepage( $wp ) {
if ( empty( $wp->query_vars['typepage'] ) ) {
return;
}

$slug = sanitize_title( wp_unslash( $wp->query_vars['typepage'] ) );
$post = get_page_by_path( $slug, OBJECT, 'audience_page' );

if ( ! $post || 'publish' !== $post->post_status ) {
$wp->query_vars['is_404'] = true;
return;
}

$wp->query_vars['post_type'] = 'audience_page';
$wp->query_vars['name']      = $slug;
$wp->query_vars['p']         = $post->ID;
$wp->query_vars['pagename']  = '';
$wp->query_vars['error']     = '';

// Канонический редирект уводил бы на /аудитории/slug/ —permalink уже чистый.
remove_filter( 'template_redirect', 'redirect_canonical' );
}

/** Канонический URL записи аудитории — без типа поста. */
add_filter( 'post_type_link', 'vtk_audience_permalink', 10, 2 );
function vtk_audience_permalink( $url, $post ) {
if ( 'audience_page' === $post->post_type ) {
return home_url( user_trailingslashit( rawurlencode( $post->post_name ) ) );
}
return $url;
}

/** Фолбэк: 404 по односегментному пути, который является слагом аудитории
 * (правила ещё не пересобраны) — временный редирект через ?typepage=. */
add_action( 'template_redirect', 'vtk_missing_audience_redirect' );
function vtk_missing_audience_redirect() {
if ( ! is_404() || empty( $_SERVER['REQUEST_URI'] ) ) {
return;
}
$path = trim( (string) wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ), '/' );
if ( '' === $path || false !== strpos( $path, '/' ) ) {
return; // только односегментные пути
}
$post = get_page_by_path( sanitize_title( $path ), OBJECT, 'audience_page' );
if ( $post && 'publish' === $post->post_status ) {
wp_safe_redirect( add_query_arg( 'typepage', rawurlencode( $post->post_name ), home_url( '/' ) ), 302 );
exit;
}
}
