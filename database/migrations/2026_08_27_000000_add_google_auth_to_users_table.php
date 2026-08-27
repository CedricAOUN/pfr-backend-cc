<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $usedNames = [];

        foreach (DB::table('users')->orderBy('id')->get(['id', 'name']) as $user) {
            $nameKey = Str::lower($user->name);

            if (! isset($usedNames[$nameKey])) {
                $usedNames[$nameKey] = true;

                continue;
            }

            $base = Str::slug($user->name, '.') ?: 'user';
            $suffix = 2;

            do {
                $ending = '.'.$suffix++;
                $candidate = Str::limit($base, 20 - strlen($ending), '').$ending;
            } while (isset($usedNames[Str::lower($candidate)]));

            DB::table('users')->where('id', $user->id)->update(['name' => $candidate]);
            $usedNames[Str::lower($candidate)] = true;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('password');
            $table->string('password')->nullable()->change();
            $table->unique('name');
        });
    }

    public function down(): void
    {
        DB::table('users')->whereNull('password')->orderBy('id')->eachById(function ($user) {
            DB::table('users')->where('id', $user->id)->update([
                'password' => Hash::make(Str::random(40)),
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropUnique(['name']);
            $table->dropColumn('google_id');
            $table->string('password')->nullable(false)->change();
        });
    }
};
