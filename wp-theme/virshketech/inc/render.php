<?php
/**
 * PHP-рендеринг динамических блоков (калькулятор, форма заказа).
 * Блоки зарегистрированы как dynamic (render_callback) — актуальные цены
 * и модулей всегда берутся из настроек/CPT, а не из закэшированного HTML.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Каркас страницы калькулятора: radio-базы + чекбоксы модулей + SVG-схема + итог.
 * data-атрибуты соответствуют селекторам assets/script.js.
 *
 * @param array $args      Атрибуты блока (base_default — pro|lite|manual|'').
 * @param bool  $return    Вернуть строку вместо echo.
 * @return string|void
 */
function vtk_render_calculator_html( $args = array(), $return = false ) {
	$data    = vtk_calc_data();
	$default = isset( $args['base_default'] ) ? $args['base_default'] : '';

	// Подсветка на схеме работает только с inline-SVG; файл схемы отдаём через data-src
	// (script.js подменит <img> на inline при http/https, по file:// — graceful degradation).
	$scheme_src = VTK_URI . '/assets/scheme.svg';

	ob_start();
	?>
	<div class="calc" data-role="calculator">
		<div class="calc__options">
			<fieldset class="calc__group">
				<legend><?php esc_html_e( 'Выбор базы', 'virshketech' ); ?></legend>
				<?php foreach ( $data['bases'] as $b ) : ?>
					<label class="calc__opt">
						<input type="radio" name="calc-base" value="<?php echo esc_attr( $b['id'] ); ?>"
							<?php checked( $default, $b['id'] ); ?>>
						<span><?php echo esc_html( $b['label'] ); ?></span>
						<b><?php echo vtk_price_html( $b['price'] ); // phpcs:ignore -- форматируется целое ?></b>
					</label>
				<?php endforeach; ?>
			</fieldset>

			<fieldset class="calc__group">
				<legend><?php esc_html_e( 'Модули', 'virshketech' ); ?></legend>
				<?php if ( empty( $data['modules'] ) ) : ?>
					<p class="calc__empty"><?php esc_html_e( 'Модули ещё не добавлены в админке.', 'virshketech' ); ?></p>
				<?php endif; ?>
				<?php foreach ( $data['modules'] as $m ) : ?>
					<label class="calc__opt">
						<input type="checkbox" name="calc-mod" value="<?php echo esc_attr( $m['id'] ); ?>"
							data-price="<?php echo esc_attr( $m['price'] ); ?>"
							data-label="<?php echo esc_attr( $m['label'] ); ?>"
							data-svg="<?php echo esc_attr( $m['svgId'] ); ?>">
						<span><?php echo esc_html( $m['label'] ); ?></span>
						<b>+<?php echo vtk_price_html( $m['price'] ); // phpcs:ignore ?></b>
					</label>
				<?php endforeach; ?>
			</fieldset>
		</div>

		<div class="calc__side">
			<div class="scheme" data-role="scheme" data-src="<?php echo esc_url( $scheme_src ); ?>">
				<img src="<?php echo esc_url( $scheme_src ); ?>" alt="<?php esc_attr_e( 'Схема станка с ТФО-160', 'virshketech' ); ?>">
			</div>
			<div class="calc__summary">
				<h3><?php esc_html_e( 'Ваша комплектация', 'virshketech' ); ?></h3>
				<ul data-role="summary-lines" aria-live="polite"></ul>
				<p class="calc__total"><?php esc_html_e( 'Итого:', 'virshketech' ); ?>
					<strong data-role="summary-total">0 ₽</strong></p>
				<a class="btn btn--accent" href="#" data-role="order-btn"><?php echo esc_html( $data['buttonText'] ); ?></a>
			</div>
		</div>
	</div>
	<?php
	$html = ob_get_clean();

	if ( $return ) {
		return $html;
	}
	echo $html; // phpcs:ignore -- собранный HTML уже экранирован внутри
}

