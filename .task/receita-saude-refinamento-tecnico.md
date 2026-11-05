# Refinamento técnico: Receita Saúde (v1)

> Complementa `.task/receita-saude-refinamento.md` (refinamento de negócio).
> Layout conferido em 23/10/2026 no manual oficial do Carnê-Leão, no manual do Receita Saúde v2.1 (out/2025) e no modelo `escrituracao-carne-leao.zip` da Receita.

## 1. O que a fonte oficial resolve

A pendência nº 1 do refinamento de negócio fica **resolvida para o arquivo de envio** e **parcialmente aberta para o retorno**.

### Arquivo de envio (recibos do Receita Saúde)

- CSV **sem cabeçalho**, separador `;`, **até 1.000 linhas por arquivo**.
- Encoding do modelo oficial: **ISO-8859-1** (o `.csv` de exemplo da Receita não é UTF-8).
- **Todos os pagamentos de um arquivo precisam ser do mesmo ano.**
- 16 campos, nesta ordem (posições vazias precisam existir, ou seja, o `;` continua lá):

| # | Campo | Valor no PSI4 |
|---|---|---|
| 1 | Data do pagamento | `paid_at` no fuso da psicóloga, `DD/MM/AAAA` |
| 2 | Código do rendimento | fixo `R01.001.001` |
| 3 | Código da ocupação | fixo `255` (psicólogo) |
| 4 | Valor | `150,00`: vírgula decimal, sem separador de milhar, maior que zero |
| 5 | Valor da dedução | vazio |
| 6 | Descrição | até 255 caracteres, genérica (ex.: "Sessão de psicologia em 14/10/2026"), sem `;` nem quebra de linha |
| 7 | Recebido de | fixo `PF` |
| 8 | CPF do pagador | 11 dígitos |
| 9 | CPF do beneficiário | 11 dígitos (é o CPF do paciente) |
| 10–13 | CPF não informado, CNPJ, IRRF, valor IRRF | vazios |
| 14 | Indicador de recibo | fixo `S` |
| 15 | CPF da profissional | `users.document`, que precisa ser o mesmo CPF usado no Carnê-Leão |
| 16 | Registro profissional | até 15 caracteres; pode ficar vazio se ela tiver um só registro ativo |

Linha de exemplo:

```
14/10/2026;R01.001.001;255;150,00;;Sessão de psicologia em 14/10/2026;PF;12345678909;12345678909;;;;;S;98765432100;
```

### Retorno

- Depois de importar, o e-CAC deixa baixar até três arquivos: lista de erros (PDF), **linhas com erro (CSV)** e **lista de recibos gerados (CSV)**, com o número de cada recibo.
- **O layout do CSV de recibos gerados não está publicado.** Para escrever o importador, precisamos de um arquivo real.
- Em "Histórico da Escrituração > Desfazer escrituração", a profissional cancela os recibos do lote inteiro.

### Regras legais com impacto no sistema

- O recibo é emitido **na data do pagamento**. Pagamento parcelado gera um recibo por parcela.
- A emissão retroativa vai até o **último dia de fevereiro do ano seguinte**.
- O recibo só pode ser cancelado em até **10 dias da emissão**, e só quando foi emitido com erro (art. 7º da IN 2.240).
- Multa de **R$ 100 por mês-calendário** por recibo não emitido ou emitido com erro.
- A importação **soma** ao que já existe e não remove duplicados. Evitar duplicidade é responsabilidade nossa.

## 2. Pontos que mudam o refinamento de negócio

1. **"Não escriturar por paciente" entra em conflito com a lei.** A obrigação é da profissional e o CPF do beneficiário é obrigatório no recibo (não existe "CPF não informado" para recibo). Uma opção de opt-out por paciente induziria a psicóloga a levar multa. **Proposta:** não ter opt-out. Em vez disso, permitir **"tirar deste lote"** caso a caso, com um aviso. Levar a dúvida ao contador.
2. **Pagador ≠ beneficiário** é comum: menor de idade, pai ou mãe que paga, cônjuge. Hoje `patients` só tem `document`. É preciso um **responsável financeiro** no paciente.
3. **Recibo em PDF para o paciente** sai da v1. O paciente já recebe o recibo oficial pelo app da Receita, e um segundo "recibo" do PSI4 confunde. Fica como ideia para depois (declaração de pagamento, não recibo).
4. **Estorno (`REFUNDED`)** não cancela nada na Receita. O PSI4 só pode **avisar**: "cancele no e-CAC até dd/mm", dentro da janela de 10 dias. Depois desse prazo, a orientação depende do contador.

