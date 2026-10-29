<?php

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
            $table->string('username')->nullable();
        });

        $used = [];

        DB::table('users')
            ->select('id', 'email')
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$used) {
                foreach ($users as $user) {
                    $base = Str::of($user->email)
                        ->before('@')
                        ->lower()
                        ->replaceMatches('/[^a-z0-9._-]/', '')
                        ->value();

                    $base = $base !== '' ? $base : 'user';
                    $username = $base;
                    $suffix = 1;

                    while (isset($used[$username])) {
                        $username = $base.$suffix;
                        $suffix++;
                    }

                    $used[$username] = true;

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['username' => $username]);
                }
            });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
