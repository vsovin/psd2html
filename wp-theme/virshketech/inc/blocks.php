<?php
/**
 * Регистрация кастомных Gutenberg-блоков темы.
 *
 * Статические блоки (audience-card, spec-row) — block.json + index.js (ES5, без сборки).
 * Динамические (scheme, audience-grid, calculator, order-form) — render_callback:
 * актуальные цены и модули всегда соответствуют админке, а не сохранённому HTML.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'block_categories_all', 'vtk_block_category', 10, 1 );
function vtk_block_category( $categories ) {
	array_unshift( $categories, array(
		'slug'  => 'virshketech',
		'title' => __( 'VirshkeTech', 'virshketech' ),
		'icon'  => 'admin-site-alt3',
	) );
	return $categories;
}

add_action( 'init', 'vtk_register_blocks' );
function vtk_register_blocks() {
	if ( ! function_exists( 'register_block_type_from_metadata' ) ) {
		return; // Требуется WP 6.5+ (apiVersion 3, renderCallback в block.json).
	}

	$dir = VTK_DIR . '/blocks';

	// spec-row: save возвращает null → строку таблицы отдаёт PHP.
	register_block_type_from_metadata( $dir . '/spec-row', array(
		'render_callback' => 'vtk_block_spec_row_render',
	) );

	// audience-hero: рендерится PHP по текущему post (single-audience_page.html).
	register_block_type_from_metadata( $dir . '/audience-hero', array(
		'render_callback' => 'vtk_block_audience_hero_render',
	) );

	foreach ( array( 'audience-card', 'scheme', 'audience-grid', 'calculator', 'order-form' ) as $slug ) {
		register_block_type_from_metadata( $dir . '/' . $slug );
	}
}

/** Строка таблицы характеристик (блок virshketech/spec-row). */
function vtk_block_spec_row_render( $attributes ) {
	$label = isset( $attributes['label'] ) ? wp_kses_post( $attributes['label'] ) : '';
	$value = isset( $attributes['value'] ) ? wp_kses_post( $attributes['value'] ) : '';

	return '<tr class="spec-row"><th scope="row">' . $label . '</th><td>' . $value . '</td></tr>';
}

/** SVG-схема станка (блок virshketech/scheme). variant: hero | calc. */
function vtk_block_scheme_render( $attributes ) {
	$variant = isset( $attributes['variant'] ) && in_array( $attributes['variant'], array( 'hero', 'calc' ), true )
		? $attributes['variant']
		: 'hero';
	$args = array( 'variant' => $variant );
	if ( ! empty( $attributes['caption'] ) ) {
		$args['aria_label'] = sanitize_text_field( $attributes['caption'] );
	}
	return vtk_render_scheme_html( $args, true );
}

/** Сетка карточек аудиторий (блок virshketech/audience-grid) по CPT audience_page. */
function vtk_block_audience_grid_render( $attributes ) {
	$heading = isset( $attributes['heading'] ) ? sanitize_text_field( $attributes['heading'] ) : '';
	$lead    = isset( $attributes['lead'] ) ? sanitize_textarea_field( $attributes['lead'] ) : '';
	$cards   = vtk_audience_cards();

	ob_start();
	?>
	<div class="container">
		<?php if ( $heading ) : ?>
			<h2 class="section-title"><?php echo esc_html( $heading ); ?></h2>
		<?php endif; ?>
		<?php if ( $lead ) : ?>
			<p class="section-lead"><?php echo esc_html( $lead ); ?></p>
		<?php endif; ?>
		<?php if ( empty( $cards ) ) : ?>
			<p class="section-lead"><?php esc_html_e( 'Страницы аудиторий ещё не созданы (раздел «Аудитории» в админке).', 'virshketech' ); ?></p>
		<?php else : ?>
			<div class="cards-3">
				<?php foreach ( $cards as $i => $c ) : ?>
					<article class="aud-card<?php echo $i === 1 ? ' featured' : ''; ?>">
						<h3><?php echo esc_html( $c['title'] ); ?></h3>
						<?php if ( $c['desc'] ) : ?><p class="brands"><?php echo esc_html( $c['desc'] ); ?></p><?php endif; ?>
						<ul class="feat-list">
							<?php foreach ( $c['features'] as $f ) : ?>
								<li><?php echo esc_html( $f['text'] ); ?></li>
							<?php endforeach; ?>
						</ul>
						<p class="price-line"><?php echo vtk_price_html( $c['price'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- форматируется целое ?><small><?php esc_html_e( 'Стоимость решения', 'virshketech' ); ?></small></p>
						<a class="card-link" href="<?php echo esc_url( $c['url'] ); ?>"><?php esc_html_e( 'Перейти', 'virshketech' ); ?></a>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/** Калькулятор (блок virshketech/calculator). */
function vtk_render_calculator_block( $attributes ) {
	$base = isset( $attributes['base_default'] ) ? sanitize_key( $attributes['base_default'] ) : '';
	if ( ! in_array( $base, array( 'pro', 'lite', 'manual' ), true ) ) {
		$base = '';
	}
	// Query-параметр ?base=... (переходы с посадочных страниц) важнее атрибута блока.
	$get_base = isset( $_GET['base'] ) ? sanitize_key( wp_unslash( $_GET['base'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- только предзаполнение, данных не меняет
	if ( in_array( $get_base, array( 'pro', 'lite', 'manual' ), true ) ) {
		$base = $get_base;
	}
	return vtk_render_calculator_html( array( 'base_default' => $base ), true );
}

/** Форма заказа (блок virshketech/order-form). */
function vtk_render_order_form_block() {
	return vtk_render_order_form_html( true );
}
