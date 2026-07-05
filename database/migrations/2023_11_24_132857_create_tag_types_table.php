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
        Schema::create('tag_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('label');
            $table->json('description');
            $table->boolean('freetext');
            $table->boolean('show_in_filter')->default(false);
            $table->boolean('use_custom_tag_order')->default(false);
            $table->unsignedInteger('order_column')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tag_types');
    }
};
