<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Viaje;
use App\Models\Admin;
use App\Models\Usuario;
use App\Models\Hijo;
use App\Models\Escuela;
use App\Models\Chofer;
use App\Models\Unidad;
use App\Models\ConfirmacionViaje;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;

class ViajeFlowTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private $admin;
    private $usuario;
    private $escuela;
    private $chofer;
    private $unidad;
    private $hijo;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Crear datos de prueba
        $this->escuela = Escuela::factory()->create([
            'nombre' => 'Escuela Benito Juárez',
            'direccion' => 'Av. Juárez #123',
        ]);

        $this->chofer = Chofer::factory()->create([
            'nombre' => 'Juan',
            'apellidos' => 'Pérez',
        ]);

        $this->unidad = Unidad::factory()->create([
            'numero_unidad' => '101',
            'modelo' => 'Mercedes Sprinter',
        ]);

        $this->admin = Admin::factory()->create([
            'email' => 'admin@trailynsafe.com',
        ]);

        $this->usuario = Usuario::factory()->create([
            'email' => 'padre@gmail.com',
            'nombre' => 'Carlos',
            'apellidos' => 'González',
        ]);

        $this->hijo = Hijo::factory()->create([
            'usuario_id' => $this->usuario->id,
            'escuela_id' => $this->escuela->id,
            'nombre' => 'María',
            'apellidos' => 'González',
        ]);
    }

    /** @test */
    public function admin_puede_crear_viaje()
    {
        Sanctum::actingAs($this->admin, ['*'], 'admin');

        $response = $this->postJson('/api/admin/viajes', [
            'nombre_ruta' => 'Ruta Norte Matutina',
            'escuela_id' => $this->escuela->id,
            'chofer_id' => $this->chofer->id,
            'unidad_id' => $this->unidad->id,
            'hora_inicio_confirmacion' => '06:00',
            'hora_fin_confirmacion' => '06:30',
            'hora_inicio_viaje' => '06:45',
            'hora_llegada_estimada' => '08:00',
            'fecha_viaje' => now()->addDay()->format('Y-m-d'),
            'capacidad_maxima' => 30,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'nombre_ruta',
                    'estado',
                    'escuela',
                    'chofer',
                    'unidad',
                ]
            ]);

        $this->assertDatabaseHas('viajes', [
            'nombre_ruta' => 'Ruta Norte Matutina',
            'estado' => 'pendiente',
        ]);
    }

    /** @test */
    public function admin_puede_abrir_confirmaciones()
    {
        Sanctum::actingAs($this->admin, ['*'], 'admin');

        $viaje = Viaje::factory()->create([
            'escuela_id' => $this->escuela->id,
            'estado' => 'pendiente',
        ]);

        $response = $this->postJson("/api/admin/viajes/{$viaje->id}/abrir-confirmaciones");

        $response->assertStatus(200);
        
        $viaje->refresh();
        $this->assertEquals('confirmaciones_abiertas', $viaje->estado);
    }

    /** @test */
    public function padre_puede_ver_viajes_disponibles()
    {
        Sanctum::actingAs($this->usuario);

        $viaje = Viaje::factory()->create([
            'escuela_id' => $this->escuela->id,
            'estado' => 'confirmaciones_abiertas',
            'fecha_viaje' => now()->addDay(),
        ]);

        $response = $this->getJson('/api/viajes/disponibles');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.escuela.id', $this->escuela->id)
            ->assertJsonPath('data.0.estado', 'confirmaciones_abiertas');
    }

    /** @test */
    public function padre_puede_confirmar_viaje_con_ubicacion()
    {
        Sanctum::actingAs($this->usuario);

        $viaje = Viaje::factory()->create([
            'escuela_id' => $this->escuela->id,
            'estado' => 'confirmaciones_abiertas',
            'fecha_viaje' => now()->addDay(),
        ]);

        $response = $this->postJson('/api/viajes/confirmar', [
            'viaje_id' => $viaje->id,
            'hijo_id' => $this->hijo->id,
            'latitud' => 25.686613,
            'longitud' => -100.316116,
            'direccion_recogida' => 'Calle Juárez #123, Monterrey, NL',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('confirmaciones_viaje', [
            'viaje_id' => $viaje->id,
            'hijo_id' => $this->hijo->id,
            'estado' => 'confirmado',
        ]);

        $viaje->refresh();
        $this->assertEquals(1, $viaje->ninos_confirmados);
        $this->assertNotNull($viaje->coordenadas_recogida);
        $this->assertCount(1, $viaje->coordenadas_recogida);
    }

    /** @test */
    public function no_se_puede_confirmar_viaje_sin_periodo_abierto()
    {
        Sanctum::actingAs($this->usuario);

        $viaje = Viaje::factory()->create([
            'escuela_id' => $this->escuela->id,
            'estado' => 'pendiente', // No está abierto
        ]);

        $response = $this->postJson('/api/viajes/confirmar', [
            'viaje_id' => $viaje->id,
            'hijo_id' => $this->hijo->id,
            'latitud' => 25.686613,
            'longitud' => -100.316116,
            'direccion_recogida' => 'Calle Juárez #123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function padre_puede_cancelar_confirmacion()
    {
        Sanctum::actingAs($this->usuario);

        $viaje = Viaje::factory()->create([
            'escuela_id' => $this->escuela->id,
            'estado' => 'confirmaciones_abiertas',
            'ninos_confirmados' => 1,
        ]);

        $confirmacion = ConfirmacionViaje::factory()->create([
            'viaje_id' => $viaje->id,
            'hijo_id' => $this->hijo->id,
            'usuario_id' => $this->usuario->id,
            'estado' => 'confirmado',
        ]);

        $response = $this->postJson('/api/viajes/cancelar', [
            'viaje_id' => $viaje->id,
            'hijo_id' => $this->hijo->id,
        ]);

        $response->assertStatus(200);

        $confirmacion->refresh();
        $this->assertEquals('cancelado', $confirmacion->estado);

        $viaje->refresh();
        $this->assertEquals(0, $viaje->ninos_confirmados);
    }

    /** @test */
    public function chofer_puede_iniciar_viaje()
    {
        $viaje = Viaje::factory()->create([
            'chofer_id' => $this->chofer->id,
            'estado' => 'confirmaciones_cerradas',
        ]);

        $response = $this->postJson("/api/chofer/viajes/{$viaje->id}/iniciar", [
            'chofer_id' => $this->chofer->id,
        ]);

        $response->assertStatus(200);

        $viaje->refresh();
        $this->assertEquals('en_curso', $viaje->estado);
    }

    /** @test */
    public function chofer_puede_escanear_qr()
    {
        $viaje = Viaje::factory()->create([
            'chofer_id' => $this->chofer->id,
            'estado' => 'en_curso',
        ]);

        $confirmacion = ConfirmacionViaje::factory()->create([
            'viaje_id' => $viaje->id,
            'hijo_id' => $this->hijo->id,
            'estado' => 'confirmado',
            'qr_escaneado' => false,
        ]);

        $response = $this->postJson('/api/chofer/escanear-qr', [
            'viaje_id' => $viaje->id,
            'hijo_id' => $this->hijo->id,
            'chofer_id' => $this->chofer->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $confirmacion->refresh();
        $this->assertTrue($confirmacion->qr_escaneado);
        $this->assertNotNull($confirmacion->hora_escaneo_qr);
        $this->assertEquals('completado', $confirmacion->estado);
    }

    /** @test */
    public function no_se_puede_escanear_qr_dos_veces()
    {
        $viaje = Viaje::factory()->create([
            'chofer_id' => $this->chofer->id,
            'estado' => 'en_curso',
        ]);

        $confirmacion = ConfirmacionViaje::factory()->create([
            'viaje_id' => $viaje->id,
            'hijo_id' => $this->hijo->id,
            'qr_escaneado' => true, // Ya escaneado
        ]);

        $response = $this->postJson('/api/chofer/escanear-qr', [
            'viaje_id' => $viaje->id,
            'hijo_id' => $this->hijo->id,
            'chofer_id' => $this->chofer->id,
        ]);

        $response->assertStatus(409); // Conflict
    }

    /** @test */
    public function admin_puede_ver_confirmaciones_de_viaje()
    {
        Sanctum::actingAs($this->admin, ['*'], 'admin');

        $viaje = Viaje::factory()->create([
            'escuela_id' => $this->escuela->id,
        ]);

        ConfirmacionViaje::factory()->count(3)->create([
            'viaje_id' => $viaje->id,
        ]);

        $response = $this->getJson("/api/admin/viajes/{$viaje->id}/confirmaciones");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    /** @test */
    public function flujo_completo_de_viaje()
    {
        // 1. Admin crea viaje
        Sanctum::actingAs($this->admin, ['*'], 'admin');
        
        $responseCrear = $this->postJson('/api/admin/viajes', [
            'nombre_ruta' => 'Ruta Test',
            'escuela_id' => $this->escuela->id,
            'chofer_id' => $this->chofer->id,
            'unidad_id' => $this->unidad->id,
            'hora_inicio_confirmacion' => '06:00',
            'hora_fin_confirmacion' => '06:30',
            'hora_inicio_viaje' => '06:45',
            'hora_llegada_estimada' => '08:00',
            'fecha_viaje' => now()->addDay()->format('Y-m-d'),
        ]);
        
        $viajeId = $responseCrear->json('data.id');
        $this->assertNotNull($viajeId);

        // 2. Admin abre confirmaciones
        $responseAbrir = $this->postJson("/api/admin/viajes/{$viajeId}/abrir-confirmaciones");
        $responseAbrir->assertStatus(200);

        // 3. Padre confirma viaje
        Sanctum::actingAs($this->usuario);
        
        $responseConfirmar = $this->postJson('/api/viajes/confirmar', [
            'viaje_id' => $viajeId,
            'hijo_id' => $this->hijo->id,
            'latitud' => 25.686613,
            'longitud' => -100.316116,
            'direccion_recogida' => 'Casa de prueba',
        ]);
        $responseConfirmar->assertStatus(201);

        // 4. Admin cierra confirmaciones
        Sanctum::actingAs($this->admin, ['*'], 'admin');
        $responseCerrar = $this->postJson("/api/admin/viajes/{$viajeId}/cerrar-confirmaciones");
        $responseCerrar->assertStatus(200);

        // 5. Chofer inicia viaje
        $responseIniciar = $this->postJson("/api/chofer/viajes/{$viajeId}/iniciar", [
            'chofer_id' => $this->chofer->id,
        ]);
        $responseIniciar->assertStatus(200);

        // 6. Chofer escanea QR
        $responseQR = $this->postJson('/api/chofer/escanear-qr', [
            'viaje_id' => $viajeId,
            'hijo_id' => $this->hijo->id,
            'chofer_id' => $this->chofer->id,
        ]);
        $responseQR->assertStatus(200);

        // 7. Chofer finaliza viaje
        $responseFinalizar = $this->postJson("/api/chofer/viajes/{$viajeId}/finalizar", [
            'chofer_id' => $this->chofer->id,
        ]);
        $responseFinalizar->assertStatus(200);

        // Verificar estado final
        $viaje = Viaje::find($viajeId);
        $this->assertEquals('completado', $viaje->estado);
        $this->assertEquals(1, $viaje->ninos_confirmados);
    }
}
