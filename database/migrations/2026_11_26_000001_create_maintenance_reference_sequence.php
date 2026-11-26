<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_reference_sequence', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('value');
        });

        $max = (int) DB::table('maintenance_records')->max('id');
        DB::table('maintenance_records')->orderBy('id')->select(['id', 'reference'])->chunkById(500, function ($records) use (&$max) {
            foreach ($records as $record) {
                if (preg_match('/^MR-([0-9]+)$/', $record->reference, $matches)) {
                    $max = max($max, (int) $matches[1]);
                }
            }
        });
        DB::table('maintenance_reference_sequence')->insert(['id' => 1, 'value' => $max]);
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_reference_sequence');
    }
};
