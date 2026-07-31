# Rezeptbuch

Eine schlanke Rezeptverwaltung in reinem PHP: Rezepte sammeln, einen Wochenplan
würfeln lassen, daraus die Einkaufsliste erzeugen und am Herd Schritt für
Schritt kochen. Kein Framework, kein Build, kein Paketmanager — Dateien
hinlegen, PHP starten, fertig.

Die KI-Funktionen laufen über die Gemini-API und sind **optional**. Ohne
API-Key funktionieren Rezeptverwaltung, Wochenplan, Einkaufsliste und
Kochmodus vollständig; nur Import per Foto/Link, die Thermomix-Umwandlung und
der Bildexport des Wochenplans bleiben dann stumm.

---

## ⚠️ Bevor du das öffentlich ins Netz stellst

**Die App hat keine Benutzerverwaltung.** Es gibt kein Login, keine Rollen,
keine Sessions. Wer die Adresse kennt, kann alles — auch löschen.

Für den Betrieb auf einem eigenen Rechner im Heimnetz ist das egal. Sobald die
App aus dem Internet erreichbar ist, gilt:

- **Schreibschutz aktivieren.** Die mitgelieferte `.htaccess` legt alle
  ändernden Endpunkte hinter HTTP-Basic-Auth. Einschalten mit
  `./scripts/set-edit-password.sh`.
- **Nur Apache oder LiteSpeed.** Auf **nginx wirkt `.htaccess` überhaupt
  nicht** — dort sind gleichwertige `location`-Regeln in der
  Serverkonfiguration Pflicht. Ohne sie sind `.env` mit deinem API-Key und die
  komplette Datenbank frei herunterladbar.
- **Nach dem ersten Deployment prüfen**, ob `https://deine-domain/.env` und
  `https://deine-domain/data/rezeptbuch.sqlite` wirklich 403 liefern.
- **Kostenrisiko:** ungeschützte KI-Endpunkte verbrennen fremdes Geld auf
  deiner Gemini-Rechnung.

Der Import per Link (`api/url-import.php`) ruft die angegebene Adresse
serverseitig ab und folgt Weiterleitungen ungefiltert. Auf einem Server mit
erreichbarem internen Netz ist das ein SSRF-Vektor — der Endpunkt gehört
deshalb zwingend hinter den Schreibschutz.

---

## Schnellstart

Die App braucht nur PHP. Nach der Installation liegt eine Demo-Datenbank mit
drei Rezepten bereit, du kannst also sofort loslegen.

### macOS

