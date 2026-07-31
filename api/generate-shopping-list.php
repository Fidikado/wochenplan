<?php
header('Content-Type: application/json');

$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../sqlite-store.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(sqlite_load_current_shopping_list($config));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Methode nicht erlaubt']);
    exit;
}

$plan = sqlite_load_current_plan($config);
if (!$plan || empty($plan['plan'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Kein Wochenplan vorhanden']);
    exit;
}

$alleZutaten = [];
foreach ($plan['plan'] as $day) {
    if (!empty($day['disabled'])) {
        continue;
    }
    $body = (string)(($day['recipe'] ?? [])['body'] ?? '');
    if (!preg_match('/## Zutaten\n([\s\S]*?)(?=\n## |$)/', $body, $match)) {
        continue;
    }

    foreach (explode("\n", trim($match[1])) as $line) {
        $line = trim((string)preg_replace('/^- /', '', trim($line)));
        if ($line !== '') {
            $alleZutaten[] = $line;
        }
    }
}

if (empty($alleZutaten)) {
    http_response_code(400);
    echo json_encode(['error' => 'Keine Zutaten in den Rezepten gefunden']);
    exit;
}

require_once __DIR__ . '/gemini.php';

$zutatenListe = implode("\n", $alleZutaten);
$prompt = <<<PROMPT
Erstelle aus den folgenden Zutaten mehrerer Rezepte eine Einkaufsliste.
Antworte ausschließlich mit einem JSON-Objekt.

Regeln:
- Fasse gleiche Zutaten zusammen und addiere die Mengen
- Verwende einkaufsfreundliche Mengenangaben (z.B. nicht "1 Prise", sondern sinnvolle Packungsgrößen)
- Wandle Mengenangaben wie TL (Teelöffel) und EL (Esslöffel) in kaufbare Mengen um (z.B. "2 TL Paprikapulver" → "1 Packung Paprikapulver", "3 EL Tomatenmark" → "1 Tube Tomatenmark", "1 TL Zimt" → "1 Packung Zimt")
- Wenn mehrere Rezepte dieselbe Zutat in TL/EL angeben, fasse sie zu einer sinnvollen Packungsgröße zusammen
- Kategorisiere nach Supermarkt-Abteilungen
- Lasse Basis-Zutaten weg die man typischerweise zuhause hat (Salz, Pfeffer, Öl, Wasser, Zucker, Mehl)
- Alle Texte auf Deutsch

JSON-Format:
{
  "kategorien": [
    { "name": "Obst & Gemüse", "zutaten": ["3 Zwiebeln", "500g Tomaten"] },
    { "name": "Fleisch & Fisch", "zutaten": ["400g Hackfleisch"] },
    { "name": "Milchprodukte", "zutaten": ["200g Käse"] },
    { "name": "Trockenwaren & Beilagen", "zutaten": ["500g Nudeln"] },
    { "name": "Sonstiges", "zutaten": ["1 Dose Tomaten"] }
  ]
}

Hinweis: Leere Kategorien weglassen. Nur Kategorien ausgeben die auch Zutaten enthalten.

Zutaten aus den Rezepten:
$zutatenListe
PROMPT;

$result = gemini_request($config, [['text' => $prompt]]);
if (isset($result['error'])) {
    http_response_code(500);
    echo json_encode($result);
    exit;
}

$data = sqlite_save_current_shopping_list($config, [
    'erstellt' => date('Y-m-d H:i:s'),
    'wochenplan_erstellt' => (string)($plan['erstellt'] ?? ''),
    'kategorien' => $result['kategorien'] ?? [],
]);

echo json_encode($data);
