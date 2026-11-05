<?php

declare(strict_types=1);

namespace App\Wrappers;

use Anthropic\Bedrock\MantleClient;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\InternalServerException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Jobs\RegisterLog;

/**
 * Rascunho de registro da sessão, gerado pelo Claude no Amazon Bedrock a partir da transcrição. O Bedrock não guarda nem
 * treina com o conteúdo, e o *invocation logging* da conta fica desligado.
 *
 * A falha é registrada só com o status e o tipo do erro. Nem o cURL nem o corpo vão para o log: seriam a fala da sessão.
 */
class SessionNoteWrapper
{
    private const int MAX_TOKENS = 16000;

    private const int MAX_FIELD = 6000;

    public const array FIELDS = ['evolucao', 'procedimentos', 'observacoes'];

    private const string SYSTEM = <<<'TXT'
        Você ajuda uma psicóloga brasileira a escrever o registro de uma sessão, que ela vai revisar, editar e assinar.
        Você recebe a transcrição automática da sessão. Cada fala vem com o tempo e com quem falou (Psicóloga ou Paciente).
        A transcrição pode ter erros de reconhecimento; não tente adivinhar palavras que não fazem sentido.

        Escreva três campos, em português do Brasil, em terceira pessoa, com linguagem técnica e sóbria:
        - evolucao: temas trazidos pelo paciente, mudanças relatadas desde a sessão anterior e combinados feitos;
        - procedimentos: intervenções e recursos usados pela psicóloga na sessão;
        - observacoes: pontos que a psicóloga disse querer retomar.

        Regras:
        - Registre só o necessário para a continuidade do atendimento (Res. CFP 001/2009). Não transcreva falas; no máximo,
          uma expressão curta entre aspas quando for indispensável.
        - Não escreva nomes de terceiros citados na sessão: use "familiar", "colega", "parceiro(a)" e afins.
        - Não escreva hipótese diagnóstica, CID, escala, pontuação, avaliação de risco, prognóstico nem recomendação de
          conduta. Isso é da psicóloga.
        - Não invente. Se a transcrição for curta, inaudível ou não trouxer informação para um campo, deixe esse campo vazio.
        - Use parágrafos curtos, sem títulos e sem marcação.
        TXT;

    /**
     * @param  string  $transcript  A transcrição já formatada, uma fala por linha.
     * @return array{evolucao: string, procedimentos: string, observacoes: string}|null null se o modelo recusar.
     */
    public function draft(string $transcript): ?array
    {
        try {
            $message = $this->client()->messages->create(
                model: (string) config('session_transcription.bedrock.model'),
                maxTokens: self::MAX_TOKENS,
                system: self::SYSTEM,
                messages: [['role' => 'user', 'content' => "<transcricao>\n".$transcript."\n</transcricao>"]],
                outputConfig: [
                    'effort' => 'medium',
                    'format' => [
                        'type' => 'json_schema',
                        'schema' => [
                            'type' => 'object',
                            'properties' => array_fill_keys(self::FIELDS, ['type' => 'string']),
                            'required' => self::FIELDS,
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            );
        } catch (RateLimitException|InternalServerException|APIConnectionException $e) {
            // Passageiro: a fila tenta de novo.
            $this->registerFailure($e);

            throw $e;
        } catch (APIStatusException $e) {
            $this->registerFailure($e);

            throw new \DomainException(__('session-transcriptions.draft_failed'));
        }

        // Recusa não é falha: ela recebe a transcrição e escreve o registro, como faria sem a IA.
        if ($message->stopReason === 'refusal') {
            RegisterLog::dispatchSync([
                'message' => 'O modelo recusou gerar o rascunho da sessão',
                'data' => ['category' => $message->stopDetails?->category],
                'method' => 'SessionNoteWrapper->draft',
            ]);

            return null;
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                return $this->validate(json_decode($block->text, true));
            }
        }

        throw new \DomainException(__('session-transcriptions.draft_failed'));
    }

    /**
     * @return array{evolucao: string, procedimentos: string, observacoes: string}
     */
    private function validate(mixed $json): array
    {
        if (! is_array($json)) {
            throw new \DomainException(__('session-transcriptions.draft_failed'));
        }

        $draft = [];

        foreach (self::FIELDS as $field) {
            $draft[$field] = mb_substr(is_string($json[$field] ?? null) ? trim($json[$field]) : '', 0, self::MAX_FIELD);
        }

        return $draft;
    }

    private function client(): MantleClient
    {
        $key = (string) config('session_transcription.aws.worker.key');
        $secret = (string) config('session_transcription.aws.worker.secret');

        if ($key === '' || $secret === '') {
            RegisterLog::dispatchSync([
                'message' => 'Credencial do Bedrock da transcrição não configurada',
                'method' => 'SessionNoteWrapper->client',
            ]);

            throw new \DomainException(__('session-transcriptions.unavailable'));
        }

        return new MantleClient(
            awsAccessKey: $key,
            awsSecretAccessKey: $secret,
            awsRegion: (string) config('session_transcription.bedrock.region'),
        );
    }

    private function registerFailure(\Throwable $e): void
    {
        RegisterLog::dispatchSync([
            'message' => 'Falha ao gerar o rascunho da sessão no Bedrock ('.$e::class.')',
            'data' => [
                'status' => $e instanceof APIStatusException ? $e->status : null,
                'type' => $e instanceof APIStatusException ? $e->type?->value : null,
            ],
            'method' => 'SessionNoteWrapper->draft',
        ]);
    }
}
