<?php
/**
 * REST API темы: приём заявок и данные калькулятора.
 * Namespace: virshketech/v1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'vtk_register_rest_routes' );
function vtk_register_rest_routes() {
	register_rest_route(
		'virshketech/v1',
		'/order',
		array(
			'methods'             => 'POST',
			'callback'            => 'vtk_rest_create_order',
			'permission_callback' => '__return_true', // публичная форма; защита — nonce + валидация полей
			'args'                => array(
				'name'    => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
				'phone'   => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
				'consent' => array( 'required' => true, 'type' => 'string' ),
			),
		)
	);

	register_rest_route(
		'virshketech/v1',
		'/calculator',
		array(
			'methods'             => 'GET',
			'callback'            => 'vtk_rest_get_calculator',
			'permission_callback' => '__return_true',
		)
	);
}

/**
 * POST /wp-json/virshketech/v1/order
 * Принимает поля формы + vtk_nonce (action 'vtk_order') в теле запроса.
 */
function vtk_rest_create_order( WP_REST_Request $request ) {
	$nonce = $request->get_param( 'vtk_nonce' );
	if ( ! $nonce || ! wp_verify_nonce( sanitize_text_field( $nonce ), 'vtk_order' ) ) {
		return new WP_Error( 'vtk_bad_nonce', __( 'Сессия устарела, обновите страницу.', 'virshketech' ), array( 'status' => 403 ) );
	}

	$fields = vtk_validate_request( $request->get_params() );
	if ( is_wp_error( $fields ) ) {
		$fields->add_data( array( 'status' => 400 ) );
		return $fields;
	}

	$post_id = vtk_create_order_request( $fields );
	if ( is_wp_error( $post_id ) ) {
		$post_id->add_data( array( 'status' => 500 ) );
		return $post_id;
	}

	return new WP_REST_Response(
		array(
			'id'         => $post_id,
			'redirectTo' => vtk_page_url( 'thanks', home_url( '/thanks/' ) ),
		),
		201
	);
}

/**
 * GET /wp-json/virshketech/v1/calculator — актуальные базы/модули (резерв для JS).
 */
function vtk_rest_get_calculator( WP_REST_Request $request ) {
	$data = vtk_calc_data();
	unset( $data['nonce'] ); // токен не отдаём через публичный GET
	return new WP_REST_Response( $data, 200 );
}
