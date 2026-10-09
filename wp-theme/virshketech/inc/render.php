<?php
/**
 * PHP-рендеринг динамических блоков (схема, калькулятор, форма заказа).
 * Разметка повторяет HTML-прототип (index.html) — классы и data-селекторы
 * соответствуют assets/style.css и assets/script.js.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SVG-схема станка (блок virshketech/scheme).
 * variant: hero — <img>; calc — контейнер с data-src для inline-подсветки.
 */
function vtk_render_scheme_html( $args = array(), $return = false ) {
	$variant   = isset( $args['variant'] ) ? $args['variant'] : 'hero';
	$label     = isset( $args['aria_label'] ) && $args['aria_label'] !== ''
		? $args['aria_label']
		: __( 'Схема токарного станка с установленной осью ТФО-160', 'virshketech' );
	$scheme_src = VTK_URI . '/assets/scheme.svg';

	ob_start();
	if ( $variant === 'calc' ) {
		?>
		<figure class="scheme-box">
			<div data-role="scheme" data-src="<?php echo esc_url( $scheme_src ); ?>" role="img"
				aria-label="<?php echo esc_attr( $label ); // phpcs:ignore -- перевод ?>">
				<img src="<?php echo esc_url( $scheme_src ); ?>" alt="<?php echo esc_attr( $label ); ?>">
			</div>
			<figcaption><?php esc_html_e( 'Выбранные модули подсвечиваются оранжевым прямо на схеме.', 'virshketech' ); ?></figcaption>
		</figure>
		<?php
	} else {
		?>
		<figure class="hero-scheme">
			<img src="<?php echo esc_url( $scheme_src ); ?>" alt="<?php echo esc_attr( $label ); ?>">
		</figure>
		<?php
	}
	$html = ob_get_clean();

	if ( $return ) {
		return $html;
	}
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- собран выше
}

/**
 * Калькулятор комплектации (блок virshketech/calculator).
 * Базы — из «Настройки › Калькулятор», модули — из CPT calc_module.
 */
function vtk_render_calculator_html( $args = array(), $return = false ) {
	$data    = vtk_calc_data();
	$default = isset( $args['base_default'] ) ? $args['base_default'] : '';
	$scheme_src = VTK_URI . '/assets/scheme.svg';

	// Уникальные id заголовков групп — на случай двух калькуляторов на странице.
	$uid     = wp_unique_id( 'vtk-calc-' );
	$base_h  = $uid . '-base';
	$mod_h   = $uid . '-mod';

	ob_start();
	?>
	<div class="calc-grid" data-calculator data-default-base="<?php echo esc_attr( $default ); ?>">
		<div class="calc-panel">
			<h3 id="<?php echo esc_attr( $base_h ); ?>"><?php esc_html_e( 'База (выберите одну)', 'virshketech' ); ?></h3>
			<fieldset class="opt-group" aria-labelledby="<?php echo esc_attr( $base_h ); ?>">
				<legend class="vis-hidden"><?php esc_html_e( 'Базовое решение ТФО-160', 'virshketech' ); ?></legend>
				<?php foreach ( $data['bases'] as $b ) : ?>
					<label class="opt"><input type="radio" name="calc-base" value="<?php echo esc_attr( $b['id'] ); ?>">
						<span class="opt-name"><?php echo esc_html( $b['label'] ); ?></span><span class="opt-price"><?php echo vtk_price_html( $b['price'] ); // phpcs:ignore -- форматируется целое ?></span></label>
				<?php endforeach; ?>
			</fieldset>

			<h3 class="calc-subhead" id="<?php echo esc_attr( $mod_h ); ?>"><?php esc_html_e( 'Модули (можно несколько)', 'virshketech' ); ?></h3>
			<fieldset class="opt-group" aria-labelledby="<?php echo esc_attr( $mod_h ); ?>">
				<legend class="vis-hidden"><?php esc_html_e( 'Дополнительные модули', 'virshketech' ); ?></legend>
				<?php if ( empty( $data['modules'] ) ) : ?>
					<p class="calc-empty"><?php esc_html_e( 'Модули ещё не добавлены в админке.', 'virshketech' ); ?></p>
				<?php endif; ?>
				<?php foreach ( $data['modules'] as $m ) : ?>
					<label class="opt"><input type="checkbox" name="calc-mod" value="<?php echo esc_attr( $m['id'] ); ?>"
							data-price="<?php echo esc_attr( $m['price'] ); ?>"
							data-label="<?php echo esc_attr( $m['label'] ); ?>"
							data-svg="<?php echo esc_attr( $m['svgId'] ); ?>">
						<span class="opt-name"><?php echo esc_html( $m['label'] ); ?></span><span class="opt-price">+<?php echo vtk_price_html( $m['price'] ); // phpcs:ignore ?></span></label>
				<?php endforeach; ?>
			</fieldset>
		</div>

		<div class="calc-side">
			<div class="calc-panel calc-summary">
				<h3><?php esc_html_e( 'Ваша комплектация', 'virshketech' ); ?></h3>
				<div data-role="summary-lines"><p class="calc-empty"><?php esc_html_e( 'Выберите базу — итог появится здесь.', 'virshketech' ); ?></p></div>
				<div class="sum-total"><span><?php esc_html_e( 'Итого:', 'virshketech' ); ?></span><span data-role="summary-total">0 &#8381;</span></div>
				<p style="margin-top:18px">
					<a class="btn btn-primary btn-block" href="<?php echo esc_url( $data['orderUrl'] ); ?>" data-role="order-btn" data-href="<?php echo esc_url( $data['orderUrl'] ); ?>"><?php echo esc_html( $data['buttonText'] ); ?></a>
				</p>
			</div>
			<figure class="scheme-box">
				<div data-role="scheme" data-src="<?php echo esc_url( $scheme_src ); ?>" role="img"
					aria-label="<?php esc_attr_e( 'Схема станка: выбранные модули подсвечиваются оранжевым', 'virshketech' ); ?>">
					<img src="<?php echo esc_url( $scheme_src ); ?>" alt="<?php esc_attr_e( 'Схема токарного станка с осью ТФО-160 и модулями', 'virshketech' ); ?>">
				</div>
				<figcaption><?php esc_html_e( 'Выбранные модули подсвечиваются оранжевым прямо на схеме.', 'virshketech' ); ?></figcaption>
			</figure>
		</div>
	</div>
	<?php
	$html = ob_get_clean();

	if ( $return ) {
		return $html;
	}
	echo $html; // phpcs:ignore
}

