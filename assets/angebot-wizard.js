/**
 * OKJA Angebots-Wizard
 *
 * Guided creation flow for the angebot post type.
 */
(function ($) {
    'use strict';

    var D = window.okjaAngebotWizardData || {};
    if (!D || !D.taxJugend || !D.taxPaed) {
        return;
    }

    var texts = D.texts || {};
    var isNew = D.isNew === '1';
    var canPublish = D.canPublish === '1';
    var taxJugend = D.taxJugend;
    var taxPaed = D.taxPaed;
    var jugendTerms = Array.isArray(D.jugend) ? D.jugend : [];
    var paedTerms = Array.isArray(D.paed) ? D.paed : [];
    var dayLabels = D.dayLabels || {};
    var staffStyles = Array.isArray(D.staffStyles) ? D.staffStyles : [];
    var isGutenberg = !!(typeof wp !== 'undefined' && wp.data && wp.data.select && wp.data.select('core/editor'));

    var currentStep = 0;
    var steps = [
        { key: 'basics', label: 'Schritt 1 von 4', title: 'Grundlagen', subtitle: 'Titel, Beschreibung und Kurztext', render: renderBasics },
        { key: 'taxonomies', label: 'Schritt 2 von 4', title: 'Kategorien & Team', subtitle: 'Jugendarbeit und Pädagogik zuordnen', render: renderTaxonomies },
        { key: 'schedule', label: 'Schritt 3 von 4', title: 'Wochentage & Zeiten', subtitle: 'Öffnungstage und Uhrzeiten festlegen', render: renderSchedule },
        { key: 'media', label: 'Schritt 4 von 4', title: 'Bild & Darstellung', subtitle: 'Beitragsbild und Kartenstil prüfen', render: renderMedia }
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

        var $launch = $('<div class="okja-wizard-launch"><button type="button" class="okja-wizard-launch-btn"><span class="dashicons dashicons-welcome-learn-more"></span><span>' + esc(texts.openWizard || 'Angebots-Wizard öffnen') + '</span></button></div>');
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

        $(document).on('keydown.okjaWizard', function (event) {
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
        $(document).off('keydown.okjaWizard');
    }

    function readExistingValues() {
        hasComplexBlocks = detectComplexBlocks();
        state = {
            title: getTitle(),
            description: getEditorContent(),
            excerpt: getExcerpt(),
            jugend: getTaxIds(taxJugend),
            paed: getTaxIds(taxPaed),
            days: getSelectedDays(),
            dayTimes: getDayTimes(),
            staffStyle: getStaffStyle()
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
            modal.find('#okja_wiz_title').addClass('has-error').focus();
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
            modal.find('#okja_wiz_title').addClass('has-error').focus();
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
            'Angebot schrittweise anlegen',
            'Der Wizard deckt die typischen Redaktionsschritte ab und schreibt am Ende in die normalen WordPress-Felder zurück.',
            [
                'Titel und Teaser',
                'Jugendarbeit und Pädagogik',
                'Wochentage und Zeiten',
                'Bild und Kartenstil'
            ]
        );

        html += '<div class="okja-wizard-field">';
        html += '<label for="okja_wiz_title">Titel</label>';
        html += '<input type="text" id="okja_wiz_title" class="okja-wizard-input" value="' + escAttr(state.title) + '" placeholder="' + escAttr(texts.newOffer || 'Neues Angebot') + '">';
        html += '</div>';

        html += '<div class="okja-wizard-field">';
        html += '<label for="okja_wiz_desc">Beschreibung</label>';
        html += '<textarea id="okja_wiz_desc" class="okja-wizard-textarea" placeholder="Beschreibe kurz, worum es im Angebot geht.">' + esc(state.description) + '</textarea>';
        html += '<div class="okja-wizard-help">Wird in den Inhalt des Angebots geschrieben, solange der Beitrag noch keine komplexen Gutenberg-Blöcke enthält.</div>';
        html += '</div>';

        html += '<div class="okja-wizard-field">';
        html += '<label for="okja_wiz_excerpt">Kurztext / Auszug</label>';
        html += '<textarea id="okja_wiz_excerpt" class="okja-wizard-textarea" style="min-height:100px" placeholder="Kurzer Teaser für Übersichten und Vorschauen">' + esc(state.excerpt) + '</textarea>';
        html += '</div>';

        if (hasComplexBlocks) {
            html += '<div class="okja-wizard-notice">' + esc(texts.contentNotice || '') + '</div>';
        }

        html += '</div>';
        $body.html(html);
    }

    function renderTaxonomies($body) {
        var html = '<div class="okja-wizard-grid two-col">';
        html += '<div class="okja-wizard-field"><div class="okja-wizard-group-title">Jugendarbeit</div>';
        html += '<div class="okja-wizard-help">Diese Auswahl steuert auch die zugehörigen Teamkarten in der Einzelansicht.</div>';
        html += '<div class="okja-wizard-check-grid okja-wizard-check-grid-jugend">' + renderTermOptions(jugendTerms, state.jugend, 'jugend') + '</div></div>';
        html += '<div class="okja-wizard-field"><div class="okja-wizard-group-title">Pädagogik</div>';
        html += '<div class="okja-wizard-check-grid okja-wizard-check-grid-paed">' + renderTermOptions(paedTerms, state.paed, 'paed') + '</div></div>';
        html += '</div>';
        $body.html(html);
    }

    function renderSchedule($body) {
        var html = '<div class="okja-wizard-grid">';
        Object.keys(dayLabels).forEach(function (dayKey) {
            var selected = state.days.indexOf(dayKey) !== -1;
            var times = state.dayTimes[dayKey] || {};
            html += '<div class="okja-wizard-day-row' + (selected ? ' is-selected' : '') + '" data-day-row="' + escAttr(dayKey) + '">';
            html += '<div class="okja-wizard-day-main"><label><input type="checkbox" class="okja-wiz-day-check" data-day="' + escAttr(dayKey) + '"' + (selected ? ' checked' : '') + '> ' + esc(dayLabels[dayKey]) + '</label></div>';
            html += '<div class="okja-wizard-day-times' + (selected ? '' : ' is-hidden') + '" data-day-times="' + escAttr(dayKey) + '">';
            html += '<div class="okja-wizard-field"><label>Von</label><input type="time" class="okja-wizard-input okja-wiz-day-start" data-day="' + escAttr(dayKey) + '" value="' + escAttr(times.start || '') + '"></div>';
            html += '<div class="okja-wizard-field"><label>Bis</label><input type="time" class="okja-wizard-input okja-wiz-day-end" data-day="' + escAttr(dayKey) + '" value="' + escAttr(times.end || '') + '"></div>';
            html += '</div></div>';
        });
        html += '</div>';
        $body.html(html);

        $body.on('change', '.okja-wiz-day-check', function () {
            var dayKey = $(this).data('day');
            var isSelected = $(this).is(':checked');
            var $row = $body.find('[data-day-row="' + dayKey + '"]');
            var $times = $body.find('[data-day-times="' + dayKey + '"]');

            $row.toggleClass('is-selected', isSelected);
            $times.toggleClass('is-hidden', !isSelected);
        });
    }

    function renderMedia($body) {
        var html = '<div class="okja-wizard-grid two-col">';
        html += '<div class="okja-wizard-field">';
        html += '<label>Beitragsbild</label>';
        html += '<div class="okja-wizard-image-preview" id="okja_wiz_image_preview">' + imagePreviewHtml() + '</div>';
        html += '<div class="okja-wizard-image-actions">';
        html += '<button type="button" class="okja-wizard-btn okja-wizard-btn-primary" id="okja_wiz_pick_image">' + esc(featuredImageId ? (texts.imageReplace || 'Beitragsbild ersetzen') : (texts.imageButton || 'Beitragsbild wählen')) + '</button>';
        if (featuredImageId) {
            html += '<button type="button" class="okja-wizard-btn okja-wizard-btn-secondary" id="okja_wiz_remove_image">' + esc(texts.removeImage || 'Bild entfernen') + '</button>';
        }
        html += '</div></div>';
        html += '<div class="okja-wizard-field"><div class="okja-wizard-group-title">Mitarbeiter-Kartenstil</div>';
        html += '<div class="okja-wizard-grid">';
        staffStyles.forEach(function (item) {
            var checked = item.value === state.staffStyle;
            html += '<label class="okja-wizard-style-item"><input type="radio" name="okja_wiz_staff_style" value="' + escAttr(item.value) + '"' + (checked ? ' checked' : '') + '><span>' + esc(item.label) + '</span></label>';
        });
        html += '</div>';
        html += '<div class="okja-wizard-help">Gilt nur für dieses Angebot. "Global" übernimmt die zentrale Plugin-Einstellung.</div>';
        html += '</div></div>';

        if (hasComplexBlocks) {
            html += '<div class="okja-wizard-notice">' + esc(texts.contentNotice || '') + '</div>';
        }

        html += '<div class="okja-wizard-summary-card"><h4>Zusammenfassung</h4>';
        html += '<div class="okja-wizard-summary-grid">';
        html += summaryItem('Titel', state.title || '—');
        html += summaryItem('Kurztext', truncate(state.excerpt || state.description || 'Noch kein Text hinterlegt.', 120));
        html += summaryItem('Bild', featuredImageId ? 'Beitragsbild gesetzt' : 'Noch kein Bild gewählt', !featuredImageId);
        html += summaryItem('Staff-Stil', getStaffStyleLabel(state.staffStyle || 'global'));
        html += '</div>';
        html += summaryList('Jugendarbeit', termNames(jugendTerms, state.jugend));
        html += summaryList('Pädagogik', termNames(paedTerms, state.paed));
        html += summaryList('Wochentage', scheduleNames());
        html += '</div>';

        $body.html(html);

        $body.on('click', '#okja_wiz_pick_image', function (event) {
            event.preventDefault();
            pickImage(function (attachment) {
                featuredImageId = attachment.id;
                featuredImageUrl = getMediaPreviewUrl(attachment);
                renderStep();
            });
        });

        $body.on('click', '#okja_wiz_remove_image', function (event) {
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
                state.title = $body.find('#okja_wiz_title').val() || '';
                state.description = $body.find('#okja_wiz_desc').val() || '';
                state.excerpt = $body.find('#okja_wiz_excerpt').val() || '';
                break;
            case 1:
                state.jugend = [];
                state.paed = [];
                $body.find('[data-tax-group="jugend"]:checked').each(function () {
                    state.jugend.push(parseInt($(this).val(), 10));
                });
                $body.find('[data-tax-group="paed"]:checked').each(function () {
                    state.paed.push(parseInt($(this).val(), 10));
                });
                break;
            case 2:
                state.days = [];
                state.dayTimes = {};
                Object.keys(dayLabels).forEach(function (dayKey) {
                    var enabled = $body.find('.okja-wiz-day-check[data-day="' + dayKey + '"]').is(':checked');
                    if (enabled) {
                        state.days.push(dayKey);
                    }
                    state.dayTimes[dayKey] = {
                        start: $body.find('.okja-wiz-day-start[data-day="' + dayKey + '"]').val() || '',
                        end: $body.find('.okja-wiz-day-end[data-day="' + dayKey + '"]').val() || ''
                    };
                });
                break;
            case 3:
                state.staffStyle = $body.find('input[name="okja_wiz_staff_style"]:checked').val() || 'global';
                break;
        }
    }

    function writeValuesToForm() {
        syncPostFields();
        syncTaxonomies();
        syncMetaboxFields();
        syncFeaturedImage();
    }

    function syncPostFields() {
        if (isGutenberg) {
            var update = {
                title: state.title,
                excerpt: state.excerpt
            };
            update[taxJugend] = state.jugend;
            update[taxPaed] = state.paed;
            wp.data.dispatch('core/editor').editPost(update);
            syncDescriptionToBlocks();
            return;
        }

        $('#title, #post_title').val(state.title).trigger('change');
        $('#excerpt').val(state.excerpt);
        setClassicEditorContent(state.description);
        setTaxCheckboxes(taxJugend + 'checklist', state.jugend);
        setTaxCheckboxes(taxPaed + 'checklist', state.paed);
    }

    function syncTaxonomies() {
        if (isGutenberg) {
            return;
        }

        setTaxCheckboxes(taxJugend + 'checklist', state.jugend);
        setTaxCheckboxes(taxPaed + 'checklist', state.paed);
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

    function syncMetaboxFields() {
        Object.keys(dayLabels).forEach(function (dayKey) {
            var checked = state.days.indexOf(dayKey) !== -1;
            var times = state.dayTimes[dayKey] || {};
            $('input[name="jhh_days[]"][value="' + dayKey + '"]').prop('checked', checked);
            $('input[name="jhh_day_times[' + dayKey + '][start]"]').val(times.start || '');
            $('input[name="jhh_day_times[' + dayKey + '][end]"]').val(times.end || '');
        });

        $('input[name="jhh_staff_card_style"][value="' + state.staffStyle + '"]').prop('checked', true);
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

    function renderTermOptions(list, selectedIds, group) {
        if (!list.length) {
            return '<div class="okja-wizard-muted">' + esc(texts.noSelection || 'Noch nichts ausgewählt') + '</div>';
        }

        var html = '';
        list.forEach(function (item) {
            var checked = selectedIds.indexOf(item.id) !== -1;
            html += '<label class="okja-wizard-check-item okja-wizard-check-item-' + escAttr(group) + '">';
            html += '<span class="okja-wizard-check-control"><input type="checkbox" data-tax-group="' + escAttr(group) + '" value="' + item.id + '"' + (checked ? ' checked' : '') + '></span>';
            html += '<span class="okja-wizard-check-content">';
            html += group === 'jugend' ? renderJugendTermCard(item) : renderPaedTermCard(item);
            html += '</span>';
            html += '</label>';
        });
        return html;
    }

    function renderJugendTermCard(item) {
        var html = '<span class="okja-wizard-term-card okja-wizard-term-card-jugend">';
        html += '<span class="okja-wizard-term-avatar">';
        if (item.avatarUrl) {
            html += '<img src="' + escAttr(item.avatarUrl) + '" alt="">';
        } else {
            html += '<span class="okja-wizard-term-avatar-fallback">' + esc(getInitials(item.name)) + '</span>';
        }
        html += '</span>';
        html += '<span class="okja-wizard-term-copy"><strong>' + esc(item.name) + '</strong>';
        if (item.role) {
            html += '<span class="okja-wizard-term-meta">' + esc(item.role) + '</span>';
        }
        html += '</span></span>';
        return html;
    }

    function renderPaedTermCard(item) {
        var styleAttr = '';
        if (item.bgColor || item.textColor) {
            styleAttr = ' style="';
            if (item.bgColor) {
                styleAttr += 'background:' + escAttr(item.bgColor) + ';';
            }
            if (item.textColor) {
                styleAttr += 'color:' + escAttr(item.textColor) + ';';
            }
            styleAttr += '"';
        }

        return '<span class="okja-wizard-term-card okja-wizard-term-card-paed"><span class="okja-wizard-term-swatch"' + styleAttr + '></span><span class="okja-wizard-term-copy"><strong>' + esc(item.name) + '</strong></span></span>';
    }

    function getInitials(name) {
        var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) {
            return '?';
        }
        return parts.slice(0, 2).map(function (part) {
            return part.charAt(0).toUpperCase();
        }).join('');
    }

    function summaryList(title, values) {
        var items = Array.isArray(values) ? values : [];
        var html = '<div class="okja-wizard-summary-list"><p><strong>' + esc(title) + ':</strong></p>';
        if (!items.length) {
            html += '<p class="okja-wizard-muted">-</p></div>';
            return html;
        }
        html += '<ul>';
        items.forEach(function (item) {
            html += '<li>' + esc(item) + '</li>';
        });
        html += '</ul></div>';
        return html;
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

    function getStaffStyleLabel(value) {
        var match = staffStyles.find(function (item) {
            return item.value === value;
        });
        return match ? match.label : value;
    }

    function scheduleNames() {
        return state.days.map(function (dayKey) {
            var label = dayLabels[dayKey] || dayKey;
            var times = state.dayTimes[dayKey] || {};
            if (times.start && times.end) {
                return label + ' ' + times.start + ' - ' + times.end;
            }
            return label;
        });
    }

    function imagePreviewHtml() {
        if (featuredImageUrl) {
            return '<img src="' + escAttr(featuredImageUrl) + '" alt="">';
        }
        return '<div class="okja-wizard-muted">' + esc(texts.noSelection || 'Noch nichts ausgewählt') + '</div>';
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

    function getTaxIds(taxonomy) {
        if (isGutenberg) {
            var ids = wp.data.select('core/editor').getEditedPostAttribute(taxonomy);
            if (Array.isArray(ids)) {
                return ids.map(function (value) {
                    return parseInt(value, 10);
                }).filter(Boolean);
            }
        }
        return getCheckedTaxIds(taxonomy + 'checklist');
    }

    function getCheckedTaxIds(checklistId) {
        return $('#' + checklistId + ' input[type="checkbox"]:checked').map(function () {
            return parseInt(this.value, 10);
        }).get().filter(Boolean);
    }

    function setTaxCheckboxes(checklistId, ids) {
        var lookup = {};
        (ids || []).forEach(function (id) {
            lookup[id] = true;
        });

        $('#' + checklistId + ' input[type="checkbox"]').each(function () {
            $(this).prop('checked', !!lookup[parseInt(this.value, 10)]);
        });
    }

    function getSelectedDays() {
        return $('input[name="jhh_days[]"]:checked').map(function () {
            return $(this).val();
        }).get();
    }

    function getDayTimes() {
        var out = {};
        Object.keys(dayLabels).forEach(function (dayKey) {
            out[dayKey] = {
                start: $('input[name="jhh_day_times[' + dayKey + '][start]"]').val() || '',
                end: $('input[name="jhh_day_times[' + dayKey + '][end]"]').val() || ''
            };
        });
        return out;
    }

    function getStaffStyle() {
        return $('input[name="jhh_staff_card_style"]:checked').val() || 'global';
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

    function termNames(list, ids) {
        var names = [];
        (ids || []).forEach(function (id) {
            var match = list.find(function (item) {
                return item.id === id;
            });
            if (match) {
                names.push(match.name);
            }
        });
        return names;
    }

    function truncate(text, maxLength) {
        var value = String(text || '');
        if (value.length <= maxLength) {
            return value;
        }
        return value.slice(0, maxLength - 3) + '...';
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