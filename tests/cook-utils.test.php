<?php

require_once __DIR__ . '/../cook-utils.php';

// === Schritt-Zerlegung ===================================================

test('cook_split_steps trennt an Zeilenumbrüchen', function (): void {
    $steps = cook_split_steps("Zwiebeln würfeln.\nAnbraten.\nServieren.");
    assert_same(3, count($steps));
    assert_same('Zwiebeln würfeln.', $steps[0]);
});

test('cook_split_steps entfernt leere Zeilen und Rand-Leerzeichen', function (): void {
    $steps = cook_split_steps("  Erster Schritt.  \n\n\n   \n  Zweiter Schritt. \n");
    assert_same(2, count($steps));
    assert_same('Erster Schritt.', $steps[0]);
    assert_same('Zweiter Schritt.', $steps[1]);
});

test('cook_split_steps entfernt führende Nummerierung', function (): void {
    $steps = cook_split_steps("1. Zwiebeln würfeln.\n2) Anbraten.\n- Servieren.");
    assert_same('Zwiebeln würfeln.', $steps[0]);
    assert_same('Anbraten.', $steps[1]);
    assert_same('Servieren.', $steps[2]);
});

test('cook_split_steps liefert bei leerem Text ein leeres Array', function (): void {
    assert_same([], cook_split_steps(''));
    assert_same([], cook_split_steps("   \n  \n"));
});

test('cook_split_steps verwirft den kaputten KI-Import', function (): void {
    // Steht so in der Bestandsdatenbank (Rezept 40).
    assert_same([], cook_split_steps('Die Zubereitungsschritte sind im bereitgestellten Text nicht enthalten.'));
});

test('cook_step_is_placeholder erkennt Platzhaltertexte', function (): void {
    assert_true(cook_step_is_placeholder('Die Zubereitungsschritte sind im bereitgestellten Text nicht enthalten.'));
    assert_true(cook_step_is_placeholder('Keine Zubereitung angegeben'));
    assert_false(cook_step_is_placeholder('Zwiebeln in feine Würfel schneiden.'));
});

// === Satz-Zerlegung ======================================================

test('cook_split_sentences trennt an Satzenden', function (): void {
    $parts = cook_split_sentences('Hack anbraten. Sauce angießen. Servieren.');
    assert_same(3, count($parts));
    assert_same('Hack anbraten.', $parts[0]);
});

test('cook_split_sentences trennt nicht an Abkürzungen', function (): void {
    $parts = cook_split_sentences('Alles verrühren und ca. 5 Min. köcheln lassen. Danach servieren.');
    assert_same(2, count($parts));
    assert_same('Alles verrühren und ca. 5 Min. köcheln lassen.', $parts[0]);
});

test('cook_split_sentences trennt nicht an z. B. und d. h.', function (): void {
    $parts = cook_split_sentences('Mit Kräutern garnieren, z. B. mit Petersilie. Dann auftragen.');
    assert_same(2, count($parts));
    $parts = cook_split_sentences('Kurz ruhen lassen, d. h. etwa zehn Minuten. Danach schneiden.');
    assert_same(2, count($parts));
});

test('cook_split_sentences trennt nicht an Dezimalzahlen', function (): void {
    $parts = cook_split_sentences('Insgesamt 1.5 Liter Wasser angießen. Aufkochen.');
    assert_same(2, count($parts));
});

test('cook_split_sentences liefert bei Text ohne Punkt einen Teil', function (): void {
    $parts = cook_split_sentences('Ein Schritt ohne Satzzeichen');
    assert_same(1, count($parts));
});

test('cook_step_is_long erkennt lange Absätze mit mehreren Sätzen', function (): void {
    $lang = 'Toastbrot in etwas Wasser einweichen. Zwiebel abziehen und fein würfeln. '
        . 'Hack mit ausgedrücktem Toast, Zwiebel, Ei und Senf mischen, mit Salz, Pfeffer und '
        . 'Zitronenschale würzen. Hackmasse in 16 Portionen teilen.';
    assert_true(cook_step_is_long($lang));
    assert_false(cook_step_is_long('Zwiebeln würfeln.'));
    // Lang, aber nur ein Satz: nicht sinnvoll teilbar.
    assert_false(cook_step_is_long(str_repeat('sehr langer text ohne satzende ', 12)));
});

// === Timer-Erkennung =====================================================