/**
 * Форма заказа. Поля и data-ролы соответствуют валидации в assets/script.js.
 * Скрытое поле config заполняется JS из localStorage / query-параметра.
 */
function vtk_render_order_form_html( $return = false ) {
	ob_start();
	?>
	<div class="order" data-role="order-form-wrap">
		<p class="order__config-summary" data-role="config-summary" hidden></p>
		<form class="order__form" data-role="order-form" novalidate>
			<?php wp_nonce_field( 'vtk_order', 'vtk_nonce' ); ?>
			<input type="hidden" name="config" data-role="config-field" value="">

			<p>
				<label for="vtk-name"><?php esc_html_e( 'Имя', 'virshketech' ); ?> <span class="req">*</span></label>
				<input type="text" id="vtk-name" name="name" required autocomplete="name">
				<span class="err" data-err="name"></span>
			</p>
			<p>
				<label for="vtk-company"><?php esc_html_e( 'Компания', 'virshketech' ); ?></label>
				<input type="text" id="vtk-company" name="company" autocomplete="organization">
			</p>
			<p>
				<label for="vtk-phone"><?php esc_html_e( 'Телефон', 'virshketech' ); ?> <span class="req">*</span></label>
				<input type="tel" id="vtk-phone" name="phone" required placeholder="+7 (___) ___-__-__" inputmode="tel">
				<span class="err" data-err="phone"></span>
			</p>
			<p>
				<label for="vtk-email"><?php esc_html_e( 'E-mail', 'virshketech' ); ?></label>
				<input type="email" id="vtk-email" name="email" autocomplete="email">
				<span class="err" data-err="email"></span>
			</p>
			<p>
				<label for="vtk-machine"><?php esc_html_e( 'Модель станка и стойка ЧПУ', 'virshketech' ); ?></label>
				<textarea id="vtk-machine" name="machine" rows="3"></textarea>
			</p>
			<p class="order__consent">
				<label>
					<input type="checkbox" name="consent" required>
					<?php esc_html_e( 'Согласен на обработку персональных данных', 'virshketech' ); ?> <span class="req">*</span>
				</label>
				<span class="err" data-err="consent"></span>
			</p>
			<button type="submit" class="btn btn--accent"><?php esc_html_e( 'Отправить заявку', 'virshketech' ); ?></button>
			<p class="order__status" data-role="form-status" role="status" aria-live="polite"></p>
		</form>
	</div>
	<?php
	$html = ob_get_clean();

	if ( $return ) {
		return $html;
	}
	echo $html; // phpcs:ignore
}

/**
 * Секция «На какой станок будем ставить?» — карточки активных audience_page.
 * Используется шаблоном главной (front-page.html) через блок virshketech/audience-grid.
 */
function vtk_audience_cards() {
	$q = new WP_Query( array(
		'post_type'      => 'audience_page',
		'post_status'    => 'publish',
		'posts_per_page' => 10,
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		'no_found_rows'  => true,
		'meta_query'     => array(
			array(
				'key'     => '_vtk_available',
				'value'   => true,
				'type'    => 'boolean',
				'compare' => '=',
			),
		),
	) );

	$out = array();
	foreach ( $q->posts as $p ) {
		$features = (array) get_post_meta( $p->ID, '_vtk_features', true );
		$items    = array();
		foreach ( $features as $f ) {
			if ( ! is_array( $f ) || empty( $f['text'] ) ) {
				continue;
			}
			$items[] = array(
				'text'      => (string) $f['text'],
				'available' => ! empty( $f['available'] ),
			);
		}
		$out[] = array(
			'id'       => $p->ID,
			'title'    => get_the_title( $p ),
			'desc'     => (string) get_post_meta( $p->ID, '_vtk_hero_subtitle', true ),
			'price'    => (int) get_post_meta( $p->ID, '_vtk_price', true ),
			'features' => $items,
			'url'      => get_permalink( $p ),
		);
	}
	return $out;
}
