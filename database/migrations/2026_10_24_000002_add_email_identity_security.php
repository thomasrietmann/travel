<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_identity_lock', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
        });
        DB::table('email_identity_lock')->insert(['id' => 1]);
        Schema::table('user_email_aliases', function (Blueprint $table): void {
            $table->timestamp('verified_at')->nullable();
        });
        Schema::table('imported_mails', function (Blueprint $table): void {
            $table->unsignedInteger('attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('imported_mails', fn (Blueprint $table) => $table->dropColumn('attempts'));
        Schema::table('user_email_aliases', fn (Blueprint $table) => $table->dropColumn('verified_at'));
        Schema::dropIfExists('email_identity_lock');
    }
};
