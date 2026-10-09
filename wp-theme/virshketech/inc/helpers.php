<?php
/**
 * Общие хелперы темы: контакты из опций, URL служебных страниц.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Значения по умолчанию для контактов (TODO: заменить на реальные реквизиты заказчика). */
function vtk_contact_defaults() {
	return array(
		'phone_display' => '+7 (000) 000-00-00', // TODO: реальный телефон
		'phone_e164'    => '+70000000000',       // TODO: реальный телефон в формате E.164
		'email'         => 'info@virshketech.com', // TODO: реальный e-mail
		'copy_start'    => 2026,
	);
}

/**
 * Контакты сайта (фильтруются как и настройки калькулятора — через sanitize).
 */
function vtk_contacts() {
	$raw = get_option( 'vtk_contacts', array() );
	return wp_parse_args( is_array( $raw ) ? $raw : array(), vtk_contact_defaults() );
}

/** Телефон для display (текст ссылки). */
function vtk_phone_display() {
	return vtk_contacts()['phone_display'];
}

/** Телефон в формате E.164 для href="tel:". */
function vtk_phone_e164() {
	return vtk_contacts()['phone_e164'];
}

/** E-mail компании. */
function vtk_site_email() {
	$c = vtk_contacts();
	return is_email( $c['email'] ) ? $c['email'] : vtk_contact_defaults()['email'];
}
/** ID опубликованной записи audience_page по URL-слагу (0 если нет). */
function vtk_audience_id_by_slug( $slug ) {
$post = get_page_by_path( sanitize_title( $slug ), OBJECT, 'audience_page' );
return ( $post && 'publish' === $post->post_status ) ? $post->ID : 0;
}

/** Ключ текущей аудитории (pro|lite|manual) или '' — для подсветки пилюль. */
function vtk_current_audience_key() {
if ( ! is_singular( 'audience_page' ) ) {
return '';
}
$map = array( 'cnc-pro' => 'pro', 'cnc-lite' => 'lite', 'manual' => 'manual' );
$key = $map[ get_post_field( 'post_name', get_queried_object_id() ) ] ?? '';
return $key;
}
