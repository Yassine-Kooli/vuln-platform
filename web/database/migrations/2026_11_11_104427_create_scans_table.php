<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_id')->constrained()->cascadeOnDelete();
            $table->json('stages');                        // ex. ["sast","sca","dast"]
            $table->string('label', 30)->default('web');
            $table->string('status', 24)->default('queued'); // queued, running, done, done_with_errors, failed
            $table->json('stage_states')->nullable();      // état de chaque étape
            $table->string('run_dir')->nullable();         // dossier results/<run>
            $table->integer('exit_code')->nullable();
            $table->string('error')->nullable();
            $table->json('summary')->nullable();
            $table->json('metrics')->nullable();
            $table->json('comparison')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scans');
    }
};
