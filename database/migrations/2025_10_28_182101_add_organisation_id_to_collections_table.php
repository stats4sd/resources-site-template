<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add column as nullable first
        Schema::table('collections', function (Blueprint $table) {
            $table->foreignId('organisation_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('organisations')
                  ->onDelete('cascade');
        });

        // Set Stats4sd (id=1) as the owner of all existing collections
        DB::table('collections')->update(['organisation_id' => 1]);

        // Make column not nullable
        Schema::table('collections', function (Blueprint $table) {
            $table->foreignId('organisation_id')
                  ->nullable(false)
                  ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            //
        });
    }
};