/**
 * Форма заказа (блок virshketech/order-form).
 * Поля и data-ролы соответствуют валидации в assets/script.js.
 */
function vtk_render_order_form_html( $return = false ) {
	$calc_path   = vtk_page_url( 'calculator', home_url( '/#calculator' ) );
	$thanks_path = vtk_page_url( 'thanks', home_url( '/thanks/' ) );

	ob_start();
	?>
	<div class="form-card" data-order-root data-calc-path="<?php echo esc_url( $calc_path ); ?>" data-thanks-path="<?php echo esc_url( $thanks_path ); ?>">
		<form data-order-form novalidate>
			<?php wp_nonce_field( 'vtk_order', 'vtk_nonce' ); ?>
			<div class="config-strip" data-role="config-strip"></div>
			<div class="field">
				<label for="vtk-name"><?php esc_html_e( 'Имя', 'virshketech' ); ?> <span class="req">*</span></label>
				<input id="vtk-name" type="text" name="name" required autocomplete="name" placeholder="<?php esc_attr_e( 'Иван Петров', 'virshketech' ); ?>">
				<p class="err-msg" role="alert"><?php esc_html_e( 'Укажите имя.', 'virshketech' ); ?></p>
			</div>
			<div class="field">
				<label for="vtk-company"><?php esc_html_e( 'Компания', 'virshketech' ); ?></label>
				<input id="vtk-company" type="text" name="company" autocomplete="organization" placeholder="<?php esc_attr_e( 'ООО «Станкоремонт»', 'virshketech' ); ?>">
				<p class="err-msg" role="alert"></p>
			</div>
			<div class="field">
				<label for="vtk-phone"><?php esc_html_e( 'Телефон', 'virshketech' ); ?> <span class="req">*</span></label>
				<input id="vtk-phone" type="tel" name="phone" required inputmode="tel" autocomplete="tel" placeholder="+7 (___) ___-__-__">
				<p class="err-msg" role="alert"><?php esc_html_e( 'Введите телефон полностью: +7 (XXX) XXX-XX-XX.', 'virshketech' ); ?></p>
			</div>
			<div class="field">
				<label for="vtk-email"><?php esc_html_e( 'E-mail', 'virshketech' ); ?></label>
				<input id="vtk-email" type="email" name="email" autocomplete="email" placeholder="you@company.ru">
				<p class="err-msg" role="alert"><?php esc_html_e( 'Проверьте формат e-mail.', 'virshketech' ); ?></p>
			</div>
			<div class="field">
				<label for="vtk-machine"><?php esc_html_e( 'Модель станка и стойка ЧПУ', 'virshketech' ); ?></label>
				<textarea id="vtk-machine" name="machine" placeholder="<?php esc_attr_e( 'Например: DMG CTX 310, стойка Fanuc 0i-TF', 'virshketech' ); ?>"></textarea>
				<p class="err-msg" role="alert"></p>
			</div>
			<input type="hidden" name="config" value="">
			<div class="field">
				<label class="consent"><input type="checkbox" name="consent" required>
					<?php esc_html_e( 'Согласен(на) на обработку персональных данных', 'virshketech' ); ?> <span class="req">*</span></label>
				<p class="err-msg" role="alert"><?php esc_html_e( 'Без согласия мы не сможем обработать заявку.', 'virshketech' ); ?></p>
			</div>
			<button class="btn btn-primary btn-block" type="submit"><?php esc_html_e( 'Отправить заявку', 'virshketech' ); ?></button>
			<p class="form-note"><?php esc_html_e( 'Ответим в рабочее время в течение 2 часов.', 'virshketech' ); ?></p>
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
 * Карточки аудиторий для главной (используется блоком virshketech/audience-grid).
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

/**
 * Посадочная страница аудитории (блок virshketech/audience-hero):
 * hero + секция «Что доступно» из мета записи audience_page.
 */
function vtk_render_audience_hero_html( $post_id = 0, $return = false ) {
$post_id = $post_id ? $post_id : get_the_ID();
if ( ! $post_id || 'audience_page' !== get_post_type( $post_id ) ) {
return $return ? '' : null;
}

$hero     = get_post_meta( $post_id, '_vtk_hero_title', true );
$sub      = get_post_meta( $post_id, '_vtk_hero_subtitle', true );
$price    = (int) get_post_meta( $post_id, '_vtk_price', true );
$features = (array) get_post_meta( $post_id, '_vtk_features', true );
$method   = (string) get_post_meta( $post_id, '_vtk_connection_method', true );
$calc_url = add_query_arg( 'base', get_post_meta( $post_id, '_vtk_slug', true ), vtk_page_url( 'calculator', home_url( '/calculator/' ) ) );
$order_url = vtk_page_url( 'order', home_url( '/order/' ) );
$scheme_src = VTK_URI . '/assets/scheme.svg';

ob_start();
?>
<section class="hero">
<div class="container hero-grid">
<div class="hero-text">
<h1><?php echo esc_html( $hero ? $hero : get_the_title( $post_id ) ); ?></h1>
<?php if ( $sub ) : ?>
<p class="sub"><?php echo esc_html( $sub ); ?></p>
<?php endif; ?>
<div class="cta-row">
<a class="btn btn-primary" href="<?php echo esc_url( $calc_url ); ?>"><?php esc_html_e( 'Рассчитать комплектацию', 'virshketech' ); ?></a>
<a class="btn btn-outline" href="<?php echo esc_url( $order_url ); ?>"><?php esc_html_e( 'Оставить заявку', 'virshketech' ); ?></a>
</div>
<?php if ( $price ) : ?>
<div class="price-badge"><?php esc_html_e( 'Стоимость решения:', 'virshketech' ); ?> ~<?php echo esc_html( number_format_i18n( $price, 0 ) . ' ₽' ); ?></div>
<?php endif; ?>
</div>
<figure class="hero-scheme">
<img src="<?php echo esc_url( $scheme_src ); ?>" alt="<?php echo esc_attr( sprintf( /* translators: %s: название станка */ __( 'Схема ТФО-160: %s', 'virshketech' ), get_the_title( $post_id ) ) ); ?>">
</figure>
</div>
</section>

<?php if ( $features || $method ) : ?>
<section class="section">
<div class="container">
<h2 class="section-title"><?php esc_html_e( 'Что доступно с ТФО-160', 'virshketech' ); ?></h2>
<div class="info-grid">
<?php if ( $features ) : ?>
<div class="info-block">
<h3><?php esc_html_e( 'Функции оси', 'virshketech' ); ?></h3>
<ul class="feat-list">
<?php foreach ( $features as $f ) :
if ( ! is_array( $f ) || empty( $f['text'] ) ) { continue; }
$cls = empty( $f['available'] ) ? ' class="no"' : '';
?>
<li<?php echo $cls; // phpcs:ignore -- статичный класс ?>><?php echo esc_html( $f['text'] ); ?></li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>
<?php if ( $method ) : ?>
<div class="info-block">
<h3><?php esc_html_e( 'Метод подключения', 'virshketech' ); ?></h3>
<?php echo wp_kses_post( wpautop( $method ) ); ?>
</div>
<?php endif; ?>
</div>
</div>
</section>
<?php endif; ?>
<?php
$html = ob_get_clean();
if ( $return ) {
return $html;
}
echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- собран выше
}

/** Герой страницы аудитории (блок virshketech/audience-hero). */
function vtk_block_audience_hero_render( $attributes, $content ) {
	if ( ! is_singular( 'audience_page' ) ) {
		return '';
	}
	return vtk_render_audience_hero_html( get_queried_object_id(), true );
}
