#!/usr/bin/env bash
#
# Traegt das FTP-Passwort in deploy.env ein.
#
#   ./scripts/set-deploy-password.sh
#
# Die Eingabe ist verdeckt, das Passwort wird nirgends ausgegeben, nicht in
# der Shell-Historie abgelegt und nicht als Argument uebergeben. deploy.env
# steht in .gitignore und bekommt Rechte 600, ist also nur fuer dich lesbar.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG="$ROOT/deploy.env"

if [ ! -f "$CONFIG" ]; then
    echo "deploy.env fehlt. Erst anlegen:" >&2
    echo "    cp deploy.env.example deploy.env" >&2
    exit 1
fi

printf 'FTP-Passwort für %s\n' "$(grep -E '^DEPLOY_USER=' "$CONFIG" | cut -d= -f2-)"
printf 'Eingabe bleibt unsichtbar.\n\n'

printf 'Passwort: '
IFS= read -rs password
printf '\n'

if [ -z "$password" ]; then
    echo "Nichts eingegeben, Datei unverändert." >&2
    exit 1
fi

printf 'Wiederholen: '
IFS= read -rs password_repeat
printf '\n\n'

if [ "$password" != "$password_repeat" ]; then
    echo "Die beiden Eingaben stimmen nicht überein, Datei unverändert." >&2
    exit 1
fi

# Zeilenweise neu schreiben, damit das Passwort nie in einem sed-Ausdruck und
# damit nie in der Prozessliste landet.
tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT
chmod 600 "$tmp"

written=0
while IFS= read -r line || [ -n "$line" ]; do
    case "$line" in
        DEPLOY_PASS=*)
            printf 'DEPLOY_PASS=%s\n' "$password" >> "$tmp"
            written=1
            ;;
        *)
            printf '%s\n' "$line" >> "$tmp"
            ;;
    esac
done < "$CONFIG"

if [ "$written" -eq 0 ]; then
    printf 'DEPLOY_PASS=%s\n' "$password" >> "$tmp"
fi

cat "$tmp" > "$CONFIG"
chmod 600 "$CONFIG"

unset password password_repeat

printf 'Gespeichert in deploy.env (Rechte 600, von Git ignoriert).\n\n'
printf 'Weiter mit:\n'
printf '    ./scripts/deploy.sh --dry-run\n'
