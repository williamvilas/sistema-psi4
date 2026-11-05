#!/bin/bash

# Diretório atual do projeto Laravel
PROJECT_DIR="$PWD"

# Nome dos arquivos de backup
DATE=$(date +%Y-%m-%d_%H-%M-%S)
BACKUP_NAME="laravel-backup-$DATE.tar.gz"
DB_BACKUP_NAME="db-backup-$DATE.sql"

# Pasta de destino
DEST_DIR="$PROJECT_DIR/.backups"
mkdir -p "$DEST_DIR"

# ========================
# Carrega variáveis do .env
# ========================
set -a
source "$PROJECT_DIR/.env"
set +a

# Usa as variáveis do .env
DB_HOST=${DB_HOST:-127.0.0.1}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=$DB_DATABASE
DB_USERNAME=$DB_USERNAME
DB_PASSWORD=$DB_PASSWORD

# Faz o dump do banco com estrutura e dados
mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" > "$DEST_DIR/$DB_BACKUP_NAME"

# Entra no diretório do projeto
cd "$PROJECT_DIR"

# Cria o backup do código-fonte
tar --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='storage/logs' \
    --exclude='storage/framework/cache' \
    --exclude='.backups' \
    --exclude='.git' \
    -czf "$DEST_DIR/$BACKUP_NAME" .

echo "✅ Backup do código: $DEST_DIR/$BACKUP_NAME"
echo "✅ Backup do banco: $DEST_DIR/$DB_BACKUP_NAME"
