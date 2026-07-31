<?php
$recipeId = (int)($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="dark">
    <title>Rezeptdatenbank - Kochmodus</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="page-cook" data-recipe-id="<?= $recipeId ?>">

    <div class="cook" id="cook-root">

        <!-- Ladezustand -->
        <div class="cook-center" id="cook-loading">
            <div class="spinner"></div>
            <p>Rezept wird geladen …</p>
        </div>

        <!-- Fehler- und Leerzustand -->
        <div class="cook-center hidden" id="cook-empty">
            <h1 id="cook-empty-title">Kochmodus nicht möglich</h1>
            <p id="cook-empty-text"></p>
            <div class="cook-empty-actions">
                <a href="rezepte.php" class="cook-btn cook-btn-ghost">Zurück zu den Rezepten</a>
                <a href="rezepte.php" class="cook-btn cook-btn-primary hidden" id="cook-empty-edit">Rezept bearbeiten</a>
            </div>
        </div>

        <!-- Kochansicht -->
        <div class="cook-shell hidden" id="cook-view">

            <header class="cook-head">
                <button type="button" class="cook-icon-btn" id="cook-exit" aria-label="Kochmodus verlassen" title="Kochmodus verlassen">&times;</button>
                <div class="cook-head-text">
                    <h1 class="cook-title" id="cook-title"></h1>
                    <p class="cook-step-count" id="cook-step-count"></p>
                </div>
                <div class="cook-servings" id="cook-servings">
                    <button type="button" class="cook-servings-btn" id="cook-servings-minus" aria-label="Eine Portion weniger">−</button>
                    <span class="cook-servings-value" id="cook-servings-value" aria-live="polite">4</span>
                    <button type="button" class="cook-servings-btn" id="cook-servings-plus" aria-label="Eine Portion mehr">+</button>
                </div>
            </header>

            <div class="cook-ticks" id="cook-ticks" role="progressbar" aria-label="Fortschritt" aria-valuemin="1"></div>

            <main class="cook-body" id="cook-body">
                <div class="cook-step" id="cook-step" aria-live="polite">
                    <p class="cook-step-number" id="cook-step-number"></p>
                    <div class="cook-step-text" id="cook-step-text"></div>

                    <button type="button" class="cook-split-toggle hidden" id="cook-split-toggle" aria-pressed="false">
                        Feiner unterteilen
                    </button>

                    <section class="cook-ingredients hidden" id="cook-ingredients">
                        <h2 class="cook-section-title" id="cook-ingredients-title">Zutaten für diesen Schritt</h2>
                        <ul class="cook-ingredient-list" id="cook-ingredient-list"></ul>
                        <button type="button" class="cook-split-toggle" id="cook-ingredients-toggle" aria-pressed="false">
                            Alle Zutaten anzeigen
                        </button>
                    </section>
                </div>
            </main>

            <div class="cook-timers hidden" id="cook-timers">
                <div class="cook-timer-chips" id="cook-timer-chips" aria-label="Timer für diesen Schritt"></div>
                <div class="cook-timer-panel hidden" id="cook-timer-panel">
                    <div class="cook-timer-row">
                        <span class="cook-timer-value" id="cook-timer-value" aria-live="off">00:00</span>
                        <span class="cook-timer-label" id="cook-timer-label"></span>
                        <div class="cook-timer-actions">
                            <button type="button" class="cook-icon-btn" id="cook-timer-toggle" aria-label="Timer starten">▶</button>
                            <button type="button" class="cook-icon-btn" id="cook-timer-reset" aria-label="Timer zurücksetzen">↻</button>
                            <button type="button" class="cook-icon-btn" id="cook-timer-close" aria-label="Timer schließen">&times;</button>
                        </div>
                    </div>
                    <div class="cook-timer-track"><div class="cook-timer-fill" id="cook-timer-fill"></div></div>
                </div>
            </div>

            <nav class="cook-nav">
                <button type="button" class="cook-btn cook-btn-ghost" id="cook-prev">Zurück</button>
                <button type="button" class="cook-btn cook-btn-primary" id="cook-next">Weiter</button>
            </nav>
        </div>

        <!-- Abschluss -->
        <div class="cook-center hidden" id="cook-done">
            <p class="cook-done-kicker">Fertig gekocht</p>
            <h1 id="cook-done-title"></h1>
            <p class="cook-done-meta" id="cook-done-meta"></p>
            <div class="cook-empty-actions">
                <a href="rezepte.php" class="cook-btn cook-btn-primary">Zurück zu den Rezepten</a>
                <button type="button" class="cook-btn cook-btn-ghost" id="cook-restart">Nochmal kochen</button>
            </div>
        </div>

        <!-- Abbruch-Nachfrage -->
        <div class="cook-dialog hidden" id="cook-exit-dialog" role="dialog" aria-modal="true" aria-labelledby="cook-exit-dialog-title">
            <div class="cook-dialog-box">
                <h2 id="cook-exit-dialog-title">Kochmodus verlassen?</h2>
                <p>Dein Fortschritt wird gespeichert. Du kannst später an dieser Stelle weitermachen.</p>
                <div class="cook-dialog-actions">
                    <button type="button" class="cook-btn cook-btn-ghost" id="cook-exit-cancel">Weiterkochen</button>
                    <button type="button" class="cook-btn cook-btn-primary" id="cook-exit-confirm">Verlassen</button>
                </div>
            </div>
        </div>

        <div class="cook-flash hidden" id="cook-flash" aria-hidden="true"></div>
        <p class="cook-status" id="cook-status" role="status"></p>
    </div>

    <script src="js/cook-format.js"></script>
    <script src="js/cook.js"></script>
</body>
</html>