## 3. Estado atual do código (o que já existe)

| Item | Onde | Situação |
|---|---|---|
| CPF da psicóloga | `users.document` (char 14), regra `cpf`, imutável depois de informado (`ProfileService::update`) | ✅ pode ser vazio, então vira pendência bloqueante |
| CRP | `psychologist_settings.crp` com fallback para `users.crp` (padrão dos templates de documento) | ✅ |
| CPF do paciente | `patients.document` (char 12), `nullable` + regra `cpf` | ⚠️ opcional, então vira alerta |
| Responsável financeiro | — | ❌ não existe |
| Recebimentos pelo Mercado Pago | `payment_orders` (`status=paid`, `paid_at` timestamp UTC) | ✅ |
| Recebimento manual (Pix/dinheiro por fora) | — | ❌ `PaymentOrderService::store` exige conta MP e despacha `CreateMercadoPagoChargeJob` |
| Sessão realizada com preço | `agenda.status=completed`, `agenda.price` | ✅ dá para sugerir recebimentos (P1) |
| Log de acesso | `access_logs.action` é `enum` e só tem `medical_record_id` | ⚠️ precisa de migration para novas ações |
| Planos | `free` e `professional` | — |

## 4. Modelagem proposta

### Decisão central: entidade fiscal própria em vez de reaproveitar `payment_orders`

O recebimento manual **não deve** virar uma `payment_order` sem gateway. Essa tabela carrega invariantes do Mercado Pago: `assertAccountReady`, job de cobrança, `refund` exige `external_id`, `integration_status`, `checkout_url`. Um registro manual quebraria essas regras e os relatórios do dashboard.

**Proposta:** uma tabela `health_receipts` (o recibo), alimentada por duas origens:

- **MP:** é criada automaticamente quando a ordem vira `PAID` em `ProcessMercadoPagoChargeWebhookService`.
- **Manual:** é cadastrada pela psicóloga ("Registrar recebimento").

Assim a tela lista uma tabela só, sem `UNION`. Ela também já faz o papel de `fiscal_documents` do refinamento anterior. Sem backfill de pagamentos antigos (memória `ignore-legacy-data-in-new-features`). Se quisermos trazer os pagamentos do ano corrente, isso vira um comando Artisan à parte. Fica a decisão.

### `health_receipts`

| Coluna | Tipo | Observação |
|---|---|---|
| `id`, `uuid` | | padrão do projeto |
| `user_id` | FK users, index | |
| `patient_id` | FK patients | relação `withTrashed()` |
| `payment_order_id` | FK nullable, **unique** | trava a duplicidade da origem MP |
| `agenda_id` | FK nullable, **unique** | trava a duplicidade da sessão (manual a partir da agenda) |
| `health_receipt_batch_id` | FK nullable, index | lote em que foi exportado |
| `source` | enum `payment_order`/`manual` | `HealthReceiptSourceEnum` |
| `amount` | decimal(10,2) | |
| `paid_on` | date | data do pagamento **já no fuso da psicóloga** |
| `description` | string(255) nullable | vazio usa o texto padrão |
| `payer_document` | char(11) nullable | snapshot no momento da exportação |
| `beneficiary_document` | char(11) nullable | snapshot no momento da exportação |
| `status` | enum | `HealthReceiptStatusEnum`: `pending`, `exported`, `issued`, `error`, `cancelled` |
| `receipt_number` | string nullable | vem do arquivo de retorno |
| `refunded_at` | timestamp nullable | estorno depois de exportado, para gerar o alerta |
| timestamps, softDeletes | | |
| índice | `(user_id, status, paid_on)` | listagem e filtros |

