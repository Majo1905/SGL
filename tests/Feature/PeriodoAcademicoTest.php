<?php

namespace Tests\Feature;

use App\Models\PeriodoAcademico;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodoAcademicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_administra_periodos_y_puede_cerrarlos(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('periodos.store'), [
            'nombre' => '2026-2',
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-12-31',
        ])->assertRedirect(route('periodos.index'));

        $periodo = PeriodoAcademico::firstOrFail();
        $this->assertSame('ACTIVO', $periodo->estado);

        $this->actingAs($admin)->post(route('periodos.store'), [
            'nombre' => '2027-1',
            'fecha_inicio' => '2027-01-01',
            'fecha_fin' => '2027-06-30',
        ])->assertRedirect(route('periodos.index'));

        $this->assertSame('CERRADO', $periodo->fresh()->estado);
        $nuevoPeriodo = PeriodoAcademico::where('nombre', '2027-1')->firstOrFail();
        $this->assertSame('ACTIVO', $nuevoPeriodo->estado);

        $this->actingAs($admin)->patch(route('periodos.update', $nuevoPeriodo), [
            'estado' => 'CERRADO',
        ])->assertRedirect(route('periodos.index'));

        $this->assertSame('CERRADO', $nuevoPeriodo->fresh()->estado);
    }

    public function test_periodos_solo_son_administrables_por_administradores(): void
    {
        $this->actingAs(User::factory()->create())->get(route('periodos.index'))->assertForbidden();
    }

    public function test_periodo_rechaza_rango_de_fechas_invertido(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('periodos.store'), [
                'nombre' => '2026-2',
                'fecha_inicio' => '2026-12-31',
                'fecha_fin' => '2026-07-01',
            ])->assertSessionHasErrors('fecha_fin');

        $this->assertSame(0, PeriodoAcademico::count());
    }
}
