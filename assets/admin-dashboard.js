(function ($) {
    function setMediaPreview(container, image) {
        var preview = container.find('[data-okja-media-preview]');
        var input = container.find('[data-okja-media-input]');

        if (!image) {
            input.val('');
            preview.text('Noch kein Bild gewählt');
            return;
        }

        input.val(image.id || '');
        preview.html('<img src="' + image.url + '" alt="">');
    }

    function openModal(modalRoot) {
        modalRoot.removeAttr('hidden');
        $('body').addClass('modal-open');
        $('#okja-dashboard').addClass('okja-dashboard-modal-open');
    }

    function closeModal(modalRoot) {
        modalRoot.attr('hidden', 'hidden');
        $('body').removeClass('modal-open');
        $('#okja-dashboard').removeClass('okja-dashboard-modal-open');
    }

    $(function () {
        $('.okja-dashboard-color-picker').wpColorPicker();

        $('[data-okja-modal-open]').on('click', function () {
            var target = $(this).data('okjaModalOpen');
            var modalRoot = $('[data-okja-modal="' + target + '"]');

            if (modalRoot.length) {
                openModal(modalRoot);
            }
        });

        $('[data-okja-modal-close]').on('click', function () {
            closeModal($(this).closest('[data-okja-modal]'));
        });

        $(document).on('keydown', function (event) {
            if (event.key === 'Escape') {
                $('[data-okja-modal]').each(function () {
                    var modal = $(this);
                    if (!modal.is('[hidden]')) {
                        closeModal(modal);
                    }
                });
            }
        });

        $('[data-okja-media-button]').on('click', function () {
            var container = $(this).closest('.okja-dashboard-media-picker');
            var frame = wp.media({
                title: okjaDashboardData && okjaDashboardData.texts ? okjaDashboardData.texts.chooseImage : 'Profilbild auswählen',
                button: {
                    text: okjaDashboardData && okjaDashboardData.texts ? okjaDashboardData.texts.useImage : 'Verwenden'
                },
                multiple: false
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first();
                if (!attachment) {
                    return;
                }

                setMediaPreview(container, attachment.toJSON());
            });

            frame.open();
        });

        $('[data-okja-media-reset]').on('click', function () {
            setMediaPreview($(this).closest('.okja-dashboard-media-picker'), null);
        });
    });
})(jQuery);
