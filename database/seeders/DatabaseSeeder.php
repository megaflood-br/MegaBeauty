<?php

namespace Database\Seeders;

use App\Models\AppointmentStatus;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Criar os status iniciais padrões do sistema (sem tenant_id, disponíveis para todos)
        $statuses = [
            ['name' => 'Aguardando', 'color' => '#fbbf24', 'is_default' => false], // Amarelo
            ['name' => 'Confirmado', 'color' => '#34d399', 'is_default' => true],  // Verde (Padrão)
            ['name' => 'Em Atendimento', 'color' => '#60a5fa', 'is_default' => false], // Azul
            ['name' => 'Finalizado', 'color' => '#9ca3af', 'is_default' => false], // Cinza
            ['name' => 'Cancelado', 'color' => '#f87171', 'is_default' => false],  // Vermelho
            ['name' => 'Não Compareceu', 'color' => '#7c3aed', 'is_default' => false], // Roxo
        ];

        foreach ($statuses as $status) {
            AppointmentStatus::updateOrCreate(
                ['name' => $status['name']],
                $status
            );
        }
    }
}
