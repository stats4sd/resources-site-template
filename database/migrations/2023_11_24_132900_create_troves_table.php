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
        Schema::disableForeignKeyConstraints();

        Schema::create('troves', function (Blueprint $table) {
            $table->id();

            $table->string('slug');
            $table->json('title');
            $table->json('description');
            $table->foreignId('trove_type_id')->nullable()->constrained();
            $table->boolean('source');
            $table->date('creation_date');
            $table->foreignId('uploader_id')->constrained('users');

            $table->json('external_links')->nullable();
            $table->json('youtube_links')->nullable();

            $table->integer('download_count')->default(0);

            $table->drafts();
            $table->foreignId('requester_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('checker_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();

            $table->json('previous_slugs')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('troves');
    }
};
