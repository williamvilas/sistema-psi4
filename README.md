
## Estrutura da Landing Pages

### 1) Rodar migrate

```shell
php artisan migrate
```

### 2) Crontab -e

```shell
* * * * * chmod -R 777 /var/www/html/psi.local/storage
* * * * * cd /var/www/html/psi.local && php artisan schedule:run >> /dev/null 2>&1
```

### Reprocessar Jobs que falhou
```shell
php artisan queue:retry {id}
```

## Configurar work

### 👉 Esse combo é o “padrão ouro” pra economia de recursos.

```shell
php artisan queue:work \
--sleep=3 \
--tries=3 \
--max-time=3600 \
--max-jobs=1000 \
--memory=128 \
--timeout=90
```

### O que cada um faz (importante):

```markdown
--sleep=3 → evita CPU alta quando fila vazia
--tries=3 → evita loop infinito de falha
--max-time=3600 → reinicia a cada 1h
--max-jobs=1000 → evita memory leak
--memory=128 → mata se passar disso
--timeout=90 → evita jobs travados
```

### Git hooks

Os hooks ficam em `.githooks/` e são ativados no `composer install` (ou manualmente com `composer install-hooks` / `./setup-hooks.sh`).

- **pre-commit:** roda o Pint nos arquivos PHP em stage e os adiciona de volta ao commit. Com `git add -p`, trechos não selecionados do arquivo também entram.
- **pre-push:** roda `php artisan test --testsuite=Unit`; se algum teste falhar, o push é bloqueado. Requer o banco de testes disponível.
- **post-merge / post-rewrite:** após o `git pull` no servidor de stage, rodam `octane:reload` e `queue:restart` no container `psi4-stage-app` para os workers pegarem o código novo. Em outras máquinas não fazem nada.

### Octane (Swoole)

A aplicação roda no Laravel Octane com Swoole, atrás do nginx. Como os workers mantêm o código em memória, alterações em PHP só valem depois de recarregar:

```shell
make local    # sobe o ambiente local (build, composer install, migrate, seed)
make reload   # php artisan octane:reload no container app
make clear    # limpa os caches, recarrega o Octane e recria os containers se o .env mudou
```

Alterações no `.env` exigem `make clear`: as variáveis entram no container quando ele é criado (`env_file`) e o Swoole herda o ambiente do `octane:start`, então `make reload`, `restart` e o `--watch` não aplicam valores novos. O `make clear` recria `app`, `queue` e `scheduler` apenas quando o `.env` mudou (queda de alguns segundos nesse caso). No stage use `make clear COMPOSE="docker compose -f docker-compose.stage.yml"`; em produção use `make prod` (o `clear` apagaria os caches gerados pelo entrypoint).

Em local o `--watch` já recarrega sozinho o código PHP; no stage o hook `post-merge` recarrega após o `git pull`. Variáveis: `OCTANE_WORKERS` (prod: 8), `OCTANE_TASK_WORKERS` (1), `OCTANE_MAX_REQUESTS` (500).

### Configurar work

```shell
sudo ./setup-work.sh
```

### Parar work
```shell
sudo systemctl stop psi4-worker@*
```

### Subir mais works
```shell
sudo systemctl start psi4-worker@3
sudo systemctl enable psi4-worker@3
```
