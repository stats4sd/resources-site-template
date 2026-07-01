<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tag_types', function (Blueprint $table) {
            $table->unsignedInteger('order_column')->nullable()->after('show_in_filter');
        });
    }

    public function down(): void
    {
        Schema::table('tag_types', function (Blueprint $table) {
            $table->dropColumn('order_column');
        });
    }
};
