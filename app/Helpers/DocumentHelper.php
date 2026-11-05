<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Documents;

class DocumentHelper
{
    public static function getContentDoc(Documents $doc, array $data = [], array $configs = []): string
    {
        if (! $doc->user_id) {
            return view($doc->path, ['data' => $data, 'configs' => $configs])->render();
        }

        return '';
    }
}