test('cook_detect_timers findet eine Minutenangabe', function (): void {
    $timers = cook_detect_timers('Hackbällchen im heißen Öl rundum 5 Minuten braten.');
    assert_same(1, count($timers));
    assert_same(300, $timers[0]['seconds']);
    assert_same('5 Minuten braten', $timers[0]['label']);
});

test('cook_detect_timers findet mehrere Angaben in einem Schritt', function (): void {
    $timers = cook_detect_timers('Unter Rühren 2 Minuten köcheln. Danach 30 Minuten backen.');
    assert_same(2, count($timers));
    assert_same(120, $timers[0]['seconds']);
    assert_same(1800, $timers[1]['seconds']);
});

test('cook_detect_timers nimmt bei Bereichen die Obergrenze', function (): void {
    $timers = cook_detect_timers('Etwa 3-4 Minuten garen.');
    assert_same(1, count($timers));
    assert_same(240, $timers[0]['seconds']);
});

test('cook_detect_timers versteht Stunden und Sekunden', function (): void {
    assert_same(3600, cook_detect_timers('1 Stunde ruhen lassen.')[0]['seconds']);
    assert_same(30, cook_detect_timers('30 Sek. mixen.')[0]['seconds']);
    assert_same(5400, cook_detect_timers('1,5 Stunden schmoren.')[0]['seconds']);
});

test('cook_detect_timers versteht abgekürzte Minuten', function (): void {
    assert_same(600, cook_detect_timers('Ca. 10 Min. ziehen lassen.')[0]['seconds']);
});

test('cook_detect_timers ignoriert Text ohne Zeitangabe', function (): void {
    assert_same([], cook_detect_timers('Mit Salz und Pfeffer abschmecken.'));
});

test('cook_detect_timers ignoriert Temperaturangaben', function (): void {
    assert_same([], cook_detect_timers('Den Ofen auf 180 Grad vorheizen.'));
});

test('cook_detect_timers entfernt Dubletten', function (): void {
    $timers = cook_detect_timers('5 Minuten braten, wenden, nochmals 5 Minuten braten.');
    assert_same(1, count($timers));
});

// === Zutaten-Zerlegung ===================================================

test('cook_parse_ingredient trennt Menge, Einheit und Namen', function (): void {
    $item = cook_parse_ingredient('500 g gemischtes Hackfleisch');
    assert_same(500.0, $item['qty']);
    assert_same('g', $item['unit']);
    assert_same('gemischtes Hackfleisch', $item['rest']);
    assert_same('500 g gemischtes Hackfleisch', $item['raw']);
});

test('cook_parse_ingredient versteht Unicode-Brüche', function (): void {
    $item = cook_parse_ingredient('1½ TL abgeriebene Bio-Zitronenschale');
    assert_same(1.5, $item['qty']);
    assert_same('TL', $item['unit']);

    $item = cook_parse_ingredient('½ Bund Petersilie');
    assert_same(0.5, $item['qty']);
    assert_same('Bund', $item['unit']);
    assert_same('Petersilie', $item['rest']);
});

test('cook_parse_ingredient versteht Bereiche', function (): void {
    $item = cook_parse_ingredient('2-3 TL Senf');
    assert_same(2.0, $item['qty']);
    assert_same(3.0, $item['qty_max']);
    assert_same('TL', $item['unit']);
});

test('cook_parse_ingredient versteht Dezimalkomma', function (): void {
    $item = cook_parse_ingredient('1,5 l Milch');
    assert_same(1.5, $item['qty']);
    assert_same('l', $item['unit']);
});

test('cook_parse_ingredient lässt mengenlose Zeilen unverändert', function (): void {
    // 23 Prozent des Bestands sehen so aus.
    $item = cook_parse_ingredient('Salz, Pfeffer');
    assert_same(null, $item['qty']);
    assert_same('', $item['unit']);
    assert_same('Salz, Pfeffer', $item['rest']);

    $item = cook_parse_ingredient('Muskatnuss');
    assert_same(null, $item['qty']);
});

test('cook_parse_ingredient kommt ohne Einheit aus', function (): void {
    $item = cook_parse_ingredient('1 Zwiebel');
    assert_same(1.0, $item['qty']);
    assert_same('', $item['unit']);
    assert_same('Zwiebel', $item['rest']);
});

test('cook_parse_ingredient erkennt Stückeinheiten', function (): void {
    $item = cook_parse_ingredient('2 Scheiben Toastbrot');
    assert_same(2.0, $item['qty']);
    assert_same('Scheiben', $item['unit']);
    assert_same('Toastbrot', $item['rest']);
});

