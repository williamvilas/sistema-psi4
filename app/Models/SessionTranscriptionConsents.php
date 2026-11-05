<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SessionTranscriptionConsentEnum;
use Illuminate\Database\Eloquent\Model;

class SessionTranscriptionConsents extends Model
{
    protected $table = 'session_transcription_consents';

    protected $fillable = [
        'session_transcription_id',
        'participant_ref',
        'decision',
        'consent_version',
        'consent_hash',
        'consent_text',
        'decided_at',
        'ip',
    ];

    protected $casts = [
        'decision' => SessionTranscriptionConsentEnum::class,
        'consent_version' => 'integer',
        'decided_at' => 'datetime',
    ];
}
