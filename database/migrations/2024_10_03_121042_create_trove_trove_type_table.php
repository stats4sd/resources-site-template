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
        Schema::create('trove_trove_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trove_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trove_type_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trove_trove_type');
    }
};
