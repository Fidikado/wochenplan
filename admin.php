<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezeptdatenbank - Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.classList.add('dark');</script>
</head>
<body class="page-admin">
    <header class="site-header">
        <div class="topbar">
            <a href="index.php" class="site-brand">
                <span class="site-brand-kicker">Rezeptbuch</span>
                <span class="site-brand-title">The Editorial Kitchen</span>
            </a>
            <nav class="main-nav">
                <a href="index.php" class="nav-link">Import</a>
                <a href="rezepte.php" class="nav-link">Rezepte</a>
                <a href="wochenplan.php" class="nav-link">Wochenplan</a>
                <a href="einkaufsliste.php" class="nav-link">Einkaufsliste</a>
                <a href="admin.php" class="nav-link active">Admin</a>
                <button class="btn-theme-toggle" id="btn-theme-toggle" title="Darkmode umschalten" aria-label="Darkmode umschalten"><span class="theme-icon-light">🌙</span><span class="theme-icon-dark">☀</span></button>
            </nav>
        </div>
    </header>

    <main class="container">
        <section class="page-header">
            <div class="page-header-copy">
                <span class="page-kicker">Steuerzentrale</span>
                <h1>Admin Dashboard</h1>
                <p class="page-subtitle">Verwalte Kategorien, Druckvorlagen und das aktive Bildmodell für deinen Plan-Export an einer zentralen Stelle.</p>
            </div>
            <div class="page-header-note">
                <strong>Systempflege ohne Overhead.</strong>
                <span>Alle Einstellungen bleiben direkt im bestehenden PHP-Workflow verankert.</span>
            </div>
        </section>

        <section class="admin-card">
            <h2>Kategorien</h2>
            <p class="admin-help">Hier kannst du neue Rezeptkategorien anlegen. Sie stehen danach direkt in den Formularen und Filtern zur Verfügung.</p>

            <form id="form-category-add" class="admin-inline-form">
                <label>Kategoriename
                    <input type="text" name="label" placeholder="z.B. Auflauf" required>
                </label>
                <button type="submit" class="btn btn-primary">Kategorie speichern</button>
            </form>

            <div id="category-summary" class="admin-summary hidden"></div>
            <div id="category-list" class="category-list"></div>
        </section>

        <section class="admin-card">
            <h2>Wochenplan-Bildvorlage</h2>
            <p class="admin-help">Lade hier die Vorlage hoch, die beim Drucken des Wochenplans als Hintergrund genutzt wird.</p>

            <form id="form-template-upload">
                <label>Vorlagenbild (JPG, PNG, WebP)
                    <input type="file" name="template" accept="image/jpeg,image/png,image/webp" required>
                </label>
                <button type="submit" class="btn btn-primary">Vorlage speichern</button>
            </form>

            <form id="form-print-model" class="admin-inline-form">
                <label>Gemini-Bildmodell
                    <select name="model" id="print-model-select">
                        <option value="gemini-3.1-flash-image">Gemini 3.1 Flash Image</option>
                        <option value="gemini-3-pro-image">Gemini 3 Pro Image</option>
                        <option value="gemini-2.5-flash-image">Gemini 2.5 Flash Image (Legacy)</option>
                    </select>
                </label>
                <button type="submit" class="btn btn-primary">Bildmodell speichern</button>
            </form>
            <p id="print-model-help" class="admin-help"></p>

            <div id="template-preview" class="template-preview hidden"></div>
        </section>

        <div id="status" class="status hidden"></div>
        <div id="loading" class="loading hidden">
            <div class="spinner"></div>
            <span>Speichere Einstellungen...</span>
        </div>
    </main>

    <nav class="mobile-nav">
        <a href="index.php" class="mobile-nav-link">Import</a>
        <a href="rezepte.php" class="mobile-nav-link">Rezepte</a>
        <a href="wochenplan.php" class="mobile-nav-link">Woche</a>
        <a href="einkaufsliste.php" class="mobile-nav-link">Liste</a>
        <a href="admin.php" class="mobile-nav-link active">Admin</a>
    </nav>

    <script src="js/app.js"></script>
</body>
</html>
