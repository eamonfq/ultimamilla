<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cruces', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_operacion')->index();
            $table->foreignId('repartidor_id')->constrained('repartidores')->cascadeOnDelete();
            $table->unsignedInteger('reservado')->default(0);
            $table->unsignedInteger('asignado')->default(0);
            $table->unsignedInteger('entregado')->default(0);
            $table->decimal('cumplimiento_pct', 5, 2)->nullable();
            $table->string('patron', 20)->nullable();
            $table->foreignId('pinit_import_id')->constrained('pinit_imports')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['fecha_operacion', 'repartidor_id']);
            $table->unique(['fecha_operacion', 'repartidor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cruces');
    }
};
