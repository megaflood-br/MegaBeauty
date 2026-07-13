<?php
use App\Models\Command;
use App\Models\Appointment;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

// Definimos o layout aqui no topo para evitar o erro de MissingLayoutException
new #[Layout('layouts.app')] class extends Component {
    public float $totalVendas = 0;
    public int $totalAgendamentos = 0;
    public int $totalComandas = 0;
    public $tendencia = [];

    public function mount() {
        $tenantId = auth()->user()->tenant_id;

        $this->totalVendas = Command::where('tenant_id', $tenantId)
            ->where('status', 'finished')
            ->sum('total_amount');

        $this->totalAgendamentos = Appointment::where('tenant_id', $tenantId)->count();
        $this->totalComandas = Command::where('tenant_id', $tenantId)->count();

        $this->tendencia = Appointment::where('tenant_id', $tenantId)
            ->where('date', '>=', now()->subDays(7))
            ->select(DB::raw('DATE(date) as dia'), DB::raw('count(*) as total'))
            ->groupBy('dia')
            ->orderBy('dia', 'ASC')
            ->get();
    }
}; ?>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-gray-400 text-xs font-bold uppercase tracking-wider">Vendas Totais</h3>
                <p class="text-3xl font-black text-gray-800 mt-2">R$ {{ number_format($totalVendas, 2, ',', '.') }}</p>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-gray-400 text-xs font-bold uppercase tracking-wider">Agendamentos</h3>
                <p class="text-3xl font-black text-indigo-600 mt-2">{{ $totalAgendamentos }}</p>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-gray-400 text-xs font-bold uppercase tracking-wider">Total Comandas</h3>
                <p class="text-3xl font-black text-emerald-500 mt-2">{{ $totalComandas }}</p>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <h3 class="text-gray-700 font-bold mb-4">Tendência de Atendimentos (Últimos 7 dias)</h3>
            <canvas id="meuGrafico" height="80"></canvas>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('livewire:navigated', () => {
            renderChart();
        });

        function renderChart() {
            const labels = @json($tendencia->pluck('dia'));
            const data = @json($tendencia->pluck('total'));

            const ctx = document.getElementById('meuGrafico').getContext('2d');

            // Destrói gráfico anterior se existir (evita erro ao navegar pelo sistema)
            if (window.chartInstance) { window.chartInstance.destroy(); }

            window.chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Atendimentos',
                        data: data,
                        backgroundColor: '#4f46e5',
                        borderColor: '#4f46e5',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: { responsive: true }
            });
        }

        renderChart();
    </script>
</div>