PHP ist auf aktuellen macOS-Versionen nicht mehr vorinstalliert. Mit
[Homebrew](https://brew.sh):

```bash
brew install php
```

Repository holen und starten:

```bash
git clone https://github.com/DEIN-NAME/rezeptbuch.git
cd rezeptbuch
php -S 127.0.0.1:8080
```

Im Browser öffnen: <http://127.0.0.1:8080>

Beenden mit `Strg + C` im Terminal.

### Windows

PHP über [winget](https://learn.microsoft.com/windows/package-manager/) in der
PowerShell:

```powershell
winget install PHP.PHP.8.4
```

Danach die PowerShell einmal schließen und neu öffnen, damit `php` im Pfad
liegt. Repository holen und starten:

```powershell
git clone https://github.com/DEIN-NAME/rezeptbuch.git
cd rezeptbuch
php -S 127.0.0.1:8080
```

Im Browser öffnen: <http://127.0.0.1:8080>

Beenden mit `Strg + C`.

<details>
<summary>Ohne winget: PHP von Hand einrichten</summary>

1. Von <https://windows.php.net/download/> das **Thread Safe**-ZIP für x64
   laden und nach `C:\php` entpacken.
2. `C:\php\php.ini-development` nach `C:\php\php.ini` kopieren.
3. In `php.ini` diese drei Zeilen suchen und das führende `;` entfernen:

   ```ini
   extension=curl
   extension=pdo_sqlite
   extension=gd
   ```

4. `C:\php` in die Umgebungsvariable `Path` aufnehmen
   (Systemsteuerung → System → Erweiterte Systemeinstellungen →
   Umgebungsvariablen).
5. Neue PowerShell öffnen und mit `php -v` prüfen.

</details>

### Läuft es?

```bash
php -v
```

Muss **8.0 oder neuer** melden. Und diese drei Erweiterungen müssen aktiv sein:

```bash
php -m
```

Gesucht: `curl`, `pdo_sqlite`, `gd`. Fehlt `pdo_sqlite`, startet die App im
dateibasierten Modus und der Wochenplan-Verlauf funktioniert nicht.

---

## Gemini-API-Key einrichten (optional)

Nur nötig für KI-Import, Thermomix-Umwandlung und den Bildexport des
Wochenplans.

1. Key kostenlos erzeugen unter <https://aistudio.google.com/apikey>
2. Im Projektverzeichnis eine Datei `.env` anlegen:

   ```env
   GEMINI_API_KEY=dein_key_hier
   ```

3. Server neu starten.

Die `.env` steht in `.gitignore` und wird nie mitcommittet. **Teile sie
niemals** und lege sie nie in ein öffentliches Verzeichnis.

Weitere Schalter, alle optional:

```env
GEMINI_MODEL=gemini-2.5-flash,gemini-2.0-flash   # Textmodell + Fallbacks
GEMINI_NANO_BANANA_MODEL=gemini-3.1-flash-image  # Bildmodell
DATA_BACKEND=auto                                # auto | files
SQLITE_PATH=/eigener/pfad/rezeptbuch.sqlite
```

---

## Was die App kann

**Rezepte** — manuell anlegen oder per KI aus Text, Foto oder Link einlesen.
Suchen, nach Kategorie und Typ filtern, mit 1–5 Sternen bewerten. Drei
Rezepttypen: Normal, Thermomix, Airfryer. Normale Rezepte lassen sich per
Gemini in eine Thermomix-Fassung übersetzen.

**Kochmodus** (`kochen.php?id=1`) — ein Schritt pro Vollbild in großer Schrift.
Zeitangaben im Text werden erkannt und als antippbare Timer angeboten. Die
Portionszahl lässt sich live ändern, alle Mengen rechnen mit — inklusive
Brüchen und Mengenbereichen. Zutaten stehen beim Schritt, der sie braucht. Der
Bildschirm bleibt an, der Fortschritt übersteht einen Reload.

**Wochenplan** — würfelt sieben Rezepte, gewichtet nach Bewertung: beliebte
kommen häufiger, unbeliebte aber nie gar nicht. Einzelne Tage lassen sich
freistellen, Rezepte per Drag & Drop tauschen. Ein bestätigter Plan wandert
unveränderlich in den Verlauf.

**Einkaufsliste** — entsteht aus dem aktuellen Plan, freigestellte Tage werden
übersprungen. Die KI gruppiert die Zutaten nach Supermarkt-Abteilungen.

**Sonst noch** — Darkmode mit Umschalter, Kategorienverwaltung im Admin.

### Seiten

| Datei | Zweck |
|---|---|
| `index.php` | Rezept hinzufügen, inkl. KI-Import aus Text, Bild oder Link |
| `rezepte.php` | Übersicht mit Suche, Filtern, Detailansicht, Bewertung |
| `wochenplan.php` | Plan anzeigen, generieren, tauschen, bestätigen, exportieren |
| `kochen.php` | Kochmodus, aufgerufen mit `?id=<Rezept-ID>` |
| `einkaufsliste.php` | Einkaufsliste erzeugen und drucken |
| `admin.php` | Kategorien, Wochenplan-Vorlage, Gemini-Bildmodell |

---

## Demo-Daten

Mitgeliefert sind drei eigens für dieses Projekt geschriebene Rezepte samt
Bildern — je eines pro Rezepttyp, damit alle Funktionen sofort etwas zu zeigen
haben.

Zurücksetzen oder neu erzeugen:

```bash
php scripts/seed-demo-data.php
```

**Achtung:** Das Skript legt `data/rezeptbuch.sqlite` neu an und löscht dabei
alles Vorhandene. Wer schon eigene Rezepte erfasst hat, ruft es nicht mehr auf.

Die Bilder unter `assets/demo/` sind KI-generiert und zeigen keine real
gekochten Gerichte. Rezepttexte und Bilder stehen unter derselben Lizenz wie
der Code.

---

## Technik

Backend ist reines PHP ohne Framework. Die Seiten werden serverseitig
gerendert, die Logik im Browser steckt in `js/app.js` — mit einer Ausnahme: der
Kochmodus bringt mit `js/cook.js` und `js/cook-format.js` eigene Skripte mit.
Gespeichert wird in SQLite über `db.php` und `sqlite-store.php`; fehlt
`PDO SQLite`, weicht die App auf Dateien aus.

Alle Schemaänderungen laufen beim ersten Request automatisch per `ALTER TABLE`
durch — kein manueller Migrationsschritt.

### Verzeichnisse

| Pfad | Inhalt |
|---|---|
| `api/` | JSON-Endpunkte für Rezepte, Planung, Einkaufsliste, Admin, Gemini |
| `data/` | SQLite-Datenbank, Logs, Vorlagen, Druckjobs |
| `rezepte/images/` | hochgeladene Rezeptbilder |
| `assets/demo/` | Bilder der drei Demo-Rezepte |
| `scripts/` | Deployment, Passwortschutz, Demo-Daten |
| `tests/` | Testfälle der Kochmodus-Fachlogik |

### Tests und Prüfungen

Keine zusätzlichen Abhängigkeiten nötig:

```bash
php tests/run-php.php
```

```bash
node --test "tests/*.test.js"
```

Syntaxprüfung einzelner Dateien:

```bash
php -l api/save-recipe.php
```

---

## Auf einen Webserver bringen

Voraussetzung ist ein Hoster mit PHP 8 und Apache oder LiteSpeed. Lies vorher
den Sicherheitsabschnitt ganz oben.

```bash
cp deploy.env.example deploy.env
```

Ausfüllen, dann erst einmal trocken laufen lassen:

```bash
./scripts/deploy.sh --dry-run
```

```bash
./scripts/deploy.sh
```

Beim allerersten Mal einmalig die Datenbank mitschicken, danach nie wieder —
sonst überschreibst du bei jedem Deployment alles, was auf dem Server
entstanden ist:

```bash
./scripts/deploy.sh --with-database
```

Nicht übertragen werden `.env`, `deploy.env`, `tests/`, `*.md` und Logs.

Auf dem Server einmalig nötig:

- `.env` von Hand anlegen, sie wird absichtlich nie übertragen
- `data/` und `rezepte/` beschreibbar machen
- prüfen, dass `.env` und die Datenbank per HTTP **nicht** erreichbar sind
- Schreibschutz aktivieren: `./scripts/set-edit-password.sh`

Die `.htpasswd` braucht Modus **644**. Bei 600 kann der Webserver sie nicht
lesen und weist *jedes* Passwort ab — das sieht exakt aus wie ein falsches
Passwort und kostet gern eine Stunde Suche.

---

## Lizenz

MIT — siehe [LICENSE](LICENSE).

Der Kochmodus ist konzeptionell von [MorphCook](https://github.com/TheMorpheus407/morphcook)
abgeleitet (ebenfalls MIT). Was genau übernommen wurde und was eigenständig
entstanden ist, steht in [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).

Importierst du Rezepte aus Kochbüchern, Zeitschriften oder von Plattformen wie
Cookidoo, bleiben diese Texte urheberrechtlich geschützt. Für den privaten
Gebrauch ist das unproblematisch — veröffentliche deine gefüllte Datenbank
aber nicht.
