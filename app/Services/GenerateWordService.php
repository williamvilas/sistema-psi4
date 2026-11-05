<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;

readonly class GenerateWordService
{
    public function handle(string $html): ResponseFactory|Response
    {
        return response($html)
            ->header('Content-Type', 'application/msword')
            ->header('Content-Disposition', 'attachment; filename="declaracao-01.docx"');
    }
}
