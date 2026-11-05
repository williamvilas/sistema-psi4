# CLAUDE.md

Manual operacional para desenvolvimento neste projeto. Consolida as instruções de `AGENTS.md` e `.github/instructions/*`. Leia antes de gerar ou alterar código.

## 1. Visão geral do projeto

- **Nome:** PSI4 (`APP_NAME` em `.env.example`).
- **Domínio:** sistema de gestão para consultórios/clínicas — pacientes, prontuários médicos, agenda, documentos/templates, WhatsApp, emissão de documentos.
- **Stack principal:** Laravel 12 (PHP ^8.5), MySQL, Laravel Octane, Socialite, SDK da Anthropic (IA no Bedrock), Dompdf, Maatwebsite Excel, Flysystem S3, localização PT-BR (`laravellegends/pt-br-validator`).
- **Frontend:** Blade + Bootstrap 5 + jQuery + SweetAlert2 + Select2 + Highcharts, empacotado com Laravel Mix/Webpack (`npm run dev` / `npm run prod`).
- **Arquitetura geral:** Controller → Service → Model, com Form Requests para validação e Blade + jQuery/AJAX no frontend, usando helpers globais em `resources/js/default.js`.

## 2. Regras fundamentais

- Sempre verificar arquivos similares existentes antes de criar código novo; seguir a estrutura e nomenclatura já usadas no projeto.
- Nunca criar código que contradiga o padrão existente nem introduzir abstrações não necessárias.
- Preferir reutilizar componentes, helpers, layouts e padrões visuais já existentes em vez de recriar.
- Usar português em comentários, labels, mensagens visuais e nomes quando for o padrão do arquivo/módulo.
- Manter separação clara de responsabilidades: controller cuida do fluxo HTTP, service da regra de negócio, model do acesso a dados.
- Não criar controllers grandes — mover lógica de negócio para services.
- Não duplicar lógica já existente (máscaras, filtros, paginação, toasts, helpers JS).
- Mensagens de `DomainException` nunca devem ser literais no código — sempre via slug de tradução (ver seção 9).
- **Octane (Swoole):** a aplicação roda em workers de longa duração — o mesmo processo atende requisições de psicólogas diferentes. Nunca guardar dado de requisição/usuário (usuário logado, paciente, token, IP, user agent) em propriedade `static`, em singleton ou em propriedade preenchida no construtor de services/controllers; obter `Auth::user()`/`request()` dentro do método que usa. Vazamento de estado entre requisições expõe dado de uma psicóloga para outra. Observers são resolvidos pelo container base do worker (não pelo sandbox da requisição): nunca injetar `Request`, guard ou service com estado de requisição no construtor de um observer.
- **Proxy/host:** o Laravel só confia em `X-Forwarded-For` e `X-Forwarded-Proto` vindos do nginx, e o `trustHosts` aceita apenas o host do `APP_URL` e seus subdomínios (`bootstrap/app.php`). Não ampliar os headers confiáveis nem gerar links absolutos (e-mail, WhatsApp, webhooks) a partir de host vindo do cliente — isso permite envenenar links de reset de senha/2FA.
- **Cache:** nunca usar o facade `Illuminate\Support\Facades\Cache` diretamente — usar sempre `App\Helpers\CacheHelper` (`get`, `put`, `set`, `add`, `forget`, `pull`, `lock`). O helper captura falhas de conexão com o cache, registra via `RegisterLog` e evita que a aplicação quebre. Se faltar um método (ex.: `remember`), implementar com `get` + `put` ou adicionar ao `CacheHelper` seguindo o mesmo padrão `try/catch` + `saveLogs`.

## 2.1 Refinamento técnico e de negócio antes de desenvolver

Ao receber uma solicitação de nova funcionalidade, alteração ou correção, **não começar a codar imediatamente**. Antes, atuar como um engenheiro de software sênior e fazer um refinamento técnico e de negócio:

0. **Ler a pesquisa e a estratégia antes de refinar:** analisar `.market-research/research.html` (perfil, critérios de compra, dores, prazeres e vocabulário das psicólogas) e `.market-research/strategy.html` (posicionamento, concorrentes, prioridades P0/P1/P2, o que **não** fazer). O refinamento deve mostrar como a mudança:
   - **Reforça o diferencial competitivo** do PSI4 — privacidade real (prontuário cifrado de ponta a ponta, "nem nós conseguimos ler o que você escreve no prontuário") — e se ajuda a superar PsicoManager/Corpora em vez de só copiar funcionalidade.
   - **Gera valor concreto para a psicóloga** atacando as dores mapeadas (burocracia/documentação, cobrança e "culpa de cobrar", faltas, sobrecarga, Receita Saúde, migração de dados).
   - **Preserva o que elas valorizam:** sigilo e segurança (critério nº 1), conformidade com o CFP (Res. 001/2009, 06/2019, 09/2024 e Código de Ética), preço justo, simplicidade e uso no celular, respeito à abordagem (psicanálise, TCC, humanista) e ao vínculo terapêutico (nada que soe frio ou invasivo para o paciente), tom acolhedor e ético, linguagem no feminino ou inclusiva.
   - **Não contradiz a estratégia:** nada de marketplace de pacientes, promessas tipo "lote sua agenda", paridade cega com o líder ou funcionalidades que exponham o conteúdo do prontuário em claro no servidor.
   Se a solicitação entrar em conflito com algum desses pontos, apontar o conflito no refinamento e sugerir uma alternativa alinhada.
1. **Entender o pedido:** reformular o objetivo, identificar requisitos implícitos e ambiguidades.
2. **Analisar o código existente:** localizar arquivos, services, models, rotas e views relacionados; identificar padrões a seguir e código a reaproveitar.
3. **Apresentar o refinamento**, de forma objetiva, cobrindo:
   - **Alinhamento com a estratégia:** diferencial competitivo e valor para a psicóloga (passo 0).
   - **Conformidade LGPD e CFP:** dados tratados, base legal, consentimento, compartilhamento com terceiros, guarda/descarte e sigilo (seção 2.2).
   - **Abordagem proposta:** o que será criado/alterado (controller, service, model, migration, Form Request, rota, view, JS, slugs de tradução).
   - **Impactos e riscos:** regressões, contrato de API, performance (N+1, paginação), segurança e dados sensíveis, transações.
   - **Edge cases e dúvidas** que precisam de decisão do usuário.
   - **Alternativas** relevantes, com recomendação clara, quando houver trade-offs reais.
   - **Testes** que fazem sentido para a mudança.
4. **Perguntar ao usuário se deseja que o desenvolvimento seja iniciado** e só implementar após a confirmação.

Exceções: ajustes triviais e inequívocos (typo, renomear label, mudança de uma linha) ou quando o usuário pedir explicitamente para executar direto, sem refinamento.

## 2.2 Conformidade com a LGPD e o CFP

**Toda funcionalidade nova ou modificada deve seguir a LGPD (Lei 13.709/2018) e as normas do CFP.** Isso vale também para correções e refatorações que toquem dados de pacientes ou da psicóloga. O refinamento (seção 2.1) deve dizer como a mudança atende a esses pontos; se não atender, apontar o conflito e propor alternativa antes de codar.

**LGPD**
- Dados de saúde, prontuário, anotações de sessão, áudio/transcrição e dados de crianças e adolescentes são **dados pessoais sensíveis** (art. 5º, II e art. 11): tratar só o mínimo necessário para a finalidade (art. 6º — finalidade, adequação, necessidade) e não reaproveitar para outra finalidade sem base legal.
- Identificar a base legal do tratamento (ex.: tutela da saúde por profissional de saúde, cumprimento de obrigação regulatória, execução de contrato, consentimento). Quando a base for o consentimento, ele deve ser livre, específico, registrado com prova (data, quem consentiu, versão do texto) e revogável — seguir o padrão de `session_transcription_consents`. Dados de criança/adolescente exigem o consentimento de um dos pais ou responsável (art. 14).
- Segurança por padrão e desde a concepção (arts. 46 a 49): conteúdo clínico continua cifrado de ponta a ponta (seção 9); nunca registrar dado sensível em claro em logs, `RegisterLog`, cache, filas, URLs, mensagens de erro ou analytics.
- Compartilhamento com terceiros (AWS, provedor de IA, WhatsApp, gateways de pagamento, e-mail) só com o necessário, preferencialmente cifrado ou pseudonimizado. Nova integração que receba dado pessoal deve ser apontada no refinamento, incluindo se há transferência internacional (art. 33).
- Garantir os direitos do titular (art. 18): acesso, correção, portabilidade/exportação e eliminação — respeitando os prazos de guarda obrigatória do CFP antes de apagar prontuário.
- Registrar acessos e operações sobre dados sensíveis (art. 37) via `AccessLogService`, e definir retenção/descarte para dados novos (nada guardado "para sempre" sem motivo).
- Textos para paciente e psicóloga (avisos, termos, consentimentos) claros, em linguagem simples, dizendo o que é coletado, para quê e com quem é compartilhado.

