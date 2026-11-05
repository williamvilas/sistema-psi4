<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DocumentsEnum;
use App\Models\Documents;
use Illuminate\Database\Seeder;

class DocumentsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->format('Y-m-d H:i:s');

        $documents = [
            [
                'id' => 1,
                'title' => '1º Modelo',
                'path' => 'documents.templates.attendance-statement-01',
                'type' => DocumentsEnum::ATTENDANCE_STATEMENT,
            ],
            [
                'id' => 2,
                'title' => '1º Modelo',
                'path' => 'documents.templates.psychological-certificate-01',
                'type' => DocumentsEnum::PSYCHOLOGICAL_CERTIFICATE,
            ],
            [
                'id' => 3,
                'title' => '1º Modelo',
                'path' => 'documents.templates.psychologist-statement-01',
                'type' => DocumentsEnum::PSYCHOLOGIST_STATEMENT,
            ],
            [
                'id' => 4,
                'title' => '1º Modelo',
                'path' => 'documents.templates.psychological-report-01',
                'type' => DocumentsEnum::PSYCHOLOGICAL_REPORT,
            ],
        ];

        foreach ($documents as $document) {
            Documents::query()->insertOrIgnore([
                ...$document,
                'user_id' => null,
                'active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
