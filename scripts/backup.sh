#!/usr/bin/env bash
# ============================================================
# Sauvegarde quotidienne GéoMonkey — à lancer via cron :
#   0 3 * * * /var/www/geomonkey/scripts/backup.sh >> /var/log/geomonkey-backup.log 2>&1
# Prérequis : DATABASE_URL dans l'environnement (ou lisible
# depuis le .env.local du projet).
# ============================================================
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="${BACKUP_DIR:-$PROJECT_DIR/var/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
STAMP="$(date +%F_%H%M)"
mkdir -p "$BACKUP_DIR"

echo "=== Sauvegarde GéoMonkey — $(date '+%d/%m/%Y %H:%M') ==="

# 1) Dump PostgreSQL (extrait DATABASE_URL du .env.local si absente)
if [ -z "${DATABASE_URL:-}" ] && [ -f "$PROJECT_DIR/.env.local" ]; then
    DATABASE_URL="$(grep -E '^DATABASE_URL=' "$PROJECT_DIR/.env.local" | head -1 | cut -d= -f2-)"
    export DATABASE_URL
fi
if [ -z "${DATABASE_URL:-}" ]; then
    echo "ERREUR : DATABASE_URL introuvable." >&2
    exit 1
fi

DB_NAME="$(echo "$DATABASE_URL" | sed -E 's#.*/([^/?]+)(\?.*)?$#\1#')"
DUMP="$BACKUP_DIR/db_${STAMP}.sql.gz"

if command -v docker >/dev/null 2>&1 && [ -n "${POSTGRES_CONTAINER:-}" ]; then
    docker exec "$POSTGRES_CONTAINER" pg_dump -U postgres "$DB_NAME" | gzip > "$DUMP"
else
    pg_dump "$DB_NAME" | gzip > "$DUMP"
fi
echo "DB     : $DUMP ($(du -h "$DUMP" | cut -f1))"

# 2) Images et vidéos téléversées
IMAGES_TAR="$BACKUP_DIR/images_${STAMP}.tar.gz"
tar -czf "$IMAGES_TAR" -C "$PROJECT_DIR/public" images 2>/dev/null || true
echo "Images : $IMAGES_TAR ($(du -h "$IMAGES_TAR" | cut -f1))"

# 3) Rotation : purger les sauvegardes de plus de RETENTION_DAYS jours
find "$BACKUP_DIR" -name 'db_*.sql.gz' -mtime +"$RETENTION_DAYS" -delete
find "$BACKUP_DIR" -name 'images_*.tar.gz' -mtime +"$RETENTION_DAYS" -delete

echo "=== Terminé. ($(ls "$BACKUP_DIR" | wc -l) archives conservées) ==="
