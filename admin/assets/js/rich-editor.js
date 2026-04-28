/**
 * Inicializador del editor WYSIWYG (TinyMCE) para Ekanet admin.
 * Se aplica automáticamente a cualquier <textarea class="ek-rich">.
 *
 * Para usar el editor en una textarea, basta con añadir la clase ek-rich:
 *   <textarea name="description" class="ek-rich">…</textarea>
 *
 * Para una versión "compacta" (toolbar reducida), usa class="ek-rich ek-rich--compact".
 */
(function () {
    if (typeof tinymce === 'undefined') {
        console.warn('[Ekanet] TinyMCE no cargado.');
        return;
    }

    var fullToolbar = [
        'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor',
        'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent',
        'link image table | removeformat | code preview fullscreen'
    ].join(' | ');

    var compactToolbar = 'undo redo | bold italic | bullist numlist | link removeformat';

    var common = {
        promotion: false,
        branding: false,
        menubar: false,
        statusbar: false,
        skin: 'oxide',
        content_css: 'default',
        height: 360,
        plugins: 'lists link image table code preview fullscreen autolink',
        relative_urls: false,
        convert_urls: false,
        block_formats: 'Párrafo=p; Título 2=h2; Título 3=h3; Título 4=h4; Cita=blockquote; Código=pre',
        // Permitir clases en cualquier elemento (útil para clases utility del frontend futuro)
        valid_elements: '*[*]',
        extended_valid_elements: 'span[class|style],div[class|style|id]',
    };

    tinymce.init(Object.assign({}, common, {
        selector: 'textarea.ek-rich:not(.ek-rich--compact)',
        toolbar: fullToolbar,
    }));

    tinymce.init(Object.assign({}, common, {
        selector: 'textarea.ek-rich.ek-rich--compact',
        toolbar: compactToolbar,
        height: 200,
    }));
})();
