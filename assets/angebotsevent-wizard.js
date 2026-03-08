/**
 * OKJA A-Event Wizard
 *
 * Guided creation flow for the angebotsevent post type.
 */
(function ($) {
    'use strict';

    var D = window.okjaAngebotsEventWizardData || {};
    if (!D) {
        return;
    }

    var texts = D.texts || {};
    var isNew = D.isNew === '1';
    var canPublish = D.canPublish === '1';
    var angebote = Array.isArray(D.angebote) ? D.angebote : [];
    var isGutenberg = !!(typeof wp !== 'undefined' && wp.data && wp.data.select && wp.data.select('core/editor'));

    var currentStep = 0;
    var steps = [
        { key: 'basics', label: 'Schritt 1 von 4', title: 'Grunddaten', subtitle: 'Titel, Kurzbeschreibung und zugehöriges Angebot', render: renderBasics },
        { key: 'schedule', label: 'Schritt 2 von 4', title: 'Termin', subtitle: 'Datum und Uhrzeiten festlegen', render: renderSchedule },
        { key: 'details', label: 'Schritt 3 von 4', title: 'Details', subtitle: 'Preis, Teilnehmerzahl und Anmeldung', render: renderDetails },
        { key: 'media', label: 'Schritt 4 von 4', title: 'Bild & Kontrolle', subtitle: 'Beitragsbild prüfen und alles zusammenfassen', render: renderMedia }
    ];

    var state = {};
    var overlay = null;
    var modal = null;
    var featuredImageId = 0;
    var featuredImageUrl = '';
    var hasComplexBlocks = false;

    $(function () {
        var launchDelay = isGutenberg ? 1400 : 0;
        setTimeout(injectLaunchButton, launchDelay);
        if (isNew) {
            setTimeout(function () {
                openWizard();
            }, launchDelay + 220);
        }
    });

    function injectLaunchButton() {
        if ($('.okja-wizard-launch').length) {
            return;
        }

        var $launch = $('<div class="okja-wizard-launch"><button type="button" class="okja-wizard-launch-btn"><span class="dashicons dashicons-calendar-alt"></span><span>' + esc(texts.openWizard || 'A-Event-Wizard öffnen') + '</span></button></div>');
        $('body').append($launch);
        $launch.find('button').on('click', openWizard);
    }

    function openWizard() {
        if (overlay) {
            overlay.addClass('is-visible');
            return;
        }

        readExistingValues();
        currentStep = 0;

        overlay = $('<div class="okja-wizard-overlay">');
        modal = $('<div class="okja-wizard-modal">');
        overlay.append(modal);
        $('body').append(overlay);

        renderStep();

        requestAnimationFrame(function () {
            overlay.addClass('is-visible');
        });

        $(document).on('keydown.okjaEventWizard', function (event) {
            if (event.key === 'Escape') {
                closeWizard();
            }
        });
    }

    function closeWizard() {
        if (!overlay) {
            return;
        }

        overlay.removeClass('is-visible');
        setTimeout(function () {
            if (overlay) {
                overlay.remove();
            }
            overlay = null;
            modal = null;
        }, 240);
        $(document).off('keydown.okjaEventWizard');
    }

    function readExistingValues() {
        hasComplexBlocks = detectComplexBlocks();
        state = {
            title: getTitle(),
            description: getEditorContent(),
            excerpt: getExcerpt(),
            angebotId: parseInt($('#jhh_event_angebot_id').val() || '0', 10) || 0,
            date: $('#jhh_event_date').val() || '',
            timeStart: $('#jhh_event_time_start').val() || '',
            timeEnd: $('#jhh_event_time_end').val() || '',
            price: $('#jhh_event_price').val() || '',
            maxParticipants: $('#jhh_event_max_participants').val() || '',
            soldOut: $('#jhh_event_sold_out').is(':checked'),
            ctaUrl: $('#jhh_event_cta_url').val() || '',
            ctaLabel: $('#jhh_event_cta_label').val() || ''
        };

        featuredImageId = 0;
        featuredImageUrl = '';

        if (isGutenberg) {
            featuredImageId = parseInt(wp.data.select('core/editor').getEditedPostAttribute('featured_media') || 0, 10) || 0;
            if (featuredImageId > 0) {
                var media = wp.data.select('core').getMedia(featuredImageId);
                if (media) {
                    featuredImageUrl = getMediaPreviewUrl(media);
                }
            }
        } else {
            featuredImageId = parseInt($('#_thumbnail_id').val() || '0', 10) || 0;
            featuredImageUrl = $('#set-post-thumbnail img').attr('src') || '';
        }
    }

    function renderStep() {
        var step = steps[currentStep];
        var html = '';

        html += '<div class="okja-wizard-header">';
        html += '<div class="okja-wizard-step-label">' + esc(step.label) + '</div>';
        html += '<div class="okja-wizard-title-row">';
        html += '<div><h2>' + esc(step.title) + '</h2><p>' + esc(step.subtitle) + '</p></div>';
        html += '<button type="button" class="okja-wizard-close" aria-label="Schließen">&times;</button>';
        html += '</div>';
        html += '<div class="okja-wizard-progress">';
        steps.forEach(function (_, idx) {
            var cls = 'okja-wizard-progress-item';
            if (idx < currentStep) {
                cls += ' is-complete';
            } else if (idx === currentStep) {
                cls += ' is-active';
            }
            html += '<div class="' + cls + '"></div>';
        });
        html += '</div></div>';
        html += '<div class="okja-wizard-body"></div>';
        html += '<div class="okja-wizard-footer">';
        html += '<div class="okja-wizard-footer-left"><button type="button" class="okja-wizard-btn okja-wizard-btn-ghost okja-wizard-close-editor">' + esc(texts.classicEditor || 'Editor direkt nutzen') + '</button></div>';
        html += '<div class="okja-wizard-footer-right">';

        if (currentStep > 0) {
            html += '<button type="button" class="okja-wizard-btn okja-wizard-btn-secondary okja-wizard-back">' + esc(texts.back || 'Zurück') + '</button>';
        }
        if (currentStep < steps.length - 1) {
            html += '<button type="button" class="okja-wizard-btn okja-wizard-btn-primary okja-wizard-next">' + esc(texts.next || 'Weiter') + '</button>';
        } else {
            html += '<button type="button" class="okja-wizard-btn okja-wizard-btn-secondary okja-wizard-save-draft">' + esc(texts.saveDraft || 'Entwurf speichern') + '</button>';
            html += '<button type="button" class="okja-wizard-btn okja-wizard-btn-primary okja-wizard-publish">' + esc(texts.publish || 'Veröffentlichen') + '</button>';
        }

        html += '</div></div>';

        modal.html(html);
        step.render(modal.find('.okja-wizard-body'));

        modal.find('.okja-wizard-close, .okja-wizard-close-editor').on('click', function () {
            collectCurrentStep();
            writeValuesToForm();
            closeWizard();
        });
        modal.find('.okja-wizard-back').on('click', goBack);
        modal.find('.okja-wizard-next').on('click', goNext);
        modal.find('.okja-wizard-save-draft').on('click', function () {
            savePost('draft');
        });
        modal.find('.okja-wizard-publish').on('click', function () {
            savePost('publish');
        });
    }

    function goBack() {
        collectCurrentStep();
        currentStep = Math.max(0, currentStep - 1);
        renderStep();
    }

    function goNext() {
        collectCurrentStep();
        if (!String(state.title || '').trim()) {
            modal.find('#okja_event_wiz_title').addClass('has-error').focus();
            return;
        }
        currentStep = Math.min(steps.length - 1, currentStep + 1);
        renderStep();
    }

    function savePost(mode) {
        collectCurrentStep();
        if (!String(state.title || '').trim()) {
            currentStep = 0;
            renderStep();
            modal.find('#okja_event_wiz_title').addClass('has-error').focus();
            return;
        }

        writeValuesToForm();

        if (isGutenberg) {
            if (mode === 'publish' && canPublish) {
                wp.data.dispatch('core/editor').editPost({ status: 'publish' });
            }
            wp.data.dispatch('core/editor').savePost();
            closeWizard();
            return;
        }

        closeWizard();
        if (mode === 'publish' && $('#publish').length) {
            $('#publish').trigger('click');
        } else if ($('#save-post').length) {
            $('#save-post').trigger('click');
        }
    }

    function renderBasics($body) {
        var html = '<div class="okja-wizard-grid">';
        html += renderIntroCard(
            'A-Event schrittweise anlegen',
            'Der Wizard bündelt die typischen Veranstaltungsdaten und schreibt sie anschließend in die bestehenden Event-Felder zurück.',
            [
                'Angebot zuordnen',
                'Datum und Uhrzeit',
                'Preis und Plätze',
                'CTA und Beitragsbild'
            ]
        );
        html += '<div class="okja-wizard-field">';
        html += '<label for="okja_event_wiz_title">Titel</label>';
        html += '<input type="text" id="okja_event_wiz_title" class="okja-wizard-input" value="' + escAttr(state.title) + '" placeholder="' + escAttr(texts.newEvent || 'Neues A-Event') + '">';
        html += '</div>';
        html += '<div class="okja-wizard-field">';
        html += '<label for="okja_event_wiz_angebot">Zugeordnetes Angebot</label>';
        html += '<select id="okja_event_wiz_angebot" class="okja-wizard-input">';
        html += '<option value="0">- Kein Angebot -</option>';
        angebote.forEach(function (angebot) {
            html += '<option value="' + angebot.id + '"' + (angebot.id === state.angebotId ? ' selected' : '') + '>' + esc(angebot.title) + '</option>';
        });
        html += '</select>';
        html += '</div>';
        html += '<div class="okja-wizard-field">';
        html += '<label for="okja_event_wiz_desc">Beschreibung</label>';
        html += '<textarea id="okja_event_wiz_desc" class="okja-wizard-textarea" placeholder="Kurze Beschreibung des Events">' + esc(state.description) + '</textarea>';
        html += '</div>';
        html += '<div class="okja-wizard-field">';
        html += '<label for="okja_event_wiz_excerpt">Kurztext / Auszug</label>';
        html += '<textarea id="okja_event_wiz_excerpt" class="okja-wizard-textarea" style="min-height:100px" placeholder="Kurzer Teaser für Übersichten und Vorschauen">' + esc(state.excerpt) + '</textarea>';
        html += '</div>';
        if (hasComplexBlocks) {
            html += '<div class="okja-wizard-notice">' + esc(texts.contentNotice || '') + '</div>';
        }
        html += '</div>';
        $body.html(html);
    }

    function renderSchedule($body) {
        var html = '<div class="okja-wizard-grid two-col">';
        html += '<div class="okja-wizard-field"><label for="okja_event_wiz_date">Datum</label><input type="date" id="okja_event_wiz_date" class="okja-wizard-input" value="' + escAttr(state.date) + '"></div>';
        html += '<div class="okja-wizard-field"><label for="okja_event_wiz_start">Startzeit</label><input type="time" id="okja_event_wiz_start" class="okja-wizard-input" value="' + escAttr(state.timeStart) + '"></div>';
        html += '<div class="okja-wizard-field"><label for="okja_event_wiz_end">Endzeit</label><input type="time" id="okja_event_wiz_end" class="okja-wizard-input" value="' + escAttr(state.timeEnd) + '"></div>';
        html += '<div class="okja-wizard-notice">Falls keine Endzeit bekannt ist, kann das Feld leer bleiben.</div>';
        html += '</div>';
        $body.html(html);
    }

    function renderDetails($body) {
        var html = '<div class="okja-wizard-grid two-col">';
        html += '<div class="okja-wizard-field"><label for="okja_event_wiz_price">Preis</label><input type="text" id="okja_event_wiz_price" class="okja-wizard-input" value="' + escAttr(state.price) + '" placeholder="z.B. kostenlos oder 5,00 €"></div>';
        html += '<div class="okja-wizard-field"><label for="okja_event_wiz_max">Max. Teilnehmer</label><input type="number" id="okja_event_wiz_max" class="okja-wizard-input" value="' + escAttr(state.maxParticipants) + '" min="0" placeholder="0 = unbegrenzt"></div>';
        html += '<label class="okja-wizard-style-item"><input type="checkbox" id="okja_event_wiz_soldout"' + (state.soldOut ? ' checked' : '') + '><span>' + esc(texts.soldOut || 'Ausgebucht') + '</span></label>';
        html += '<div class="okja-wizard-section-card okja-wizard-span-2">';
        html += '<h4>Call to Action / Anmeldung</h4>';
        html += '<p>Optional. Wenn du einen Link, eine E-Mail oder Telefonnummer angibst, kann auf der Event-Kachel ein Anmelde-Button angezeigt werden.</p>';
        html += '</div>';
        html += '<div class="okja-wizard-field"><label for="okja_event_wiz_cta_url">Link / E-Mail / Telefon</label><input type="text" id="okja_event_wiz_cta_url" class="okja-wizard-input" value="' + escAttr(state.ctaUrl) + '" placeholder="https://..., mailto:... oder tel:..."><div class="okja-wizard-help">Erlaubt sind normale URLs sowie mailto:- und tel:-Links.</div></div>';
        html += '<div class="okja-wizard-field"><label for="okja_event_wiz_cta_label">Button-Text</label><input type="text" id="okja_event_wiz_cta_label" class="okja-wizard-input" value="' + escAttr(state.ctaLabel) + '" placeholder="Jetzt anmelden"><div class="okja-wizard-help">Kann leer bleiben, wenn du den Text später im Editor setzen willst.</div></div>';
        html += '</div>';
        $body.html(html);
    }

    function renderMedia($body) {
        var html = '<div class="okja-wizard-grid two-col">';
        html += '<div class="okja-wizard-field">';
        html += '<label>Beitragsbild</label>';
        html += '<div class="okja-wizard-image-preview">' + imagePreviewHtml() + '</div>';
        html += '<div class="okja-wizard-image-actions">';
        html += '<button type="button" class="okja-wizard-btn okja-wizard-btn-primary" id="okja_event_wiz_pick_image">' + esc(featuredImageId ? (texts.imageReplace || 'Beitragsbild ersetzen') : (texts.imageButton || 'Beitragsbild wählen')) + '</button>';
        if (featuredImageId) {
            html += '<button type="button" class="okja-wizard-btn okja-wizard-btn-secondary" id="okja_event_wiz_remove_image">' + esc(texts.removeImage || 'Bild entfernen') + '</button>';
        }
        html += '</div></div>';
        html += '<div class="okja-wizard-summary-card">';
        html += '<h4>Zusammenfassung</h4>';
        html += '<div class="okja-wizard-summary-grid">';
        html += summaryItem('Titel', state.title || '—');
        html += summaryItem('Angebot', getAngebotLabel(state.angebotId) || 'Kein Angebot gewählt', !state.angebotId);
        html += summaryItem('Termin', formatSchedule(), formatSchedule() === '-');
        html += summaryItem('Preis', state.price || 'Keine Angabe', !state.price);
        html += summaryItem('Teilnehmer', formatParticipants());
        html += summaryItem('Status', state.soldOut ? (texts.soldOut || 'Ausgebucht') : 'Verfügbar');
        html += summaryItem('Bild', featuredImageId ? 'Beitragsbild gesetzt' : 'Noch kein Bild gewählt', !featuredImageId);
        html += '</div>';
        if (state.ctaUrl || state.ctaLabel) {
            html += '<div class="okja-wizard-summary-list"><p><strong>Call to Action:</strong></p><ul><li>' + esc((state.ctaLabel || 'CTA') + ': ' + (state.ctaUrl || '')) + '</li></ul></div>';
        }
        html += '</div>';
        html += '</div>';
        if (hasComplexBlocks) {
            html += '<div class="okja-wizard-notice">' + esc(texts.contentNotice || '') + '</div>';
        }
        $body.html(html);

        $body.on('click', '#okja_event_wiz_pick_image', function (event) {
            event.preventDefault();
            pickImage(function (attachment) {
                featuredImageId = attachment.id;
                featuredImageUrl = getMediaPreviewUrl(attachment);
                renderStep();
            });
        });
        $body.on('click', '#okja_event_wiz_remove_image', function (event) {
            event.preventDefault();
            featuredImageId = 0;
            featuredImageUrl = '';
            renderStep();
        });
    }

    function collectCurrentStep() {
        if (!modal) {
            return;
        }

        var $body = modal.find('.okja-wizard-body');
        switch (currentStep) {
            case 0:
                state.title = $body.find('#okja_event_wiz_title').val() || '';
                state.angebotId = parseInt($body.find('#okja_event_wiz_angebot').val() || '0', 10) || 0;
                state.description = $body.find('#okja_event_wiz_desc').val() || '';
                state.excerpt = $body.find('#okja_event_wiz_excerpt').val() || '';
                break;
            case 1:
                state.date = $body.find('#okja_event_wiz_date').val() || '';
                state.timeStart = $body.find('#okja_event_wiz_start').val() || '';
                state.timeEnd = $body.find('#okja_event_wiz_end').val() || '';
                break;
            case 2:
                state.price = $body.find('#okja_event_wiz_price').val() || '';
                state.maxParticipants = $body.find('#okja_event_wiz_max').val() || '';
                state.ctaUrl = $body.find('#okja_event_wiz_cta_url').val() || '';
                state.ctaLabel = $body.find('#okja_event_wiz_cta_label').val() || '';
                state.soldOut = $body.find('#okja_event_wiz_soldout').is(':checked');
                break;
        }
    }

    function writeValuesToForm() {
        syncPostFields();
        syncMetaFields();
        syncFeaturedImage();
    }

    function syncPostFields() {
        if (isGutenberg) {
            wp.data.dispatch('core/editor').editPost({
                title: state.title,
                excerpt: state.excerpt
            });
            syncDescriptionToBlocks();
            return;
        }

        $('#title, #post_title').val(state.title).trigger('change');
        $('#excerpt').val(state.excerpt);
        setClassicEditorContent(state.description);
    }

    function syncMetaFields() {
        $('#jhh_event_angebot_id').val(String(state.angebotId || 0));
        $('#jhh_event_date').val(state.date || '');
        $('#jhh_event_time_start').val(state.timeStart || '');
        $('#jhh_event_time_end').val(state.timeEnd || '');
        $('#jhh_event_price').val(state.price || '');
        $('#jhh_event_max_participants').val(state.maxParticipants || '');
        $('#jhh_event_cta_url').val(state.ctaUrl || '');
        $('#jhh_event_cta_label').val(state.ctaLabel || '');
        $('#jhh_event_sold_out').prop('checked', !!state.soldOut);
        $('input[name="jhh_event_sold_out"]').first().val(state.soldOut ? '1' : '0');
    }

    function syncFeaturedImage() {
        if (isGutenberg) {
            wp.data.dispatch('core/editor').editPost({ featured_media: featuredImageId > 0 ? featuredImageId : 0 });
            return;
        }
        if ($('#_thumbnail_id').length) {
            $('#_thumbnail_id').val(featuredImageId > 0 ? featuredImageId : '');
        }
    }

    function syncDescriptionToBlocks() {
        if (!isGutenberg || hasComplexBlocks) {
            return;
        }

        var paragraphs = String(state.description || '').split(/\n{2,}|\n/).map(function (part) {
            return part.trim();
        }).filter(Boolean);
        var blocks = paragraphs.map(function (part) {
            return wp.blocks.createBlock('core/paragraph', { content: part });
        });

        if (!blocks.length) {
            blocks = [wp.blocks.createBlock('core/paragraph', { content: '' })];
        }

        wp.data.dispatch('core/block-editor').resetBlocks(blocks);
    }

    function pickImage(callback) {
        var frame = wp.media({
            title: texts.imageButton || 'Beitragsbild wählen',
            button: { text: texts.imageButton || 'Beitragsbild wählen' },
            multiple: false
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            callback(attachment);
        });
        frame.open();
    }

    function imagePreviewHtml() {
        if (featuredImageUrl) {
            return '<img src="' + escAttr(featuredImageUrl) + '" alt="">';
        }
        return '<div class="okja-wizard-muted">' + esc(texts.noSelection || 'Noch nichts ausgewählt') + '</div>';
    }

    function getMediaPreviewUrl(media) {
        if (!media) {
            return '';
        }
        if (media.sizes) {
            if (media.sizes.large && media.sizes.large.url) {
                return media.sizes.large.url;
            }
            if (media.sizes.medium && media.sizes.medium.url) {
                return media.sizes.medium.url;
            }
            if (media.sizes.thumbnail && media.sizes.thumbnail.url) {
                return media.sizes.thumbnail.url;
            }
        }
        return media.url || media.source_url || '';
    }

    function getAngebotLabel(id) {
        var match = angebote.find(function (angebot) {
            return angebot.id === id;
        });
        return match ? match.title : '';
    }

    function formatSchedule() {
        if (!state.date && !state.timeStart && !state.timeEnd) {
            return '-';
        }
        var parts = [];
        if (state.date) {
            parts.push(state.date);
        }
        if (state.timeStart && state.timeEnd) {
            parts.push(state.timeStart + ' - ' + state.timeEnd);
        } else if (state.timeStart) {
            parts.push(state.timeStart);
        }
        return parts.join(' | ');
    }

    function formatParticipants() {
        if (!state.maxParticipants || String(state.maxParticipants) === '0') {
            return texts.unlimited || 'Unbegrenzt';
        }
        return state.maxParticipants + ' Plätze';
    }

    function summaryItem(label, value, muted) {
        return '<div class="okja-wizard-summary-item"><div class="okja-wizard-summary-label">' + esc(label) + '</div><div class="okja-wizard-summary-value' + (muted ? ' is-muted' : '') + '">' + esc(value || '—') + '</div></div>';
    }

    function renderIntroCard(title, text, pills) {
        var html = '<div class="okja-wizard-intro"><h3>' + esc(title) + '</h3><p>' + esc(text) + '</p>';
        if (Array.isArray(pills) && pills.length) {
            html += '<div class="okja-wizard-pill-row">';
            pills.forEach(function (pill) {
                html += '<span class="okja-wizard-pill">' + esc(pill) + '</span>';
            });
            html += '</div>';
        }
        html += '</div>';
        return html;
    }

    function getEditorContent() {
        if (!isGutenberg) {
            if (typeof tinyMCE !== 'undefined') {
                var editor = tinyMCE.get('content');
                if (editor && !editor.isHidden()) {
                    return editor.getContent({ format: 'text' }) || '';
                }
            }
            return $('#content').val() || '';
        }

        var editor = wp.data.select('core/editor');
        var rawContent = editor.getEditedPostAttribute('content') || '';
        return String(rawContent)
            .replace(/<!--\/?wp:[^>]*-->/g, '')
            .replace(/<br\s*\/?>/gi, '\n')
            .replace(/<\/p>/gi, '\n\n')
            .replace(/<[^>]+>/g, '')
            .replace(/&nbsp;/g, ' ')
            .trim();
    }

    function setClassicEditorContent(text) {
        if (typeof tinyMCE !== 'undefined') {
            var editor = tinyMCE.get('content');
            if (editor && !editor.isHidden()) {
                editor.setContent(text ? '<p>' + escHtml(text).replace(/\n+/g, '</p><p>') + '</p>' : '');
            }
        }
        $('#content').val(text || '');
    }

    function getTitle() {
        if (isGutenberg) {
            return String(wp.data.select('core/editor').getEditedPostAttribute('title') || '');
        }
        return $('#title, #post_title').first().val() || '';
    }

    function getExcerpt() {
        if (isGutenberg) {
            return String(wp.data.select('core/editor').getEditedPostAttribute('excerpt') || '');
        }
        return $('#excerpt').val() || '';
    }

    function detectComplexBlocks() {
        if (!isGutenberg || !wp.data.select('core/block-editor')) {
            return false;
        }

        var blocks = wp.data.select('core/block-editor').getBlocks() || [];
        return blocks.some(function (block) {
            return ['core/paragraph', 'core/freeform', 'core/classic'].indexOf(block.name) === -1;
        });
    }

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function escAttr(value) {
        return esc(value).replace(/\n/g, '&#10;');
    }

    function escHtml(value) {
        return esc(value).replace(/\n/g, '<br>');
    }
})(jQuery);