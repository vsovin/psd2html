/* global wp */
/**
 * Блок «Герой страницы аудитории» — превью в редакторе, рендерит PHP.
 */
( function ( blocks, element, blockEditor ) {
var el = element.createElement;
var __ = wp.i18n.__;
var useBlockProps = blockEditor.useBlockProps;

function Edit() {
return el( 'div', useBlockProps(),
el( 'p', { style: { color: '#666' } },
__( 'Hero и «Что доступно» формируются из полей этой записи аудитории (метабокс «Параметры страницы аудитории»).', 'virshketech' )
)
);
}

blocks.registerBlockType( 'virshketech/audience-hero', {
edit: Edit,
save: function () { return null; }
} );
} )( wp.blocks, wp.element, wp.blockEditor );
