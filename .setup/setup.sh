#!/bin/bash

set -e

echo "🚀 Atualizando sistema..."
sudo apt update && sudo apt upgrade -y

echo "📦 Instalando dependências básicas..."
sudo apt install -y software-properties-common apt-transport-https lsb-release ca-certificates curl unzip git build-essential

echo "🐘 Adicionando repositório PHP (Ondřej)..."
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

PHP_VERSION="8.5"

# Verifica se a versão existe no repositório
if ! apt-cache show php$PHP_VERSION >/dev/null 2>&1; then
  echo "❌ PHP $PHP_VERSION não está disponível no repositório."
  echo "👉 Verifique as versões disponíveis com: apt search php8."
  exit 1
fi

echo "🐘 Instalando PHP $PHP_VERSION e extensões..."

sudo apt install -y \
php$PHP_VERSION \
php$PHP_VERSION-cli \
php$PHP_VERSION-fpm \
php$PHP_VERSION-common \
php$PHP_VERSION-mysql \
php$PHP_VERSION-sqlite3 \
php$PHP_VERSION-pgsql \
php$PHP_VERSION-bcmath \
php$PHP_VERSION-bz2 \
php$PHP_VERSION-curl \
php$PHP_VERSION-intl \
php$PHP_VERSION-mbstring \
php$PHP_VERSION-xml \
php$PHP_VERSION-zip \
php$PHP_VERSION-gd \
php$PHP_VERSION-imagick \
php$PHP_VERSION-soap \
php$PHP_VERSION-readline

echo "🌐 Instalando Nginx..."
sudo apt install -y nginx
sudo systemctl enable nginx
sudo systemctl start nginx

echo "🗄️ Instalando MySQL Server..."
sudo apt install -y mysql-server
sudo systemctl enable mysql
sudo systemctl start mysql

echo "🔒 Rode manualmente depois se quiser:"
echo "   sudo mysql_secure_installation"

echo "📦 Instalando Composer..."
curl -sS https://getcomposer.org/installer -o composer-setup.php
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php

echo "🟢 Instalando NVM (Node Version Manager)..."

# Instala NVM no usuário atual (não usar sudo)
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash

# Carrega NVM na sessão atual
export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
[ -s "$NVM_DIR/bash_completion" ] && \. "$NVM_DIR/bash_completion"

# Instala automaticamente a versão LTS do Node
echo "📦 Instalando Node LTS via NVM..."
nvm install --lts
nvm use --lts
nvm alias default lts/*

echo "⚙️ Ajustando PHP CLI padrão..."
sudo update-alternatives --set php /usr/bin/php$PHP_VERSION

echo "🔁 Reiniciando serviços..."
sudo systemctl restart php$PHP_VERSION-fpm
sudo systemctl restart nginx
sudo systemctl restart mysql

source ~/.bashrc

echo ""
echo "✅ Instalação concluída!"
echo ""
echo "📌 Versões instaladas:"
php -v
nginx -v
mysql --version
node -v
composer -V
echo ""
echo "🔥 Ambiente pronto para rodar Laravel!"