**CFP**
- **Código de Ética Profissional do Psicólogo:** sigilo profissional (art. 9º) — a funcionalidade não pode expor ao sistema, a terceiros ou a outros usuários informações obtidas na relação terapêutica; em documentos e comunicações, só a informação estritamente necessária (art. 6º).
- **Res. CFP 001/2009 (registro documental):** prontuário/registro obrigatório, acesso restrito, guarda mínima de 5 anos, e o paciente tem direito de acesso ao que lhe diz respeito.
- **Res. CFP 06/2019 (documentos psicológicos):** declarações, atestados, relatórios, laudos e pareceres seguem a estrutura e as exigências da resolução; templates e geração automática (incluindo IA) não podem fugir delas, e a responsabilidade e a assinatura são da psicóloga.
- **Res. CFP 09/2024 (serviços por TIC):** atendimento online exige plataforma segura, sigilo da comunicação e consentimento informado; gravação/transcrição de sessão só com aceite expresso do paciente.
- Testes e escalas psicológicas seguem o SATEPSI; nada de pontuação, interpretação ou diagnóstico automático sem a avaliação da psicóloga.
- Nenhuma funcionalidade pode induzir publicidade antiética, captação de pacientes ou promessas de resultado.

## 3. Arquitetura

- **Controller:** fluxo HTTP, métodos CRUD (`index, create, store, show, edit, update, destroy`), injeção de services via construtor, validação via Form Request, retorno de view ou JSON. Referência: `app/Http/Controllers/PatientsController.php`.
- **Service (`app/Services/`):** lógica de negócio, injeção de repositories/models/outros services, operações CRUD e regras específicas, uso de transações quando necessário. Referência: `app/Services/MedicalRecordService.php`.
- **Model (`app/Models/`):** `fillable`, `hidden`, `casts` e relacionamentos definidos com base na migration correspondente; usar `HasFactory`/`SoftDeletes` quando aplicável. Referência: `app/Models/MedicalRecords.php`.
- **Form Request (`app/Http/Requests/<NomeDoController>/`):** uma pasta por controller, com classes nomeadas pela ação (`StoreRequest`, `UpdateRequest`, `FilterRequest`). Controller acessa dados via `$request->validated()`, sem repetir validação.
- **Frontend:** Blade view → JS de página (`resources/js/<recurso>.js`) → AJAX (`$.ajax`/helpers globais) → Controller retorna JSON ou HTML parcial → JS atualiza o DOM.

## 4. Convenções de código

### PHP / Laravel
- Controllers seguem o modelo CRUD de `PatientsController.php`.
- Services seguem o modelo de `MedicalRecordService.php`, com métodos de negócio e tratamento de transações de banco.
- Models seguem o modelo de `MedicalRecords.php`; atributos sempre extraídos da migration correspondente em `database/migrations/`, incluindo no `fillable` apenas os campos relevantes.
- Usar Eloquent ORM para queries (evitar SQL cru sem necessidade).
- Implementar paginação sempre que houver listagem de dados.
- Usar transações de banco em operações sensíveis (create/update/delete que afetam múltiplas tabelas).

