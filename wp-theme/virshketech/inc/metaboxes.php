<?php
/**
 * Метабоксы CPT в классическом редакторе (meta box API) — надёжно без JS-сборки.
 * Поля тех же ключей, что и register_post_meta из inc/cpt.php, поэтому данные
 * видны и через Gutenberg (show_in_rest), и здесь; дублирование исключено:
 * для этих CPT на экране записи используется классический редактор метабоксов.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'add_meta_boxes', 'vtk_register_metaboxes' );
function vtk_register_metaboxes() {
	add_meta_box( 'vtk_audience', __( 'Параметры страницы аудитории', 'virshketech' ), 'vtk_render_audience_mb', 'audience_page', 'normal', 'high' );
	add_meta_box( 'vtk_module', __( 'Параметры модуля', 'virshketech' ), 'vtk_render_module_mb', 'calc_module', 'normal', 'high' );
	add_meta_box( 'vtk_request', __( 'Данные заявки', 'virshketech' ), 'vtk_render_request_mb', 'order_request', 'normal', 'high' );
}

/** Единый рендер поля. */
function vtk_mb_field( $args ) {
	$id    = $args['id'];
	$name  = VTK_META_PREFIX . $id;
	$value = $args['value'];
	echo '<p><label for="' . esc_attr( $name ) . '"><strong>' . esc_html( $args['label'] ) . '</strong></label><br>';
	switch ( $args['type'] ) {
		case 'textarea':
			echo '<textarea class="large-text" rows="4" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $value ) . '</textarea>';
			break;
		case 'number':
			echo '<input type="number" min="0" step="1000" class="regular-text" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
			break;
		case 'checkbox':
			echo '<label><input type="checkbox" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( $value, '1', false ) . '> ' . esc_html( isset( $args['cb_label'] ) ? $args['cb_label'] : '' ) . '</label>';
			break;
		case 'select':
			echo '<select id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">';
			foreach ( (array) $args['options'] as $k => $l ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $value, $k, false ) . '>' . esc_html( $l ) . '</option>';
			}
			echo '</select>';
			break;
		default:
			echo '<input type="text" class="large-text" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
	}
	if ( ! empty( $args['desc'] ) ) {
		echo '<span class="description">' . esc_html( $args['desc'] ) . '</span>';
	}
	echo '</p>';
}

define( 'VTK_META_PREFIX', 'vtk_' ); // префикс name-атрибутов формы (ключи меты — с подчёркиванием, см. cpt.php)

