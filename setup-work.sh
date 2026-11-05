#!/bin/bash

# ==============================
# CONFIGURAÇÕES (EDITE AQUI)
# ==============================

PROJECT_PATH="$(pwd)"
SERVICE_NAME="psi4-worker"
USER="www-data"
PHP_PATH="/usr/bin/php"
WORKERS=2

# ==============================
# VALIDAÇÕES
# ==============================

if [ ! -d "$PROJECT_PATH" ]; then
  echo "❌ Projeto não encontrado em: $PROJECT_PATH"
  exit 1
fi

echo "🚀 Configurando Laravel Queue Worker..."

# ==============================
# CRIAR SERVICE TEMPLATE
# ==============================

SERVICE_FILE="/etc/systemd/system/${SERVICE_NAME}@.service"

sudo bash -c "cat > $SERVICE_FILE" <<EOF
[Unit]
Description=Laravel Queue Worker %i
After=network.target

[Service]
User=${USER}
Group=${USER}
Restart=always
RestartSec=5

WorkingDirectory=${PROJECT_PATH}

ExecStart=${PHP_PATH} artisan queue:work \
--sleep=3 \
--tries=3 \
--max-time=3600 \
--max-jobs=1000 \
--memory=128 \
--timeout=90

Nice=10
LimitNOFILE=10000

[Install]
WantedBy=multi-user.target
EOF

echo "✅ Service criado em $SERVICE_FILE"

# ==============================
# RELOAD SYSTEMD
# ==============================

sudo systemctl daemon-reexec
sudo systemctl daemon-reload

# ==============================
# ATIVAR E SUBIR WORKERS
# ==============================

for i in $(seq 1 $WORKERS)
do
  echo "⚙️ Iniciando worker $i..."
  sudo systemctl enable ${SERVICE_NAME}@$i
  sudo systemctl start ${SERVICE_NAME}@$i
done

# ==============================
# STATUS FINAL
# ==============================

echo ""
echo "🎉 Setup concluído!"
echo ""
echo "📊 Ver status:"
echo "systemctl status ${SERVICE_NAME}@1"
echo ""
echo "📜 Ver logs:"
echo "journalctl -u ${SERVICE_NAME}@1 -f"
echo ""
echo "🔄 Reiniciar todos:"
echo "systemctl restart ${SERVICE_NAME}@*"
