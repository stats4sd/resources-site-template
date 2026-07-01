<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tag_types', function (Blueprint $table) {
            $table->boolean('show_in_filter')->default(false)->after('freetext');
        });
    }

    public function down(): void
    {
        Schema::table('tag_types', function (Blueprint $table) {
            $table->dropColumn('show_in_filter');
        });
    }
};
