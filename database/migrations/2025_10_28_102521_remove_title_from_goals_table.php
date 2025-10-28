<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            if (Schema::hasColumn('goals', 'title')) {
                $table->dropColumn('title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->string('title')->nullable();
        });
    }
};