/** audience_page. */
function vtk_render_audience_mb( $post ) {
	wp_nonce_field( 'vtk_save_mb', 'vtk_mb_nonce' );
	$features = (array) get_post_meta( $post->ID, '_vtk_features', true );
	// Repeater функций: добавление/удаление строк делает JS ниже (без сбора).
	vtk_mb_field( array( 'id' => 'slug', 'label' => __( 'URL-сегмент', 'virshketech' ), 'type' => 'text', 'value' => get_post_meta( $post->ID, '_vtk_slug', true ), 'desc' => __( 'Например: cnc-pro. Совпадение с permalink.', 'virshketech' ) ) );
	vtk_mb_field( array( 'id' => 'hero_title', 'label' => __( 'Заголовок hero', 'virshketech' ), 'type' => 'text', 'value' => get_post_meta( $post->ID, '_vtk_hero_title', true ) ) );
	vtk_mb_field( array( 'id' => 'hero_subtitle', 'label' => __( 'Подзаголовок hero', 'virshketech' ), 'type' => 'textarea', 'value' => get_post_meta( $post->ID, '_vtk_hero_subtitle', true ) ) );
	vtk_mb_field( array( 'id' => 'connection_method', 'label' => __( 'Метод подключения', 'virshketech' ), 'type' => 'textarea', 'value' => get_post_meta( $post->ID, '_vtk_connection_method', true ) ) );
	vtk_mb_field( array( 'id' => 'price', 'label' => __( 'Стоимость решения (₽)', 'virshketech' ), 'type' => 'number', 'value' => get_post_meta( $post->ID, '_vtk_price', true ) ) );
	vtk_mb_field( array( 'id' => 'available', 'label' => __( 'Показывать карточку на главной', 'virshketech' ), 'type' => 'checkbox', 'cb_label' => __( 'Да', 'virshketech' ), 'value' => get_post_meta( $post->ID, '_vtk_available', true ) ? '1' : '' ) );

	echo '<h4>' . esc_html__( 'Функции (repeater)', 'virshketech' ) . '</h4>';
	echo '<div id="vtk-features">';
	$i = 0;
	$features[] = array(); // пустая строка для добавления
	foreach ( $features as $f ) :
		$f = is_array( $f ) ? $f : array();
		?>
		<div class="vtk-feature-row" style="margin-bottom:8px;display:flex;gap:8px;align-items:center">
			<input type="text" name="vtk_features[<?php echo esc_attr( $i ); ?>][text]" value="<?php echo esc_attr( isset( $f['text'] ) ? $f['text'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Текст функции', 'virshketech' ); ?>" style="flex:1">
			<label><input type="checkbox" name="vtk_features[<?php echo esc_attr( $i ); ?>][available]" value="1" <?php checked( ! empty( $f['available'] ) ); ?>> <?php esc_html_e( 'Доступна', 'virshketech' ); ?></label>
		</div>
		<?php
		$i++;
	endforeach;
	echo '</div>';
	echo '<button type="button" class="button" id="vtk-add-feature">' . esc_html__( 'Добавить функцию', 'virshketech' ) . '</button>';
	?>
	<script>
	jQuery(function($){
		var idx=<?php echo (int) $i; ?>;
		$('#vtk-add-feature').on('click',function(){
			$('#vtk-features').append('<div class="vtk-feature-row" style="margin-bottom:8px;display:flex;gap:8px;align-items:center"><input type="text" name="vtk_features['+idx+'][text]" style="flex:1"><label><input type="checkbox" name="vtk_features['+idx+'][available]" value="1"> Доступна</label></div>');
			idx++;
		});
	});
	</script>
	<?php
}

/** calc_module. */
function vtk_render_module_mb( $post ) {
	wp_nonce_field( 'vtk_save_mb', 'vtk_mb_nonce' );
	vtk_mb_field( array( 'id' => 'module_price', 'label' => __( 'Цена модуля (₽)', 'virshketech' ), 'type' => 'number', 'value' => get_post_meta( $post->ID, '_vtk_module_price', true ) ) );
	vtk_mb_field( array( 'id' => 'svg_id', 'label' => __( 'SVG-идентификатор подсветки', 'virshketech' ), 'type' => 'text', 'value' => get_post_meta( $post->ID, '_vtk_svg_id', true ), 'desc' => __( 'Один из: m-chuck, m-vise, m-tailstock, m-robot (см. assets/scheme.svg).', 'virshketech' ) ) );
	vtk_mb_field( array( 'id' => 'active', 'label' => __( 'Модуль активен', 'virshketech' ), 'type' => 'checkbox', 'cb_label' => __( 'Показывать в калькуляторе', 'virshketech' ), 'value' => get_post_meta( $post->ID, '_vtk_active', true ) ? '1' : '' ) );
}

