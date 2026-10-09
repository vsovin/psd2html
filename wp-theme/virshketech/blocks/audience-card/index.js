/* global wp */
/**
 * Блок «Карточка аудитории». ES5 + createElement — без JSX и сборщика.
 * Настройки (заголовок, описание, цена, список функций, ссылка) — в InspectorControls.
 */
( function ( blocks, element, components, blockEditor ) {
	var el = element.createElement;
	var __ = wp.i18n.__;
	var useBlockProps = blockEditor.useBlockProps;

	function cardMarkup( a ) {
		return el( 'article', { className: 'card card--branch' },
			el( 'h3', null, a.title ),
			a.desc ? el( 'p', { className: 'muted' }, a.desc ) : null,
			el( 'ul', { className: 'featlist' },
				( a.features || [] ).map( function ( f, i ) {
					return el( 'li', { key: i, className: f.available ? 'yes' : 'no' }, f.text );
				} )
			),
			el( 'p', { className: 'card--branch__price' },
				new Intl.NumberFormat( 'ru-RU' ).format( a.price || 0 ) + ' ₽' ),
			el( 'a', { className: 'btn btn--accent', href: a.url }, a.linkText )
		);
	}

	function Edit( props ) {
		var a = props.attributes;
		var set = props.setAttributes;

		function setFeature( i, key, val ) {
			var f = ( a.features || [] ).slice();
			var o = Object.assign( {}, f[ i ] );
			o[ key ] = val;
			f[ i ] = o;
			set( { features: f } );
		}

		return el( 'div', useBlockProps(),
			el( blockEditor.InspectorControls, {},
				el( components.PanelBody, { title: __( 'Настройки карточки', 'virshketech' ), initialOpen: true },
					el( components.TextControl, { label: __( 'Заголовок', 'virshketech' ), value: a.title,
						onChange: function ( v ) { set( { title: v } ); } } ),
					el( components.TextControl, { label: __( 'Описание', 'virshketech' ), value: a.desc,
						onChange: function ( v ) { set( { desc: v } ); } } ),
					el( components.TextControl, { type: 'number', label: __( 'Цена решения, ₽', 'virshketech' ), value: a.price,
						onChange: function ( v ) { set( { price: parseInt( v || '0', 10 ) || 0 } ); } } ),
					el( components.TextControl, { label: __( 'Ссылка', 'virshketech' ), value: a.url,
						onChange: function ( v ) { set( { url: v } ); } } ),
					el( components.TextControl, { label: __( 'Текст кнопки', 'virshketech' ), value: a.linkText,
						onChange: function ( v ) { set( { linkText: v } ); } } )
				),
				el( components.PanelBody, { title: __( 'Функции', 'virshketech' ), initialOpen: true },
					( a.features || [] ).map( function ( f, i ) {
						return el( 'div', { key: i, style: { marginBottom: '12px' } },
							el( components.TextControl, { label: __( 'Текст функции', 'virshketech' ), value: f.text,
								onChange: function ( v ) { setFeature( i, 'text', v ); } } ),
							el( components.CheckboxControl, { label: __( 'Доступна', 'virshketech' ), checked: !! f.available,
								onChange: function ( v ) { setFeature( i, 'available', v ); } } ),
							el( components.Button, { isDestructive: true, variant: 'link',
								onClick: function () {
									var f2 = ( a.features || [] ).slice();
									f2.splice( i, 1 );
									set( { features: f2 } );
								} }, __( 'Удалить', 'virshketech' ) )
						);
					} ),
					el( components.Button, { variant: 'secondary',
						onClick: function () {
							set( { features: ( a.features || [] ).concat( [ { text: '', available: true } ] ) } );
						} }, __( 'Добавить функцию', 'virshketech' ) )
				)
			),
			cardMarkup( a )
		);
	}

	blocks.registerBlockType( 'virshketech/audience-card', {
		edit: Edit,
		save: function ( props ) { return cardMarkup( props.attributes ); },
	} );
}( window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor ) );
