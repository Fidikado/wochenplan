<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezeptdatenbank - Rezept hinzufügen</title>
    <link rel="stylesheet" href="css/style.css">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.classList.add('dark');</script>
</head>
<body class="page-add">
    <header class="site-header">
        <div class="topbar">
            <a href="index.php" class="site-brand">
                <span class="site-brand-kicker">Rezeptbuch</span>
                <span class="site-brand-title">The Editorial Kitchen</span>
            </a>
            <nav class="main-nav">
                <a href="index.php" class="nav-link active">Import</a>
                <a href="rezepte.php" class="nav-link">Rezepte</a>
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
                <span class="page-kicker">Import Studio</span>
                <h1>Rezepte erfassen</h1>
                <p class="page-subtitle">Neue Rezepte manuell anlegen oder per Text, Bild und URL automatisch aufbereiten lassen.</p>
            </div>
            <div class="page-header-note">
                <strong>Vier Wege, ein Ziel.</strong>
                <span>Alle Importwege landen im gleichen sauberen Rezeptformat.</span>
            </div>
        </section>

        <div class="tabs">
            <button class="tab active" data-tab="manuell">Manuell</button>
            <button class="tab" data-tab="textblock">Textblock</button>
            <button class="tab" data-tab="bild">Bild</button>
            <button class="tab" data-tab="link">Link</button>
        </div>

        <!-- Manuell -->
        <div class="tab-content active" id="tab-manuell">
            <form id="form-manuell">
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
                    <input type="text" name="tags" placeholder="pasta, italienisch, schnell">
                </label>
                <label>Zutaten (eine pro Zeile)
                    <textarea name="zutaten" rows="8" required></textarea>
                </label>
                <label>Zubereitung (ein Schritt pro Zeile)
                    <textarea name="zubereitung" rows="8" required></textarea>
                </label>
                <label>Gekochtes Bild (optional)
                    <input type="file" name="image" accept="image/*">
                </label>
                <label>Anmerkung / Quelle
                    <input type="text" name="anmerkung" placeholder="z.B. Quelle, Notizen...">
                </label>
                <button type="submit" class="btn btn-primary">Rezept speichern</button>
            </form>
        </div>

        <!-- Textblock -->
        <div class="tab-content" id="tab-textblock">
            <p>Füge einen Rezepttext ein. Die KI extrahiert die Daten automatisch.</p>
            <textarea id="textblock-input" rows="12" placeholder="Rezepttext hier einfügen..."></textarea>
            <button class="btn btn-primary" id="btn-parse-text">Mit KI analysieren</button>
        </div>

        <!-- Bild -->
        <div class="tab-content" id="tab-bild">
            <p>Lade bis zu 3 Fotos eines Rezepts hoch. Die KI erkennt den Inhalt.</p>
            <input type="file" id="bild-input" accept="image/*" multiple>
            <div id="bild-preview"></div>
            <button class="btn btn-primary" id="btn-parse-image" disabled>Mit KI analysieren</button>
        </div>

        <!-- Link -->
        <div class="tab-content" id="tab-link">
            <p>Gib eine URL zu einem Rezept ein. Die KI extrahiert die Daten.</p>
            <input type="url" id="link-input" placeholder="https://www.example.com/rezept">
            <button class="btn btn-primary" id="btn-parse-url">Mit KI analysieren</button>
        </div>

        <!-- Vorschau (wird nach KI-Analyse angezeigt) -->
        <div id="preview" class="preview hidden">
            <h2>Vorschau</h2>
            <form id="form-preview">
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
                <label>Zutaten (eine pro Zeile)
                    <textarea name="zutaten" rows="8" required></textarea>
                </label>
                <label>Zubereitung (ein Schritt pro Zeile)
                    <textarea name="zubereitung" rows="8" required></textarea>
                </label>
                <label>Gekochtes Bild (optional)
                    <input type="file" name="image" accept="image/*">
                </label>
                <label>Anmerkung / Quelle
                    <input type="text" name="anmerkung" placeholder="z.B. Quelle, Notizen...">
                </label>
                <button type="submit" class="btn btn-primary">Rezept speichern</button>
            </form>
        </div>

        <!-- Status -->
        <div id="status" class="status hidden"></div>
        <div id="loading" class="loading hidden">
            <div class="spinner"></div>
            <span>KI analysiert...</span>
        </div>
    </main>

    <nav class="mobile-nav">
        <a href="index.php" class="mobile-nav-link active">Import</a>
        <a href="rezepte.php" class="mobile-nav-link">Rezepte</a>
        <a href="wochenplan.php" class="mobile-nav-link">Woche</a>
        <a href="einkaufsliste.php" class="mobile-nav-link">Liste</a>
        <a href="admin.php" class="mobile-nav-link">Admin</a>
    </nav>

    <script src="js/app.js"></script>
</body>
</html>
