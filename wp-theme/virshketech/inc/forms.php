<?php
/**
 * Форма заказа: сохранение заявки (CPT order_request), email-уведомление,
 * AJAX-обработчик и CSV-экспорт списка заявок.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Валидация и нормализация «сырых» полей заявки.
 *
 * @param array $raw Сырые значения (не экранированные).
 * @return array|WP_Error Нормализованные поля или ошибка валидации.
 */
function vtk_validate_request( $raw ) {
	$name  = sanitize_text_field( $raw['name'] ?? '' );
	$phone = preg_replace( '/\D+/', '', (string) ( $raw['phone'] ?? '' ) );
	$email = sanitize_email( $raw['email'] ?? '' );

	if ( '' === $name || mb_strlen( $name ) > 100 ) {
		return new WP_Error( 'vtk_name', __( 'Укажите имя (до 100 символов).', 'virshketech' ) );
	}
	if ( strlen( $phone ) !== 11 ) {
		return new WP_Error( 'vtk_phone', __( 'Телефон должен содержать 11 цифр.', 'virshketech' ) );
	}
	if ( '' !== $raw['email'] && '' === $email ) {
		return new WP_Error( 'vtk_email', __( 'Некорректный e-mail.', 'virshketech' ) );
	}
	if ( empty( $raw['consent'] ) ) {
		return new WP_Error( 'vtk_consent', __( 'Требуется согласие на обработку персональных данных.', 'virshketech' ) );
	}

	// Конфигурация из калькулятора: JSON {items:[{label,price}], total}.
	$config_raw = (string) ( $raw['config'] ?? '' );
	$config     = array( 'items' => array(), 'total' => 0 );
	if ( '' !== $config_raw ) {
		$decoded = json_decode( $config_raw, true );
		if ( is_array( $decoded ) ) {
			$items = array();
			foreach ( (array) ( $decoded['items'] ?? array() ) as $it ) {
				if ( ! is_array( $it ) || empty( $it['label'] ) ) {
					continue;
				}
				$items[] = array(
					'label' => sanitize_text_field( $it['label'] ),
					'price' => absint( $it['price'] ?? 0 ),
				);
			}
			$config = array(
				'items' => $items,
				'total' => isset( $decoded['total'] ) ? absint( $decoded['total'] ) : array_sum( wp_list_pluck( $items, 'price' ) ),
			);
		}
	}

	return array(
		'name'    => $name,
		'company' => sanitize_text_field( $raw['company'] ?? '' ),
		'phone'   => $phone,
		'email'   => $email,
		'machine' => sanitize_textarea_field( $raw['machine'] ?? '' ),
		'config'  => $config,
		'source'  => sanitize_text_field( $raw['source'] ?? '' ),
	);
}

/**
 * Создать заявку (post + мета) и отправить уведомление.
 * Используется REST-эндпоинтом и AJAX-фолбэком.
 *
 * @param array $fields Результат vtk_validate_request().
 * @return int|WP_Error ID заявки.
 */
