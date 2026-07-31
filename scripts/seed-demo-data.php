<?php
/**
 * Erzeugt eine frische Demo-Datenbank mit drei Beispielrezepten.
 *
 * Aufruf aus dem Projektverzeichnis:
 *   php scripts/seed-demo-data.php
 *
 * Legt data/rezeptbuch.sqlite NEU an. Eine bereits vorhandene Datei wird
 * vorher geloescht, damit keine Reste alter Datensaetze in den Datenbankseiten
 * zurueckbleiben. Wer schon eigene Rezepte erfasst hat, sollte das Skript
 * also nicht mehr ausfuehren.
 *
 * Die drei Rezepte sind eigens fuer dieses Projekt geschrieben und stehen
 * unter derselben Lizenz wie der Code. Sie zeigen bewusst alle drei
 * Rezepttypen (normal, thermomix, airfryer).
 */

$root = dirname(__DIR__);
$config = require $root . '/config.php';
require_once $root . '/sqlite-store.php';

$dbPath = $root . '/data/rezeptbuch.sqlite';
if (!is_dir(dirname($dbPath))) {
    mkdir(dirname($dbPath), 0775, true);
}
foreach ([$dbPath, $dbPath . '-wal', $dbPath . '-shm'] as $f) {
    if (is_file($f)) {
        unlink($f);
    }
}

