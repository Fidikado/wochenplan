#!/usr/bin/env bash
#
# Aktualisiert die App auf dem Webserver.
#
#   ./scripts/deploy.sh                  Update: Programmdateien ueberschreiben
#   ./scripts/deploy.sh --dry-run        nur anzeigen, nichts uebertragen
#   ./scripts/deploy.sh --with-database  lokale DB ueber die des Servers legen
#   ./scripts/deploy.sh --delete-remote  Ueberzaehliges auf dem Server loeschen
#
# Standardmaessig werden nur Dateien geschrieben, nie welche geloescht, und
# data/ sowie rezepte/ auf dem Server bleiben unberuehrt. Dort liegen die
# Datenbank und die hochgeladenen Bilder.
#
# Zugangsdaten kommen aus deploy.env (siehe deploy.env.example).
# Das Passwort wird nie ausgegeben und nie als Kommandozeilenargument
# uebergeben, damit es nicht in der Prozessliste auftaucht.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG="$ROOT/deploy.env"

DRY_RUN=0
WITH_DATABASE=0
DELETE_REMOTE=0
for arg in "$@"; do
    case "$arg" in
        --dry-run) DRY_RUN=1 ;;
        --with-database) WITH_DATABASE=1 ;;
        --delete-remote) DELETE_REMOTE=1 ;;
        -h|--help) sed -n '3,12p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Unbekannte Option: $arg" >&2; exit 1 ;;
    esac
done

# --- Konfiguration laden -------------------------------------------------

if [ ! -f "$CONFIG" ]; then
    cat >&2 <<MSG
Es gibt noch keine deploy.env.

    cp deploy.env.example deploy.env

Danach die Werte eintragen. Die Datei steht in .gitignore und wird
nie committet.
MSG
    exit 1
fi

set -a
# shellcheck disable=SC1090
. "$CONFIG"
set +a

DEPLOY_METHOD="${DEPLOY_METHOD:-ftp}"
DEPLOY_PASS="${DEPLOY_PASS:-}"
DEPLOY_SSH_KEY="${DEPLOY_SSH_KEY:-}"
DEPLOY_PORT="${DEPLOY_PORT:-}"
DEPLOY_URL="${DEPLOY_URL:-}"
DEPLOY_TLS_VERIFY="${DEPLOY_TLS_VERIFY:-yes}"

for required in DEPLOY_HOST DEPLOY_USER DEPLOY_PATH; do
    if [ -z "${!required:-}" ]; then
        echo "In deploy.env fehlt: $required" >&2
        exit 1
    fi
done

# --- Was nicht auf den Server gehoert ------------------------------------

EXCLUDES=(
    ".git"
    ".gitignore"
    ".claude"
    ".env"
    "deploy.env"
    "deploy.env.example"
    "tests"
    "scripts"
    "*.md"
    ".DS_Store"
    # data/ und rezepte/ gehoeren dem Server. Dort liegen die Datenbank, die
    # hochgeladenen Rezeptbilder, Vorlagen und Logs - alles Dinge, die auf dem
    # Server entstehen und die eine lokale Kopie nie ueberschreiben darf.
    "data"
    "rezepte"
)

# --- Zusammenfassung -----------------------------------------------------

echo "Ziel      $DEPLOY_USER@$DEPLOY_HOST:$DEPLOY_PATH"
echo "Verfahren $DEPLOY_METHOD"
echo "Modus     Update - Programmdateien werden überschrieben"
echo "          data/ und rezepte/ auf dem Server bleiben unangetastet"

if [ "$WITH_DATABASE" -eq 1 ]; then
    echo
    echo "  ACHTUNG --with-database: die lokale Datenbank überschreibt die"
    echo "  Version auf dem Server. Alle dort angelegten Rezepte gehen verloren."
fi

if [ "$DELETE_REMOTE" -eq 1 ]; then
    echo
    echo "  ACHTUNG --delete-remote: Dateien auf dem Server, die es lokal nicht"
    echo "  gibt, werden gelöscht."
fi

[ "$DRY_RUN" -eq 1 ] && { echo; echo "Probelauf: es wird nichts geschrieben."; }
echo

if [ "$DRY_RUN" -eq 0 ]; then
    read -r -p "Deployment starten? [j/N] " answer
    case "$answer" in
        j|J|y|Y) ;;
        *) echo "Abgebrochen."; exit 0 ;;
    esac
fi

# --- Übertragen ----------------------------------------------------------

case "$DEPLOY_METHOD" in

    rsync)
        command -v rsync >/dev/null || { echo "rsync fehlt" >&2; exit 1; }

        args=(-az --human-readable)
        [ "$DELETE_REMOTE" -eq 1 ] && args+=(--delete-after)
        [ "$DRY_RUN" -eq 1 ] && args+=(--dry-run --itemize-changes)
        for item in "${EXCLUDES[@]}"; do args+=(--exclude "$item"); done

        ssh_cmd="ssh"
        [ -n "$DEPLOY_PORT" ] && ssh_cmd="$ssh_cmd -p $DEPLOY_PORT"
        [ -n "$DEPLOY_SSH_KEY" ] && ssh_cmd="$ssh_cmd -i $DEPLOY_SSH_KEY"
        args+=(-e "$ssh_cmd")

        rsync "${args[@]}" "$ROOT/" "$DEPLOY_USER@$DEPLOY_HOST:$DEPLOY_PATH/"
        ;;

    ftp|sftp)
        command -v rsync >/dev/null || { echo "rsync fehlt" >&2; exit 1; }
        if ! command -v lftp >/dev/null; then
            cat >&2 <<'MSG'
