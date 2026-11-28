<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // aprobación previa del tutor antes de que el proyecto llegue a los jurados
        Schema::table('proyectos', function (Blueprint $table) {
            $table->foreignId('validado_por_tutor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validado_tutor_at')->nullable();
        });

        // notificaciones internas (canal "database" de Laravel)
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        // el documento define la unicidad sobre (proyecto, usuario, rol en el proyecto).
        // Se crea primero la nueva para que las llaves foráneas sigan teniendo índice.
        Schema::table('integrante_proyectos', function (Blueprint $table) {
            $table->unique(['proyecto_id', 'user_id', 'rol_en_proyecto'], 'integrante_proyecto_usuario_rol_unique');
        });
        Schema::table('integrante_proyectos', function (Blueprint $table) {
            $table->dropUnique(['proyecto_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('integrante_proyectos', function (Blueprint $table) {
            $table->unique(['proyecto_id', 'user_id']);
        });
        Schema::table('integrante_proyectos', function (Blueprint $table) {
            $table->dropUnique('integrante_proyecto_usuario_rol_unique');
        });

        Schema::dropIfExists('notifications');

        Schema::table('proyectos', function (Blueprint $table) {
            $table->dropForeign(['validado_por_tutor_id']);
            $table->dropColumn(['validado_por_tutor_id', 'validado_tutor_at']);
        });
    }
};
