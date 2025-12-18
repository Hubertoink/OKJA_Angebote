/**
 * Dynamischer Tilt-Effekt basierend auf Mausposition
 */
(function() {
    'use strict';

    const TILT_MAX = 15; // Maximaler Neigungswinkel in Grad
    const SCALE_HOVER = 1.05; // Zoom bei Hover
    const TRANSITION_LEAVE = 'transform 0.4s ease-out, box-shadow 0.4s ease-out';
    const TRANSITION_MOVE = 'transform 0.1s ease-out';

    function initTiltEffect() {
        // Alle Elemente mit Tilt-Effekt auswählen
        const tiltElements = document.querySelectorAll('.jhh-hover-tilt, .jhh-hover-tilt-zoom');

        tiltElements.forEach(container => {
            const img = container.querySelector('img');
            if (!img) return;

            // Mausbewegung auf dem Container
            container.addEventListener('mousemove', (e) => {
                const rect = container.getBoundingClientRect();
                
                // Position relativ zum Element (0 bis 1)
                const x = (e.clientX - rect.left) / rect.width;
                const y = (e.clientY - rect.top) / rect.height;

                // Umrechnen in Neigungswinkel (-TILT_MAX bis +TILT_MAX)
                // X-Achse: links = positive Rotation, rechts = negative
                // Y-Achse: oben = negative Rotation, unten = positive
                const rotateY = (x - 0.5) * TILT_MAX * 2;
                const rotateX = (0.5 - y) * TILT_MAX * 2;

                // Schatten basierend auf Neigung
                const shadowX = rotateY * 0.5;
                const shadowY = -rotateX * 0.5;

                // Prüfen ob auch Zoom aktiviert ist
                const hasZoom = container.classList.contains('jhh-hover-tilt-zoom');
                const scale = hasZoom ? SCALE_HOVER : 1.02;

                img.style.transition = TRANSITION_MOVE;
                img.style.transform = `perspective(800px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(${scale})`;
                img.style.boxShadow = `${shadowX}px ${shadowY + 10}px 25px rgba(0,0,0,0.35)`;
            });

            // Maus verlässt den Container
            container.addEventListener('mouseleave', () => {
                img.style.transition = TRANSITION_LEAVE;
                img.style.transform = 'perspective(800px) rotateX(0deg) rotateY(0deg) scale(1)';
                img.style.boxShadow = 'none';
            });

            // Maus betritt den Container
            container.addEventListener('mouseenter', () => {
                img.style.transition = TRANSITION_MOVE;
            });
        });
    }

    // Initialisieren wenn DOM bereit ist
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTiltEffect);
    } else {
        initTiltEffect();
    }

    // Auch für dynamisch geladene Inhalte (z.B. AJAX)
    // Optional: MutationObserver für späte Initialisierung
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.addedNodes.length) {
                initTiltEffect();
            }
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
})();