- O **snapshot dos CPFs** no momento da exportação garante que o arquivo reemitido seja idêntico ao enviado, mesmo que o cadastro do paciente mude depois.
- **`paid_on` como `date`** evita o bug do fuso: um Pix pago às 22h de 31/12 em Brasília fica em 01/01 no UTC e cairia no ano errado. A conversão usa `users.timezone`, como os relatórios já fazem.

### `health_receipt_batches`

| Coluna | Tipo |
|---|---|
| `id`, `uuid`, `user_id` | |
| `year` | smallint (regra do mesmo ano) |
| `receipts_count`, `total_amount` | |
| `status` | `generated`, `imported`, `return_processed`, `undone` |
| `imported_at`, `return_processed_at`, `undone_at` | nullable |
| timestamps | |

- **O CSV não é guardado em disco/S3.** Ele é gerado na hora a partir dos snapshots, sempre com o mesmo resultado. Assim nenhum arquivo com CPFs fica parado no bucket.
- Lote com mais de 1.000 recibos: o service divide em **vários lotes** de no máximo 1.000. O caso é raro, porque uma psicóloga tem cerca de 80 a 150 sessões por mês.

### `patients`: responsável financeiro

- `payer_name` string nullable e `payer_document` char(11) nullable, com regra `cpf`, no Store/Update Request.
- No formulário do paciente: checkbox "Quem paga as sessões é outra pessoa (responsável financeiro)".
- Pagador do recibo = `payer_document` se existir; se não, `document`.

### `access_logs`

- Migration que amplia o `enum` com `health_receipts_exported` e `health_receipts_return_imported`, seguindo o modelo de `2026_10_18_000001_add_exported_action_to_access_logs_table.php`, além dos slugs em `lang/pt_BR/access-logs.php`.
- `medical_record_id` fica nulo. Não vale criar coluna nova só para isso.

## 5. Camadas

```
app/Enums/HealthReceiptStatusEnum.php, HealthReceiptSourceEnum.php, HealthReceiptBatchStatusEnum.php
app/Models/HealthReceipt.php, HealthReceiptBatch.php          (queries em métodos nomeados do Model)
app/Services/HealthReceiptService.php                         (listar, pendências, CRUD manual, criar a partir de PaymentOrder, estorno)
app/Services/HealthReceiptBatchService.php                    (gerar lote, montar CSV, marcar importado, desfazer, importar retorno)
app/Http/Controllers/HealthReceiptsController.php             (index, paginate, store, update, destroy)
app/Http/Controllers/HealthReceiptBatchesController.php       (store=gerar, download, markImported, undo, importReturn)
app/Http/Requests/HealthReceipts/{PaginateRequest,StoreRequest,UpdateRequest,DeleteRequest}.php
app/Http/Requests/HealthReceiptBatches/{StoreRequest,ImportReturnRequest}.php
resources/views/health-receipts/index.blade.php + partials (lista, filterModal, modal de recebimento, aba "Arquivos gerados")
resources/js/health-receipts.js  (+ entrada no webpack.mix.js)
lang/pt_BR/health-receipts.php
routes/web.php → prefix('health-receipts') no grupo autenticado, com nomes health-receipts.*
```

### Regras do `HealthReceiptBatchService::generate(array $ids, int $userId)`

Tudo dentro de `DB::transaction` com `lockForUpdate()` nos recibos, para que dois cliques não exportem o mesmo recibo duas vezes:

1. Carregar só recibos `pending` (ou `error`) da usuária.
2. Rodar `pendencies()`. Pendência bloqueante → `DomainException` com slug.
3. Exigir o mesmo ano em todos (`paid_on`). Senão, slug `health-receipts.different_years`.
4. Fazer o snapshot dos CPFs, criar o lote, marcar os recibos como `exported` e vincular `health_receipt_batch_id`.
5. Montar o CSV com a ordem dos 16 campos **em um único método** (layout isolado), `mb_convert_encoding(..., 'ISO-8859-1', 'UTF-8')`, linhas com `\r\n`, sem cabeçalho.
6. Responder em `StreamedResponse` com o nome `receita-saude-AAAA-lote-N.csv`.

### `HealthReceiptService::pendencies()` (alimenta os alertas antes de gerar)