test('cook_parse_ingredient markiert gebündelte Zeilen als mehrdeutig', function (): void {
    // Steht so im Bestand: die führende 1 gehört nur zum Ei.
    $item = cook_parse_ingredient('1 Ei, 2-3 TL Senf');
    assert_same(1.0, $item['qty']);
    assert_true($item['ambiguous']);

    $item = cook_parse_ingredient('2 EL Butter, 2 EL Mehl');
    assert_true($item['ambiguous']);
});

test('cook_parse_ingredient markiert eindeutige Zeilen nicht', function (): void {
    assert_false(cook_parse_ingredient('500 g gemischtes Hackfleisch')['ambiguous']);
    assert_false(cook_parse_ingredient('1 Zwiebel')['ambiguous']);
    assert_false(cook_parse_ingredient('Salz, Pfeffer')['ambiguous']);
});

// === Zutaten-Zuordnung ===================================================

test('cook_match_ingredients findet erwähnte Zutaten', function (): void {
    $ingredients = cook_parse_ingredients("2 Scheiben Toastbrot\n1 Zwiebel\n500 g gemischtes Hackfleisch");
    $hits = cook_match_ingredients('Toastbrot in etwas Wasser einweichen. Zwiebel abziehen und fein würfeln.', $ingredients);
    assert_same([0, 1], $hits);
});

test('cook_match_ingredients erkennt Pluralformen', function (): void {
    $ingredients = cook_parse_ingredients("3 Zwiebeln\n2 Möhren");
    $hits = cook_match_ingredients('Zwiebel und Möhre klein schneiden.', $ingredients);
    assert_same([0, 1], $hits);
});

test('cook_match_ingredients liefert nichts bei fehlender Erwähnung', function (): void {
    $ingredients = cook_parse_ingredients("500 g Hackfleisch\n1 Zwiebel");
    assert_same([], cook_match_ingredients('Den Ofen vorheizen.', $ingredients));
});

test('cook_match_ingredients ignoriert sehr kurze Namen', function (): void {
    // "Ei" ist zu kurz, sonst träfe es "Eintopf", "einweichen", "Eiweiß" ...
    $ingredients = cook_parse_ingredients("1 Ei");
    assert_same([], cook_match_ingredients('Alles gut einweichen und ziehen lassen.', $ingredients));
});

test('cook_match_ingredients ignoriert Füllwörter', function (): void {
    $ingredients = cook_parse_ingredients('2 EL Öl zum Braten');
    // "Braten" darf nicht über das Füllwort "zum" oder den Kochbegriff greifen.
    $hits = cook_match_ingredients('Zwiebeln in der Pfanne anschwitzen.', $ingredients);
    assert_same([], $hits);
});

// === Session-Aufbau ======================================================

test('cook_build_steps liefert Schritte mit Timern und Zutaten', function (): void {
    $steps = cook_build_steps(
        "Zwiebel würfeln und 5 Minuten anbraten.\nSahne angießen und abschmecken.",
        cook_parse_ingredients("1 Zwiebel\n200 g Schlagsahne")
    );

    assert_same(2, count($steps));
    assert_same(0, $steps[0]['index']);
    assert_same(1, count($steps[0]['timers']));
    assert_same([0], $steps[0]['ingredients']);
    assert_same([1], $steps[1]['ingredients']);
    assert_same([], $steps[1]['timers']);
});

test('cook_build_steps markiert teilbare Schritte', function (): void {
    $lang = 'Toastbrot in etwas Wasser einweichen. Zwiebel abziehen und fein würfeln. '
        . 'Hack mit ausgedrücktem Toast, Zwiebel, Ei und Senf mischen, mit Salz und Pfeffer würzen. '
        . 'Hackmasse in 16 Portionen teilen.';
    $steps = cook_build_steps($lang, []);
    assert_same(1, count($steps));
    assert_true($steps[0]['splittable']);
    assert_same(4, count($steps[0]['sentences']));
});

test('cook_build_steps kommt mit einem einzigen Schritt zurecht', function (): void {
    $steps = cook_build_steps('Alles verrühren.', []);
    assert_same(1, count($steps));
    assert_false($steps[0]['splittable']);
});

test('cook_build_steps liefert bei fehlender Zubereitung nichts', function (): void {
    assert_same([], cook_build_steps('', cook_parse_ingredients('1 Zwiebel')));
});
