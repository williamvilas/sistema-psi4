<?php

declare(strict_types=1);

use App\Enums\PaymentAccountsStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->string('status', 20)->default(PaymentAccountsStatusEnum::PENDING->value)->after('access_token');
        });
    }

    public function down(): void
    {
        Schema::table('payment_accounts', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