### Exceções e mensagens
- `DomainException` nunca deve conter mensagem literal no `throw`.
- Criar um slug em `lang/<idioma>/<contexto>.php`, com o nome do arquivo igual ao contexto da operação (ex.: `lang/pt_BR/profile.php` → `'not_found' => 'Perfil não encontrado.'`).
- Mensagem deve ser curta, amigável e voltada ao usuário final, sem termos técnicos.
- Já existem arquivos de contexto em `lang/pt_BR/` (ex.: `agenda.php`, `medical-records.php`, `documents.php`) — reaproveitar o contexto certo antes de criar um novo.

### JavaScript
- Ver seção 4 do arquivo de diretrizes JS original — resumido na seção 5/11 deste documento e no arquivo de origem `.github/instructions/javascript-guidelines.md`.

## 5. Estrutura de diretórios

```
app/
  Console/        Commands Artisan
  Enums/          Enumerações
  Excel/          Exports/Imports (Maatwebsite Excel)
  Helpers/        Helpers de domínio (ex.: DateTimeHelper, NumberHelper, UserHelper)
  Http/
    Controllers/  Controllers HTTP
    Requests/<NomeDoController>/  Form Requests por controller
  Jobs/           Jobs de fila
  Mail/           Mailables
  Models/         Models Eloquent
  Observers/      Model observers
  Presenters/     Presenters de apresentação de dados
  Providers/      Service Providers
  Rules/          Regras de validação customizadas
  Services/       Regras de negócio
  Traits/         Traits reutilizáveis
  Transformers/   Transformers (league/fractal)
  Wrappers/       Wrappers de integrações externas
resources/
  js/             Scripts por recurso (ex.: medical-records.js, default.js com helpers globais)
  views/          Views Blade (layouts, components, e uma pasta por módulo: patients, agenda, documents, medical-records, etc.)
routes/
  web.php, api.php, auth.php, console.php
lang/
  en/, pt_BR/, pt_BR.json   Slugs de tradução por contexto (usados também para DomainException)
database/
  migrations/     Fonte da verdade para atributos de Models
tests/
  Unit/, Feature/ Testes PHPUnit
```

## 6. Desenvolvimento

- **Instalação:** `composer install` e `npm install`.
- **Ambiente:** `php artisan migrate` para rodar as migrations.
- **Dev completo** (server + queue listener + logs + vite/mix): `composer run dev` (roda `php artisan serve`, `php artisan queue:listen --tries=1`, `php artisan pail`, `npm run dev` em paralelo).
- **Frontend assets:** `npm run dev` (build), `npm run watch` (watch), `npm run hot` (hot reload), `npm run prod` (build de produção) — via Laravel Mix (`webpack.mix.js`). Rodar após qualquer alteração em `resources/js/*`.
- **Formatação de código PHP:** `vendor/bin/pint` (script `composer format-code`).
- **Fila (worker) em produção**, conforme README:
  ```
  php artisan queue:work --sleep=3 --tries=3 --max-time=3600 --max-jobs=1000 --memory=128 --timeout=90
  ```
  Gerenciado via `systemctl` (`psi4-worker@*`).
- **Jobs falhos:** `php artisan queue:retry {id}`.
- **Agendador:** cron chamando `php artisan schedule:run` a cada minuto.
- **Runtime HTTP:** Laravel Octane com Swoole (`config/octane.php`) atrás do nginx (`proxy_pass` para `app:8000`), em local, stage e prod. O Octane mantém o código em memória:
  - **local** (`make local`): `octane:start --watch` recarrega os workers a cada alteração em PHP (Node no target `dev` + `chokidar` do `node_modules`). Recarga manual: `make reload`.
  - **stage:** o `git pull` dispara os hooks `.githooks/post-merge`/`post-rewrite`, que rodam `octane:reload` e `queue:restart` no container (hooks ativos via `./setup-hooks.sh`).
  - **prod:** `make prod` recria os containers (o entrypoint regera os caches; não rodar `make clear` em prod, ele apagaria esses caches).
  - **Mudança no `.env`:** só vale com `make clear` (no stage, `make clear COMPOSE="docker compose -f docker-compose.stage.yml"`), que roda `up -d` e recria `app`/`queue`/`scheduler` quando o `.env` mudou. `make reload`, `docker compose restart` e o `--watch` **não** aplicam valores novos: as variáveis entram no container na criação (`env_file`) e o Swoole herda o ambiente do `octane:start` (o Laravel não sobrescreve variável já existente).
  - IP real do cliente via `trustProxies` (redes privadas) em `bootstrap/app.php` — `request()->ip()` depende disso.

