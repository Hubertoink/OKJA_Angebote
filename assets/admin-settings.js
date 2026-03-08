(function ($) {
    'use strict';

    var data = window.okjaAdminSettingsData || {};
    var grainyMap = data.grainyMap || {};
    var updateTimer = null;

    function setCssVar(element, name, value) {
        if (element && element.style) {
            element.style.setProperty(name, value);
        }
    }

    function scheduleUpdate() {
        if (updateTimer) {
            clearTimeout(updateTimer);
        }
        updateTimer = setTimeout(function () {
            updateStaffPreview();
            updateEventPreview();
        }, 40);
    }

    function initColorPickers() {
        if (!$.fn.wpColorPicker) {
            setTimeout(initColorPickers, 50);
            return;
        }

        $('.okja-color-picker').each(function () {
            var $input = $(this);
            if ($input.data('wpWpColorPicker')) {
                return;
            }
            $input.wpColorPicker({
                change: scheduleUpdate,
                clear: scheduleUpdate
            });
        });
    }

    function updateStaffPreview() {
        var preview = document.getElementById('okja-staff-preview');
        if (!preview) {
            return;
        }

        var bg = $('input[name="okja_staff_bg_color"]').val() || '#2b2727';
        var text = $('input[name="okja_staff_text_color"]').val() || '#ffffff';
        var accent = $('input[name="okja_staff_accent_color"]').val() || '#b9aaff';
        var style = $('input[name="okja_default_staff_style"]:checked').val() || 'simple';

        setCssVar(preview, '--okja-staff-bg', bg);
        setCssVar(preview, '--okja-staff-text', text);
        setCssVar(preview, '--okja-staff-accent', accent);
        setCssVar(preview, '--okja-staff-bg-image', grainyMap[style] ? 'url("' + grainyMap[style] + '")' : 'none');
        preview.dataset.style = style;
        preview.classList.toggle('is-grainy', !!grainyMap[style]);
    }

    function updateEventPreview() {
        var preview = document.getElementById('okja-event-card-preview');
        if (!preview) {
            return;
        }

        var bg = $('input[name="okja_event_card_bg"]').val() || '#1e1b1b';
        var text = $('input[name="okja_event_card_text"]').val() || '#ffffff';
        var accent = $('input[name="okja_event_card_accent"]').val() || '#b9aaff';
        var style = $('input[name="okja_event_card_style"]:checked').val() || 'simple';
        var topline = $('input[name="okja_event_card_topline"]:checked').length > 0 ? '1' : '0';
        var labelColor = 'rgba(255,255,255,0.55)';

        if (style === 'notebook') {
            labelColor = '#888888';
        } else if (style === 'custom') {
            labelColor = accent;
        }

        setCssVar(preview, '--okja-event-bg', bg);
        setCssVar(preview, '--okja-event-text', text);
        setCssVar(preview, '--okja-event-accent', accent);
        setCssVar(preview, '--okja-event-bg-image', grainyMap[style] ? 'url("' + grainyMap[style] + '")' : 'none');
        setCssVar(preview, '--okja-event-label', labelColor);
        preview.dataset.style = style;
        preview.dataset.topline = topline;
    }

    $(function () {
        initColorPickers();
        $(document).on('change', 'input[name="okja_default_staff_style"], input[name="okja_event_card_style"], input[name="okja_event_card_topline"]', scheduleUpdate);
        scheduleUpdate();
    });
})(jQuery);