function vtk_create_order_request( $fields ) {
	$status = get_post_status_object( 'publish' ) ? 'publish' : 'private';

	$title = sprintf(
		/* translators: 1: имя, 2: сумма */
		__( 'Заявка: %1$s — %2$s', 'virshketech' ),
		$fields['name'],
		$fields['config']['total'] ? number_format_i18n( $fields['config']['total'], 0 ) . ' ₽' : __( 'без комплектации', 'virshketech' )
	);

	$post_id = wp_insert_post( array(
		'post_type'   => 'order_request',
		'post_status' => $status,
		'post_title'  => $title,
	), true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	$now = current_time( 'mysql' );
	update_post_meta( $post_id, '_vtk_req_name', $fields['name'] );
	update_post_meta( $post_id, '_vtk_req_company', $fields['company'] );
	update_post_meta( $post_id, '_vtk_req_phone', $fields['phone'] );
	update_post_meta( $post_id, '_vtk_req_email', $fields['email'] );
	update_post_meta( $post_id, '_vtk_req_machine', $fields['machine'] );
	update_post_meta( $post_id, '_vtk_req_config', wp_json_encode( $fields['config'], JSON_UNESCAPED_UNICODE ) );
	update_post_meta( $post_id, '_vtk_req_total', $fields['config']['total'] );
	update_post_meta( $post_id, '_vtk_req_status', 'new' );
	update_post_meta( $post_id, '_vtk_req_date', $now );

	vtk_notify_new_request( $post_id, $fields );

	do_action( 'vtk_order_created', $post_id, $fields );
	return $post_id;
}

/**
 * Email-уведомление администратору о новой заявке.
 */
function vtk_notify_new_request( $post_id, $fields ) {
	$to      = apply_filters( 'vtk_notify_email', get_option( 'admin_email' ) );
	$subject = sprintf(
		/* translators: %s — имя клиента */
		__( '[VirshkeTech] Новая заявка от %s', 'virshketech' ),
		$fields['name']
	);

	$lines = array(
		__( 'Имя:', 'virshketech' ) . ' ' . $fields['name'],
		__( 'Компания:', 'virshketech' ) . ' ' . ( $fields['company'] ?: '—' ),
		__( 'Телефон:', 'virshketech' ) . ' ' . $fields['phone'],
		__( 'E-mail:', 'virshketech' ) . ' ' . ( $fields['email'] ?: '—' ),
		__( 'Модель станка:', 'virshketech' ) . ' ' . ( $fields['machine'] ?: '—' ),
		'',
		__( 'Конфигурация:', 'virshketech' ),
	);
	foreach ( $fields['config']['items'] as $it ) {
		$lines[] = ' - ' . $it['label'] . ': ' . number_format_i18n( $it['price'], 0 ) . ' ₽';
	}
	$lines[] = __( 'Итого:', 'virshketech' ) . ' ' . number_format_i18n( $fields['config']['total'], 0 ) . ' ₽';
	$lines[] = '';
	$lines[] = admin_url( 'edit.php?post_type=order_request' );

	wp_mail( $to, $subject, implode( "\n", $lines ) );
}

/* ------------------------------------------------------------------ */
/* AJAX-обработчик (запасной путь, если REST недоступен)              */
/* ------------------------------------------------------------------ */

add_action( 'wp_ajax_vtk_submit_order', 'vtk_ajax_submit_order' );
add_action( 'wp_ajax_nopriv_vtk_submit_order', 'vtk_ajax_submit_order' );
function vtk_ajax_submit_order() {
	check_ajax_referer( 'vtk_order', 'nonce' );

	$fields = vtk_validate_request( wp_unslash( $_POST ) );
	if ( is_wp_error( $fields ) ) {
		wp_send_json_error( array( 'message' => $fields->get_error_message() ), 400 );
	}

	$post_id = vtk_create_order_request( $fields );
	if ( is_wp_error( $post_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Не удалось сохранить заявку.', 'virshketech' ) ), 500 );
	}

	wp_send_json_success(
		array(
			'id'         => $post_id,
			'redirectTo' => vtk_page_url( 'thanks', home_url( '/thanks/' ) ),
		)
	);
}

/* ------------------------------------------------------------------ */
/* Фильтры списка заявок по статусу и дате                            */
/* ------------------------------------------------------------------ */

add_filter( 'manage_edit-order_request_sortable_columns', static function ( $columns ) {
	$columns['vtk_req_date'] = 'vtk_req_date';
	return $columns;
} );

add_action( 'restrict_manage_posts', 'vtk_requests_filter_ui' );
function vtk_requests_filter_ui( $post_type ) {
	if ( 'order_request' !== $post_type ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- только чтение фильтров URL
	$status = isset( $_GET['vtk_status'] ) ? sanitize_key( $_GET['vtk_status'] ) : '';
	?>
	<select name="vtk_status">
		<option value=""><?php esc_html_e( 'Все статусы', 'virshketech' ); ?></option>
		<?php foreach ( vtk_request_statuses() as $key => $label ) : ?>
			<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select>
	<input type="date" name="vtk_after" value="<?php echo isset( $_GET['vtk_after'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['vtk_after'] ) ) ) : ''; ?>">
	<input type="date" name="vtk_before" value="<?php echo isset( $_GET['vtk_before'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['vtk_before'] ) ) ) : ''; ?>">
	<?php
	// phpcs:enable
}

add_action( 'parse_query', 'vtk_requests_admin_query' );
function vtk_requests_admin_query( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || 'order_request' !== $query->get( 'post_type' ) ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- чтение фильтров
	$mq = (array) $query->get( 'meta_query' );
	if ( ! empty( $_GET['vtk_status'] ) && array_key_exists( sanitize_key( $_GET['vtk_status'] ), vtk_request_statuses() ) ) {
		$mq[] = array(
			'key'   => '_vtk_req_status',
			'value' => sanitize_key( $_GET['vtk_status'] ),
		);
	}
	$date_query = (array) $query->get( 'date_query' );
	if ( ! empty( $_GET['vtk_after'] ) ) {
		$date_query[] = array(
			'column' => 'post_date',
			'after'  => sanitize_text_field( wp_unslash( $_GET['vtk_after'] ) ),
		);
	}
	if ( ! empty( $_GET['vtk_before'] ) ) {
		$date_query[] = array(
			'column' => 'post_date',
			'before' => sanitize_text_field( wp_unslash( $_GET['vtk_before'] ) ) . ' 23:59:59',
		);
	}
	// phpcs:enable
	if ( $date_query ) {
		$query->set( 'date_query', $date_query );
	}
	if ( $mq ) {
		$query->set( 'meta_query', $mq );
	}
}

/* ------------------------------------------------------------------ */
/* CSV-экспорт заявок                                                 */
/* ------------------------------------------------------------------ */

add_action( 'admin_post_export_vtk_requests', 'vtk_export_requests_csv' );
function vtk_export_requests_csv() {
	if ( ! current_user_can( 'export' ) ) {
		wp_die( esc_html__( 'Недостаточно прав для экспорта.', 'virshketech' ) );
	}
	check_admin_referer( 'vtk_export_requests' );

	$posts = get_posts( array(
		'post_type'      => 'order_request',
		'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
		'posts_per_page' => 5000,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );

	$statuses = vtk_request_statuses();
	$rows     = array( array( 'ID', 'Дата', 'Имя', 'Компания', 'Телефон', 'E-mail', 'Станок', 'Конфигурация', 'Сумма, ₽', 'Статус' ) );
	foreach ( $posts as $p ) {
		$status = get_post_meta( $p->ID, '_vtk_req_status', true ) ?: 'new';
		$rows[] = array(
			$p->ID,
			get_post_meta( $p->ID, '_vtk_req_date', true ) ?: $p->post_date,
			get_post_meta( $p->ID, '_vtk_req_name', true ),
			get_post_meta( $p->ID, '_vtk_req_company', true ),
			get_post_meta( $p->ID, '_vtk_req_phone', true ),
			get_post_meta( $p->ID, '_vtk_req_email', true ),
			get_post_meta( $p->ID, '_vtk_req_machine', true ),
			get_post_meta( $p->ID, '_vtk_req_config', true ),
			(int) get_post_meta( $p->ID, '_vtk_req_total', true ),
			$statuses[ $status ] ?? $status,
		);
	}

	$filename = 'virshketech-requests-' . gmdate( 'Y-m-d' ) . '.csv';
	nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // BOM для корректной кириллицы в Excel
	foreach ( $rows as $row ) {
		 fputcsv( $out, $row, ';' );
	}
	fclose( $out );
	exit;
}
