<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pinit_imports', function (Blueprint $table) {
            $table->id();
            $table->string('archivo_path', 500);
            $table->date('fecha_archivo')->index();
            $table->unsignedInteger('total_filas')->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->json('errores')->nullable();
            $table->json('warnings')->nullable();
            $table->foreignId('importado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pinit_imports');
    }
};
