/* global wp */
/**
 * Блок «Развилка аудиторий» — edit-часть (save рендерит PHP по CPT audience_page).
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
				el( components.PanelBody, { title: __( 'Заголовок секции', 'virshketech' ), initialOpen: true },
					el( components.TextControl, { label: __( 'Заголовок', 'virshketech' ), value: a.heading,
						onChange: function ( v ) { set( { heading: v } ); } } ),
					el( components.Disabled, { label: __( 'Источник карточек', 'virshketech' ) },
						el( 'p', { style: { margin: '4px 0 0', fontSize: '12px' } },
							__( 'Карточки берутся из раздела «Аудитории»: заголовок, подзаголовок, функции, цена, ссылка. Отметка «Показывать на главной» управляет выводом.', 'virshketech' ) )
						)
				)
			),
			el( 'h2', null, a.heading ),
			el( 'p', { className: 'muted' },
				__( 'Предпросмотр: карточки активных страниц аудиторий (обновляется при сохранении записей).', 'virshketech' ) ),
			el( blockEditor.ServerSideRender, { block: 'virshketech/audience-grid', attributes: a } )
		);
	}

	blocks.registerBlockType( 'virshketech/audience-grid', { edit: Edit, save: false } );
}( window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor ) );
