<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 16);
            $table->string('tool', 40);
            $table->string('approach', 8);                 // SAST, SCA, DAST
            $table->string('rule')->nullable();
            $table->text('title');
            $table->string('severity', 10);
            $table->text('location');
            $table->string('cwe', 20)->nullable();
            $table->timestamps();
            $table->index(['scan_id', 'severity']);
            $table->index(['scan_id', 'approach']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
