<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum AccessLogActionEnum: string
{
    use EnumTrait;

    case LOGIN = 'login';
    case MEDICAL_RECORD_CREATED = 'medical_record_created';
    case MEDICAL_RECORD_VIEWED = 'medical_record_viewed';
    case MEDICAL_RECORD_UPDATED = 'medical_record_updated';
    case MEDICAL_RECORD_DELETED = 'medical_record_deleted';
    case MEDICAL_RECORD_EXPORTED = 'medical_record_exported';
    case SCALE_APPLICATION_CREATED = 'scale_application_created';
    case SCALE_APPLICATION_VIEWED = 'scale_application_viewed';
    case SCALE_APPLICATION_DELETED = 'scale_application_deleted';
    case SCALE_APPLICATION_EXPORTED = 'scale_application_exported';
    case SCALE_APPLICATION_ANSWERED = 'scale_application_answered';
    case SESSION_ACTIVITY_CREATED = 'session_activity_created';
    case SESSION_ACTIVITY_VIEWED = 'session_activity_viewed';
    case SESSION_ACTIVITY_PIN_FAILED = 'session_activity_pin_failed';
    case SESSION_ACTIVITY_PIN_CHANGED = 'session_activity_pin_changed';
    case SESSION_ACTIVITY_AUTHORIZED = 'session_activity_authorized';
    case SESSION_TRANSCRIPTION_STARTED = 'session_transcription_started';
    case SESSION_TRANSCRIPTION_VIEWED = 'session_transcription_viewed';
    case SESSION_TRANSCRIPTION_ACCEPTED = 'session_transcription_accepted';
    case SESSION_TRANSCRIPTION_REJECTED = 'session_transcription_rejected';
    case SESSION_TRANSCRIPTION_EXPIRED = 'session_transcription_expired';
    case SESSION_TRANSCRIPTION_CONSENT_REVOKED = 'session_transcription_consent_revoked';
    case ENCRYPTION_KEY_CREATED = 'encryption_key_created';
    case ENCRYPTION_KEY_PASSPHRASE_CHANGED = 'encryption_key_passphrase_changed';
    case ENCRYPTION_KEY_RECOVERY_CHANGED = 'encryption_key_recovery_changed';
    case HEALTH_RECEIPTS_EXPORTED = 'health_receipts_exported';
    case HEALTH_RECEIPTS_DOWNLOADED = 'health_receipts_downloaded';

    public function label(): string
    {
        return __('access-logs.action.'.$this->value);
    }

    public function icon(): string
    {
        return match($this) {
            self::LOGIN => 'fa-right-to-bracket',
            self::MEDICAL_RECORD_CREATED => 'fa-file-circle-plus',
            self::MEDICAL_RECORD_VIEWED => 'fa-eye',
            self::MEDICAL_RECORD_UPDATED => 'fa-pen',
            self::MEDICAL_RECORD_DELETED => 'fa-trash',
            self::MEDICAL_RECORD_EXPORTED => 'fa-file-export',
            self::SCALE_APPLICATION_CREATED => 'fa-clipboard-list',
            self::SCALE_APPLICATION_VIEWED => 'fa-chart-line',
            self::SCALE_APPLICATION_DELETED => 'fa-trash',
            self::SCALE_APPLICATION_EXPORTED => 'fa-file-export',
            self::SCALE_APPLICATION_ANSWERED => 'fa-paper-plane',
            self::SESSION_ACTIVITY_CREATED => 'fa-video',
            self::SESSION_ACTIVITY_VIEWED => 'fa-chart-line',
            self::SESSION_ACTIVITY_PIN_FAILED,
            self::SESSION_ACTIVITY_PIN_CHANGED,
            self::SESSION_ACTIVITY_AUTHORIZED => 'fa-key',
            self::SESSION_TRANSCRIPTION_STARTED => 'fa-microphone-lines',
            self::SESSION_TRANSCRIPTION_VIEWED => 'fa-eye',
            self::SESSION_TRANSCRIPTION_ACCEPTED => 'fa-file-circle-check',
            self::SESSION_TRANSCRIPTION_REJECTED,
            self::SESSION_TRANSCRIPTION_EXPIRED,
            self::SESSION_TRANSCRIPTION_CONSENT_REVOKED => 'fa-trash',
            self::ENCRYPTION_KEY_CREATED,
            self::ENCRYPTION_KEY_PASSPHRASE_CHANGED,
            self::ENCRYPTION_KEY_RECOVERY_CHANGED => 'fa-key',
            self::HEALTH_RECEIPTS_EXPORTED,
            self::HEALTH_RECEIPTS_DOWNLOADED => 'fa-file-invoice',
        };
    }

    public function isMedicalRecord(): bool
    {
        return str_starts_with($this->value, 'medical_record_');
    }

    public function isScaleApplication(): bool
    {
        return str_starts_with($this->value, 'scale_application_');
    }

    public function isDeletion(): bool
    {
        return $this === self::MEDICAL_RECORD_DELETED || $this === self::SCALE_APPLICATION_DELETED;
    }

    /** @return self[] */
    public static function medicalRecordCases(): array
    {
        return array_values(array_filter(self::cases(), fn (self $action) => $action->isMedicalRecord()));
    }
}
