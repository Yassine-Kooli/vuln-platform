<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('targets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();          // slug utilisé par le pipeline
            $table->string('label')->nullable();           // nom affiché
            $table->string('url');                         // cible DAST
            $table->string('source_path')->nullable();     // code source (SAST / SCA)
            $table->string('image')->nullable();           // image Docker (SCA, optionnel)
            $table->string('snapshot_path')->nullable();   // capture d'écran (public disk)
            $table->string('snapshot_error')->nullable();
            $table->timestamp('snapshot_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('targets');
    }
};
