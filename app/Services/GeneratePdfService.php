<?php

declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;

readonly class GeneratePdfService
{
    public function handle(string $html): ResponseFactory|Response
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        // Templates com várias páginas definem o próprio @page; o reset de html/body anularia essa margem.
        $pageReset = str_contains($html, '@page')
            ? ''
            : '@page { margin: 0; } html, body { margin: 0; padding: 0; border: 0; }';
        $html = str_replace(
            '</head>',
            '<style>
                '.$pageReset.'
                .container{ border: 0;}
                .footer { position: fixed !important; bottom: 20px !important; left: 30px !important; right: 30px !important; }
            </style></head>',
            $html,
        );
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="documento.pdf"',
        ]);
    }
}
