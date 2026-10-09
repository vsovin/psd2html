/* global wp */
/**
 * Блок «Характеристика ТФО» — строка таблицы из двух колонок (параметр / значение).
 * Инлайновые RichText прямо в ячейках + быстрые поля в InspectorControls.
 */
( function ( blocks, element, components, blockEditor ) {
	var el = element.createElement;
	var __ = wp.i18n.__;
	var useBlockProps = blockEditor.useBlockProps;
	var RichText = blockEditor.RichText;

	function rowMarkup( a, editable ) {
		function cell( key, tag, cls ) {
			if ( editable ) {
				return el( tag, { className: cls },
					el( RichText, {
						tagName: 'span',
						value: a[ key ],
						onChange: function ( v ) { /* set через props.setAttributes в edit */ },
					} )
				);
			}
			return el( tag, { className: cls }, el( 'span', null, a[ key ] ) );
		}
		return el( 'tr', {}, cell( 'label', 'th', 'spec-row__label' ), cell( 'value', 'td', 'spec-row__value' ) );
	}

	function Edit( props ) {
		var a = props.attributes;
		var set = props.setAttributes;
		return el( 'div', useBlockProps(),
			el( InspectorControlsBridge, { a: a, set: set } ),
			el( 'table', { className: 'spec-table' },
				el( 'tbody', {},
					el( 'tr', {},
						el( 'th', { className: 'spec-row__label' },
							el( RichText, { tagName: 'span', value: a.label, placeholder: __( 'Параметр', 'virshketech' ),
								onChange: function ( v ) { set( { label: v } ); } } ) ),
						el( 'td', { className: 'spec-row__value' },
							el( RichText, { tagName: 'span', value: a.value, placeholder: __( 'Значение', 'virshketech' ),
								onChange: function ( v ) { set( { value: v } ); } } ) )
					)
				)
			)
		);
	}

	function InspectorControlsBridge( props ) {
		return el( blockEditor.InspectorControls, {},
			el( components.PanelBody, { title: __( 'Характеристика', 'virshketech' ), initialOpen: true },
				el( components.TextControl, { label: __( 'Параметр', 'virshketech' ), value: props.a.label,
					onChange: function ( v ) { props.set( { label: v } ); } } ),
				el( components.TextControl, { label: __( 'Значение', 'virshketech' ), value: props.a.value,
					onChange: function ( v ) { props.set( { value: v } ); } } )
			)
		);
	}

	blocks.registerBlockType( 'virshketech/spec-row', {
		edit: Edit,
		save: function () { return null; }, // HTML отдаёт PHP (vtk_block_spec_row_render)
	} );
}( window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor ) );
