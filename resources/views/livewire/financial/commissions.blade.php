<?php

use App\Models\Professional;
use App\Models\Commission;
use App\Models\ProfessionalPayment;
use App\Models\Transaction;
use App\Models\FinancialCategory;
use App\Models\Command;
use App\Models\PaymentMethod;
use App\Models\Service;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Barryvdh\DomPDF\Facade\Pdf;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    // Filtros de busca
    public ?string $professional_id = '';
    public string $start_date = '';
    public string $end_date = '';
    public string $filter_status = 'pending';

    // Propriedades do Modal de Pagamento
    public bool $showPaymentModal = false;
    public string $payment_notes = '';
    public string $selected_payment_method_id = '';

    // Propriedades do Modal de Detalhes do Desconto
    public bool $showDiscountModal = false;
    public array $discountModalData = [];
    public array $insumosDetalhados = [];

    // Controle de Seleção Individual de Comissões
    public array $selectedEntries = [];

    public function mount(): void
    {
        $this->start_date = now()->startOfMonth()->format('Y-m-d');
        $this->end_date = now()->endOfMonth()->format('Y-m-d');
    }

    public function updatedProfessionalId(): void
    {
        $this->resetPage();
        $this->selectedEntries = [];
    }
    public function updatedStartDate(): void { $this->resetPage(); $this->selectedEntries = []; }
    public function updatedEndDate(): void { $this->resetPage(); $this->selectedEntries = []; }
    public function updatedFilterStatus(): void { $this->resetPage(); $this->selectedEntries = []; }

    public function toggleSelectAll($visibleIds): void
    {
        if (count($this->selectedEntries) === count($visibleIds)) {
            $this->selectedEntries = [];
        } else {
            $this->selectedEntries = array_map('strval', $visibleIds);
        }
    }

    public function openDiscountModal(string $clientName, string $serviceName, float $precoOriginal, float $insumos, float $taxas, float $base, float $aliquota, float $liquido, bool $isVale, string $comandaId, string $serviceId): void
    {
        $this->insumosDetalhados = [];

        if (!$isVale && !empty($serviceId)) {
            $service = Service::with('products')->find($serviceId);
            $prof = Professional::find($this->professional_id);

            if ($service && $prof) {
                foreach ($service->products as $p) {
                    $qtdGasta = $p->pivot->consumed_quantity;

                    $precoInsumo = match($prof->consumed_product_price_type) {
                        'cost' => $p->cost_price,
                        'sale' => $p->sale_price,
                        default => $p->professional_price
                    };

                    $this->insumosDetalhados[] = [
                        'name' => $p->name,
                        'qty' => $qtdGasta,
                        'total' => $precoInsumo * $qtdGasta
                    ];
                }
            }
        }

        $this->discountModalData = [
            'client_name' => $clientName,
            'service_name' => $serviceName,
            'preco_original' => $precoOriginal,
            'insumos' => $insumos,
            'taxas' => $taxas,
            'base' => $base,
            'aliquota' => $aliquota,
            'liquido' => $liquido,
            'is_vale' => $isVale,
            'comanda_id' => $comandaId
        ];

        $this->showDiscountModal = true;
    }

    public function openPaymentModal(): void
    {
        if (empty($this->professional_id)) {
            session()->flash('error', 'Selecione um profissional para fechar a folha.');
            return;
        }

        $selectedCount = Commission::where('tenant_id', auth()->user()->tenant_id)
            ->whereIn('id', $this->selectedEntries)
            ->where('status', 'pending')
            ->count();

        if ($selectedCount === 0) {
            session()->flash('error', 'Por favor, selecione ao menos um lançamento marcado na tabela antes de fechar a folha.');
            return;
        }

        $tenantId = auth()->user()->tenant_id;
        $defaultMethod = PaymentMethod::where('tenant_id', $tenantId)->where('is_active', true)->first();
        $this->selected_payment_method_id = $defaultMethod ? (string)$defaultMethod->id : '';

        $this->payment_notes = 'Fechamento parcial de comissões selecionadas ref. período ' . date('d/m/Y', strtotime($this->start_date)) . ' até ' . date('d/m/Y', strtotime($this->end_date));
        $this->showPaymentModal = true;
    }

    public function pagarComissoes(): void
    {
        if (empty($this->professional_id)) return;
        if (empty($this->selected_payment_method_id)) {
            session()->flash('error', 'Por favor, selecione uma forma de pagamento válida.');
            return;
        }

        $tenantId = auth()->user()->tenant_id;

        $paymentMethod = PaymentMethod::where('tenant_id', $tenantId)->find($this->selected_payment_method_id);
        $methodName = $paymentMethod ? $paymentMethod->name : 'Dinheiro';
        $accountId = $paymentMethod ? $paymentMethod->account_id : null; // CAPTURA A CONTA VINCULADA (Ex: Itaú)

        $commissions = Commission::where('tenant_id', $tenantId)
            ->where('user_id', $this->professional_id)
            ->where('status', 'pending')
            ->whereIn('id', $this->selectedEntries)
            ->get();

        $totalLiquido = $commissions->sum('calculated_amount');

        if ($totalLiquido <= 0) {
            session()->flash('error', 'O valor líquido dos lançamentos selecionados precisa ser maior que zero para gerar um pagamento.');
            $this->showPaymentModal = false;
            return;
        }

        $payment = ProfessionalPayment::create([
            'tenant_id' => $tenantId,
            'user_id' => $this->professional_id,
            'amount' => $totalLiquido,
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => $methodName,
            'notes' => $this->payment_notes,
        ]);

        foreach ($commissions as $comm) {
            $comm->update([
                'status' => 'paid',
                'professional_payment_id' => $payment->id
            ]);
        }

        $category = FinancialCategory::firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Pagamento de Comissão'],
            ['type' => 'expense', 'is_active' => true]
        );

        // GRAVAÇÃO CORRIGIDA COM ACCOUNT_ID INJETADO CORRETAMENTE
        Transaction::create([
            'tenant_id' => $tenantId,
            'user_id' => auth()->id(),
            'financial_category_id' => $category->id,
            'payment_method_id' => $this->selected_payment_method_id,
            'account_id' => $accountId, // VINCULAÇÃO CORRIGIDA AQUI
            'gross_amount' => $totalLiquido,
            'fee_amount' => 0,
            'net_amount' => $totalLiquido,
            'due_date' => now()->format('Y-m-d'),
            'payment_date' => now()->format('Y-m-d'),
            'status' => 'paid',
            'notes' => "Saída ref. Folha de comissão selecionada paga via {$methodName} ao colaborador ID #{$this->professional_id}. " . $this->payment_notes,
        ]);

        session()->flash('message', 'Os lançamentos selecionados foram fechados e pagos com sucesso!');
        $this->selectedEntries = [];
        $this->showPaymentModal = false;
    }

    public function exportPdf()
    {
        if (empty($this->professional_id)) {
            session()->flash('error', 'Selecione um profissional para conseguir exportar o PDF.');
            return null;
        }

        $tenantId = auth()->user()->tenant_id;
        $professional = Professional::findOrFail($this->professional_id);

        $commissions = Commission::where('tenant_id', $tenantId)
            ->where('user_id', $this->professional_id)
            ->when($this->filter_status, fn($q) => $q->where('status', $this->filter_status))
            ->whereBetween('accrued_date', [$this->start_date, $this->end_date])
            ->orderBy('accrued_date', 'asc')
            ->get();

        $totalBruto = $commissions->where('calculated_amount', '>', 0)->sum('calculated_amount');
        $totalVales = $commissions->where('calculated_amount', '<', 0)->sum('calculated_amount');
        $totalLiquido = $commissions->sum('calculated_amount');

        $data = [
            'professional' => $professional,
            'commissions' => $commissions,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'filter_status' => $this->filter_status,
            'totalBruto' => $totalBruto,
            'totalVales' => abs($totalVales),
            'totalLiquido' => $totalLiquido,
            'emitido_em' => now()->format('d/m/Y H:i')
        ];

        $pdf = Pdf::loadHTML($this->renderPdfHtml($data));

        return response()->streamDownload(
            fn () => print($pdf->output()),
            "extrato-comissoes-{$professional->id}-" . now()->format('YmdHis') . ".pdf"
        );
    }

    private function renderPdfHtml(array $data): string
    {
        $statusTxt = $data['filter_status'] === 'paid' ? 'PAGAS/FECHADAS' : ($data['filter_status'] === 'pending' ? 'PENDENTES (A RECEBER)' : 'HISTÓRICO COMPLETO');
        $linhasHtml = '';

        $insumosParaUltimaPagina = [];

        foreach ($data['commissions'] as $e) {
            $isVale = $e->calculated_amount < 0;
            $valorFormatado = 'R$ ' . number_format(abs($e->calculated_amount), 2, ',', '.');
            $sinalVale = $isVale ? '-' : '';

            $clientName = 'Consumidor Final';
            $serviceName = 'Serviço Prestado';
            $custosDeduzidos = 0.00;
            $precoOriginal = $e->base_amount;

            if (!$isVale && $e->source_type === 'App\Models\Command' && $e->source_id) {
                $comanda = Command::with(['customer', 'services.service.products'])->find($e->source_id);
                if ($comanda) {
                    $clientName = $comanda->customer ? $comanda->customer->name : 'Consumidor Final';

                    $servicePivot = $comanda->services->where('professional_id', $this->professional_id)->first();
                    if ($servicePivot) {
                        $precoOriginal = $servicePivot->price;
                        if ($servicePivot->service) {
                            $serviceName = $servicePivot->service->name;

                            foreach ($servicePivot->service->products as $p) {
                                $qtdGasta = $p->pivot->consumed_quantity;
                                $precoInsumo = match($data['professional']->consumed_product_price_type) {
                                    'cost' => $p->cost_price,
                                    'sale' => $p->sale_price,
                                    default => $p->professional_price
                                };

                                $insumosParaUltimaPagina[] = [
                                    'data' => date('d/m/Y', strtotime($e->accrued_date)),
                                    'servico' => $serviceName,
                                    'insumo' => $p->name,
                                    'qty' => $qtdGasta,
                                    'custo' => $precoInsumo * $qtdGasta
                                ];
                            }
                        }
                    }
                    $custosDeduzidos = max(0, $precoOriginal - $e->base_amount);
                }
            }

            $taxaMeio = 0.00;
            if ($e->transaction && $e->transaction->paymentMethod) {
                $pm = $e->transaction->paymentMethod;
                $taxaMeio = ($precoOriginal * ($pm->fee_percentage / 100));
            }

            if ($isVale) {
                $detalheColuna = "<b style='color:#b91c1c;'>[VALE]</b> Retirada / Adiantamento";
            } else {
                $detalheColuna = "<span style='color:#666; font-size:10px; text-transform:uppercase;'>Cliente: <b>{$clientName}</b> (Comanda #{$e->source_id})</span><br><span style='font-weight:bold; color:#111827;'>{$serviceName}</span>";
            }

            $linhasHtml .= "
                <tr style='background: " . ($isVale ? '#fef2f2' : '#ffffff') . ";'>
                    <td style='padding: 8px; border-bottom: 1px solid #e5e7eb;'>" . date('d/m/Y', strtotime($e->accrued_date)) . "</td>
                    <td style='padding: 8px; border-bottom: 1px solid #e5e7eb;'>{$detalheColuna}</td>
                    <td style='padding: 8px; border-bottom: 1px solid #e5e7eb; text-align: right;'>R$ " . number_format($precoOriginal, 2, ',', '.') . "</td>
                    <td style='padding: 8px; border-bottom: 1px solid #e5e7eb; text-align: right; color:#b91c1c;'> " . ($custosDeduzidos > 0 ? 'R$ '.number_format($custosDeduzidos, 2, ',', '.') : '-') . "</td>
                    <td style='padding: 8px; border-bottom: 1px solid #e5e7eb; text-align: right; color:#f97316;'> " . ($taxaMeio > 0 ? 'R$ '.number_format($taxaMeio, 2, ',', '.') : '-') . "</td>
                    <td style='padding: 8px; border-bottom: 1px solid #e5e7eb; text-align: right;'>R$ " . number_format($e->base_amount, 2, ',', '.') . "</td>
                    <td style='padding: 8px; border-bottom: 1px solid #e5e7eb; text-align: center;'>" . number_format($e->commission_percentage, 1, ',', '.') . "%</td>
                    <td style='padding: 8px; border-bottom: 1px solid #e5e7eb; text-align: right; font-weight: bold; color: " . ($isVale ? '#b91c1c' : '#15803d') . ";'>{$sinalVale} {$valorFormatado}</td>
                </tr>
            ";
        }

        $linhasInsumosHtml = '';
        foreach ($insumosParaUltimaPagina as $insumoItem) {
            $linhasInsumosHtml .= "
                <tr>
                    <td style='padding: 7px; border-bottom: 1px solid #e5e7eb;'>{$insumoItem['data']}</td>
                    <td style='padding: 7px; border-bottom: 1px solid #e5e7eb;'>{$insumoItem['servico']}</td>
                    <td style='padding: 7px; border-bottom: 1px solid #e5e7eb; font-weight:bold;'>{$insumoItem['insumo']}</td>
                    <td style='padding: 7px; border-bottom: 1px solid #e5e7eb; text-align: center;'>x{$insumoItem['qty']}</td>
                    <td style='padding: 7px; border-bottom: 1px solid #e5e7eb; text-align: right; color:#b91c1c; font-weight:bold;'>R$ " . number_format($insumoItem['custo'], 2, ',', '.') . "</td>
                </tr>
            ";
        }

        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; font-size: 10px; color: #374151; margin: 15px; }
                .header { border-bottom: 2px solid #4f46e5; padding-bottom: 10px; margin-bottom: 20px; }
                .title { font-size: 15px; font-weight: bold; color: #4f46e5; }
                .summary-box { background: #f9fafb; padding: 12px; border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 20px; }
                .table-resumo { width: 100%; margin-top: 10px; }
                .table-resumo td { padding: 4px 0; font-size: 11px; }
                .table-main { width: 100%; border-collapse: collapse; margin-top: 15px; }
                .table-main th { background: #4f46e5; color: white; padding: 7px; font-size: 9px; text-transform: uppercase; font-weight: bold; }
                .ultima-pagina { page-break-before: always; margin-top: 20px; }
                .footer-pdf { margin-top: 40px; text-align: center; font-size: 10px; color: #9ca3af; }
                .signature { margin-top: 55px; border-top: 1px solid #4b5563; width: 280px; margin-left: auto; margin-right: auto; padding-top: 5px; font-weight: bold; font-size: 11px; color: #1f2937; }
            </style>
        </head>
        <body>
            <div class='header'>
                <span class='title'>MEGABEAUTY - EXTRATO DE COMISSÕES</span><br>
                <small>Emitido em: {$data['emitido_em']}</small>
            </div>

            <div class='summary-box'>
                <strong>Profissional:</strong> {$data['professional']->name}<br>
                <strong>Período de Competência:</strong> " . date('d/m/Y', strtotime($data['start_date'])) . " até " . date('d/m/Y', strtotime($data['end_date'])) . "<br>
                <strong>Situação dos Lançamentos no PDF:</strong> {$statusTxt}

                <table class='table-resumo'>
                    <tr>
                        <td>Total Comissões (Créditos):</td>
                        <td style='text-align: right; font-weight: bold; color: #15803d;'>R$ " . number_format($data['totalBruto'], 2, ',', '.') . "</td>
                    </tr>
                    <tr>
                        <td>Total Vales/Adiantamentos (Débitos):</td>
                        <td style='text-align: right; font-weight: bold; color: #b91c1c;'>(-) R$ " . number_format($data['totalVales'], 2, ',', '.') . "</td>
                    </tr>
                    <tr style='font-size: 13px; border-top: 1px dashed #d1d5db;'>
                        <td style='padding-top: 6px;'><strong>SALDO LÍQUIDO CONSOLIDADO:</strong></td>
                        <td style='text-align: right; font-weight: bold; color: #4f46e5; padding-top: 6px;'>R$ " . number_format($data['totalLiquido'], 2, ',', '.') . "</td>
                    </tr>
                </table>
            </div>

            <h4 style='color: #111827; margin-bottom: 5px; font-size: 11px;'>Detalhamento de Linhas Registradas</h4>
            <table class='table-main'>
                <thead>
                    <tr>
                        <th style='text-align: left; width: 10%;'>Data</th>
                        <th style='text-align: left; width: 34%;'>Cliente / Serviço</th>
                        <th style='text-align: right; width: 11%;'>Bruto</th>
                        <th style='text-align: right; width: 11%;'>Insumos</th>
                        <th style='text-align: right; width: 11%;'>Taxas</th>
                        <th style='text-align: right; width: 11%;'>Base</th>
                        <th style='text-align: center; width: 6%;'>Aliq.</th>
                        <th style='text-align: right; width: 11%;'>Líquido</th>
                    </tr>
                </thead>
                <tbody>
                    {$linhasHtml}
                </tbody>
            </table>

            <div class='ultima-pagina'>
                <div class='header'>
                    <span class='title'>ANEXO: DESCRIMINAÇÃO DE INSUMOS DEDUZIDOS</span><br>
                    <small>Profissional: {$data['professional']->name}</small>
                </div>

                <p style='font-size:11px; color:#4b5563; margin-bottom:15px;'>Abaixo estão relacionados, item a item, todos os produtos de consumo do salão que foram deduzidos da base de cálculo de comissões do profissional no período selecionado.</p>

                <table class='table-main'>
                    <thead>
                        <tr style='background:#b91c1c;'>
                            <th style='text-align: left;'>Data</th>
                            <th style='text-align: left;'>Serviço de Origem</th>
                            <th style='text-align: left;'>Nome do Insumo Deduzido</th>
                            <th style='text-align: center;'>Qtd Gasta</th>
                            <th style='text-align: right;'>Custo do Insumo</th>
                        </tr>
                    </thead>
                    <tbody>
                        " . ($linhasInsumosHtml ?: "<tr><td colspan='5' style='padding: 15px; text-align: center; color: #666;'>Nenhum insumo ou produto foi deduzido dos atendimentos deste período.</td></tr>") . "
                    </tbody>
                </table>
            </div>

            <div class='footer-pdf'>
                <p>Este documento serve como demonstrativo interno de repasses financeiros e auditoria de serviços.</p>
                " . ($data['filter_status'] === 'paid' ? "
                <div class='signature'>
                    Assinatura do Colaborador: {$data['professional']->name}
                </div>
                " : "") . "
            </div>
        </body>
        </html>
        ";
    }

    public function with(): array
    {
        $tenantId = auth()->user()->tenant_id;

        $commissionsQuery = Commission::with(['transaction.paymentMethod'])
            ->where('tenant_id', $tenantId)
            ->when($this->professional_id, fn($q) => $q->where('user_id', $this->professional_id))
            ->when($this->filter_status, fn($q) => $q->where('status', $this->filter_status))
            ->whereBetween('accrued_date', [$this->start_date, $this->end_date])
            ->orderBy('accrued_date', 'desc');

        $totals = clone $commissionsQuery;
        $bruto = (clone $totals)->where('calculated_amount', '>', 0)->sum('calculated_amount');
        $vales = (clone $totals)->where('calculated_amount', '<', 0)->sum('calculated_amount');
        $liquido = (clone $totals)->sum('calculated_amount');

        $paginatedEntries = $commissionsQuery->paginate(15);
        $visibleIds = $paginatedEntries->pluck('id')->map('strval')->toArray();

        $totalSelecionado = 0;
        if (!empty($this->selectedEntries) && !empty($this->professional_id)) {
            $totalSelecionado = Commission::where('tenant_id', $tenantId)
                ->whereIn('id', $this->selectedEntries)
                ->where('status', 'pending')
                ->sum('calculated_amount');
        }

        return [
            'entries' => $paginatedEntries,
            'visibleIds' => $visibleIds,
            'professionals' => Professional::where('is_active', true)->orderBy('name', 'asc')->get(),
            'paymentMethods' => PaymentMethod::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name', 'asc')->get(),
            'totalBruto' => $bruto,
            'totalVales' => abs($vales),
            'totalLiquido' => $liquido,
            'totalSelecionado' => $totalSelecionado,
        ];
    }
}; ?>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="bg-white shadow sm:rounded-lg p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-800">Filtros da Folha de Comissões</h2>
                @if(!empty($professional_id))
                    <button wire:click="exportPdf" class="px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white text-xs font-bold rounded shadow-sm inline-flex items-center space-x-1 transition">
                        <span>🖨️ Exportar PDF</span>
                    </button>
                @endif
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <x-input-label value="Profissional / Colaborador" />
                    <select wire:model.live="professional_id" class="w-full mt-1 border-gray-300 rounded-md text-sm shadow-sm">
                        <option value="">-- Selecione para analisar --</option>
                        @foreach($professionals as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label value="De (Data Competência)" />
                    <x-text-input type="date" wire:model.live="start_date" class="w-full mt-1 text-sm" />
                </div>
                <div>
                    <x-input-label value="Até (Data Competência)" />
                    <x-text-input type="date" wire:model.live="end_date" class="w-full mt-1 text-sm" />
                </div>
                <div>
                    <x-input-label value="Situação dos Lançamentos" />
                    <select wire:model.live="filter_status" class="w-full mt-1 border-gray-300 rounded-md text-sm shadow-sm">
                        <option value="">Todos (Histórico)</option>
                        <option value="pending">Apenas Pendentes (A fechar)</option>
                        <option value="paid">Já Pagos / Fechados</option>
                    </select>
                </div>
            </div>
        </div>

        @if (session()->has('message'))
            <div class="mb-4 p-4 bg-green-100 text-green-700 rounded shadow-sm border border-green-300">
                {{ session('message') }}
            </div>
        @endif
        @if (session()->has('error'))
            <div class="mb-4 p-4 bg-red-100 text-red-700 rounded shadow-sm border border-red-300">
                {{ session('error') }}
            </div>
        @endif

        @if(!empty($professional_id))
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white p-5 rounded-lg shadow border-l-4 border-indigo-400">
                    <div class="text-xs font-bold text-gray-400 uppercase">Total do Período (Líquido)</div>
                    <div class="text-xl font-bold text-gray-700">R$ {{ number_format($totalLiquido, 2, ',', '.') }}</div>
                </div>
                <div class="bg-white p-5 rounded-lg shadow border-l-4 border-blue-500">
                    <div class="text-xs font-bold text-blue-500 uppercase">Qtd Selecionada para Pagar</div>
                    <div class="text-xl font-bold text-gray-800">{{ count($selectedEntries) }} item(ns) marcado(s)</div>
                </div>
                <div class="bg-white p-5 rounded-lg shadow border-l-4 border-green-500 flex justify-between items-center">
                    <div>
                        <div class="text-xs font-bold text-green-600 uppercase">Valor Selecionado a Pagar</div>
                        <div class="text-2xl font-bold text-green-700">R$ {{ number_format($totalSelecionado, 2, ',', '.') }}</div>
                    </div>
                    @if($filter_status === 'pending' && $totalSelecionado > 0)
                        <button wire:click="openPaymentModal" class="px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded text-xs font-bold shadow-sm transition">
                            Fechar Marcados ✓
                        </button>
                    @endif
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6 overflow-x-auto">
                <h3 class="text-lg font-medium text-gray-800 mb-4">Selecione as linhas que deseja liquidar nesta folha</h3>
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50 text-gray-500 uppercase font-medium">
                        <tr>
                            <th class="px-3 py-3 text-center w-10">
                                @if($filter_status === 'pending')
                                    <input type="checkbox"
                                           wire:click="toggleSelectAll([{{ implode(',', $visibleIds) }}])"
                                           {{ count($selectedEntries) === count($visibleIds) && count($visibleIds) > 0 ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                @endif
                            </th>
                            <th class="px-4 py-3 text-left">Data</th>
                            <th class="px-4 py-3 text-left">Cliente / Item Operacional</th>
                            <th class="px-4 py-3 text-right">Preço Bruto</th>
                            <th class="px-4 py-3 text-right">Insumos/Custos</th>
                            <th class="px-4 py-3 text-right">Taxas Meio</th>
                            <th class="px-4 py-3 text-right">Valor Base</th>
                            <th class="px-4 py-3 text-right">Alíquota</th>
                            <th class="px-4 py-3 text-right">Comissão Líquida</th>
                            <th class="px-4 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 text-gray-700">
                        @forelse($entries as $e)
                            @php
                                $isVale = $e->calculated_amount < 0;
                                $isPending = $e->status === 'pending';

                                $clientName = 'Consumidor Final';
                                $serviceName = 'Serviço Prestado';
                                $custosDeduzidos = 0.00;
                                $precoOriginal = $e->base_amount;
                                $realServiceId = '';

                                if (!$isVale && $e->source_type === 'App\Models\Command' && $e->source_id) {
                                    $comanda = Command::with(['customer', 'services.service'])->find($e->source_id);
                                    if ($comanda) {
                                        $clientName = $comanda->customer ? $comanda->customer->name : 'Consumidor Final';

                                        $servicePivot = $comanda->services->where('professional_id', $this->professional_id)->first();
                                        if ($servicePivot) {
                                            $precoOriginal = $servicePivot->price;
                                            $realServiceId = (string) $servicePivot->service_id;
                                            if ($servicePivot->service) {
                                                $serviceName = $servicePivot->service->name;
                                            }
                                        }
                                        $custosDeduzidos = max(0, $precoOriginal - $e->base_amount);
                                    }
                                }

                                $taxaMeio = 0.00;
                                if ($e->transaction && $e->transaction->paymentMethod) {
                                    $pm = $e->transaction->paymentMethod;
                                    $taxaMeio = ($precoOriginal * ($pm->fee_percentage / 100));
                                }
                            @endphp
                            <tr class="{{ in_array((string)$e->id, $selectedEntries) ? 'bg-indigo-50/70' : ($isVale ? 'bg-red-50/40' : '') }} hover:bg-gray-50 transition">
                                <td class="px-3 py-4 text-center">
                                    @if($isPending)
                                        <input type="checkbox"
                                               value="{{ $e->id }}"
                                               wire:model.live="selectedEntries"
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    @else
                                        <span class="text-green-600 font-bold">✓</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-gray-500 whitespace-nowrap">
                                    {{ date('d/m/Y', strtotime($e->accrued_date)) }}
                                </td>
                                <td class="px-4 py-4">
                                    @if($isVale)
                                        <span class="font-bold text-red-700 uppercase">[VALE]</span>
                                        <span class="text-gray-600 block text-[11px] mt-0.5">Adiantamento em dinheiro / retirada</span>
                                    @else
                                        <span class="text-gray-400 block text-[10px] uppercase font-semibold">Cliente: {{ $clientName }} (Comanda #{{ $e->source_id }})</span>
                                        <span class="font-bold text-gray-900 block mt-0.5 text-[12px]">{{ $serviceName }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-right font-medium text-gray-600">
                                    R$ {{ number_format($precoOriginal, 2, ',', '.') }}
                                </td>
                                <td class="px-4 py-4 text-right text-red-500 font-medium cursor-pointer hover:underline"
                                    wire:click="openDiscountModal('{{ addslashes($clientName) }}', '{{ addslashes($serviceName) }}', {{ $precoOriginal }}, {{ $custosDeduzidos }}, {{ $taxaMeio }}, {{ $e->base_amount }}, {{ $e->commission_percentage }}, {{ $e->calculated_amount }}, {{ $isVale ? 'true' : 'false' }}, '{{ $e->source_id ?? '' }}', '{{ $realServiceId }}')">
                                    {{ $custosDeduzidos > 0 ? 'R$ ' . number_format($custosDeduzidos, 2, ',', '.') : '-' }}
                                </td>
                                <td class="px-4 py-4 text-right text-orange-500 font-medium cursor-pointer hover:underline"
                                    wire:click="openDiscountModal('{{ addslashes($clientName) }}', '{{ addslashes($serviceName) }}', {{ $precoOriginal }}, {{ $custosDeduzidos }}, {{ $taxaMeio }}, {{ $e->base_amount }}, {{ $e->commission_percentage }}, {{ $e->calculated_amount }}, {{ $isVale ? 'true' : 'false' }}, '{{ $e->source_id ?? '' }}', '{{ $realServiceId }}')">
                                    {{ $taxaMeio > 0 ? 'R$ ' . number_format($taxaMeio, 2, ',', '.') : '-' }}
                                </td>
                                <td class="px-4 py-4 text-right font-semibold text-gray-700">
                                    R$ {{ number_format($e->base_amount, 2, ',', '.') }}
                                </td>
                                <td class="px-4 py-4 text-right font-medium text-gray-500 whitespace-nowrap">
                                    {{ number_format($e->commission_percentage, 1, ',', '.') }} %
                                </td>
                                <td class="px-4 py-4 text-right font-bold text-sm {{ $isVale ? 'text-red-600' : 'text-green-700' }} whitespace-nowrap">
                                    {{ $isVale ? '-' : '' }} R$ {{ number_format(abs($e->calculated_amount), 2, ',', '.') }}
                                </td>
                                <td class="px-4 py-4 text-center whitespace-nowrap">
                                    <span class="px-2.5 py-1 inline-flex text-[10px] font-bold rounded-md {{ $e->status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                        {{ $e->status === 'paid' ? 'Pago' : 'Pendente' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-6 py-8 text-center text-gray-500">Nenhum registro de comissão ou vale localizado neste período.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">{{ $entries->links() }}</div>
            </div>
        @else
            <div class="bg-gray-50 border border-dashed rounded-lg p-12 text-center text-gray-500 text-sm">
                Selecione um profissional acima para abrir, exportar o PDF ou auditar o extrato financeiro de comissões.
            </div>
        @endif
    </div>

    @if($showDiscountModal)
        <div class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500 opacity-75" wire:click="$set('showDiscountModal', false)"></div>
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 z-50">
                <div class="flex justify-between items-center mb-4 border-b pb-2">
                    <h3 class="text-base font-bold text-gray-900">🔍 Detalhamento de Descontos</h3>
                    <button wire:click="$set('showDiscountModal', false)" class="text-gray-400 hover:text-gray-600 text-sm font-bold">&times;</button>
                </div>

                <div class="text-xs space-y-3">
                    @if($discountModalData['is_vale'])
                        <div class="p-3 bg-red-50 text-red-800 border border-red-200 rounded-md">
                            Este lançamento refere-se puramente a um <strong>Vale/Adiantamento</strong>. Não possui discounts fracionados de comanda.
                        </div>
                    @else
                        <div>
                            <span class="text-gray-500 uppercase font-semibold">Origem:</span>
                            <span class="text-gray-900 font-bold">Comanda #{{ $discountModalData['comanda_id'] }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 uppercase font-semibold">Cliente:</span>
                            <span class="text-gray-900 font-bold">{{ $discountModalData['client_name'] }}</span>
                        </div>
                        <div class="pb-3 border-b">
                            <span class="text-gray-500 uppercase font-semibold">Serviço:</span>
                            <span class="text-gray-900 font-bold">{{ $discountModalData['service_name'] }}</span>
                        </div>

                        <table class="min-w-full divide-y divide-gray-200 text-[11px] mt-2">
                            <thead class="bg-gray-50 text-gray-600 uppercase font-semibold">
                                <tr>
                                    <th class="py-2 text-left">Item Descontado / Base</th>
                                    <th class="py-2 text-right">Valor</th>
                                    <th class="py-2 text-center">Tipo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700">
                                <tr>
                                    <td class="py-2 font-medium">Preço Bruto Original Cobrado</td>
                                    <td class="py-2 text-right text-gray-900 font-bold">R$ {{ number_format($discountModalData['preco_original'], 2, ',', '.') }}</td>
                                    <td class="py-2 text-center text-blue-600 font-bold">BASE</td>
                                </tr>

                                @if(count($insumosDetalhados) > 0)
                                    @foreach($insumosDetalhados as $insumo)
                                        <tr>
                                            <td class="py-2 pl-4 text-gray-600">↳ Insumo: {{ $insumo['name'] }} (x{{ $insumo['qty'] }})</td>
                                            <td class="py-2 text-right text-red-500">(-) R$ {{ number_format($insumo['total'], 2, ',', '.') }}</td>
                                            <td class="py-2 text-center text-red-400">PRODUTO</td>
                                        </tr>
                                    @endforeach
                                @elseif($discountModalData['insumos'] > 0)
                                    <tr>
                                        <td class="py-2">Custos Operacionais de Insumos</td>
                                        <td class="py-2 text-right text-red-600 font-semibold">(-) R$ {{ number_format($discountModalData['insumos'], 2, ',', '.') }}</td>
                                        <td class="py-2 text-center text-red-500">PRODUTO</td>
                                    </tr>
                                @endif

                                @if($discountModalData['taxas'] > 0)
                                    <tr>
                                        <td class="py-2">Taxas Operacionais do Meio de Pagamento (Cartão/Link)</td>
                                        <td class="py-2 text-right text-red-600 font-semibold">(-) R$ {{ number_format($discountModalData['taxas'], 2, ',', '.') }}</td>
                                        <td class="py-2 text-center text-red-500">GATEWAY</td>
                                    </tr>
                                @endif
                                <tr class="bg-gray-50 font-bold">
                                    <td class="py-2">(=) Valor Base Líquido para Comissão</td>
                                    <td class="py-2 text-right text-gray-900">R$ {{ number_format($discountModalData['base'], 2, ',', '.') }}</td>
                                    <td class="py-2 text-center text-gray-500">-</td>
                                </tr>
                                <tr>
                                    <td class="py-2">Alíquota Aplicada do Profissional</td>
                                    <td class="py-2 text-right text-indigo-600 font-bold">{{ number_format($discountModalData['aliquota'], 1, ',', '.') }} %</td>
                                    <td class="py-2 text-center text-indigo-500 font-bold">PERCENTUAL</td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="mt-4 p-3 bg-green-50 border border-green-200 rounded-md flex justify-between items-center text-xs font-bold text-green-800">
                            <span>Comissão Líquida Final Gerada:</span>
                            <span>R$ {{ number_format(abs($discountModalData['liquido']), 2, ',', '.') }}</span>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end pt-4 mt-4 border-t">
                    <button type="button" wire:click="$set('showDiscountModal', false)" class="px-4 py-1.5 bg-gray-800 hover:bg-gray-900 text-white rounded text-xs font-medium">
                        Fechar Detalhes
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($showPaymentModal)
        <div class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500 opacity-75" wire:click="$set('showPaymentModal', false)"></div>
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 z-50">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Confirmar Liquidação Selecionada</h3>
                <p class="text-sm text-gray-500 mb-4">Você está prestes a quitar apenas as comissões marcadas, somando um valor líquido de **R$ {{ number_format($totalSelecionado, 2, ',', '.') }}**.</p>

                <form wire:submit.prevent="pagarComissoes" class="space-y-4">
                    <div>
                        <x-input-label value="Forma de Pagamento de Saída" />
                        <select wire:model="selected_payment_method_id" class="w-full mt-1 border-gray-300 rounded-md text-sm shadow-sm" required>
                            <option value="">-- Selecione a forma de pagamento --</option>
                            @foreach($paymentMethods as $method)
                                <option value="{{ $method->id }}">{{ $method->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Observações / Recibo de Pagamento" />
                        <textarea wire:model="payment_notes" rows="3" class="w-full mt-1 border-gray-300 rounded-md text-sm shadow-sm" required></textarea>
                    </div>

                    <div class="flex justify-end space-x-2 pt-4 border-t">
                        <button type="button" wire:click="$set('showPaymentModal', false)" class="px-4 py-2 border rounded-md text-sm text-gray-700 bg-white">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md text-sm font-medium">Confirmar e Pagar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
