<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodos_academicos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 20)->unique();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('estado', 20)->default('ACTIVO');
            $table->timestamps();
            $table->index(['estado', 'fecha_inicio', 'fecha_fin']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('documento_identidad', 20)->nullable()->unique();
            $table->boolean('activo')->default(true);
        });

        Schema::table('proyectos', function (Blueprint $table) {
            $table->foreignId('periodo_academico_id')
                ->nullable()
                ->constrained('periodos_academicos')
                ->nullOnDelete();
            $table->index(['fecha_radicacion', 'estado']);
            $table->enum('estado', ['RADICADO', 'EN_REVISION', 'CON_OBSERVACIONES', 'APROBADO', 'RECHAZADO'])
                ->default('RADICADO')
                ->change();
        });

        Schema::table('correccions', function (Blueprint $table) {
            $table->foreignId('usuario_autor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('respuesta_estudiante')->nullable();
            $table->timestamp('fecha_resolucion')->nullable();
            $table->index(['proyecto_id', 'estado', 'fecha_limite']);
        });

        Schema::table('integrante_proyectos', function (Blueprint $table) {
            $table->unique(['proyecto_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('integrante_proyectos', function (Blueprint $table) {
            $table->dropUnique(['proyecto_id', 'user_id']);
        });

        Schema::table('correccions', function (Blueprint $table) {
            $table->dropForeign(['usuario_autor_id']);
            $table->dropIndex(['proyecto_id', 'estado', 'fecha_limite']);
            $table->dropColumn(['usuario_autor_id', 'respuesta_estudiante', 'fecha_resolucion']);
        });

        DB::table('proyectos')->where('estado', 'RADICADO')->update(['estado' => 'EN_REVISION']);

        Schema::table('proyectos', function (Blueprint $table) {
            $table->dropForeign(['periodo_academico_id']);
            $table->dropIndex(['fecha_radicacion', 'estado']);
            $table->dropColumn('periodo_academico_id');
            $table->enum('estado', ['EN_REVISION', 'CON_OBSERVACIONES', 'APROBADO', 'RECHAZADO'])
                ->default('EN_REVISION')
                ->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['documento_identidad']);
            $table->dropColumn(['documento_identidad', 'activo']);
        });

        Schema::dropIfExists('periodos_academicos');
    }
};
