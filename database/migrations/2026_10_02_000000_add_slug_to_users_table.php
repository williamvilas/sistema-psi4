<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug', 60)->nullable()->unique()->after('code');
        });

        $used = [];

        DB::table('users')->select(['id', 'name'])->orderBy('id')->each(function ($user) use (&$used) {
            $base = Str::limit(Str::slug($user->name) ?: 'psicologo', 50, '');
            $slug = $base;
            $i = 2;

            while (isset($used[$slug])) {
                $slug = $base.'-'.$i++;
            }

            $used[$slug] = true;
            DB::table('users')->where('id', $user->id)->update(['slug' => $slug]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