## 7. Testes

- Framework: PHPUnit (`phpunit.xml` — suites `Unit` em `tests/Unit` e `Feature` em `tests/Feature`; `DB_CONNECTION=mysql`).
- **Rodar testes:** `composer test` (`php artisan migrate:fresh --env=testing && php artisan test`).
- **Cobertura:** `composer coverage` (`phpunit --coverage-html coverage`).

### Padrão para geração de testes unitários de Service (workflow `generate-unit-tests`)
Ao ser solicitado a gerar testes unitários para um Service, seguir rigorosamente:
1. **Antes de gerar testes**, analisar o código em busca de bugs, edge cases não tratados, problemas de performance/design — **exceto validações** (FormRequest/Validator/rules são tratados em outra camada e nunca devem ser testados ou sugeridos). Se encontrar algo, explicar o problema, sugerir correção e perguntar ao usuário se deve corrigir antes ou gerar os testes mesmo assim. Só gerar os testes após a resposta do usuário.
2. **Estrutura:** namespace `Tests\Unit\Services`, classe `<NomeDoService>Test extends Tests\TestCase`.
3. **Setup:** criar usuário via factory, autenticar com `Auth::login`, mockar o model com `Mockery::mock` e injetar no service.
   - **Nunca usar `RefreshDatabase`** — usar sempre a trait `DatabaseTransactions`, conforme `tests/Unit/Services/PatientServiceTest.php`.
4. **Nomenclatura obrigatória:** todo método de teste começa com `testIt`, seguindo `testIt<Acao><Contexto>` (ex.: `testItStoreSuccessfully`, `testItPaginateWithAllFilters`, `testItThrowExceptionWhenInvalidData`). Não usar nomes genéricos.
5. **Técnica:** usar Mockery (`shouldReceive`, `andReturnSelf()` para encadeamento), mockar corretamente Eloquent e `DB::transaction` quando necessário; evitar acesso a banco real exceto quando explicitamente necessário. Estrutura interna em AAA (Arrange/Act/Assert).
6. **Cobrir:** sucesso, filtros, edge cases (exceto validação), retornos vazios, falhas, transações (create/update/delete). **Nunca** cobrir validações de entrada, regras de validação ou mensagens de erro de validação.
7. Buscar a maior cobertura possível (idealmente 100%, exceto validações); se não for possível, explicar o motivo e sugerir melhorias no código.
8. **Importante:** `RefreshDatabase` está proibido neste projeto — usar sempre `DatabaseTransactions`, conforme o teste real existente (`tests/Unit/Services/PatientServiceTest.php`).

## 8. Banco de dados

- Migrations são a fonte da verdade para os atributos dos Models — sempre conferir a migration em `database/migrations/` antes de definir `fillable`, tipos e constraints.
- Seguir o padrão de nomes, tipos, índices e foreign keys já usado nas migrations existentes.
- Acessar dados via Eloquent ORM, mantendo queries otimizadas.
- Usar paginação em listagens.
- Usar transações de banco em operações sensíveis.

## 9. Segurança

