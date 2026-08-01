# Changelog

## 2026-08-01

### Wochenplan-Export ohne Hintergrundprozess
- **Behoben**: Der Bild-Export brach mit "Print-Job konnte nicht gestartet werden" ab, sobald `exec()` gesperrt war — auf Shared Hosting der Normalfall. Der Job wurde angelegt und blieb dann unbearbeitet in `data/print-jobs/` liegen.
- `api/generate-plan-print.php` legt den Job jetzt nur noch an. Bearbeitet wird er vom ersten Status-Poll in `api/get-plan-print-status.php`, es braucht also keinen Unterprozess mehr.
- `plan_print_claim_job()` haelt dabei einen exklusiven `flock` ueber den Uebergang `queued -> running`, damit zwei gleichzeitige Polls denselben Job nicht doppelt starten und die Gemini-Quote nicht zweimal kosten.
- `ignore_user_abort()` sorgt dafuer, dass ein geschlossener Tab einen laufenden Job nicht auf halbem Weg abschneidet.
- Am Frontend aendert sich nichts: es pollt weiterhin alle 2 Sekunden mit 5 Minuten Gesamtbudget.
- `api/process-plan-print-job.php` bleibt als CLI-Einstieg erhalten, um einen Job von Hand nachzuziehen.
- Neue Tests in `tests/plan-print-job.test.php`.

## 2026-07-27

### Kochmodus
- Neue Seite `kochen.php`: ein Zubereitungsschritt pro Vollbild, mit grosser Schrift und eigenem dunklen Kuechen-Theme unabhaengig vom Site-Theme.
- Einstieg ueber den Button "Kochen starten" im Rezept-Detail-Modal auf `rezepte.php` und `wochenplan.php`.
- **Timer**: Zeitangaben im Schritttext werden erkannt und als antippbare Chips angeboten. Ein Schritt kann mehrere haben ("5 Minuten braten", "2 Minuten koecheln"); es startet nie einer von allein. Bei Bereichen wie "3-4 Minuten" gilt die Obergrenze.
- **Portionen**: live umrechenbar, die Mengen der Zutaten skalieren mit. Zeilen ohne Menge ("Salz, Pfeffer") und gebuendelte Zeilen ("1 Ei, 2-3 TL Senf") bleiben unveraendert und werden als "nicht skaliert" markiert.
- **Zutaten je Schritt**: heuristisch ueber die Zutatennamen zugeordnet, in beide Richtungen abgeglichen, damit "Sahne" im Schritt auch "Schlagsahne" trifft. Findet die Heuristik nichts, zeigt der Schritt die vollstaendige Liste.
- **Lange Absaetze** lassen sich satzweise feiner unterteilen und einzeln abhaken. Deutsche Abkuerzungen wie "ca.", "Min." oder "z. B." gelten dabei nicht als Satzende.
- **Fortschritt** wird serverseitig gespeichert und ueberlebt einen Reload. Das Timer-Ende steht als absoluter Zeitpunkt in der Datenbank, damit waehrend eines Reloads keine Zeit verloren geht.
- **Barrierefreiheit**: Farbimpuls bei Timer-Ende fuer laute Kuechen, respektiert `prefers-reduced-motion`. Bedienung per Pfeiltasten, Leertaste und Escape. Screen Wake Lock haelt den Bildschirm an.
- Randfaelle abgefangen: Rezept ohne Schritte (im Bestand ein fehlgeschlagener KI-Import), Ein-Schritt-Rezepte, geloeschte Rezepte bei gespeichertem Fortschritt.

