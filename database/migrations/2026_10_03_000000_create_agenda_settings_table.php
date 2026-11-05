<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->time('consultation_time')->default('01:00:00');
            $table->time('available_time_minutes')->default('00:30:00');
            $table->json('agenda_days')->nullable();
            $table->boolean('lunch_enabled')->default(true);
            $table->time('lunch_start')->nullable();
            $table->time('lunch_end')->nullable();
            $table->timestamps();
        });

        Schema::create('agenda_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('name')->nullable();
            $table->boolean('work_enabled')->default(false);
            $table->time('start')->nullable();
            $table->time('end')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'date']);
        });

        $this->copyAgendaSettings();
        $this->copyKeyValueSettings();
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_holidays');
        Schema::dropIfExists('agenda_settings');
    }

    private function copyAgendaSettings(): void
    {
        $now = now();

        DB::table('psychologist_settings')->orderBy('id')->each(function ($row) use ($now) {
            DB::table('agenda_settings')->insert([
                'user_id' => $row->user_id,
                'agenda_days' => $row->agenda_days,
                'lunch_enabled' => $row->lunch_enabled,
                'lunch_start' => $row->lunch_start,
                'lunch_end' => $row->lunch_end,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (json_decode($row->holidays ?? '[]', true) ?: [] as $holiday) {
                if (empty($holiday['date'])) {
                    continue;
                }

                DB::table('agenda_holidays')->insert([
                    'user_id' => $row->user_id,
                    'date' => $holiday['date'],
                    'name' => $holiday['name'] ?? null,
                    'work_enabled' => ! empty($holiday['work_enabled']),
                    'start' => ($holiday['start'] ?? null) ?: null,
                    'end' => ($holiday['end'] ?? null) ?: null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    /**
     * Move consultation_time e available_time_minutes da tabela chave/valor `settings` para colunas.
     */
    private function copyKeyValueSettings(): void
    {
        $now = now();
        $keys = ['consultation_time', 'available_time_minutes'];

        $rows = DB::table('settings')->where('feature', 'agenda')->whereIn('key', $keys)->get()->groupBy('user_id');

        foreach ($rows as $userId => $items) {
            $values = $items->pluck('value', 'key')->map(fn ($value) => trim((string) $value))
                ->filter(fn ($value) => preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value))
                ->all();

            if (! $values) {
                continue;
            }

            if (DB::table('agenda_settings')->where('user_id', $userId)->exists()) {
                DB::table('agenda_settings')->where('user_id', $userId)->update($values + ['updated_at' => $now]);

                continue;
            }

            DB::table('agenda_settings')->insert($values + ['user_id' => $userId, 'created_at' => $now, 'updated_at' => $now]);
        }

        DB::table('settings')->where('feature', 'agenda')->whereIn('key', $keys)->delete();
    }
};
