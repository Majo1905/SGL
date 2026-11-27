<?php

namespace Database\Seeders;

use App\Models\Correccion;
use App\Models\IntegranteProyecto;
use App\Models\PeriodoAcademico;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $semestre = now()->month <= 6 ? 1 : 2;
        $fechaInicio = $semestre === 1 ? now()->startOfYear() : now()->month(7)->startOfMonth();
        $fechaFin = $semestre === 1 ? now()->month(6)->endOfMonth() : now()->endOfYear();
        $periodo = PeriodoAcademico::firstOrCreate(
            ['nombre' => now()->year.'-'.$semestre],
            [
                'fecha_inicio' => $fechaInicio->toDateString(),
                'fecha_fin' => $fechaFin->toDateString(),
                'estado' => 'ACTIVO',
            ]
        );

        // 1. Usuarios, uno por cada rol del sistema (clave de todos: 12345678)
        $admin = User::create([
            'name' => 'Carlos Pérez (Coordinador)',
            'email' => 'admin@unicordoba.edu.co',
            'password' => Hash::make('12345678'),
            'rol' => 'ADMIN',
        ]);

        $tutor = User::create([
            'name' => 'Dr. Roberto Gómez (Tutor)',
            'email' => 'tutor@unicordoba.edu.co',
            'password' => Hash::make('12345678'),
            'rol' => 'TUTOR',
        ]);

        $jurado = User::create([
            'name' => 'Dra. Ana Torres (Jurado)',
            'email' => 'jurado@unicordoba.edu.co',
            'password' => Hash::make('12345678'),
            'rol' => 'JURADO',
        ]);

        $estudiante1 = User::create([
            'name' => 'Juan Agamez (Estudiante)',
            'email' => 'estudiante1@unicordoba.edu.co',
            'password' => Hash::make('12345678'),
            'rol' => 'ESTUDIANTE',
        ]);

        $estudiante2 = User::create([
            'name' => 'María Martínez (Estudiante)',
            'email' => 'estudiante2@unicordoba.edu.co',
            'password' => Hash::make('12345678'),
            'rol' => 'ESTUDIANTE',
        ]);

        // 2. Proyectos de grado
        $p1 = Proyecto::create([
            'codigo_radicado' => 'PRJ-2026-001',
            'titulo' => 'Sistema de Inteligencia Artificial para Diagnóstico Agrícola en Cultivos de Maíz',
            'asunto_linea_investigacion' => 'Ingeniería de Software y Machine Learning',
            'resumen' => 'Desarrollo de un modelo convolucional basado en Python y Keras para la detección temprana de plagas.',
            'estado' => 'CON_OBSERVACIONES',
            'fecha_radicacion' => '2026-02-10',
            'periodo_academico_id' => $periodo->id,
            'usuario_creador_id' => $estudiante1->id,
        ]);

        $p2 = Proyecto::create([
            'codigo_radicado' => 'PRJ-2026-002',
            'titulo' => 'Plataforma Web para Control de Trámites y Seguimiento Académico (SGTG)',
            'asunto_linea_investigacion' => 'Desarrollo Web y Arquitectura de Software',
            'resumen' => 'Aplicación full-stack en Laravel con componentes interactivos y control estricto de roles académicos.',
            'estado' => 'APROBADO',
            'fecha_radicacion' => '2026-02-15',
            'periodo_academico_id' => $periodo->id,
            'usuario_creador_id' => $estudiante2->id,
        ]);

        $p3 = Proyecto::create([
            'codigo_radicado' => 'PRJ-2026-003',
            'titulo' => 'Optimización de Enrutamiento en Redes de Sensores Inalámbricos (WSN)',
            'asunto_linea_investigacion' => 'Telemática y Redes de Computadores',
            'resumen' => 'Simulación y análisis de protocolos de comunicación eficientes bajo escenarios de alta densidad de nodos.',
            'estado' => 'EN_REVISION',
            'fecha_radicacion' => '2026-02-20',
            'periodo_academico_id' => $periodo->id,
            'usuario_creador_id' => $estudiante1->id,
        ]);

        // 3. Integrantes de cada proyecto
        $integrantes = [
            [$p1, $estudiante1, 'AUTOR_ESTUDIANTE'],
            [$p1, $tutor, 'DIRECTOR_TUTOR'],
            [$p1, $jurado, 'JURADO_EVALUADOR'],
            [$p2, $estudiante2, 'AUTOR_ESTUDIANTE'],
            [$p2, $tutor, 'DIRECTOR_TUTOR'],
            [$p3, $estudiante1, 'AUTOR_ESTUDIANTE'],
            [$p3, $tutor, 'DIRECTOR_TUTOR'],
        ];

        foreach ($integrantes as [$proyecto, $usuario, $rol]) {
            IntegranteProyecto::create([
                'proyecto_id' => $proyecto->id,
                'user_id' => $usuario->id,
                'rol_en_proyecto' => $rol,
            ]);
        }

        // 4. Correcciones / compromisos
        Correccion::create([
            'proyecto_id' => $p1->id,
            'descripcion_observacion' => 'Ajustar la justificación metodológica y detallar el tamaño de la muestra de datos recolectados en campo.',
            'fecha_limite' => '2026-03-15',
            'estado' => 'PENDIENTE',
        ]);

        Correccion::create([
            'proyecto_id' => $p1->id,
            'descripcion_observacion' => 'Incluir diagrama de arquitectura del modelo neuronal propuesto.',
            'fecha_limite' => '2026-03-20',
            'estado' => 'RESUELTO',
        ]);

        Correccion::create([
            'proyecto_id' => $p3->id,
            'descripcion_observacion' => 'Validar las métricas de retardo (delay) frente a escenarios de pérdida de paquetes del 10%.',
            'fecha_limite' => '2026-04-01',
            'estado' => 'PENDIENTE',
        ]);
    }
}
