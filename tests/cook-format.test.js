const test = require('node:test');
const assert = require('node:assert');

const { formatQuantity, formatDuration, scaleIngredient, countScalable } = require('../js/cook-format.js');

test('formatQuantity gibt ganze Zahlen ohne Nachkomma aus', () => {
    assert.strictEqual(formatQuantity(500), '500');
    assert.strictEqual(formatQuantity(2), '2');
    assert.strictEqual(formatQuantity(1), '1');
});

test('formatQuantity schreibt gängige Brüche als Zeichen', () => {
    assert.strictEqual(formatQuantity(0.5), '½');
    assert.strictEqual(formatQuantity(1.5), '1½');
    assert.strictEqual(formatQuantity(0.25), '¼');
    assert.strictEqual(formatQuantity(2.75), '2¾');
    assert.strictEqual(formatQuantity(1 / 3), '⅓');
});

test('formatQuantity rundet krumme Werte auf eine Nachkommastelle mit Komma', () => {
    assert.strictEqual(formatQuantity(1.2), '1,2');
    assert.strictEqual(formatQuantity(0.7), '0,7');
});

test('formatQuantity rundet große Mengen auf ganze Zahlen', () => {
    assert.strictEqual(formatQuantity(333.33), '333');
    assert.strictEqual(formatQuantity(24.5), '25');
});

test('formatQuantity verträgt Unsinn', () => {
    assert.strictEqual(formatQuantity(NaN), '');
    assert.strictEqual(formatQuantity(-5), '');
    assert.strictEqual(formatQuantity(0), '0');
});

test('formatDuration formatiert mm:ss', () => {
    assert.strictEqual(formatDuration(0), '00:00');
    assert.strictEqual(formatDuration(59), '00:59');
    assert.strictEqual(formatDuration(300), '05:00');
    assert.strictEqual(formatDuration(125), '02:05');
});

test('formatDuration formatiert Stunden aus', () => {
    assert.strictEqual(formatDuration(3600), '1:00:00');
    assert.strictEqual(formatDuration(5400), '1:30:00');
});

test('formatDuration verträgt negative Werte', () => {
    assert.strictEqual(formatDuration(-10), '00:00');
});

test('scaleIngredient verdoppelt eine einfache Menge', () => {
    const item = { raw: '500 g Mehl', qty: 500, qty_max: null, unit: 'g', rest: 'Mehl', ambiguous: false };
    assert.deepStrictEqual(scaleIngredient(item, 2), { text: '1000 g Mehl', scaled: true });
});

test('scaleIngredient skaliert Bereiche an beiden Grenzen', () => {
    const item = { raw: '2-3 TL Senf', qty: 2, qty_max: 3, unit: 'TL', rest: 'Senf', ambiguous: false };
    assert.deepStrictEqual(scaleIngredient(item, 2), { text: '4-6 TL Senf', scaled: true });
});

test('scaleIngredient lässt mengenlose Zutaten unverändert', () => {
    const item = { raw: 'Salz, Pfeffer', qty: null, qty_max: null, unit: '', rest: 'Salz, Pfeffer', ambiguous: false };
    assert.deepStrictEqual(scaleIngredient(item, 3), { text: 'Salz, Pfeffer', scaled: false });
});

test('scaleIngredient rührt gebündelte Zeilen nicht an', () => {
    const item = { raw: '1 Ei, 2-3 TL Senf', qty: 1, qty_max: null, unit: '', rest: 'Ei, 2-3 TL Senf', ambiguous: true };
    assert.deepStrictEqual(scaleIngredient(item, 2), { text: '1 Ei, 2-3 TL Senf', scaled: false });
});

test('scaleIngredient gibt bei Faktor 1 das Original zurück', () => {
    const item = { raw: '500 g Mehl', qty: 500, qty_max: null, unit: 'g', rest: 'Mehl', ambiguous: false };
    assert.deepStrictEqual(scaleIngredient(item, 1), { text: '500 g Mehl', scaled: false });
});

test('scaleIngredient halbiert zu Brüchen', () => {
    const item = { raw: '1 Zwiebel', qty: 1, qty_max: null, unit: '', rest: 'Zwiebel', ambiguous: false };
    assert.deepStrictEqual(scaleIngredient(item, 0.5), { text: '½ Zwiebel', scaled: true });
});

test('scaleIngredient verträgt fehlende Angaben', () => {
    assert.deepStrictEqual(scaleIngredient(null, 2), { text: '', scaled: false });
    assert.deepStrictEqual(scaleIngredient({ raw: 'x', qty: 1 }, 0), { text: 'x', scaled: false });
});

test('countScalable zählt nur echte Mengen', () => {
    const list = [
        { qty: 500, ambiguous: false },
        { qty: null, ambiguous: false },
        { qty: 1, ambiguous: true },
        { qty: 2, ambiguous: false },
    ];
    assert.strictEqual(countScalable(list), 2);
    assert.strictEqual(countScalable([]), 0);
    assert.strictEqual(countScalable(undefined), 0);
});
