<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repartidor_id')->constrained('repartidores')->cascadeOnDelete();
            $table->date('fecha_operacion')->index();
            $table->unsignedInteger('paquetes');
            $table->unsignedTinyInteger('rutas');
            $table->string('estado', 20)->default('activa');
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['repartidor_id', 'fecha_operacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