$recipes = [
    [
        'title'      => 'Spaghetti mit schneller Tomatensauce',
        'kategorie'  => 'hauptspeise',
        'typ'        => 'normal',
        'kochzeit'   => 25,
        'portionen'  => 4,
        'tags'       => ['Nudeln', 'Vegetarisch', 'Feierabend'],
        'anmerkung'  => 'Beispielrezept dieses Projekts.',
        'image'      => 'assets/demo/spaghetti-tomatensauce.webp',
        'zutaten'    => implode("\n", [
            '500 g Spaghetti',
            '2 Dosen stückige Tomaten (je 400 g)',
            '1 Zwiebel',
            '2 Knoblauchzehen',
            '3 EL Olivenöl',
            '1 TL Zucker',
            '1 Handvoll frisches Basilikum',
            'Salz, Pfeffer',
            '40 g Parmesan, gerieben',
        ]),
        'zubereitung' => implode("\n", [
            'Zwiebel und Knoblauch schälen und fein würfeln. Das Basilikum waschen, die Blätter abzupfen und grob zerrupfen.',
            'Das Olivenöl in einem weiten Topf erhitzen. Zwiebel darin bei mittlerer Hitze 5 Minuten glasig dünsten, den Knoblauch erst in der letzten Minute zugeben, damit er nicht bitter wird.',
            'Die stückigen Tomaten angießen, Zucker einrühren und mit Salz und Pfeffer würzen. Die Sauce offen 15 Minuten leise köcheln lassen, bis sie sichtbar eindickt.',
            'Währenddessen reichlich Salzwasser aufkochen und die Spaghetti darin nach Packungsangabe bissfest garen. Vor dem Abgießen eine Tasse Nudelwasser beiseitestellen.',
            'Das Basilikum unter die Sauce heben. Ist die Sauce zu dick, schluckweise Nudelwasser zugeben, bis sie gut an den Nudeln haftet.',
            'Die abgetropften Spaghetti in den Topf geben, einmal kräftig durchschwenken und mit dem Parmesan bestreut servieren.',
        ]),
    ],
    [
        'title'      => 'Möhren-Ingwer-Suppe',
        'kategorie'  => 'suppe',
        'typ'        => 'thermomix',
        'kochzeit'   => 30,
        'portionen'  => 4,
        'tags'       => ['Suppe', 'Vegetarisch', 'Meal Prep'],
        'anmerkung'  => 'Beispielrezept dieses Projekts.',
        'image'      => 'assets/demo/moehren-ingwer-suppe.webp',
        'zutaten'    => implode("\n", [
            '800 g Möhren',
            '1 Zwiebel',
            '20 g frischer Ingwer',
            '20 g Olivenöl',
            '700 g Wasser',
            '1 EL Gemüsebrühe, gekörnt',
            '100 g Sahne',
            '1 Spritzer Zitronensaft',
            'Salz, Pfeffer',
            '2 EL Kürbiskerne zum Bestreuen',
        ]),
        'zubereitung' => implode("\n", [
            'Möhren schälen und in grobe Stücke schneiden. Zwiebel und Ingwer schälen und halbieren.',
            'Zwiebel und Ingwer in den Mixtopf geben und 5 Sek./Stufe 5 zerkleinern. Mit dem Spatel nach unten schieben.',
            'Das Olivenöl zugeben und 3 Min./120 °C/Stufe 1 andünsten.',
            'Möhren, Wasser und Gemüsebrühe einfüllen und 20 Min./100 °C/Stufe 1 garen.',
            'Anschließend 1 Min./Stufe 10 fein pürieren. Dabei den Messbecher aufsetzen und ein Tuch darüberhalten, heiße Suppe spritzt gern.',
            'Sahne und Zitronensaft unterrühren, mit Salz und Pfeffer abschmecken und mit den Kürbiskernen bestreut servieren.',
        ]),
    ],
    [
        'title'      => 'Knusprige Kartoffelspalten aus dem Airfryer',
        'kategorie'  => 'beilage',
        'typ'        => 'airfryer',
        'kochzeit'   => 35,
        'portionen'  => 3,
        'tags'       => ['Beilage', 'Vegetarisch', 'Kartoffeln'],
        'anmerkung'  => 'Beispielrezept dieses Projekts.',
        'image'      => 'assets/demo/kartoffelspalten-airfryer.webp',
        'zutaten'    => implode("\n", [
            '800 g festkochende Kartoffeln',
            '2 EL Olivenöl',
            '1 TL Paprikapulver, edelsüß',
            '1 TL getrockneter Rosmarin',
            '½ TL Knoblauchpulver',
            'Salz, Pfeffer',
            '250 g Quark mit 3 EL Milch und frischen Kräutern für den Dip',
        ]),
        'zubereitung' => implode("\n", [
            'Die Kartoffeln gründlich waschen und ungeschält längs in Spalten schneiden, etwa acht pro Kartoffel. Gleichmäßige Dicke ist wichtiger als exakte Zahl.',
            'Die Spalten 10 Minuten in kaltes Wasser legen, damit Stärke austritt. Danach abgießen und mit einem Küchentuch sehr gründlich trocken tupfen — feuchte Kartoffeln werden nicht knusprig.',
            'In einer Schüssel mit Öl, Paprikapulver, Rosmarin, Knoblauchpulver, Salz und Pfeffer mischen, bis jede Spalte glänzt.',
            'Den Airfryer 3 Minuten auf 200 °C vorheizen.',
            'Die Spalten in einer Lage in den Korb legen, bei zu wenig Platz lieber in zwei Durchgängen arbeiten. 20 Minuten bei 200 °C garen und den Korb nach der Hälfte einmal kräftig schütteln.',
            'Für den Dip Quark mit Milch glattrühren, die gehackten Kräuter unterheben und salzen. Zu den heißen Spalten servieren.',
        ]),
    ],
];

$created = [];
foreach ($recipes as $input) {
    $result = sqlite_recipe_create($config, $input);
    $created[] = $result;
    printf("angelegt: #%-3d %s\n", $result['id'] ?? 0, $result['title'] ?? '?');
}

// Datei kompakt schreiben, damit keine unbelegten Seiten mitgeliefert werden.
db($config)->exec('VACUUM');

printf("\n%d Rezepte in %s\n", count($created), $dbPath);
printf("Groesse: %.1f KB\n", filesize($dbPath) / 1024);
