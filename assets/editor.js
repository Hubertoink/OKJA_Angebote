(function (wp) {
  const { registerBlockType } = wp.blocks;
  const {
    PanelBody, ToggleControl, SelectControl, RangeControl,
    CheckboxControl, Button, TextControl
  } = wp.components;
  const { InspectorControls, PanelColorSettings } = wp.blockEditor || wp.editor;
  const el = wp.element.createElement;
  const ServerSideRender = wp.serverSideRender;

  const postTypes = (window.JHH_POSTS_BLOCK_DATA && window.JHH_POSTS_BLOCK_DATA.postTypes) || [];
  const taxData   = (window.JHH_POSTS_BLOCK_DATA && window.JHH_POSTS_BLOCK_DATA.taxonomies) || {};
  const jugend    = taxData.jugendarbeit || [];
  const paed      = taxData.paedagogik || [];
  const tage      = taxData.tage || [];

  const ELEMENT_LABELS = {
    image: 'Beitragsbild',
    title: 'Titel',
    meta: 'Meta (Datum/Autor)',
    taxonomies: 'Taxonomien',
    excerpt: 'Auszug',
    readmore: 'Weiterlesen-Link',
    gradientline: 'Gradientenlinie',
  };

  function moveInArray(arr, from, to) {
    const copy = arr.slice();
    const item = copy.splice(from, 1)[0];
    copy.splice(to, 0, item);
    return copy;
  }

  registerBlockType("jhh/posts", {
    title: "OKJA Angebote",
    icon: "screenoptions",
    category: "widgets",
    attributes: {
      postType: { type: 'string', default: 'angebot' },
      postsToShow: { type: 'number', default: 6 },
      order: { type: 'string', default: 'DESC' },
      orderBy: { type: 'string', default: 'date' },
      layout: { type: 'string', default: 'grid' },
      columns: { type: 'number', default: 3 },
      gap: { type: 'number', default: 16 },
  // Carousel options (frontend only when layout=carousel)
  carouselAutoplay: { type: 'boolean', default: true },
  carouselInterval: { type: 'number', default: 7 },
  carouselPauseOnHover: { type: 'boolean', default: true },
  carouselIndicators: { type: 'boolean', default: true },
  carouselArrows: { type: 'boolean', default: true },
  backUrl: { type: 'string', default: '' },
      showImage: { type: 'boolean', default: true },
      imageSize: { type: 'string', default: 'medium' },
      imageHoverEffect: { type: 'string', default: 'none' }, // none, tilt, zoom, glow
      showTitle: { type: 'boolean', default: true },
      showDate: { type: 'boolean', default: true },
      showAuthor: { type: 'boolean', default: false },
      showExcerpt: { type: 'boolean', default: true },
      excerptLength: { type: 'number', default: 20 },
      showReadMore: { type: 'boolean', default: true },
      showGradientLine: { type: 'boolean', default: false },
      gradientMarginTop: { type: 'number', default: 16 },
      gradientMarginBottom: { type: 'number', default: 16 },
      termsJugend: { type: 'array', default: [] },
      termsPaed: { type: 'array', default: [] },
      termsTage: { type: 'array', default: [] },
      showTaxonomies: { type: 'boolean', default: false },
      showTaxJugend: { type: 'boolean', default: true },
      showTaxPaed: { type: 'boolean', default: true },
      showTaxTage: { type: 'boolean', default: true },
      elementsOrder: { type: 'array', default: ['image', 'title', 'meta', 'taxonomies', 'excerpt', 'readmore', 'gradientline'] }
    },
    edit: (props) => {
      const { attributes: a, setAttributes } = props;

      const updateChecked = (list, id, checked) => {
        const set = new Set(list || []);
        if (checked) set.add(id); else set.delete(id);
        return Array.from(set);
      };

      const reorderUI = el(
        'div',
        { className: 'jhh-reorder' },
        (a.elementsOrder || []).map((key, idx) =>
          el(
            'div',
            { key: key + '-' + idx, className: 'jhh-reorder-row' },
            el('span', { className: 'jhh-reorder-label' }, ELEMENT_LABELS[key] || key),
            el('div', { className: 'jhh-reorder-actions' },
              el(Button, {
                icon: 'arrow-up',
                label: 'Nach oben',
                isSmall: true,
                disabled: idx === 0,
                onClick: () => setAttributes({ elementsOrder: moveInArray(a.elementsOrder, idx, idx - 1) })
              }),
              el(Button, {
                icon: 'arrow-down',
                label: 'Nach unten',
                isSmall: true,
                disabled: idx === a.elementsOrder.length - 1,
                onClick: () => setAttributes({ elementsOrder: moveInArray(a.elementsOrder, idx, idx + 1) })
              })
            )
          )
        )
      );

      return [
        el(
          InspectorControls,
          { key: "inspector" },

          // Abfrage-Panel
          el(
            PanelBody,
            { title: "Abfrage", initialOpen: true },
            el(SelectControl, {
              label: "Post-Typ",
              value: a.postType,
              options: postTypes.map((pt) => ({ label: pt.label, value: pt.slug })),
              onChange: (val) => setAttributes({ postType: val }),
            }),
            el(RangeControl, {
              label: "Anzahl Beiträge",
              value: a.postsToShow,
              onChange: (val) => setAttributes({ postsToShow: val }),
              min: 1, max: 24,
            }),
            el(SelectControl, {
              label: "Sortieren nach",
              value: a.orderBy,
              options: [
                { label: "Datum", value: "date" },
                { label: "Titel", value: "title" },
                { label: "Menüreihenfolge", value: "menu_order" },
                { label: "Zufällig", value: "rand" },
              ],
              onChange: (val) => setAttributes({ orderBy: val }),
            }),
            el(SelectControl, {
              label: "Reihenfolge",
              value: a.order,
              options: [
                { label: "Absteigend", value: "DESC" },
                { label: "Aufsteigend", value: "ASC" },
              ],
              onChange: (val) => setAttributes({ order: val }),
            })
          ),

          // Taxonomie-Panel
          el(
            PanelBody,
            { title: "Taxonomien (Filter)", initialOpen: false },
            el("div", null, el("strong", null, "Jugendarbeit")),
            jugend.length
              ? jugend.map((t) =>
                  el(CheckboxControl, {
                    key: "jug-" + t.id,
                    label: t.name,
                    checked: (a.termsJugend || []).includes(t.id),
                    onChange: (checked) =>
                      setAttributes({ termsJugend: updateChecked(a.termsJugend, t.id, checked) }),
                  })
                )
              : el("em", null, "Keine Begriffe gefunden."),
            el("div", { style: { marginTop: "12px" } }, el("strong", null, "Pädagogik")),
            paed.length
              ? paed.map((t) =>
                  el(CheckboxControl, {
                    key: "paed-" + t.id,
                    label: t.name,
                    checked: (a.termsPaed || []).includes(t.id),
                    onChange: (checked) =>
                      setAttributes({ termsPaed: updateChecked(a.termsPaed, t.id, checked) }),
                  })
                )
			  : el("em", null, "Keine Begriffe gefunden."),
            el("div", { style: { marginTop: "12px" } }, el("strong", null, "Tage")),
            tage.length
              ? tage.map((t) =>
                  el(CheckboxControl, {
                    key: "tage-" + t.id,
                    label: t.name,
                    checked: (a.termsTage || []).includes(t.id),
                    onChange: (checked) =>
                      setAttributes({ termsTage: updateChecked(a.termsTage, t.id, checked) }),
                  })
                )
              : el("em", null, "Keine Begriffe gefunden.")
          ),

          // Elemente & Reihenfolge
          el(
            PanelBody,
            { title: "Elemente & Reihenfolge", initialOpen: true },
            el(ToggleControl, {
              label: "Beitragsbild",
              checked: !!a.showImage,
              onChange: (val) => setAttributes({ showImage: val }),
            }),
            el(SelectControl, {
              label: "Bildgröße",
              value: a.imageSize,
              options: [
                { label: "Thumbnail", value: "thumbnail" },
                { label: "Medium", value: "medium" },
                { label: "Large", value: "large" },
                { label: "Full", value: "full" },
              ],
              onChange: (val) => setAttributes({ imageSize: val }),
            }),
            el(SelectControl, {
              label: "Bild Hover-Effekt",
              value: a.imageHoverEffect || 'none',
              options: [
                { label: "Keiner", value: "none" },
                { label: "Kippen (Tilt)", value: "tilt" },
                { label: "Vergrößern (Zoom)", value: "zoom" },
                { label: "Leuchten (Glow)", value: "glow" },
                { label: "Kippen + Zoom", value: "tilt-zoom" },
              ],
              onChange: (val) => setAttributes({ imageHoverEffect: val }),
            }),
            el(ToggleControl, {
              label: "Titel",
              checked: !!a.showTitle,
              onChange: (val) => setAttributes({ showTitle: val }),
            }),
            el(ToggleControl, {
              label: "Datum anzeigen",
              checked: !!a.showDate,
              onChange: (val) => setAttributes({ showDate: val }),
            }),
            el(ToggleControl, {
              label: "Autor anzeigen",
              checked: !!a.showAuthor,
              onChange: (val) => setAttributes({ showAuthor: val }),
            }),
            el(ToggleControl, {
              label: "Taxonomien (Badges)",
              checked: !!a.showTaxonomies,
              onChange: (val) => setAttributes({ showTaxonomies: val }),
            }),
            !!a.showTaxonomies && el(ToggleControl, {
              label: "— Jugendarbeit zeigen",
              checked: !!a.showTaxJugend,
              onChange: (val) => setAttributes({ showTaxJugend: val }),
            }),
            !!a.showTaxonomies && el(ToggleControl, {
              label: "— Pädagogik zeigen",
              checked: !!a.showTaxPaed,
              onChange: (val) => setAttributes({ showTaxPaed: val }),
            }),
			      !!a.showTaxonomies && el(ToggleControl, {
              label: "— Tage zeigen",
              checked: !!a.showTaxTage,
              onChange: (val) => setAttributes({ showTaxTage: val }),
            }),

            el(ToggleControl, {
              label: "Auszug",
              checked: !!a.showExcerpt,
              onChange: (val) => setAttributes({ showExcerpt: val }),
            }),
            el(RangeControl, {
              label: "Auszugslänge (Wörter)",
              value: a.excerptLength,
              onChange: (val) => setAttributes({ excerptLength: val }),
              min: 5, max: 80,
            }),
            el(ToggleControl, {
              label: "Weiterlesen-Link",
              checked: !!a.showReadMore,
              onChange: (val) => setAttributes({ showReadMore: val }),
            }),
            el(ToggleControl, {
              label: "Gradientenlinie",
              checked: !!a.showGradientLine,
              onChange: (val) => setAttributes({ showGradientLine: val }),
            }),
            el('hr'),
            el('div', { className: 'jhh-reorder-title' }, 'Reihenfolge der Elemente'),
            reorderUI
          ),

          // Layout
          el(
            PanelBody,
            { title: "Layout", initialOpen: false },
            el(SelectControl, {
              label: "Darstellung",
              value: a.layout,
              options: [
                { label: "Grid", value: "grid" },
                { label: "Liste", value: "list" },
                { label: "Carousel", value: "carousel" },
              ],
              onChange: (val) => setAttributes({ layout: val }),
            }),
            
            a.layout === "grid" &&
              el(RangeControl, {
                label: "Spalten",
                value: a.columns,
                onChange: (val) => setAttributes({ columns: val }),
                min: 1, max: 6,
              }),
                       el(RangeControl, {
                label: "Abstand (px)",
                value: a.gap,
                onChange: (val) => setAttributes({ gap: val }),
                min: 0,
                max: 64,
            }),
            !!a.showGradientLine && el(RangeControl, {
              label: "Gradientenlinie Margin Top (px)",
              value: a.gradientMarginTop || 16,
              onChange: (val) => setAttributes({ gradientMarginTop: val }),
              min: 0,
              max: 50,
            }),
            !!a.showGradientLine && el(RangeControl, {
              label: "Gradientenlinie Margin Bottom (px)", 
              value: a.gradientMarginBottom || 16,
              onChange: (val) => setAttributes({ gradientMarginBottom: val }),
              min: 0,
              max: 50,
            }),

            // Carousel Controls
            a.layout === 'carousel' && el(ToggleControl, {
              label: 'Automatisch abspielen',
              checked: !!a.carouselAutoplay,
              onChange: (val) => setAttributes({ carouselAutoplay: val })
            }),
            a.layout === 'carousel' && el(RangeControl, {
              label: 'Wechselintervall (Sekunden)',
              value: a.carouselInterval || 7,
              onChange: (val) => setAttributes({ carouselInterval: val }),
              min: 3,
              max: 15
            }),
            a.layout === 'carousel' && el(ToggleControl, {
              label: 'Pause bei Hover/Touch',
              checked: !!a.carouselPauseOnHover,
              onChange: (val) => setAttributes({ carouselPauseOnHover: val })
            }),
            a.layout === 'carousel' && el(ToggleControl, {
              label: 'Pfeile anzeigen',
              checked: !!a.carouselArrows,
              onChange: (val) => setAttributes({ carouselArrows: val })
            }),
            a.layout === 'carousel' && el(ToggleControl, {
              label: 'Indikatoren anzeigen',
              checked: !!a.carouselIndicators,
              onChange: (val) => setAttributes({ carouselIndicators: val })
            }),

            // Back URL for single page
            el(TextControl, {
              label: 'Zurück-Link (Alle Angebote) – URL',
              help: 'Diese URL wird als ?back=... an den Weiterlesen-/Titel-Link angehängt und in der Single-Ansicht für den Zurück-Button verwendet.',
              value: a.backUrl || '',
              onChange: (val) => setAttributes({ backUrl: val })
            }),
            el('div', { 
              style: { 
                marginTop: '16px', 
                padding: '12px', 
                backgroundColor: '#f0f0f1', 
                borderRadius: '4px',
                borderLeft: '4px solid #007cba'
              } 
            },
              el('strong', null, 'Tipp: '),
              el('span', null, 'Den globalen Staff-Card Style für alle Angebote findest du unter '),
              el('em', null, 'Einstellungen → OKJA Angebote'),
              el('span', null, '.')
            )
          )
        ),
        // Vorschau im Editor
        el(ServerSideRender, {
            block: 'jhh/posts',
            attributes: a
        })
      ];
    },
    save: () => null
  });

  // ======================
  // New Block: JHH Team
  // ======================
  registerBlockType("jhh/team", {
    title: "OKJA Team",
    icon: "groups",
    category: "widgets",
    attributes: {
      layout: { type: 'string', default: 'grid' }, // grid | list
      columns: { type: 'number', default: 3 },
      gap: { type: 'number', default: 16 },
      termIds: { type: 'array', default: [] },
      termOrder: { type: 'array', default: [] },
      orderMode: { type: 'string', default: 'custom' },
  cardBgStyle: { type: 'string', default: 'dark' }, // none | dark | blue | purple | sunset | rainbow | notebook | simple
      showAvatar: { type: 'boolean', default: true },
      showName: { type: 'boolean', default: true },
      showEmail: { type: 'boolean', default: true },
      showBio: { type: 'boolean', default: true },
      showOffers: { type: 'boolean', default: true },
      maxOffers: { type: 'number', default: 6 },
      backUrl: { type: 'string', default: '' },
    },
    edit: (props) => {
      const { attributes: a, setAttributes } = props;

      const updateChecked = (list, id, checked) => {
        const set = new Set(list || []);
        if (checked) set.add(id); else set.delete(id);
        return Array.from(set);
      };

      const toggleTerm = (id, checked) => {
        const nextIds = updateChecked(a.termIds || [], id, checked);
        let nextOrder = Array.isArray(a.termOrder) ? a.termOrder.slice() : [];
        if (checked) {
          if (!nextOrder.includes(id)) nextOrder.push(id);
        } else {
          nextOrder = nextOrder.filter((x) => x !== id);
        }
        setAttributes({ termIds: nextIds, termOrder: nextOrder });
      };

      const ensureDisplayOrder = () => {
        const selected = new Set(a.termIds || []);
        const inOrder = (a.termOrder || []).filter((id) => selected.has(id));
        const remaining = (a.termIds || []).filter((id) => !inOrder.includes(id));
        return inOrder.concat(remaining);
      };

      return [
        el(
          InspectorControls,
          { key: 'inspector-team' },
          el(
            PanelBody,
            { title: 'Team – Auswahl', initialOpen: true },
            el('div', null, el('strong', null, 'Teammitglieder (Jugendarbeit)')),
            jugend.length
              ? jugend.map((t) =>
                  el(CheckboxControl, {
                    key: 'team-' + t.id,
                    label: t.name,
                    checked: (a.termIds || []).includes(t.id),
                    onChange: (checked) => toggleTerm(t.id, checked)
                  })
                )
              : el('em', null, 'Keine Begriffe gefunden.'),
            el(SelectControl, {
              label: 'Reihenfolge',
              value: a.orderMode || 'custom',
              options: [
                { label: 'Manuell (unten sortieren)', value: 'custom' },
                { label: 'Name (A–Z)', value: 'name_asc' },
                { label: 'Name (Z–A)', value: 'name_desc' },
              ],
              onChange: (val) => setAttributes({ orderMode: val })
            }),
            (a.orderMode || 'custom') === 'custom' && (a.termIds || []).length > 0 &&
              el('div', { className: 'jhh-reorder' },
                ensureDisplayOrder().map((id, idx) => {
                  const term = jugend.find((t) => t.id === id);
                  const label = term ? term.name : String(id);
                  return el('div', { key: 'order-' + id, className: 'jhh-reorder-row' },
                    el('span', { className: 'jhh-reorder-label' }, label),
                    el('div', { className: 'jhh-reorder-actions' },
                      el(Button, { icon: 'arrow-up', label: 'Nach oben', isSmall: true, disabled: idx === 0, onClick: () => {
                        const ordered = ensureDisplayOrder();
                        const moved = moveInArray(ordered, idx, idx - 1);
                        setAttributes({ termOrder: moved });
                      }}),
                      el(Button, { icon: 'arrow-down', label: 'Nach unten', isSmall: true, disabled: idx === ensureDisplayOrder().length - 1, onClick: () => {
                        const ordered = ensureDisplayOrder();
                        const moved = moveInArray(ordered, idx, idx + 1);
                        setAttributes({ termOrder: moved });
                      }})
                    )
                  );
                })
              )
          ),
          el(
            PanelBody,
            { title: 'Felder', initialOpen: true },
            el(ToggleControl, { label: 'Avatar', checked: !!a.showAvatar, onChange: (v) => setAttributes({ showAvatar: v }) }),
            el(ToggleControl, { label: 'Name', checked: !!a.showName, onChange: (v) => setAttributes({ showName: v }) }),
            el(ToggleControl, { label: 'E-Mail', checked: !!a.showEmail, onChange: (v) => setAttributes({ showEmail: v }) }),
            el(ToggleControl, { label: 'Beschreibung', checked: !!a.showBio, onChange: (v) => setAttributes({ showBio: v }) }),
            el(ToggleControl, { label: 'Angebote (Badges)', checked: !!a.showOffers, onChange: (v) => setAttributes({ showOffers: v }) }),
            !!a.showOffers && el(RangeControl, { label: 'Max. Angebote', value: a.maxOffers || 6, onChange: (v) => setAttributes({ maxOffers: v }), min: 0, max: 20 })
          ),
          el(
            PanelBody,
            { title: 'Layout', initialOpen: false },
            el(SelectControl, {
              label: 'Darstellung',
              value: a.layout,
              options: [
                { label: 'Kacheln (Grid)', value: 'grid' },
                { label: 'Liste', value: 'list' },
              ],
              onChange: (val) => setAttributes({ layout: val })
            }),
            a.layout === 'grid' && el(RangeControl, { label: 'Spalten', value: a.columns, min: 1, max: 6, onChange: (v) => setAttributes({ columns: v }) }),
            el(RangeControl, { label: 'Abstand (px)', value: a.gap, min: 0, max: 64, onChange: (v) => setAttributes({ gap: v }) }),
            el(SelectControl, {
              label: 'Kachel-Hintergrund',
              value: a.cardBgStyle || 'dark',
              options: [
                { label: 'Ohne Hintergrund', value: 'none' },
                { label: 'Dunkel', value: 'dark' },
                { label: 'Schlicht (dunkel, Linie)', value: 'simple' },
                { label: 'Pastell 1', value: 'blue' },
                { label: 'Pastell 2', value: 'purple' },
                { label: 'Pastell 3', value: 'sunset' },
                { label: 'Pastell 4', value: 'rainbow' },
                { label: 'Notizbuch (Papier)', value: 'notebook' },
              ],
              onChange: (val) => setAttributes({ cardBgStyle: val })
            }),
            // Back URL for single page
            el(TextControl, {
              label: 'Zurück-Link (Alle Angebote) – URL',
              help: 'Diese URL wird als ?back=... an Angebots-Links angehängt und in der Single-Ansicht für den Zurück-Button verwendet.',
              value: a.backUrl || '',
              onChange: (val) => setAttributes({ backUrl: val })
            })
          )
        ),
        el(ServerSideRender, { block: 'jhh/team', attributes: a })
      ];
    },
    save: () => null
  });
})(window.wp);
             