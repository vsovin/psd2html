/* global wp */
/**
 * Блок «Схема станка с модулями» — edit-часть (save рендерит PHP: renderCallback).
 * Картинка + подсказка о подсветке; в инспекторе — URL SVG и подпись.
 */
( function ( blocks, element, components, blockEditor ) {
	var el = element.createElement;
	var __ = wp.i18n.__;
	var useBlockProps = blockEditor.useBlockProps;

	function Edit( props ) {
		var a = props.attributes;
		var set = props.setAttributes;
		var src = a.src || ( window.VirshkeTechAssets ? VirshkeTechAssets.scheme : '' );

		return el( 'div', useBlockProps(),
			el( blockEditor.InspectorControls, {},
				el( components.PanelBody, { title: __( 'Схема станка', 'virshketech' ), initialOpen: true },
					el( components.TextControl, { label: __( 'URL SVG-схемы', 'virshketech' ), value: a.src,
						placeholder: __( 'Пусто — схема темы (assets/scheme.svg)', 'virshketech' ),
						onChange: function ( v ) { set( { src: v } ); } } ),
					el( components.TextControl, { label: __( 'Подпись', 'virshketech' ), value: a.caption,
						onChange: function ( v ) { set( { caption: v } ); } } ),
					el( components.Disabled, { label: __( 'Подсветка модулей', 'virshketech' ) },
						el( 'p', { style: { margin: '4px 0 0', fontSize: '12px' } },
							__( 'На фронтенде выбранные в калькуляторе модули подсвечиваются оранжевым автоматически.', 'virshketech' ) )
						)
				)
			),
			el( 'figure', { className: 'scheme scheme--editor' },
				el( 'img', { src: src, alt: __( 'Схема токарного станка с осью ТФО-160 и модулями', 'virshketech' ) } ),
				a.caption ? el( 'figcaption', null, a.caption ) : null
			)
		);
	}

	blocks.registerBlockType( 'virshketech/scheme', { edit: Edit, save: false } );
}( window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor ) );
