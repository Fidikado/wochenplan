#!/usr/bin/env bash
#
# Richtet den Schreibschutz ein: Lesen bleibt fuer alle offen, Aendern und
# Loeschen verlangen ein Passwort.
#
#   ./scripts/set-edit-password.sh          einrichten oder Passwort aendern
#   ./scripts/set-edit-password.sh --off    Schutz wieder abschalten
#
# Erzeugt .htpasswd und schaltet den Block zwischen den Markern
# "# BEGIN Schreibschutz" und "# END Schreibschutz" in .htaccess um. Beides
# wird beim naechsten ./scripts/deploy.sh hochgeladen. Die Eingabe bleibt
# verdeckt, .htpasswd steht in .gitignore.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
HTACCESS="$ROOT/.htaccess"
HTPASSWD="$ROOT/.htpasswd"

# Schaltet den Markerblock um und prueft danach, dass die Direktiven paarig
# sind. Eine unpaarige <FilesMatch> wuerde Apache mit HTTP 500 quittieren -
# und zwar fuer die komplette Seite, nicht nur fuer die geschuetzten Pfade.
toggle_block() {
    local mode="$1"
    python3 - "$HTACCESS" "$mode" <<'PY'
import sys

path, mode = sys.argv[1], sys.argv[2]
lines = open(path, encoding='utf-8').read().split('\n')

try:
    start = next(i for i, l in enumerate(lines) if l.strip() == '# BEGIN Schreibschutz')
    end = next(i for i, l in enumerate(lines) if l.strip() == '# END Schreibschutz')
except StopIteration:
    sys.exit('Marker in .htaccess nicht gefunden')

for i in range(start + 1, end):
    stripped = lines[i].lstrip('#')
    if mode == 'on':
        lines[i] = stripped
    elif not lines[i].startswith('#'):
        lines[i] = '#' + lines[i]

text = '\n'.join(lines)

# Nur aktive, also nicht auskommentierte Direktiven zaehlen.
active = [l for l in text.split('\n') if not l.lstrip().startswith('#')]
opened = sum(l.count('<FilesMatch') for l in active)
closed = sum(l.count('</FilesMatch>') for l in active)
if opened != closed:
    sys.exit(f'Abbruch: {opened} <FilesMatch> aber {closed} </FilesMatch> - '
             'die Datei wurde nicht geändert.')

open(path, 'w', encoding='utf-8').write(text)
PY
}

# --- Abschalten ----------------------------------------------------------

if [ "${1:-}" = "--off" ]; then
    toggle_block off
    rm -f "$HTPASSWD"
    echo "Schreibschutz abgeschaltet, .htpasswd gelöscht."
    echo "Wirksam auf dem Server nach dem nächsten ./scripts/deploy.sh"
    exit 0
fi

# --- Werkzeug pruefen ----------------------------------------------------

if ! command -v htpasswd >/dev/null; then
    echo "htpasswd fehlt. Auf macOS ist es normalerweise dabei." >&2
    echo "Sonst: brew install httpd" >&2
    exit 1
fi

# --- Passwort abfragen ---------------------------------------------------

printf 'Benutzername [koch]: '
IFS= read -r username
username="${username:-koch}"

printf 'Passwort: '
IFS= read -rs password
printf '\n'

if [ ${#password} -lt 8 ]; then
    echo "Bitte mindestens 8 Zeichen, die Seite steht öffentlich im Netz." >&2
    exit 1
fi

printf 'Wiederholen: '
IFS= read -rs password_repeat
printf '\n\n'

if [ "$password" != "$password_repeat" ]; then
    echo "Die beiden Eingaben stimmen nicht überein, nichts geändert." >&2
    exit 1
fi

# -i liest von stdin, das Passwort landet nie in der Prozessliste.
# -B erzwingt bcrypt statt des schwachen Standardverfahrens.
printf '%s' "$password" | htpasswd -i -B -c "$HTPASSWD" "$username" 2>/dev/null

# 644 statt 600: der Webserver laeuft unter einem anderen Benutzer und kann
# die Datei sonst nicht lesen - dann wird JEDES Passwort abgelehnt, egal ob
# richtig oder falsch. Ueber HTTP ist sie trotzdem nicht erreichbar, das
# sperrt die Punktdatei-Regel weiter oben in dieser .htaccess.
chmod 644 "$HTPASSWD"
unset password password_repeat

toggle_block on

echo "Schreibschutz aktiviert für Benutzer: $username"
echo ".htpasswd erzeugt (bcrypt, Rechte 644, von Git ignoriert)."
echo
echo "Offen bleiben:      Rezepte ansehen, suchen, filtern, Kochmodus."
echo "Passwort verlangen: Anlegen, Bearbeiten, Löschen, KI-Importe, Admin."
echo
echo "Hochladen mit:"
echo "    ./scripts/deploy.sh"