| Pendência | Tipo |
|---|---|
| Psicóloga sem CPF em `users.document` | **bloqueia tudo** (link para o perfil) |
| Paciente sem CPF / CPF inválido | bloqueia o recibo |
| Responsável financeiro com CPF inválido | bloqueia o recibo |
| Valor ≤ 0 | bloqueia o recibo |
| Recibo já exportado | fica fora da seleção |
| Pagamento de um ano cujo prazo retroativo (fim de fevereiro do ano seguinte) já passou | alerta |
| Estornado depois de exportado | alerta: "cancele no e-CAC até dd/mm" |

A validação de CPF no backend reaproveita a regra do `laravellegends/pt-br-validator`. No frontend, `validarCPF()` serve só para dar o retorno visual.

### Integração com o fluxo MP (mudança pequena e localizada)

- `ProcessMercadoPagoChargeWebhookService`, na transição para `PAID`: `HealthReceiptService::createFromPaymentOrder($order)`, de forma idempotente graças ao `payment_order_id` unique (`firstOrCreate`).
- Na transição para `REFUNDED`: recibo `pending` → `cancelled`; recibo `exported`/`issued` → grava `refunded_at` e mostra o alerta.
- Nada no observer, que roda no container base do Octane. A chamada fica explícita no service.

### Importação do retorno

- **v1:** botão "Já importei no e-CAC" no lote (`imported_at`) e "Desfazer" (o lote volta a `pending`, ou seja, a psicóloga desfez lá no e-CAC).
- **v1.1:** upload do "CSV de recibos gerados" para preencher `receipt_number` e marcar `issued`/`error`. O parser cruza por `(data, valor, CPF do beneficiário)` dentro do lote. Linha que não casa vai para um relatório e **não derruba o lote**. **Depende de um arquivo real**, porque o layout não é público.

## 6. Tela (mobile first)

- Menu: **"Receita Saúde"**, perto de Pagamentos.
- **Aba "A emitir":** lista `.list-paginate` com checkbox, filtros em `#filterModal` (período por data de pagamento, paciente, status), cartão de pendências em cima, botão **"Gerar arquivo para o Carnê-Leão"** e botão **"Registrar recebimento"** (modal: paciente em Select2, data, valor, descrição opcional).
- **Aba "Arquivos gerados":** lotes com reemissão (baixar de novo), "já importei" e "desfazer".
- **Passo a passo** curto e fixo: e-CAC → Carnê-Leão → Escrituração → Importar Escrituração.
- **Aviso fixo**, em tom acolhedor: "Seus dados fiscais não saem do seu controle: o PSI4 não envia nada à Receita. A escrituração é de sua responsabilidade; em caso de dúvida, fale com seu contador."
- Nada de conteúdo clínico em lugar nenhum. A descrição padrão é genérica.

## 7. Impactos e riscos

| Risco | Mitigação |
|---|---|
| Recibo duplicado na Receita (a importação soma) | `payment_order_id`/`agenda_id` unique, status `exported`, `lockForUpdate`, aviso ao baixar de novo um lote já importado |
| Ano errado por fuso | `paid_on` date convertido com `users.timezone` |
| Encoding com acento corrompido | ISO-8859-1 + teste unitário do conteúdo do arquivo |
| Excel abrir o CSV e estragar datas/CPF | a instrução diz para importar o arquivo direto, sem abrir no Excel |
| CPF em claro no banco | mesmo nível de `patients.document`; o arquivo não fica salvo; exportação registrada em `access_logs`. (Opcional: cast `encrypted` nos snapshots.) |
| Octane | services sem estado; `Auth::user()` dentro dos métodos |
| Contrato de API | nenhuma rota existente muda; só rotas novas e um efeito colateral no webhook do MP |
| Performance | listagem paginada com índice `(user_id, status, paid_on)`, `with('patient:id,name,document,payer_document')` |
| Layout da Receita mudar | montagem do CSV em um único método, coberta por teste |

## 8. Testes (`DatabaseTransactions`, `testIt*`)

