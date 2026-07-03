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

            // Publishing / single-shadow-draft model (app-owned; replaces oddvalue/laravel-drafts).
            // published_at is the sole source of truth for "is this published": NULL = not published.
            // published_id links a shadow draft row to its canonical published row (NULL on canonical rows).
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_id')->nullable()->constrained('troves')->cascadeOnDelete();
            $table->unique('published_id');
            $table->index('published_at');

            // Review handshake (app-owned). A review is *outstanding* iff review_requested_at
            // is set and reviewed_at is null. reviewed_at is the durable "✓ reviewed" fact and
            // survives publish on the canonical; the request fields are cleared on publish.
            $table->foreignId('requester_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            // reviewer_id: the assigned reviewer while a review is outstanding; overwritten with
            // whoever ACTUALLY reviewed + approved on completion.
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('review_requested_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();

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
