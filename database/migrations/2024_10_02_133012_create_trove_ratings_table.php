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
        Schema::create('trove_ratings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('trove_id')->constrained()->onDelete('cascade');
            $table->double('rating_usefulness')->nullable();
            $table->double('rating_recommend')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trove_ratings');
    }
};
