<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pinit_rutas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pinit_import_id')->constrained('pinit_imports')->cascadeOnDelete();
            $table->string('id_ruta_pinit', 30)->index();
            $table->string('cedula', 20)->index();
            $table->string('nombre_operador', 200);
            $table->string('ciudad_parseada', 10)->nullable();
            $table->string('placa', 10)->nullable();
            $table->foreignId('repartidor_id')->nullable()->constrained('repartidores')->nullOnDelete();
            $table->string('status', 20);
            $table->unsignedInteger('total');
            $table->unsignedInteger('entregados');
            $table->decimal('porcentaje', 5, 2)->nullable();
            $table->json('excepciones')->nullable();
            $table->json('tiempos')->nullable();
            $table->json('performance')->nullable();
            $table->boolean('es_devolucion')->default(false)->index();
            $table->timestamps();

            $table->index('pinit_import_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pinit_rutas');
    }
};
