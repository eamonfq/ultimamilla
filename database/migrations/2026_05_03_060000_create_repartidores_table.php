<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repartidores', function (Blueprint $table) {
            $table->id();
            $table->string('cedula', 20)->unique();
            $table->string('nombre', 150);
            $table->string('telefono', 20)->nullable();
            $table->string('ciudad', 10);
            $table->string('placa', 10)->nullable();
            $table->string('pin');
            $table->unsignedInteger('cupo_personalizado')->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('cedula');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repartidores');
    }
};