- Sempre validar e sanitizar inputs — usar Form Request para validações mais complexas.
- Nunca expor mensagens técnicas de exceção ao usuário: `DomainException` sempre usa slug de tradução em `lang/<idioma>/<contexto>.php` (ver seção 4).
- Prontuários têm criptografia de ponta a ponta no navegador (`crypto.subtle`), centralizada em `resources/js/crypto.js`: chave AES-GCM-256 por usuário, embrulhada pela senha mestre e pela chave de recuperação (PBKDF2-SHA256, 600k iterações) e guardada embrulhada em `user_encryption_keys` (`EncryptionKeyService`). Para cifrar/decifrar conteúdo sensível, reaproveitar `unlockMedicalRecordKey()`, `encryptMedicalContent()`, `decryptMedicalContent()` e `downloadAndDecryptMedicalRecord()` — não recriar a lógica. O servidor nunca recebe senha mestre, chave de recuperação nem a chave em claro.
- Dinâmicas da sessão de vídeo (`SessionActivityService`, plano em `jitsi-meet-psi/doc/psi/refinamento-dinamicas.md`): o Jitsi grava no prontuário sem login, cifrando no navegador com a chave pública (`scale_public_key`), e o servidor só recebe o formato `PSA`. `downloadAndDecryptMedicalRecord` trata esse formato e monta o HTML a partir de JSON validado (`validateSessionActivityRecord`), porque a página tem acesso à chave. O JWT da sala prova apenas o CPF e não basta para gravar: a sala pede, e ela aprova no PSI4 com login e senha mestre (`SessionAuthorizationService`, `proveScalePrivateKey`). Nunca digitar a senha mestre no Jitsi nem entregar a ele a chave embrulhada: poderia ser atacada fora do sistema. Dinâmica não é escala nem teste psicológico: sem pontuação, faixa ou interpretação, e tabela separada (`session_activities`).
- Transcrição da sessão e rascunho por IA (`SessionTranscriptionService`, desligada por padrão em `PSI_TRANSCRIPTION_ENABLED`; plano em `jitsi-meet-psi/doc/psi/refinamento-transcricao.md`): o navegador da psicóloga grava o áudio em trechos e envia direto a um bucket S3 temporário por URL assinada (`AwsTranscribeWrapper`), sem passar pelo Laravel. Sem o aceite do paciente, gravado com o JWT dele em `session_transcription_consents`, não sai URL. Começar não pede autorização no PSI4: quem tiver só o JWT não lê nada. Depois da sessão, `ProcessSessionTranscriptionJob` roda o Transcribe em lote, gera o rascunho com o Claude no Bedrock (`SessionNoteWrapper`, SDK `anthropic-ai/sdk`), cifra rascunho e transcrição com a chave pública (`PublicKeyEnvelope`, formato `PSN`) e apaga o temporário; o ciclo de vida do bucket apaga em 24 h o que sobrar. O texto em claro só existe na memória do job: nunca em log, payload, exceção ou `RegisterLog` (os wrappers registram só status e código de erro). Revogar para a gravação, mas o que já foi gravado segue para o rascunho. Enquanto pendente, é rascunho, não prontuário: fica fora do limite, da exportação e da guarda de 5 anos. "Aprovar" grava só o texto do editor, cifrado no navegador; a transcrição e o rascunho nunca entram no prontuário. Recusar não pede a senha mestre e apaga a pasta do rascunho; aprovar também apaga, após o commit. Não adicionar o assistente de IA à tela do rascunho: enviaria o texto em claro ao provedor.
- Acessos a prontuários (criar, abrir/emitir link, editar, apagar) e logins são registrados em `access_logs` via `AccessLogService` — novos fluxos que exponham conteúdo de prontuário devem registrar o acesso.
- Não introduzir nova biblioteca JS sem antes perguntar ao usuário.
- Se uma mudança alterar contrato de API (rotas/payloads), avisar explicitamente que o backend também precisa ser atualizado.

## 10. Git e versionamento

- **Ao terminar uma tarefa, fazer commit e push automaticamente, sem pedir confirmação.** Incluir no commit só os arquivos da tarefa (não os builds de `public/` se não foram pedidos).
Nenhum dos arquivos de instrução do projeto define convenção formal de commits, branches ou PRs. Observação do histórico do repositório (não é uma regra documentada): os commits recentes seguem o estilo Conventional Commits (`feat:`, `fix:`), o que pode ser usado como referência na ausência de instrução explícita.

## 11. Regras específicas por diretório

### `app/Http/Controllers`
- Seguir o modelo de `PatientsController.php`: métodos CRUD, injeção de services, uso de Form Request, retorno de view/JSON.

### `app/Http/Requests/<NomeDoController>/`
- Uma pasta por controller (mesmo nome do controller). Classes nomeadas pela ação: `StoreRequest`, `UpdateRequest`, `FilterRequest`. Centralizar regras, mensagens e autorização.