`HealthReceiptBatchServiceTest`:
- `testItGenerateCsvWithOfficialLayout`: 16 campos, ordem, `R01.001.001`, `255`, `PF`, `S`, vírgula decimal, ISO-8859-1, sem cabeçalho
- `testItGenerateUsesPayerDocumentWhenPatientHasFinancialResponsible`
- `testItGenerateConvertsPaidAtToUserTimezone`
- `testItThrowExceptionWhenUserHasNoDocument`
- `testItThrowExceptionWhenReceiptsAreFromDifferentYears`
- `testItSplitBatchesWhenMoreThanThousandReceipts`
- `testItIgnoreAlreadyExportedReceipts`
- `testItUndoBatchReturnsReceiptsToPending`
- `testItSanitizeDescriptionRemovingSemicolonAndLineBreaks`

`HealthReceiptServiceTest`:
- `testItCreateFromPaymentOrderOnlyOnce` (idempotente)
- `testItCancelPendingReceiptWhenRefunded` / `testItFlagExportedReceiptWhenRefunded`
- `testItStoreManualReceiptSuccessfully` / `testItThrowExceptionWhenPatientNotFromUser`
- `testItListPendenciesWithAllCases`
- `testItPaginateWithAllFilters`

Teste manual: um mês de sessões de teste, importação no e-CAC de uma psicóloga real (ou do contador) e conferência do total. **É o único jeito de validar o retorno.**

## 9. Decisões para o usuário

1. **Opt-out por paciente:** trocar por "tirar deste lote" com aviso, por causa do conflito com a obrigação legal? *Recomendação: sim.*
2. **Responsável financeiro no paciente** (`payer_name`/`payer_document`) entra na v1? *Recomendação: sim, sem ele o recibo de menores sai errado.*
3. **Importação do retorno:** v1 só com "já importei"/"desfazer", e o parser na v1.1 quando houver um arquivo real? *Recomendação: sim.* Alguém consegue um "CSV de recibos gerados" real?
4. **Backfill** dos pagamentos MP já pagos no ano corrente, por comando Artisan? *Recomendação: não, seguindo a regra de não tratar dados antigos. Talvez só 2026, se pedirem.*
5. **Sugestão a partir da agenda** (sessões `completed` com preço e sem recebimento): P1, depois da v1?
6. **Plano:** liberar no trial e no `professional`, e bloquear no `free`?
7. **Registro profissional (campo 16):** mandar vazio por padrão (caso de quem tem um CRP só) e preencher só se ela informar mais de um? *Recomendação: vazio por padrão, porque o formato exato que a Receita espera não está documentado.*

## 10. O que entrou na v1 (24/10/2026)

As recomendações acima foram aplicadas, com estes ajustes feitos durante o desenvolvimento:

- **Status do recibo:** `pending` ("A emitir"), `exported` ("No arquivo") e `cancelled` ("Não emitir"). `issued`, `error` e `receipt_number` ficam para a v1.1, junto com a leitura do arquivo de retorno.
- **Sem `agenda_id`** em `health_receipts`, porque a sugestão a partir da agenda ficou para depois.
- **A geração é por período** (padrão: o mês anterior), não por seleção de checkbox. Entram todos os recibos a emitir sem pendência; os que têm pendência ficam de fora e são contados na resposta.
- **"Não emitir"** pede confirmação e explica a obrigação legal. "Voltar a emitir" desfaz, exceto quando o pagamento foi estornado.
- **Plano:** gerar, registrar e desfazer são `POST` e ficam bloqueados no plano Grátis pelo `EnsureTrialNotExpired`. Baixar de novo (`GET`) continua liberado.
- **Campo 16** (registro profissional) vai sempre vazio.
- **`access_logs`:** `health_receipts_exported` e `health_receipts_downloaded`.

## Fontes

- [Manual do Carnê-Leão: Importar Escrituração](https://www.gov.br/receitafederal/pt-br/assuntos/meu-imposto-de-renda/pagamento/carne-leao/manual)
- [Formato do arquivo de Escrituração](https://www.gov.br/receitafederal/pt-br/assuntos/meu-imposto-de-renda/pagamento/carne-leao/manual/formato-arquivo)
- [Modelos oficiais (zip)](https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/documentos-tecnicos/escrituracao-do-carne-leao/escrituracao-carne-leao.zip)
- [Manual Receita Saúde v2.1 (out/2025)](https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/manuais/orientacao-tributaria/receita-saude-2.1.pdf)
