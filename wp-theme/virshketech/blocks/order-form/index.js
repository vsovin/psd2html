/* global wp */
/**
 * Блок «Форма заказа» — edit-часть (save рендерит PHP).
 */
( function ( blocks, element, blockEditor ) {
	var el = element.createElement;
	var __ = wp.i18n.__;
	var useBlockProps = blockEditor.useBlockProps;

	function Edit() {
		return el( 'div', useBlockProps(),
			el( 'p', { className: 'muted' },
				__( 'Заявки сохраняются в разделе «Заявки» и отправляются письмом на e-mail администратора.', 'virshketech' ) ),
			el( blockEditor.ServerSideRender, { block: 'virshketech/order-form' } )
		);
	}

	blocks.registerBlockType( 'virshketech/order-form', { edit: Edit, save: false } );
}( window.wp.blocks, window.wp.element, window.wp.blockEditor ) );
