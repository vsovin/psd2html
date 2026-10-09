<?php
/**
 * Настройки темы: страница «Настройки > Калькулятор» + данные для фронтенда.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VTK_SETTINGS_OPTION', 'vtk_calc_settings' );

/** Значения по умолчанию (из ТЗ; точные цены — TODO: подтвердить у заказчика). */
function vtk_calc_defaults() {
	return array(
		'base_pro'    => 370000, // Базовая цена: профессиональные ЧПУ
		'base_lite'   => 340000, // Базовая цена: ЧПУ начального уровня
		'base_manual' => 370000, // Базовая цена: ручные станки
		'button_text' => __( 'Оформить заказ', 'virshketech' ),
		'order_url'   => '',     // Пусто = авто: страница /order/
	);
}

function vtk_calc_get_settings() {
	$raw = get_option( VTK_SETTINGS_OPTION, array() );
	return wp_parse_args( is_array( $raw ) ? $raw : array(), vtk_calc_defaults() );
}

add_action( 'admin_menu', 'vtk_register_settings_page' );
function vtk_register_settings_page() {
	add_options_page(
		__( 'Калькулятор — VirshkeTech', 'virshketech' ),
		__( 'Калькулятор', 'virshketech' ),
		'manage_options',
		'vtk-calculator',
		'vtk_render_settings_page'
	);
}

add_action( 'admin_init', 'vtk_register_settings_fields' );
function vtk_register_settings_fields() {
	register_setting( 'vtk_calc_group', VTK_SETTINGS_OPTION, array(
		'type'              => 'array',
		'sanitize_callback' => 'vtk_sanitize_settings',
		'default'           => vtk_calc_defaults(),
	) );
}

function vtk_sanitize_settings( $input ) {
	$in    = is_array( $input ) ? $input : array();
	$clean = array();

	foreach ( array( 'base_pro', 'base_lite', 'base_manual' ) as $key ) {
		$clean[ $key ] = max( 0, absint( $in[ $key ] ?? 0 ) );
	}

	$clean['button_text'] = sanitize_text_field( $in['button_text'] ?? '' );
	if ( '' === $clean['button_text'] ) {
		$clean['button_text'] = __( 'Оформить заказ', 'virshketech' );
	}

	$order_url = esc_url_raw( $in['order_url'] ?? '' );
	// Разрешаем только абсолютные URL этого же сайта или пустое значение (авто).
	if ( $order_url && (string) wp_parse_url( $order_url, PHP_URL_HOST ) !== (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) {
		$order_url = '';
	}
	$clean['order_url'] = $order_url;

	return $clean;
}

function vtk_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Недостаточно прав.', 'virshketech' ) );
	}
	$s = vtk_calc_get_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Настройки калькулятора комплектации', 'virshketech' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'vtk_calc_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="vtk_base_pro"><?php esc_html_e( 'Базовая цена: профессиональные ЧПУ (₽)', 'virshketech' ); ?></label></th>
					<td><input type="number" min="0" step="1000" id="vtk_base_pro" name="<?php echo esc_attr( VTK_SETTINGS_OPTION ); ?>[base_pro]" value="<?php echo esc_attr( $s['base_pro'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="vtk_base_lite"><?php esc_html_e( 'Базовая цена: ЧПУ начального уровня (₽)', 'virshketech' ); ?></label></th>
					<td><input type="number" min="0" step="1000" id="vtk_base_lite" name="<?php echo esc_attr( VTK_SETTINGS_OPTION ); ?>[base_lite]" value="<?php echo esc_attr( $s['base_lite'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="vtk_base_manual"><?php esc_html_e( 'Базовая цена: ручной станок (₽)', 'virshketech' ); ?></label></th>
					<td><input type="number" min="0" step="1000" id="vtk_base_manual" name="<?php echo esc_attr( VTK_SETTINGS_OPTION ); ?>[base_manual]" value="<?php echo esc_attr( $s['base_manual'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="vtk_button_text"><?php esc_html_e( 'Текст кнопки «Оформить заказ»', 'virshketech' ); ?></label></th>
					<td><input type="text" id="vtk_button_text" name="<?php echo esc_attr( VTK_SETTINGS_OPTION ); ?>[button_text]" value="<?php echo esc_attr( $s['button_text'] ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="vtk_order_url"><?php esc_html_e( 'Ссылка на страницу заказа', 'virshketech' ); ?></label></th>
					<td>
						<input type="url" id="vtk_order_url" name="<?php echo esc_attr( VTK_SETTINGS_OPTION ); ?>[order_url]" value="<?php echo esc_attr( $s['order_url'] ); ?>" class="large-text">
						<p class="description"><?php esc_html_e( 'Оставьте пустым — будет использована страница /order/ автоматически.', 'virshketech' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<p class="description">
			<?php esc_html_e( 'Модули калькулятора управляются в разделе «Модули» (CPT calc_module): цена, SVG-идентификатор подсветки, активность.', 'virshketech' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Данные калькулятора для wp_localize_script и REST-ответа.
 * Один источник правды для фронта и API.
 */
function vtk_calc_data() {
	$s = vtk_calc_get_settings();

	$bases = array(
		array( 'id' => 'pro',    'label' => __( 'Для профессиональных ЧПУ', 'virshketech' ),    'price' => (int) $s['base_pro'] ),
		array( 'id' => 'lite',   'label' => __( 'Для ЧПУ начального уровня', 'virshketech' ),   'price' => (int) $s['base_lite'] ),
		array( 'id' => 'manual', 'label' => __( 'Для ручного станка', 'virshketech' ),          'price' => (int) $s['base_manual'] ),
	);

	$modules = array();
	$q = new WP_Query( array(
		'post_type'      => 'calc_module',
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		'no_found_rows'  => true,
		'meta_query'     => array(
			array(
				'key'     => '_vtk_active',
				'value'   => true,
				'type'    => 'boolean',
				'compare' => '=',
			),
		),
	) );
	foreach ( $q->posts as $p ) {
		$modules[] = array(
			'id'     => $p->ID,
			'label'  => get_the_title( $p ),
			'price'  => (int) get_post_meta( $p->ID, '_vtk_module_price', true ),
			'svgId'  => sanitize_key( get_post_meta( $p->ID, '_vtk_svg_id', true ) ),
			'desc'   => wp_strip_all_tags( $p->post_excerpt ?: wp_trim_words( $p->post_content, 25 ) ),
		);
	}

	$order_url = $s['order_url'] ? $s['order_url'] : vtk_page_url( 'order', home_url( '/order/' ) );

	return array(
		'restUrl'    => esc_url_raw( rest_url( 'virshketech/v1' ) ),
		'nonce'      => wp_create_nonce( 'wp_rest' ),
		'bases'      => $bases,
		'modules'    => $modules,
		'buttonText' => $s['button_text'],
		'orderUrl'   => $order_url,
		'thanksUrl'  => vtk_page_url( 'thanks', home_url( '/thanks/' ) ),
		'priceTpl'   => wp_json_encode( array( '%s&nbsp;&#8381;' ) ),
	);
}

/* Метабоксы CPT — в inc/metaboxes.php (классический meta box API). */
