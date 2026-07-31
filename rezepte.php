<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezeptdatenbank - Alle Rezepte</title>
    <link rel="stylesheet" href="css/style.css">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.classList.add('dark');</script>
</head>
<body class="page-recipes">
    <header class="site-header">
        <div class="topbar">
            <a href="index.php" class="site-brand">
                <span class="site-brand-kicker">Rezeptbuch</span>
                <span class="site-brand-title">The Editorial Kitchen</span>
            </a>
            <nav class="main-nav">
                <a href="index.php" class="nav-link">Import</a>
                <a href="rezepte.php" class="nav-link active">Rezepte</a>
                <a href="wochenplan.php" class="nav-link">Wochenplan</a>
                <a href="einkaufsliste.php" class="nav-link">Einkaufsliste</a>
                <a href="admin.php" class="nav-link">Admin</a>
                <button class="btn-theme-toggle" id="btn-theme-toggle" title="Darkmode umschalten" aria-label="Darkmode umschalten"><span class="theme-icon-light">🌙</span><span class="theme-icon-dark">☀</span></button>
            </nav>
        </div>
    </header>

    <main class="container">
        <section class="page-header">
            <div class="page-header-copy">
                <span class="page-kicker">Kulinarische Bibliothek</span>
                <h1>Alle Rezepte</h1>
                <p class="page-subtitle">Durchsuche deine Sammlung nach Kategorien, Typen und schnellen Favoriten für die nächste Woche.</p>
            </div>
            <div class="page-actions">
                <a href="index.php" class="btn btn-primary">Neues Rezept</a>
            </div>
        </section>

        <div class="filter-bar">
            <input type="text" id="search-input" placeholder="Rezept suchen...">
            <select id="filter-kategorie" data-category-select data-include-all="1">
                <option value="alle">Alle Kategorien</option>
            </select>
            <select id="filter-typ">
                <option value="alle">Alle Typen</option>
                <option value="normal">Normal</option>
                <option value="thermomix">Thermomix</option>
                <option value="airfryer">Airfryer</option>
            </select>
        </div>

        <div class="recipe-grid" id="recipe-list">
            <!-- Wird per JS gefüllt -->
        </div>

        <!-- Rezept-Detail-Modal -->
        <div id="recipe-modal" class="modal hidden">
            <div class="modal-content">
                <button class="modal-close">&times;</button>
                <h2 id="modal-title"></h2>
                <div id="modal-kategorie"></div>
                <div id="modal-typ"></div>
                <div class="modal-meta">
                    <span id="modal-kochzeit"></span>
                    <span id="modal-portionen"></span>
                </div>
                <div id="modal-rating" class="modal-rating"></div>
                <div id="modal-appearances" class="modal-appearances hidden"></div>
                <div id="modal-tags"></div>
                <div id="modal-body"></div>
                <div id="modal-anmerkung" class="modal-anmerkung"></div>
                <div id="thermomix-preview" class="thermomix-preview hidden">
                    <div class="thermomix-preview-head">
                        <span class="badge badge-tm">KI-Vorschlag</span>
                        <strong>Thermomix-Version</strong>
                        <span class="thermomix-preview-hint">Noch nicht gespeichert</span>
                    </div>
                    <h3 id="tm-preview-title"></h3>
                    <div class="modal-meta thermomix-preview-meta">
                        <span id="tm-preview-kochzeit"></span>
                        <span id="tm-preview-portionen"></span>
                    </div>
                    <div id="tm-preview-tags" class="thermomix-preview-tags"></div>
                    <div id="tm-preview-body"></div>
                    <div class="thermomix-preview-actions">
                        <button class="btn btn-primary" id="btn-save-thermomix" type="button">Als neues Thermomix-Rezept speichern</button>
                    </div>
                </div>
                <div class="modal-actions">
                    <button class="btn btn-primary" id="btn-cook-recipe" type="button">Kochen starten</button>
                    <button class="btn btn-primary hidden" id="btn-show-thermomix" type="button">Als Thermomix anzeigen</button>
                    <button class="btn btn-edit" id="btn-edit-recipe">Bearbeiten</button>
                    <button class="btn btn-delete" id="btn-delete-recipe">Löschen</button>
                </div>
            </div>
        </div>

        <!-- Rezept-Edit-Modal -->
        <div id="edit-modal" class="modal hidden">
            <div class="modal-content">
                <button class="modal-close">&times;</button>
                <h2>Rezept bearbeiten</h2>
                <form id="form-edit">
                    <input type="hidden" name="id">
                    <label>Titel
                        <input type="text" name="title" required>
                    </label>
                    <label>Kategorie
                        <select name="kategorie" data-category-select>
                            <option value="hauptspeise">Hauptspeise</option>
                        </select>
                    </label>
                    <label>Rezept-Art
                        <select name="typ">
                            <option value="normal">Normal</option>
                            <option value="thermomix">Thermomix</option>
                            <option value="airfryer">Airfryer</option>
                        </select>
                    </label>
                    <div class="row">
                        <label>Kochzeit (Minuten)
                            <input type="number" name="kochzeit" min="1" required>
                        </label>
                        <label>Portionen
                            <input type="number" name="portionen" min="1" required>
                        </label>
                    </div>
                    <label>Tags (kommagetrennt)
                        <input type="text" name="tags">
                    </label>
                    <button type="button" class="btn btn-ghost btn-remove-random-tag hidden" data-remove-random-tag>Zufallsrezept-Markierung entfernen</button>
                    <label>Zutaten (eine pro Zeile)
                        <textarea name="zutaten" rows="8" required></textarea>
                    </label>
                    <label>Zubereitung (ein Schritt pro Zeile)
                        <textarea name="zubereitung" rows="8" required></textarea>
                    </label>
                    <label>Gekochtes Bild (optional)
                        <input type="file" name="image" accept="image/*">
                    </label>
                    <div class="image-preview" id="edit-image-preview"></div>
                    <button type="button" class="btn btn-ghost btn-remove-image" data-remove-image>Bild entfernen</button>
                    <label>Anmerkung / Quelle
                        <input type="text" name="anmerkung" placeholder="z.B. Quelle, Notizen...">
                    </label>
                    <div class="edit-actions">
                        <button type="submit" class="btn btn-primary">Speichern</button>
                        <button type="button" class="btn btn-cancel" id="btn-cancel-edit">Abbrechen</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Status -->
        <div id="status" class="status hidden"></div>
    </main>

    <nav class="mobile-nav">
        <a href="index.php" class="mobile-nav-link">Import</a>
        <a href="rezepte.php" class="mobile-nav-link active">Rezepte</a>
        <a href="wochenplan.php" class="mobile-nav-link">Woche</a>
        <a href="einkaufsliste.php" class="mobile-nav-link">Liste</a>
        <a href="admin.php" class="mobile-nav-link">Admin</a>
    </nav>

    <script src="js/app.js"></script>
</body>
</html>
