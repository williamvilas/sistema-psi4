# Refinamento revisado: Receita Saúde

> Fonte da análise do concorrente: https://usecorpora.com.br/receita-saude-psicologos/
> Os detalhes sobre a Corpora vêm de um resumo da página e **não foram verificados na Receita Federal**.

## O que a Corpora faz

Não há API oficial, certificado nem login gov.br. É um fluxo manual em lote, nesta ordem:

1. A psicóloga filtra os recebimentos por data de pagamento e por paciente.
2. A tela mostra alertas de pendência antes de gerar, como CPF incompleto ou pagamento sem data.
3. Um clique gera um **CSV no formato de escrituração do Carnê-Leão**.
4. A psicóloga importa o arquivo em **e-CAC → Carnê-Leão → Escrituração**.
5. Ela baixa o **arquivo de retorno** da Receita e o importa de volta na Corpora, que atualiza o status de cada recibo.

Os arquivos gerados ficam em "Arquivos Gerados", para consulta, reemissão e cancelamento. A página diz que a "Corpora não envia dados para a Receita em seu nome".

- **Base legal citada:** IN RFB 2.240/2024, em vigor desde 01/01/2025 para psicólogos pessoa física.
- **Plano:** o recurso está no plano Profissional, R$ 89/mês. A página extraída não deixa claro se o plano gratuito inclui a emissão.
- **NFS-e:** a página não menciona NFS-e.

## O que isso muda no refinamento

- **A decisão mais arriscada sumiu.** Ficam descartados o RPA, a guarda de credencial gov.br e a dúvida sobre API. O trabalho passa a ser gerar o arquivo no layout correto e ler o retorno.
- **Combina com o diferencial de privacidade.** O PSI4 nunca fala com a Receita, e a psicóloga continua no controle. Dá para usar na comunicação: "seus dados fiscais não saem do seu controle".
- **A Corpora não mostra NFS-e nessa página.** O `.market-research/pricing.html` marca "sim" para NFS-e na Corpora, e essa informação não se sustenta aqui. Corrigir o documento ou confirmar em outra fonte. Se for verdade que ela não tem, a **NFS-e vira diferencial possível**, e não paridade. Segue como Fase 2, para PJ/MEI/Simples.
- **O arquivo de retorno dá a sensação de "emitido".** Sem ele, fica difícil mostrar o status real de cada recibo. Entra no escopo.

## Alinhamento com a estratégia

- **P0 no `strategy.html`:** a lei manda e os dois concorrentes anunciam a funcionalidade. Sem ela, o PSI4 perde na primeira comparação.
- **Dores atacadas:** burocracia e documentação, Receita Saúde e culpa de cobrar. O recibo sai da fila de pagamentos sem a psicóloga tocar no assunto com o paciente.
- **Indicador:** "% de clientes com cobrança ativa > 30%" sobe, porque quem escritura pelo sistema não sai dele.
- **Privacidade:** o recibo leva CPF do paciente e valor, **nunca conteúdo clínico**. Descrição genérica ("Serviço de psicoterapia"). Nada de CID, queixa ou evolução.
- **Controle por paciente:** o paciente pode não querer o recibo na Receita. É preciso uma opção explícita de não escriturar.
- **Sem conflito com a estratégia.** A emissão é assistida e opcional, com a profissional no controle.

## Escopo proposto da v1

- **Tela "Receita Saúde"** com lista paginada de recebimentos e filtros por data de pagamento e por paciente (`list-paginate` + `filterModal`).
- **Alertas antes de gerar:** paciente sem CPF, pagamento sem `paid_at`, CPF inválido (o `validarCPF()` já existe no JS) e recebimento já escriturado.
- **Geração do CSV do Carnê-Leão** em lote, com um registro por arquivo gerado (`fiscal_batches`) e um por recibo (`fiscal_documents`). O `payment_order_id` único evita escriturar o mesmo pagamento duas vezes.
- **Importação do arquivo de retorno** para atualizar o status por recibo. Depende do formato real.
- **Cancelamento** da escrituração, ligado ao estorno (`REFUNDED`).
- **Recebimento manual** (Pix ou dinheiro fora do Mercado Pago). Sem isso, o módulo só serve a quem cobra pelo PSI4. Exige um recebimento sem gateway no `payment_orders` ou uma entidade nova. Decisão de modelagem em aberto.
- **Registro em `access_logs`** de cada geração, download e importação.
- **Recibo em PDF para o paciente** como extra (Dompdf).

Não precisa de Wrapper nem de Job. Sem HTTP externo, não há `RegisterLog` de falha a tratar. A geração de arquivos grandes pode ir para a fila.

## Impactos e riscos

| Risco | Mitigação |
|---|---|
| Dado sensível (CPF + saúde) sob LGPD | Dado mínimo, descrição genérica, registro em `access_logs` |
| Escrituração duplicada | `payment_order_id` único |
| Estorno (`REFUNDED`) depois de escriturado | Definir o fluxo de cancelamento. Decisão em aberto |
| Sessão paga fora do PSI4 | Recebimento manual na v1 |
| Layout do CSV mudar | Isolar a geração no Service, com o formato em um único lugar |
| Octane | Nada de estado do usuário em propriedade de service |
| Responsabilidade fiscal | Aviso de que a escrituração é responsabilidade da profissional, e validação com um contador |

## Dados a confirmar no código

- `users` e `patients` guardam CPF? `psychologist_settings` já tem `cnpj`, `crp` e endereço, mas falta confirmar o CPF do profissional.
- Se o CPF do paciente é obrigatório hoje.

## Pendências que bloqueiam o código

1. **Layout oficial do CSV e do arquivo de retorno** do Carnê-Leão: colunas, separador, encoding, regras de CPF e de data. Sem a especificação da Receita, qualquer arquivo gerado é chute.
2. **Validação com um contador**, como o `strategy.html` já pede. Principal dúvida: se a escrituração de recibos de sessão cobre todos os casos (paciente que paga por terceiro, convênio, sessão sem cobrança).
3. **Plano:** entra no plano único de R$ 49? A Corpora parece cobrar R$ 89 por ela. Como é obrigação legal e P0, a recomendação é incluir no plano pago.

## Decisões em aberto

1. Buscar a documentação oficial do layout do Carnê-Leão na Receita antes de seguir?
2. Recebimento manual entra na v1? Recomendação: sim.
3. Opção "não escriturar" por paciente? Recomendação: sim.
4. Corrigir o `pricing.html` sobre a NFS-e da Corpora, ou existe outra fonte?

## Testes que fazem sentido

Testes unitários do service com `DatabaseTransactions` e nomenclatura `testIt*`:

- geração com sucesso;
- não duplica a escrituração do mesmo pagamento;
- rejeita pagamento que não está `PAID`;
- ignora paciente marcado como "não escriturar";
- cancelamento após `REFUNDED`;
- importação do retorno atualiza o status de cada recibo;
- retorno com linha inválida não derruba o lote.

Teste manual: um mês de sessões de uma psicóloga de teste, com conferência do total contra o carnê-leão.

## Próximo passo

Confirmar a decisão 1 para buscar o layout oficial e fechar o modelo de dados. Depois disso, iniciar o desenvolvimento.
