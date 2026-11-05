<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Junta a saída do Amazon Transcribe, trecho a trecho, em uma lista de falas no tempo da sessão. O canal 0 é o microfone
 * da psicóloga e o 1, o áudio dos convidados.
 */
class SessionTranscriptHelper
{
    // Pausa que separa duas falas da mesma pessoa.
    private const int PAUSE_MS = 1500;

    // Abaixo disso, a diferença entre trechos é só o tempo de fechar um arquivo e abrir o próximo.
    private const int MIN_GAP_MS = 5000;

    /**
     * @param  array<string, mixed>  $output  O JSON do Transcribe para um trecho.
     * @return list<array{channel: int, startMs: int, endMs: int, text: string}>
     */
    public static function segments(array $output, int $offsetMs): array
    {
        $segments = [];

        foreach ($output['results']['channel_labels']['channels'] ?? [] as $channel) {
            $number = (int) preg_replace('/\D/', '', (string) ($channel['channel_label'] ?? 'ch_0'));

            if ($number > 1) {
                continue;
            }

            $open = null;

            foreach ($channel['items'] ?? [] as $item) {
                $content = (string) ($item['alternatives'][0]['content'] ?? '');

                if ($content === '') {
                    continue;
                }

                if (($item['type'] ?? '') === 'punctuation') {
                    if ($open) {
                        $open['text'] .= $content;
                    }

                    continue;
                }

                $start = $offsetMs + (int) round((float) ($item['start_time'] ?? 0) * 1000);
                $end = $offsetMs + (int) round((float) ($item['end_time'] ?? 0) * 1000);

                if ($open && $start - $open['endMs'] <= self::PAUSE_MS) {
                    $open['text'] .= ' '.$content;
                    $open['endMs'] = $end;

                    continue;
                }

                if ($open) {
                    $segments[] = $open;
                }

                $open = ['channel' => $number, 'startMs' => $start, 'endMs' => $end, 'text' => $content];
            }

            if ($open) {
                $segments[] = $open;
            }
        }

        usort($segments, fn (array $a, array $b) => $a['startMs'] <=> $b['startMs']);

        return $segments;
    }

    /**
     * Os intervalos sem áudio: pausa por convidado novo, aparelho em segundo plano ou trecho que não chegou.
     *
     * @param  list<array{start_ms: int, duration_ms: int}>  $segments  Os trechos transcritos, em ordem.
     * @return list<array{startMs: int, endMs: int}>
     */
    public static function gaps(array $segments): array
    {
        $gaps = [];
        $cursor = 0;

        foreach ($segments as $segment) {
            if ($segment['start_ms'] - $cursor >= self::MIN_GAP_MS) {
                $gaps[] = ['startMs' => $cursor, 'endMs' => $segment['start_ms']];
            }

            $cursor = max($cursor, $segment['start_ms'] + $segment['duration_ms']);
        }

        return $gaps;
    }

    /**
     * O texto que vai ao modelo: uma fala por linha, com o tempo e quem falou.
     *
     * @param  list<array{channel: int, startMs: int, text: string}>  $segments
     * @param  list<array{startMs: int, endMs: int}>  $gaps
     */
    public static function format(array $segments, array $gaps): string
    {
        $lines = [];

        foreach ($segments as $segment) {
            $lines[] = [$segment['startMs'], '['.self::time($segment['startMs']).'] '.($segment['channel'] === 0 ? 'Psicóloga' : 'Paciente').': '.$segment['text']];
        }

        foreach ($gaps as $gap) {
            $lines[] = [$gap['startMs'], '['.self::time($gap['startMs']).'] (trecho não gravado: '.max(1, (int) round(($gap['endMs'] - $gap['startMs']) / 60000)).' min)'];
        }

        usort($lines, fn (array $a, array $b) => $a[0] <=> $b[0]);

        return implode("\n", array_column($lines, 1));
    }

    private static function time(int $ms): string
    {
        $seconds = intdiv($ms, 1000);

        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
