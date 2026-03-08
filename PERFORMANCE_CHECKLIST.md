# Performance Checklist

Stand: Mobile Lighthouse / PageSpeed-Review vom 2026-03-08 fuer https://hochstaett.majo.de/

## Prioritaet 1

- LCP-Bild im Above-the-fold-Bereich identifizieren und als echtes `img`/`picture` mit `srcset`, `sizes` und hoher Prioritaet ausliefern.
- Keine CSS-Hintergrundbilder fuer den wichtigsten sichtbaren Hero verwenden, wenn das Element das LCP ist.
- Hero-Bild fuer Mobil in passender Groesse bereitstellen. Keine unnoetige `full`-Auslieferung.
- Theme- oder Builder-Slider im sichtbaren Erstbereich nur verwenden, wenn sie wirklich noetig sind.

## Prioritaet 2

- Statische Assets mit langen Cache-Laufzeiten ausliefern: Bilder, CSS, JS, Fonts.
- Cache-Control fuer versionierte Plugin-Assets auf mindestens mehrere Wochen oder Monate setzen.
- Falls moeglich ueber Server oder CDN `immutable` fuer versionierte Dateien nutzen.
- Brotli oder Gzip fuer Textressourcen aktivieren.

## Prioritaet 3

- Render-blocking CSS reduzieren: Theme-CSS, Builder-CSS und Zusatzplugin-CSS pruefen.
- Nicht benoetigte globale CSS-Dateien nur auf den Seiten laden, die sie wirklich brauchen.
- Google Fonts oder externe Fonts moeglichst lokal hosten oder weiter optimieren.
- Kritische Header-/Hero-Stile klein halten; alles andere spaeter laden, wenn Theme/Stack das sauber unterstuetzt.

## Plugin-spezifisch

- Single-Hero im Plugin als echtes Bild statt CSS-Hintergrund rendern.
- Angebots-Single Styles nur fuer vorhandene Bereiche laden: `team` nur bei sichtbaren Team-Karten, `events` nur bei sichtbaren A-Events.
- Event-Modal nur dann laden, wenn Event-Links auf der Seite vorhanden sind.
- Prefetch fuer bekannte A-Event-Modals auf Angebotsseiten nutzen, damit Popups schneller oeffnen.
- Grosse Hintergrundeffekte und Hero-Animationen auf Mobile sparsam einsetzen.

## Theme / WordPress

- Sicherstellen, dass ein gueltiger `<title>` im Head ausgegeben wird.
- Meta-Description ueber Theme oder SEO-Plugin pflegen.
- Emojis, Embeds und ungenutzte WordPress-Frontend-Skripte deaktivieren, falls sie nicht gebraucht werden.
- Ueberpruefen, ob das Theme zusaetzliche globale CSS/JS auf jeder Seite einreiht.

## Bilder

- Startseiten-Hero und Teaser-Bilder auf moderne Formate pruefen: WebP oder AVIF.
- Bildabmessungen im HTML konsistent setzen, um Layout-Shifts zu vermeiden.
- Kleine Vorschaubilder fuer Karten und Listen nicht groesser als noetig ausliefern.
- Lazy-Loading nur unterhalb des Folds verwenden. Das wichtigste Hero-Bild nicht lazy laden.

## Server / Infrastruktur

- Full-page Cache fuer nicht eingeloggte Besucher aktivieren.
- Objekt-Cache pruefen, falls viele dynamische Queries oder Term-Meta-Abfragen laufen.
- CDN fuer Bilder und statische Assets pruefen, wenn die Zielgruppe mobil und regional breit verteilt ist.
- TTFB im Hosting kontrollieren; bei dauerhaft hohem Wert zuerst Hosting, Cache und PHP-Setup pruefen.

## Nachkontrolle

- Nach jeder Aenderung erneut mobile Lighthouse-Messung ausfuehren.
- Immer separat pruefen: Startseite, Angebots-Single, Angebotsevent-Single.
- In Chrome DevTools das tatsaechliche LCP-Element bestaetigen, bevor weiter optimiert wird.