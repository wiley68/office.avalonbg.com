<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Encrypt plaintext descriptions already stored in `notes.description`
     * (matches Eloquent `encrypted` cast: Encrypter::encrypt($value, false)).
     */
    public function up(): void
    {
        DB::table('notes')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                $raw = $row->description;
                if ($raw === null || $raw === '') {
                    continue;
                }
                try {
                    Crypt::decrypt($raw, false);

                    continue;
                } catch (Throwable) {
                    // Not Laravel-encrypted payload; treat as legacy plaintext.
                }

                DB::table('notes')->where('id', $row->id)->update([
                    'description' => Crypt::encrypt($raw, false),
                ]);
            }
        });
    }

    /**
     * Restore plaintext descriptions (for rollback only).
     */
    public function down(): void
    {
        DB::table('notes')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                $raw = $row->description;
                if ($raw === null || $raw === '') {
                    continue;
                }
                try {
                    $plain = Crypt::decrypt($raw, false);
                } catch (Throwable) {
                    continue;
                }

                DB::table('notes')->where('id', $row->id)->update([
                    'description' => $plain,
                ]);
            }
        });
    }
};
