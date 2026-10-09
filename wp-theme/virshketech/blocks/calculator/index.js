/* global wp */
/**
 * Блок «Калькулятор комплектации» — edit-часть (save рендерит PHP по актуальным ценам).
 */
( function ( blocks, element, components, blockEditor ) {
	var el = element.createElement;
	var __ = wp.i18n.__;
	var useBlockProps = blockEditor.useBlockProps;

	function Edit( props ) {
		var a = props.attributes;
		var set = props.setAttributes;
		return el( 'div', useBlockProps(),
			el( blockEditor.InspectorControls, {},
				el( components.PanelBody, { title: __( 'Калькулятор', 'virshketech' ), initialOpen: true },
					el( components.SelectControl, {
						label: __( 'База по умолчанию', 'virshketech' ),
						value: a.base_default,
						options: [
							{ value: '',        label: __( '— не выбрана —', 'virshketech' ) },
							{ value: 'pro',     label: __( 'Профессиональные ЧПУ', 'virshketech' ) },
							{ value: 'lite',    label: __( 'ЧПУ начального уровня', 'virshketech' ) },
							{ value: 'manual',  label: __( 'Ручной станок', 'virshketech' ) },
						],
						onChange: function ( v ) { set( { base_default: v } ); },
					} ),
					el( 'p', { className: 'components-base-control__help' },
						__( 'Цены баз — в «Настройки › Калькулятор»; модули — в разделе «Модули». URL ?base=pro|lite|manual перекрывает выбор по умолчанию.', 'virshketech' ) )
				)
			),
			el( blockEditor.ServerSideRender, { block: 'virshketech/calculator', attributes: a } )
		);
	}

	blocks.registerBlockType( 'virshketech/calculator', { edit: Edit, save: false } );
}( window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor ) );
