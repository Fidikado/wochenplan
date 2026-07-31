<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezeptdatenbank - Einkaufsliste</title>
    <link rel="stylesheet" href="css/style.css">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.classList.add('dark');</script>
</head>
<body class="page-shopping">
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
                <a href="einkaufsliste.php" class="nav-link active">Einkaufsliste</a>
                <a href="admin.php" class="nav-link">Admin</a>
                <button class="btn-theme-toggle" id="btn-theme-toggle" title="Darkmode umschalten" aria-label="Darkmode umschalten"><span class="theme-icon-light">🌙</span><span class="theme-icon-dark">☀</span></button>
            </nav>
        </div>
    </header>

    <main class="container">
        <section class="page-header">
            <div class="page-header-copy">
                <span class="page-kicker">Von Plan zu Einkauf</span>
                <h1>Einkaufsliste</h1>
                <p class="page-subtitle">Erzeuge aus deinem Wochenplan eine aufgeräumte Zutatenliste und streiche erledigte Posten direkt weg.</p>
            </div>
            <div class="page-actions shopping-actions">
                <button class="btn btn-primary" id="btn-generate-list">Liste generieren</button>
                <button class="btn btn-print hidden" id="btn-print-list">Drucken / PDF</button>
            </div>
        </section>

        <div id="shopping-list">
            <!-- Wird per JS gefüllt -->
        </div>

        <!-- Loading -->
        <div id="loading-shopping" class="loading hidden">
            <div class="spinner"></div>
            <span>KI erstellt Einkaufsliste...</span>
        </div>

        <!-- Status -->
        <div id="status" class="status hidden"></div>
    </main>

    <nav class="mobile-nav">
        <a href="index.php" class="mobile-nav-link">Import</a>
        <a href="rezepte.php" class="mobile-nav-link">Rezepte</a>
        <a href="wochenplan.php" class="mobile-nav-link">Woche</a>
        <a href="einkaufsliste.php" class="mobile-nav-link active">Liste</a>
        <a href="admin.php" class="mobile-nav-link">Admin</a>
    </nav>

    <script src="js/app.js"></script>
</body>
</html>
