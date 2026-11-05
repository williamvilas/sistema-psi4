.PHONY: local prod stage clear logs reload push

# Comando do compose usado pelos targets clear/reload. Padrão: ambiente
# local (docker-compose.yml). No servidor, os targets prod/stage repassam o
# arquivo certo, ex.: make clear COMPOSE="docker compose -f docker-compose.stage.yml".
COMPOSE ?= docker compose
PROD = docker compose -f docker-compose.prod.yml
STAGE = docker compose -f docker-compose.stage.yml

# Ambiente local (docker-compose.yml) com Octane --watch. O composer install
# roda antes do `up` porque o Octane não sobe sem o vendor; o node_modules
# precisa existir no host por causa do chokidar usado pelo --watch.
local:
	[ -d node_modules ] || npm install
	docker compose build
	docker compose run --rm --no-deps app composer install
	docker compose up -d
	sudo chmod -R 777 storage bootstrap/cache
	docker compose exec -T app php artisan migrate --force
	docker compose exec -T app php artisan db:seed --force
	make clear

# Produção: código e vendor vêm assados na imagem (target `app`) e o
# entrypoint já limpa e regera os caches de config/rota/evento/view ao subir,
# então não há composer install nem `make clear` depois do `up` (o clear
# apagaria os caches gerados pelo entrypoint). O --wait só retorna quando o
# healthcheck do app (octane:status) passar. O repositório do serviço de
# integrações (../integrations.local) é atualizado antes do build da imagem.
prod:
	git pull
	git -C ../integrations.local pull --ff-only
	$(PROD) up -d --build --wait --wait-timeout 180
	$(PROD) exec -T app php artisan migrate --force
	$(PROD) exec -T app php artisan db:seed --force

# Stage: código e vendor vêm do bind mount do repositório, então o Octane sobe
# com o que estiver no disco. O cache de pacotes é limpo e o composer install
# roda ANTES do `up` (em um container avulso), senão o Octane quebra no boot
# (vendor sem pacote novo ou bootstrap/cache/packages.php desatualizado) e o
# container fica reiniciando. O --wait só retorna quando o healthcheck do app
# (octane:status) passar. A imagem do app/queue/scheduler vem pronta do GHCR
# (workflow stage-image.yml); só o serviço de integrações é compilado aqui.
stage:
	git pull
	git -C ../integrations.local pull --ff-only
	sudo chmod -R 777 storage bootstrap/cache
	rm -f bootstrap/cache/*.php
	$(STAGE) pull app queue scheduler
	$(STAGE) build integrations
	$(STAGE) run --rm --no-deps app composer install --no-dev --optimize-autoloader
	$(STAGE) up -d --wait --wait-timeout 180
	$(STAGE) exec -T app php artisan migrate --force
	$(STAGE) exec -T app php artisan db:seed --force
	make clear COMPOSE="$(STAGE)"

# O `up -d` recria app/queue/scheduler somente se o .env (env_file) ou o
# compose mudou: as variáveis entram no container na criação e o Swoole herda
# o ambiente do `octane:start`, então nem `restart` nem `octane:reload` aplicam
# valores novos do .env. Roda antes dos clears para que eles já usem as
# credenciais novas. Sem mudança no .env, nada é recriado (sem queda).
clear:
	$(COMPOSE) up -d --no-deps --wait app queue scheduler
	$(COMPOSE) exec -T app sh -c "rm -f bootstrap/cache/*.php"
	$(COMPOSE) exec -T app php artisan cache:clear
	$(COMPOSE) exec -T app php artisan config:clear
	$(COMPOSE) exec -T app php artisan route:clear
	$(COMPOSE) exec -T app php artisan view:clear
	$(COMPOSE) exec -T app php artisan event:clear
	$(COMPOSE) exec -T app php artisan octane:reload
	$(COMPOSE) restart queue scheduler

logs:
	sudo chmod -R 777 storage storage/logs
	find storage/logs -type f ! -name ".gitignore" -delete

reload:
	$(COMPOSE) exec -T app php artisan octane:reload

# Envia os commits sem rodar os hooks do git (pre-push com os testes unitários).
# Use quando já testou localmente; os commits seguintes continuam com o Pint do
# pre-commit. Para outra branch ou remoto: make push ARGS="origin minha-branch".
push:
	git push --no-verify $(ARGS)