### `app/Services`
- Seguir o modelo de `MedicalRecordService.php`. Injeção de dependências, métodos CRUD/lógica de negócio, transações quando necessário. Nunca colocar lógica de negócio pesada no controller.

### `app/Wrappers`
Padrão obrigatório sempre que criar ou alterar um Wrapper de integração externa (referência: `app/Wrappers/PaymentsApiWrapper.php`):
- **Toda classe dentro de `app/Wrappers/` que faz requisição HTTP deve registrar o log do cURL da falha.** É o registro no `RegisterLog` que dispara o alerta: o comando `app:send-logs-command` (`SendLogsCommand`) envia os logs ainda não enviados via Telegram. Falha sem `RegisterLog` = falha sem alerta.
- **Falhas de requisição HTTP devem ser registradas via `RegisterLog::dispatchSync([...])`** (nunca com `Log::*`), e **somente quando a requisição falhar** (resposta 4xx/5xx ou `ConnectionException`). Requisições com sucesso não geram registro.
- Campos do `RegisterLog`:
  - `message`: descrição curta da falha (ex.: `Falha na requisição à API de pagamentos (HTTP 500)`) ou a mensagem da `ConnectionException`.
  - `data`: obrigatoriamente contém `curl` (cURL equivalente da requisição enviada); em respostas com erro incluir também `status` e `response` (corpo limitado, ex.: `Str::limit($body, 2000)`).
  - `route`: URL da requisição; `method`: método HTTP.
- **Mascarar credenciais no cURL** (ex.: `Authorization` → `Bearer ***`); nunca gravar tokens/chaves em `data`. Omitir headers irrelevantes (`content-length`, `host`, `user-agent`).
- Capturar a requisição enviada com `->beforeSending(...)` (guardar em propriedade) para montar o cURL apenas na falha; não montar o cURL em chamadas bem-sucedidas.
- Erros 4xx (exceto 408/429) viram `DomainException` com slug de tradução; demais falhas (rede, 5xx) são relançadas para o job tentar novamente.

### `app/Models`
- Seguir o modelo de `MedicalRecords.php`. Atributos sempre a partir da migration correspondente. Apenas campos relevantes no `fillable`.

### `resources/js/<recurso>.js`
- Um arquivo por recurso principal (ex.: `patients.js`, `medical-records.js`, `agenda.js`), seguindo o modelo de `medical-records.js`.
- Inicialização: `$(document).ready(...)` (compatível com jQuery) ou `DOMContentLoaded` para módulos puramente DOM.
- Nomenclatura: forms `#form-store-<recurso>` / `#form-update-<recurso>`; funções `paginate<Recurso>()` (com callback opcional), `store<Recurso>`, `update<Recurso>`, `delete<Recurso>`/`delete<Recurso>s`.
- Comunicação com backend via helpers globais: `sendRequest(url, type, selectorForm, dataType, callbackSuccess, loading = true, callbackError = null, payload = null)`, `sendRequestByJson(url, type, json, callbackSuccess, callbackError = null)`, `loadingAjax()`/`closeLoadingAjax()`, `errorResponseJson(jqXHR, selectorForm)`/`successResponseJson(title, message)`.
- Usar `sendRequest` para fluxos com serialização de formulário/validação frontend; `sendRequestByJson`/`fetch` para JSON puro, streams ou arquivos (uploads grandes seguem o padrão de chunking de `medical-records.js`).
- Listagem/filtro/paginação sempre via `$.ajax` com `beforeSend`/`success`/`error`, atualizando o container `.list-paginate` via `.html(...)`; paginação intercepta o clique com `preventDefault()`.
- Reaproveitar utilitários de `resources/js/default.js`: `maskWhatsApp()`, `maksCPF()`, `maskDate()`, `formatWhatsApp(phone)`, `applyMask(mask, value)`, `strReplace()`, `elementExists(selector)`, `countFields(form)`, `validForm(selectorForm)`, `getSortBy()`, `validarCPF()`. Não duplicar essa lógica.
- Verificar existência de elemento antes de manipular (`if (!elementExists(selector)) return;`).
- Confirmações/alertas via `Swal.fire(...)`.
- Integrações com o microserviço de IA: usar streaming via `response.body.getReader()` quando o endpoint suportar; desabilitar botões/mostrar spinner durante geração e reabilitar no `finally`.
- `try/catch` completo em chamadas `async/await`/`fetch`.