### Technisch
- Neue Fachlogik in `cook-utils.php`: Schritt-Zerlegung, Timer-Erkennung, deutsche Mengen-Grammatik, Zutaten-Zuordnung. Das Parsing passiert ausschliesslich in PHP; das Frontend bekommt Zutaten zerlegt geliefert und multipliziert nur noch.
- Neue Endpunkte `api/get-cook-session.php`, `api/save-cook-progress.php`, `api/finish-cook-session.php`.
- Neue Tabelle `cook_progress` (eine Zeile, additive Migration, bestehende Daten unberuehrt).
- Erste Testinfrastruktur des Projekts, ohne neue Abhaengigkeiten: `php tests/run-php.php` und `node --test "tests/*.test.js"`.
- Konzepte des Kochmodus sind vom MIT-lizenzierten MorphCook abgeleitet, siehe `THIRD_PARTY_NOTICES.md`.

## 2026-05-06

### Bewertungen & gewichtete Wochenplan-Logik
- Rezepte koennen jetzt mit 1–5 Sternen bewertet werden. Die Bewertung ist direkt im Rezept-Detail-Modal klickbar und wird sofort gespeichert.
- Der Wochenplan-Generator waehlt Rezepte gewichtet nach Bewertung aus (★1 = Gewicht 1, ★5 = Gewicht 8, unbewertet = 3). Niedrig bewertete Rezepte erscheinen seltener, aber nie gar nicht.
- Jedes Rezept zeigt im Modal einen Zaehler, wie oft es in einem bestaedigten Wochenplan vorkam.

### Wochenplan bestaetigen & Verlauf
- Neuer Button "Wochenplan bestaetigen": oeffnet ein Vorschau-Modal und speichert den Plan als unveraenderlichen Snapshot in der Datenbank.
- Beim Bestaetigen wird der Erscheinungszaehler aller aktiven Rezepte um 1 erhoeht.
- Neuer Button "Vergangene Plaene": zeigt alle bestaedigten Wochenplaene als Liste, mit Einzelansicht je Plan (sortiert nach Wochentag, Typ-Badges, deaktivierte Tage ausgewiesen).

### Tage ausschalten
- Jede Tag-Karte im Wochenplan hat einen "Frei"-Button. Ein Klick deaktiviert den Tag (schraffierter Platzhalter, keine Rezept-Anzeige).
- Deaktivierte Tage werden bei der Einkaufslisten-Generierung uebersprungen.
- Der Zustand wird automatisch gespeichert.

### Airfryer als Rezept-Typ
- Neben Normal und Thermomix gibt es jetzt Airfryer als dritten Typ.
- Airfryer-Rezepte zeigen ein oranges "AF"-Badge in Tagkarten, Rezeptliste, Tausch-Modal und Detail-Modal.
- Alle Import- und Bearbeitungs-Formulare unterstuetzen den neuen Typ.

### Darkmode
- Vollstaendiger Darkmode ueber `html.dark`-Klasse und CSS Custom Properties.
- Toggle-Button (🌙 / ☀) in der Navbar auf allen Seiten.
- Praeferenz wird in `localStorage` gespeichert und per Inline-Script ohne Flackern beim Laden angewendet.

### Technisch
- Neue API-Endpunkte: `api/rate-recipe.php`, `api/confirm-plan.php`, `api/get-confirmed-plans.php`.
- DB-Migrationen: Spalten `rating` und `plan_appearances` in `recipes`, Spalte `is_disabled` in `current_plan_days`, neue Tabelle `confirmed_plans`. Alles laeuft automatisch beim ersten Request.

## 2026-03-27

- Added KI-based conversion from normal recipes to Thermomix recipes, including preview and optional save flow.
- Added Gemini-backed weekly plan image export with async background jobs, status polling, and configurable image model selection in admin.
- Improved Gemini response parsing, timeout handling, and prompt control for plan-print generation.
- Migrated core recipe, plan, and shopping-list persistence logic toward SQLite-backed helpers and added migration tooling.
- Added monthly planning page scaffolding and new header assets.
- Fixed weekly overview cook-time display so saved times are shown even when a plan snapshot has no recipe ID.
- Updated `AGENTS.md` to reflect the current project structure, print pipeline, and validation commands.
