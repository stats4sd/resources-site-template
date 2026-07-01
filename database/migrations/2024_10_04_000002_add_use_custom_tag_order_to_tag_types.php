<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tag_types', function (Blueprint $table) {
            $table->boolean('use_custom_tag_order')->default(false)->after('show_in_filter');
        });
    }

    public function down(): void
    {
        Schema::table('tag_types', function (Blueprint $table) {
            $table->dropColumn('use_custom_tag_order');
        });
    }
};
