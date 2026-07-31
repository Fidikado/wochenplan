# Third Party Notices

Diese Datei dokumentiert fremde Software, deren Code oder Konzepte in dieses
Projekt eingeflossen sind, sowie die Herkunft der mitgelieferten Demo-Inhalte.

---

## Demo-Inhalte

Die drei Rezepte in `data/rezeptbuch.sqlite` sind eigens fuer dieses Projekt
geschrieben und stammen aus keiner fremden Quelle. Sie stehen unter derselben
MIT-Lizenz wie der Code.

Die Bilder in `assets/demo/` wurden mit einem KI-Bildgenerator (Nano Banana 2)
erzeugt, die Kopfgrafiken in `assets/headers/` mit GPT-4o. Beide tragen eine
C2PA-Kennzeichnung als `trainedAlgorithmicMedia`. Sie zeigen keine real
fotografierten Gerichte und tragen keine fremden Bildrechte.

Hinweis fuer eigene Bestaende: Rezepttexte aus Kochbuechern, Zeitschriften oder
von Plattformen wie Cookidoo bleiben urheberrechtlich geschuetzt. Fuer den
privaten Gebrauch ist das unproblematisch, eine damit gefuellte Datenbank
gehoert aber nicht in ein oeffentliches Repository.

---

## MorphCook

- Projekt: MorphCook
- Quelle: https://github.com/TheMorpheus407/morphcook
- Herangezogener Stand: Commit `3cf6f5f`, Verzeichnis `claude-opus-5/app/lib/screens/cook/`
- Lizenz: MIT

### Lizenztext

```
MIT License

Copyright (c) 2026 Cedric Mössner (The Morpheus)

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

### Art der Nutzung

**Es wurde keine Datei aus MorphCook kopiert.** MorphCook ist eine Flutter-App
in Dart, dieses Projekt ist PHP und JavaScript. Der Kochmodus wurde aus der
Analyse des Verhaltens neu implementiert. Einzelne Algorithmen sind jedoch
erkennbar nah adaptiert, deshalb erfolgt hier die Attribution.

#### Abgeleitet von MorphCook

| Konzept | MorphCook-Ursprung | Umsetzung hier |
|---|---|---|
| Zustandsautomat des Schritt-Timers (`configure`/`start`/`pause`/`reset`, genau ein Ablauf-Ereignis) | `cook_controller.dart`, Klasse `StepTimer` | `js/cook.js`, Objekt `timer` |
| Zutaten-zu-Schritt-Zuordnung per Namensheuristik, Wörter unter vier Zeichen ignorieren, Plural abschneiden | `cook_controller.dart`, `ingredientsMentionedIn()` und `_mentions()` | `cook-utils.php`, `cook_match_ingredients()`, `cook_ingredient_keywords()`, `cook_stem()` |
| Fortschrittsmodell einer laufenden Kochsession (Rezept, Schrittindex, Portionen, Timer-Rest) | `domain/collections.dart`, Klasse `CookProgress` | Tabelle `cook_progress` in `db.php`, Funktionen in `sqlite-store.php` |
| Visueller Blitz bei Timer-Ende, Wechsel zweier Farben, abgeschwächt bei reduzierter Bewegung | `cook_screen.dart`, `_FlashOverlay` | `.cook-flash` in `css/style.css`, `flash()` in `js/cook.js` |
| Bedienkonzept: ein Schritt pro Vollbild, Strichleiste als Fortschritt, Portionen-Pille im Kopf, Zurück/Weiter mit „Fertig" am Ende, Abschlussansicht | `cook_screen.dart` | `kochen.php`, `js/cook.js` |

#### Eigenständig in diesem Projekt entstanden

Diese Teile haben in MorphCook keine Entsprechung, weil dort der Rezeptkorpus
bereits strukturiert vorliegt:

- Erkennung von Zeitangaben aus deutschem Fließtext (`cook_detect_timers()`).
  MorphCook-Rezepte tragen ein autoriertes Feld `timer_seconds` je Schritt.
- Deutsche Mengen-Grammatik: Unicode-Brüche, Bereiche, Dezimalkomma,
  Einheitenliste, Erkennung gebündelter Zeilen (`cook_parse_ingredient()`).
- Satzweise Feinunterteilung langer Absätze mit Schutz deutscher Abkürzungen
  (`cook_split_sentences()`).
- Serverseitige Persistenz über SQLite statt lokalem Gerätespeicher, inklusive
  absolutem Timer-Ende, damit ein Browser-Reload keine Zeit verschluckt.
- Screen Wake Lock, Tastaturbedienung und `aria-live`-Ausgaben als
  Web-Entsprechungen zum Flutter-Bedienkonzept.

#### Nicht übernommen

Bewusst ausgelassen wurden `OneHandedCookModeController` (Tipp-zum-Blättern),
die Zweisprachigkeit über `Localized`/`S(lang)`, das Zutaten-Wörterbuch mit
`ingredientId`, Kalorienangaben sowie sämtliche Flutter-Bausteine
(`ChangeNotifier`, `provider`, `ThemeExtension`, `PopScope`, `HapticFeedback`).