/** order_request. */
function vtk_render_request_mb( $post ) {
	wp_nonce_field( 'vtk_save_mb', 'vtk_mb_nonce' );
	vtk_mb_field( array( 'id' => 'req_name', 'label' => __( 'Имя', 'virshketech' ), 'type' => 'text', 'value' => get_post_meta( $post->ID, '_vtk_req_name', true ) ) );
	vtk_mb_field( array( 'id' => 'req_company', 'label' => __( 'Компания', 'virshketech' ), 'type' => 'text', 'value' => get_post_meta( $post->ID, '_vtk_req_company', true ) ) );
	vtk_mb_field( array( 'id' => 'req_phone', 'label' => __( 'Телефон', 'virshketech' ), 'type' => 'text', 'value' => get_post_meta( $post->ID, '_vtk_req_phone', true ) ) );
	vtk_mb_field( array( 'id' => 'req_email', 'label' => __( 'E-mail', 'virshketech' ), 'type' => 'text', 'value' => get_post_meta( $post->ID, '_vtk_req_email', true ) ) );
	vtk_mb_field( array( 'id' => 'req_machine', 'label' => __( 'Модель станка / стойка ЧПУ', 'virshketech' ), 'type' => 'textarea', 'value' => get_post_meta( $post->ID, '_vtk_req_machine', true ) ) );
	vtk_mb_field( array( 'id' => 'req_config', 'label' => __( 'Конфигурация (JSON из калькулятора)', 'virshketech' ), 'type' => 'textarea', 'value' => get_post_meta( $post->ID, '_vtk_req_config', true ) ) );
	vtk_mb_field( array( 'id' => 'req_total', 'label' => __( 'Итоговая сумма (₽)', 'virshketech' ), 'type' => 'number', 'value' => get_post_meta( $post->ID, '_vtk_req_total', true ) ) );
	vtk_mb_field( array( 'id' => 'req_status', 'label' => __( 'Статус', 'virshketech' ), 'type' => 'select', 'value' => get_post_meta( $post->ID, '_vtk_req_status', true ) ?: 'new', 'options' => vtk_request_statuses() ) );
	vtk_mb_field( array( 'id' => 'req_date', 'label' => __( 'Дата заявки', 'virshketech' ), 'type' => 'text', 'value' => get_post_meta( $post->ID, '_vtk_req_date', true ), 'desc' => __( 'ISO: ГГГГ-ММ-ДД ЧЧ:ММ:СС', 'virshketech' ) ) );
}

/** Статусы заявки (используются также в фильтрах списка и CSV). */
function vtk_request_statuses() {
	return array(
		'new'     => __( 'Новая', 'virshketech' ),
		'in_work' => __( 'В работе', 'virshketech' ),
		'done'    => __( 'Завершена', 'virshketech' ),
	);
}

add_action( 'save_post', 'vtk_save_metaboxes', 10, 2 );
function vtk_save_metaboxes( $post_id, $post ) {
	if ( ! isset( $_POST['vtk_mb_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['vtk_mb_nonce'] ), 'vtk_save_mb' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$text_fields   = array( 'slug', 'hero_title', 'hero_subtitle', 'connection_method', 'svg_id', 'req_name', 'req_company', 'req_phone', 'req_email', 'req_machine', 'req_config', 'req_date' );
	$number_fields = array( 'price', 'module_price', 'req_total' );
	$bool_fields   = array( 'available', 'active' );

	foreach ( $text_fields as $f ) {
		if ( isset( $_POST[ VTK_META_PREFIX . $f ] ) ) {
			update_post_meta( $post_id, '_vtk_' . $f, sanitize_textarea_field( wp_unslash( $_POST[ VTK_META_PREFIX . $f ] ) ) );
		}
	}
	foreach ( $number_fields as $f ) {
		if ( isset( $_POST[ VTK_META_PREFIX . $f ] ) ) {
			update_post_meta( $post_id, '_vtk_' . $f, absint( $_POST[ VTK_META_PREFIX . $f ] ) );
		}
	}
	foreach ( $bool_fields as $f ) {
		update_post_meta( $post_id, '_vtk_' . $f, isset( $_POST[ VTK_META_PREFIX . $f ] ) );
	}
	if ( isset( $_POST[ VTK_META_PREFIX . 'req_status' ] ) && array_key_exists( $_POST[ VTK_META_PREFIX . 'req_status' ], vtk_request_statuses() ) ) {
		update_post_meta( $post_id, '_vtk_req_status', sanitize_key( wp_unslash( $_POST[ VTK_META_PREFIX . 'req_status' ] ) ) );
	}

	// Repeater функций audience_page.
	if ( 'audience_page' === $post->post_type && isset( $_POST['vtk_features'] ) && is_array( $_POST['vtk_features'] ) ) {
		$clean = array();
		foreach ( wp_unslash( $_POST['vtk_features'] ) as $row ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- санитизируется ниже
			if ( ! is_array( $row ) || empty( $row['text'] ) ) {
				continue;
			}
			$clean[] = array(
				'text'      => sanitize_text_field( $row['text'] ),
				'available' => ! empty( $row['available'] ),
			);
		}
		update_post_meta( $post_id, '_vtk_features', $clean );
	}
}
