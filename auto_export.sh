#!/bin/bash
# ============================================================
#  auto_export.sh
#  run crontab -e
#  Output: ~/Documents/plant_export/ at /opt/lampp/plant/backups/
# ============================================================

DB_NAME="plant"
DB_USER="root"
DB_PASS=""

DOC_DIR="$HOME/Documents/plant_export"
LAMPP_DIR="/opt/lampp/htdocs/plant/backups"
LOG="$DOC_DIR/export_log.txt"

mkdir -p "$DOC_DIR"
mkdir -p "$LAMPP_DIR"

TIMESTAMP=$(date '+%Y-%m-%d_%H-%M-%S')
FILENAME="plant_${TIMESTAMP}.sql"

if ! /opt/lampp/bin/mysqladmin -u "$DB_USER" ping --silent 2>/dev/null; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] SKIPPED: LAMPP/MySQL is not running" >> "$LOG"
    exit 0
fi

if [ -z "$DB_PASS" ]; then
    /opt/lampp/bin/mysqldump -u "$DB_USER" --no-tablespaces "$DB_NAME" > "/tmp/$FILENAME" 2>/dev/null
else
    /opt/lampp/bin/mysqldump -u "$DB_USER" -p"$DB_PASS" --no-tablespaces "$DB_NAME" > "/tmp/$FILENAME" 2>/dev/null
fi

if [ $? -eq 0 ] && [ -s "/tmp/$FILENAME" ]; then
    cp "/tmp/$FILENAME" "$DOC_DIR/$FILENAME"
    cp "/tmp/$FILENAME" "$LAMPP_DIR/$FILENAME"

    ls -t "$DOC_DIR"/*.sql 2>/dev/null | tail -n +11 | xargs -r rm
    ls -t "$LAMPP_DIR"/*.sql 2>/dev/null | tail -n +11 | xargs -r rm

    echo "[$(date '+%Y-%m-%d %H:%M:%S')] SUCCESS: $FILENAME" >> "$LOG"
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: mysqldump failed" >> "$LOG"
fi

rm -f "/tmp/$FILENAME"