Für FTP und SFTP wird lftp gebraucht:

    brew install lftp

Alternativ in deploy.env DEPLOY_METHOD=rsync setzen, falls dein Hoster
SSH-Zugang anbietet - das ist ohnehin der bessere Weg.
MSG
            exit 1
        fi

        if [ "$DEPLOY_METHOD" = "ftp" ]; then
            scheme="ftp"
            # FTP ohne Verschlüsselung überträgt das Passwort im Klartext.
            tls_setup="set ftp:ssl-force true; set ftp:ssl-protect-data true;"
        else
            scheme="sftp"
            tls_setup=""
        fi

        target="$scheme://$DEPLOY_HOST"
        [ -n "$DEPLOY_PORT" ] && target="$target:$DEPLOY_PORT"

        # Erst mit rsync einen sauberen Zwischenstand bauen, dann diesen
        # unveraendert hochladen.
        #
        # Frueher liefen die Ausschluesse direkt in lftp - dabei greifen
        # --exclude-glob-Muster aber nur auf Dateinamen, nicht auf
        # Verzeichnisse. .git/, .claude/ und tests/ waeren so auf dem Server
        # gelandet, ein oeffentlich lesbares .git/ haette die komplette
        # Repository-Historie preisgegeben. rsync-Ausschluesse verhalten sich
        # eindeutig, und was im Zwischenstand nicht liegt, kann nicht hoch.
        STAGING="$(mktemp -d)"
        trap 'rm -rf "$STAGING"' EXIT

        stage_args=(-a --delete)
        for item in "${EXCLUDES[@]}"; do stage_args+=(--exclude "$item"); done
        rsync "${stage_args[@]}" "$ROOT/" "$STAGING/"

        if [ "$WITH_DATABASE" -eq 1 ]; then
            mkdir -p "$STAGING/data"
            cp "$ROOT/data/rezeptbuch.sqlite" "$STAGING/data/rezeptbuch.sqlite"
        fi

        echo "Zwischenstand: $(find "$STAGING" -type f | wc -l | tr -d ' ') Dateien"
        forbidden_list=(.git .claude .env deploy.env tests scripts rezepte)
        [ "$WITH_DATABASE" -eq 0 ] && forbidden_list+=(data)
        for forbidden in "${forbidden_list[@]}"; do
            if [ -e "$STAGING/$forbidden" ]; then
                echo "ABBRUCH: $forbidden liegt im Zwischenstand und darf nicht hoch." >&2
                exit 1
            fi
        done
        echo

        mirror_args=(-R --verbose)
        [ "$DELETE_REMOTE" -eq 1 ] && mirror_args+=(--delete)
        [ "$DRY_RUN" -eq 1 ] && mirror_args+=(--dry-run)

        key_setup=""
        [ -n "$DEPLOY_SSH_KEY" ] && key_setup="set sftp:connect-program \"ssh -a -x -i $DEPLOY_SSH_KEY\";"

        if [ "$DEPLOY_TLS_VERIFY" = "no" ]; then
            verify_setup="set ssl:verify-certificate false;"
            echo "Hinweis: Zertifikatsprüfung ist abgeschaltet (DEPLOY_TLS_VERIFY=no)."
            echo "         Die Übertragung bleibt verschlüsselt, der Server wird"
            echo "         aber nicht authentifiziert."
            echo
        else
            verify_setup="set ssl:verify-certificate true;"
        fi

        # Das Passwort geht über stdin an lftp, nicht über die Kommandozeile.
        LFTP_PASSWORD="$DEPLOY_PASS" lftp -c "
            set cmd:interactive false;
            $verify_setup
            $tls_setup
            $key_setup
            open --user \"$DEPLOY_USER\" --env-password \"$target\";
            mirror ${mirror_args[*]} \"$STAGING/\" \"$DEPLOY_PATH/\";
            bye
        "
        ;;

    *)
        echo "DEPLOY_METHOD muss ftp, sftp oder rsync sein (ist: $DEPLOY_METHOD)" >&2
        exit 1
        ;;
esac

echo
if [ "$DRY_RUN" -eq 1 ]; then
    echo "Probelauf beendet, es wurde nichts übertragen."
else
    echo "Fertig."
    [ -n "$DEPLOY_URL" ] && echo "$DEPLOY_URL"
    cat <<'MSG'

Nicht vergessen:
  - .env auf dem Server muss existieren, sie wird nie übertragen
  - data/ und rezepte/ müssen beschreibbar sein (chmod 775)
  - prüfen, dass https://deine-domain/.env NICHT abrufbar ist
MSG
fi
