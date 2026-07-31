/**
 * Reine Formatier- und Skalierlogik des Kochmodus.
 *
 * Bewusst frei von DOM-Zugriffen, damit die Regeln unter `node --test` prüfbar
 * bleiben. Das Zerlegen der Mengen passiert in cook-utils.php - hier wird nur
 * noch multipliziert und lesbar gemacht.
 */
(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    } else {
        root.CookFormat = api;
    }
})(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    const FRACTIONS = [
        [0.25, '¼'],
        [1 / 3, '⅓'],
        [0.5, '½'],
        [2 / 3, '⅔'],
        [0.75, '¾'],
    ];

    /** Mengen als Bruch schreiben, wo Köche das erwarten: 1,5 TL wird 1½ TL. */
    function formatQuantity(value) {
        if (typeof value !== 'number' || !isFinite(value) || value < 0) {
            return '';
        }
        if (value === 0) {
            return '0';
        }

        // Ab zweistelligen Mengen sind Bruchteile nur Rauschen.
        if (value >= 20) {
            return String(Math.round(value));
        }

        const whole = Math.floor(value + 1e-9);
        const fraction = value - whole;

        if (fraction < 0.02) {
            return String(whole);
        }

        for (const [size, symbol] of FRACTIONS) {
            if (Math.abs(fraction - size) < 0.03) {
                return whole > 0 ? String(whole) + symbol : symbol;
            }
        }

        const rounded = Math.round(value * 10) / 10;
        return String(rounded).replace('.', ',');
    }

    /** mm:ss, ab einer Stunde h:mm:ss. */
    function formatDuration(seconds) {
        const total = Math.max(0, Math.round(Number(seconds) || 0));
        const hours = Math.floor(total / 3600);
        const minutes = Math.floor((total % 3600) / 60);
        const secs = total % 60;

        const pad = (value) => String(value).padStart(2, '0');
        return hours > 0
            ? hours + ':' + pad(minutes) + ':' + pad(secs)
            : pad(minutes) + ':' + pad(secs);
    }

    /**
     * Skaliert eine zerlegte Zutat. Zeilen ohne Menge und gebündelte Zeilen wie
     * "1 Ei, 2-3 TL Senf" bleiben unangetastet - dort wäre jede Skalierung
     * geraten und damit falsch.
     */
    function scaleIngredient(item, scale) {
        const safe = item || {};
        const raw = String(safe.raw || '');

        if (safe.qty === null || safe.qty === undefined || safe.ambiguous) {
            return { text: raw, scaled: false };
        }

        const factor = typeof scale === 'number' && isFinite(scale) && scale > 0 ? scale : 1;
        if (factor === 1) {
            return { text: raw, scaled: false };
        }

        let amount = formatQuantity(safe.qty * factor);
        if (safe.qty_max !== null && safe.qty_max !== undefined) {
            amount += '-' + formatQuantity(safe.qty_max * factor);
        }

        const parts = [amount];
        if (safe.unit) {
            parts.push(safe.unit);
        }
        if (safe.rest) {
            parts.push(safe.rest);
        }

        return { text: parts.join(' '), scaled: true };
    }

    /** Wie viele Zutaten einer Liste eine Skalierung überhaupt mitmachen. */
    function countScalable(ingredients) {
        return (ingredients || []).filter(
            (item) => item && item.qty !== null && item.qty !== undefined && !item.ambiguous
        ).length;
    }

    return {
        formatQuantity,
        formatDuration,
        scaleIngredient,
        countScalable,
    };
});