### `resources/views`
- `@extends('layouts.app')` para views do painel; `@section('title', ...)`, `@section('content')` e `@section('footer')` para scripts específicos da página.
- Reaproveitar componentes em `resources/views/components/` e `@include(...)` para blocos repetidos.
- Bootstrap classes já usadas no projeto (`btn btn-primary`, `btn btn-outline-secondary`, etc.).
- Listas paginadas usam container `.list-paginate`; filtros usam modal `#filterModal` com botão de busca.
- Iniciar lógica de página com `document.addEventListener('DOMContentLoaded', ...)`.

### `lang/<idioma>/<contexto>.php`
- Um arquivo por contexto de operação (ex.: `profile.php`, `medical-records.php`, `agenda.php`). Usado para mensagens amigáveis de `DomainException`. Verificar se já existe o contexto antes de criar um novo arquivo.

### `tests/Unit/Services`
- Seguir o workflow descrito na seção 7 ao gerar testes para uma Service.

## 12. Checklist antes de finalizar uma tarefa

- [ ] Funcionalidade nova ou alterada segue a LGPD e as normas do CFP (seção 2.2): mínimo de dados, base legal/consentimento, sigilo, sem dado sensível em claro em logs/terceiros, acesso registrado e guarda respeitada.
- [ ] Rota registrada em `routes/web.php`/`routes/api.php`, nomeada e consistente com o padrão existente.
- [ ] Controller segue o padrão (CRUD, injeção de service, Form Request).
- [ ] Form Request criado em `app/Http/Requests/<NomeDoController>/` quando há validação.
- [ ] Service contém a lógica de negócio (nada pesado no controller).
- [ ] Model com `fillable`/casts/relacionamentos batendo com a migration correspondente.
- [ ] View Blade usa o layout e a estrutura padrão (`list-paginate`, `filterModal`, etc., quando aplicável).
- [ ] JavaScript segue nomenclatura e reaproveita helpers de `default.js` (`loadingAjax`, `closeLoadingAjax`, `errorResponseJson`, `sendRequest`/`sendRequestByJson`).
- [ ] Se criou/alterou um Wrapper, seguiu o padrão de `app/Wrappers` (falhas registradas via `RegisterLog` com cURL mascarado em `data`, apenas em falhas, para disparar o alerta).
- [ ] Nenhuma mensagem literal em `DomainException` — slug criado/reaproveitado em `lang/<idioma>/<contexto>.php`.
- [ ] Sem duplicação de lógica já existente (máscaras, paginação, toasts, helpers).
- [ ] Sem regressão de UX ou funcionalidade existente.
- [ ] Se alterou contrato de API, backend e frontend atualizados juntos.
- [ ] Se for gerar testes de Service, seguiu o workflow da seção 7 (análise prévia, nomenclatura `testIt*`, sem testes de validação, `DatabaseTransactions` em vez de `RefreshDatabase`).
- [ ] Assets recompilados (`npm run dev`/`npm run prod`) quando houve alteração em `resources/js`.

## 13. Fontes das regras

- `AGENTS.md`
- `.github/instructions/ai-instructions.md`
- `.github/instructions/backend-blade-js-instructions.md`
- `.github/instructions/javascript-guidelines.md`
- `.github/instructions/generate-unit-tests.md`
- `.market-research/research.html` e `.market-research/strategy.html` (pesquisa de mercado e estratégia de produto, base do refinamento de negócio)
- LGPD (Lei 13.709/2018), Código de Ética Profissional do Psicólogo e Resoluções CFP 001/2009, 06/2019 e 09/2024 (seção 2.2)
- Verificação cruzada com código real do projeto: `app/Http/Controllers/PatientsController.php`, `app/Services/MedicalRecordService.php`, `app/Models/MedicalRecords.php`, `resources/js/medical-records.js`, `resources/js/default.js`, `tests/Unit/Services/PatientServiceTest.php`, `composer.json`, `phpunit.xml`, `README.md`.
