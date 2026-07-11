<?php

use App\Models\Command;
use App\Models\CommandService;
use App\Models\CommandProduct;
use App\Models\User;
use App\Models\Professional;
use App\Models\Service;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\FinancialCategory;
use App\Models\Commission;
use App\Models\Appointment;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filter_status = '';
    public string $filter_date = '';

    public bool $showModal = false;
    public int $checkoutStep = 1;

    public ?int $selectedCommandId = null;
    public string $client_search = '';
    public ?string $customer_id = null;
    public ?string $professional_id = '';
    public string $code = '';
    public string $discount = '0,00';
    public ?string $payment_method_id = null;
    public string $command_status = 'open';
    public string $finished_date = '';
    public string $saved_payment_method = '';

    public array $itemsServices = [];
    public array $itemsProducts = [];

    public ?int $activeServiceDropdown = null;
    public bool $showClientDropdown = false;

    public float $subtotalServices = 0.00;
    public float $subtotalProducts = 0.00;
    public float $totalFinal = 0.00;

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterDate(): void { $this->resetPage(); }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->addServiceRow();
        $this->checkoutStep = 1;
        $this->command_status = 'open';
        $this->showModal = true;
    }

    public function addServiceRow(): void
    {
        $this->itemsServices[] = [
            'service_search' => '',
            'service_id' => '',
            'professional_id' => $this->professional_id ?: '',
            'price' => '0,00'
        ];
        $this->recalculateTotals();
    }

    public function removeServiceRow(int $index): void
    {
        unset($this->itemsServices[$index]);
        $this->itemsServices = array_values($this->itemsServices);
        $this->activeServiceDropdown = -1;
        $this->recalculateTotals();
    }

    public function addProductRow(): void
    {
        $this->itemsProducts[] = [
            'product_id' => '',
            'product_search' => '',
            'quantity' => 1,
            'price' => '0,00'
        ];
        $this->recalculateTotals();
    }

    public function removeProductRow(int $index): void
    {
        unset($this->itemsProducts[$index]);
        $this->itemsProducts = array_values($this->itemsProducts);
        $this->recalculateTotals();
    }

    public function selectClient(int $id, string $name): void
    {
        $this->customer_id = $id;
        $this->client_search = $name;
        $this->showClientDropdown = false;
    }

    public function selectService(int $index, int $id, string $name, float $price): void
    {
        $this->itemsServices[$index]['service_id'] = $id;
        $this->itemsServices[$index]['service_search'] = $name;
        $this->itemsServices[$index]['price'] = number_format($price, 2, ',', '.');
        $this->activeServiceDropdown = -1;
        $this->recalculateTotals();
    }

    public function searchServiceItem(int $index, string $searchString): void
    {
        $this->activeServiceDropdown = $index;
        $this->itemsServices[$index]['service_search'] = $searchString;
        $this->itemsServices[$index]['service_id'] = '';
        $this->recalculateTotals();
    }

    public function updatedClientSearch(): void
    {
        $this->customer_id = null;
        $this->showClientDropdown = true;
    }

    public function updatedDiscount(): void
    {
        $this->recalculateTotals();
    }

    public function recalculateTotals(): void
    {
        $this->subtotalServices = 0;
        foreach ($this->itemsServices as $item) {
            $this->subtotalServices += $this->parseCurrency($item['price'] ?? '0');
        }

        $this->subtotalProducts = 0;
        foreach ($this->itemsProducts as $item) {
            $qty = (int) ($item['quantity'] ?? 1);
            $this->subtotalProducts += ($this->parseCurrency($item['price'] ?? '0') * $qty);
        }

        $disc = $this->parseCurrency($this->discount);
        $this->totalFinal = max(0, ($this->subtotalServices + $this->subtotalProducts) - $disc);
    }

    private function parseCurrency(string $value): float
    {
        if (empty($value)) return 0.00;
        if (is_numeric($value) && strpos($value, ',') === false) return (float) $value;
        $cleaned = str_replace('.', '', $value);
        $cleaned = str_replace(',', '.', $cleaned);
        return (float) $cleaned;
    }

    public function edit(int $id): void
    {
        $this->resetForm();
        $this->checkoutStep = 1;
        $this->selectedCommandId = $id;

        $command = Command::where('tenant_id', auth()->user()->tenant_id)->findOrFail((int)$id);

        $this->code = $command->code;
        $this->customer_id = $command->customer_id;
        $this->client_search = $command->customer?->name ?? 'Consumidor Final';
        $this->discount = number_format($command->discount, 2, ',', '.');
        $this->payment_method_id = $command->payment_method_id;
        $this->command_status = $command->status;
        $this->finished_date = $command->finished_at ? $command->finished_at->format('d/m/Y') : '';

        if (!empty($command->payment_method_id)) {
            $pm = PaymentMethod::find($command->payment_method_id);
            $this->saved_payment_method = $pm ? $pm->name : 'Recebimento';
        } else {
            $this->saved_payment_method = 'Recebimento';
        }

        // SOLUÇÃO: Primeiro busca agendamentos da Agenda vinculados a essa comanda
        $agendaAppointments = Appointment::where('command_id', $command->id)->get();
        foreach ($agendaAppointments as $app) {
            $this->itemsServices[] = [
                'service_search' => $app->service?->name ?? 'Serviço',
                'service_id' => (string) $app->service_id,
                'professional_id' => (string) $app->professional_id,
                'price' => number_format($app->service?->price ?? 0.00, 2, ',', '.')
            ];
        }

        // Depois busca itens diretos se houver lançamentos avulsos de serviços
        $savedServices = CommandService::where('command_id', $command->id)->get();
        foreach ($savedServices as $srv) {
            $serviceModel = Service::find($srv->service_id);
            $this->itemsServices[] = [
                'service_search' => $serviceModel ? $serviceModel->name : '',
                'service_id' => (string) $srv->service_id,
                'professional_id' => (string) $srv->professional_id,
                'price' => number_format($srv->price, 2, ',', '.')
            ];
        }

        // Busca os produtos de revenda vinculados
        $savedProducts = CommandProduct::where('command_id', $command->id)->get();
        foreach ($savedProducts as $prod) {
            $productModel = Product::find($prod->product_id);
            $this->itemsProducts[] = [
                'product_id' => (string) $prod->product_id,
                'product_search' => $productModel ? $productModel->name : '',
                'quantity' => $prod->quantity,
                'price' => number_format($prod->price, 2, ',', '.')
            ];
        }

        if (empty($this->itemsServices)) {
            $this->addServiceRow();
        }

        $this->recalculateTotals();
        $this->showModal = true;
    }

    public function reabrirComanda(): void
    {
        if ($this->selectedCommandId) {
            $command = Command::where('tenant_id', auth()->user()->tenant_id)->findOrFail($this->selectedCommandId);
            $command->update([
                'status' => 'open',
                'payment_method_id' => null,
                'finished_at' => null
            ]);

            Transaction::where('tenant_id', auth()->user()->tenant_id)->where('source_type', Command::class)->where('source_reference', $command->id)->delete();
            Commission::where('tenant_id', auth()->user()->tenant_id)->where('source_type', Command::class)->where('source_id', $command->id)->delete();
            Appointment::where('command_id', $command->id)->update(['status' => 'checked_in']);

            $this->command_status = 'open';
            $this->checkoutStep = 1;
            $this->payment_method_id = null;
            $this->saved_payment_method = '';
            $this->finished_date = '';
            $this->recalculateTotals();

            session()->flash('message', 'Comanda reaberta com sucesso!');
        }
    }

    public function saveCommand(string $targetStatus = 'open'): void
    {
        $this->validate([
            'itemsServices.*.service_id' => ['required'],
            'itemsServices.*.professional_id' => ['required'],
            'payment_method_id' => $targetStatus === 'finished' ? ['required'] : ['nullable'],
        ]);

        $tenantId = auth()->user()->tenant_id;

        if ($this->selectedCommandId) {
            $command = Command::where('tenant_id', $tenantId)->findOrFail($this->selectedCommandId);

            $hasPaidCommissions = Commission::where('tenant_id', $tenantId)
                ->where('source_type', Command::class)
                ->where('source_id', $command->id)
                ->where('status', 'paid')
                ->exists();

            if ($hasPaidCommissions) {
                session()->flash('error', 'Esta comanda não pode ser alterada porque as comissões já foram pagas!');
                $this->showModal = false;
                return;
            }

            $command->update([
                'customer_id' => $this->customer_id,
                'code' => $this->code,
                'status' => $targetStatus,
                'total_services' => $this->subtotalServices,
                'total_products' => $this->subtotalProducts,
                'discount' => $this->parseCurrency($this->discount),
                'total_amount' => $this->totalFinal,
                'payment_method_id' => $targetStatus === 'finished' ? $this->payment_method_id : null,
                'finished_at' => $targetStatus === 'finished' ? now() : null,
            ]);
            CommandService::where('command_id', $command->id)->delete();
            CommandProduct::where('command_id', $command->id)->delete();
            Commission::where('tenant_id', $tenantId)->where('source_type', Command::class)->where('source_id', $command->id)->delete();
        } else {
            $command = Command::create([
                'tenant_id' => $tenantId,
                'customer_id' => $this->customer_id,
                'code' => $this->code ?: '#' . rand(1000, 9999),
                'status' => $targetStatus,
                'total_services' => $this->subtotalServices,
                'total_products' => $this->subtotalProducts,
                'discount' => $this->parseCurrency($this->discount),
                'total_amount' => $this->totalFinal,
                'payment_method_id' => $targetStatus === 'finished' ? $this->payment_method_id : null,
                'finished_at' => $targetStatus === 'finished' ? now() : null,
            ]);
        }

        $transactionId = null;
        if ($targetStatus === 'finished') {
            $financialCategory = FinancialCategory::firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => 'Venda de Comanda'],
                ['type' => 'revenue', 'is_active' => true]
            );

            $feeAmount = 0.00;
            $accountId = null;

            if (!empty($this->payment_method_id)) {
                $pm = PaymentMethod::find($this->payment_method_id);
                if ($pm) {
                    $feeAmount = ($this->totalFinal * ($pm->fee_percentage / 100)) + $pm->fixed_fee;
                    $accountId = $pm->account_id;
                }
            }

            $netAmount = max(0, $this->totalFinal - $feeAmount);

            $transaction = Transaction::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'source_type' => Command::class,
                    'source_reference' => $command->id,
                ],
                [
                    'financial_category_id' => $financialCategory->id,
                    'payment_method_id' => $this->payment_method_id,
                    'account_id' => $accountId,
                    'user_id' => auth()->id(),
                    'gross_amount' => $this->totalFinal,
                    'fee_amount' => $feeAmount,
                    'net_amount' => $netAmount,
                    'due_date' => now()->format('Y-m-d'),
                    'payment_date' => now()->format('Y-m-d'),
                    'status' => 'paid',
                    'notes' => "Fechamento automático da comanda código {$command->code}",
                ]
            );
            $transactionId = $transaction->id;
        }

        foreach ($this->itemsServices as $item) {
            $service = Service::with('products')->find($item['service_id']);
            $prof = Professional::find($item['professional_id']);
            $rawPrice = $this->parseCurrency($item['price']);

            $commissionValue = 0.00;
            $baseCalculo = $rawPrice;
            $pctComissao = $prof ? (float)$prof->default_commission : 0.00;

            $detalhesDescontos = "Serviço: " . ($service?->name ?? 'Serviço') . " | Valor: R$ " . number_format($rawPrice, 2, ',', '.');

            if ($prof && $prof->earns_commission) {
                if ($prof->deduct_additional_cost && $service && $service->additional_cost > 0) {
                    $baseCalculo -= $service->additional_cost;
                    $detalhesDescontos .= " | (-) Custo fixo: R$ " . number_format($service->additional_cost, 2, ',', '.');
                }

                if ($prof->deduct_consumed_products === 'service' && $service->products->count() > 0) {
                    $totalInsumos = 0;
                    foreach ($service->products as $p) {
                        $qtdGasta = $p->pivot->consumed_quantity;
                        $precoInsumo = match($prof->consumed_product_price_type) {
                            'cost' => $p->cost_price,
                            'sale' => $p->sale_price,
                            default => $p->professional_price
                        };
                        $totalInsumos += ($precoInsumo * $qtdGasta);
                    }
                    $baseCalculo -= $totalInsumos;
                    $detalhesDescontos .= " | (-) Insumos: R$ " . number_format($totalInsumos, 2, ',', '.');
                }

                if ($prof->commission_type === 'percentage') {
                    $commissionValue = $baseCalculo * ($pctComissao / 100);
                } else {
                    $commissionValue = $pctComissao;
                }

                if ($prof->deduct_consumed_products === 'comission' && $service->products->count() > 0) {
                    $totalInsumosComissao = 0;
                    foreach ($service->products as $p) {
                        $qtdGasta = $p->pivot->consumed_quantity;
                        $precoInsumo = match($prof->consumed_product_price_type) {
                            'cost' => $p->cost_price,
                            'sale' => $p->sale_price,
                            default => $p->professional_price
                        };
                        $totalInsumosComissao += ($precoInsumo * $qtdGasta);
                    }
                    $commissionValue -= $totalInsumosComissao;
                }

                $commissionValue = max(0, $commissionValue);
            }

            CommandService::create([
                'command_id' => $command->id,
                'service_id' => $item['service_id'],
                'professional_id' => $item['professional_id'],
                'price' => $rawPrice,
                'commission_value' => $commissionValue,
            ]);

            if ($targetStatus === 'finished' && $prof && $prof->earns_commission && $commissionValue > 0) {
                Commission::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $prof->id,
                    'transaction_id' => $transactionId,
                    'source_type' => Command::class,
                    'source_id' => $command->id,
                    'base_amount' => max(0, $baseCalculo),
                    'commission_percentage' => $prof->commission_type === 'percentage' ? $pctComissao : 100.00,
                    'calculated_amount' => $commissionValue,
                    'status' => 'pending',
                    'accrued_date' => now()->format('Y-m-d'),
                    'notes' => $detalhesDescontos,
                ]);
            }

            if ($targetStatus === 'finished' && $service) {
                foreach ($service->products as $productInsumo) {
                    $qtyGasta = (float) $productInsumo->pivot->consumed_quantity;
                    StockMovement::create([
                        'tenant_id' => $tenantId,
                        'product_id' => $productInsumo->id,
                        'user_id' => auth()->id(),
                        'quantity' => $qtyGasta,
                        'type' => 'output',
                        'reason' => 'Consumo em Serviço',
                        'description' => "Comanda finalizada de número {$command->code}",
                    ]);
                    $productInsumo->decrement('stock_quantity', $qtyGasta);
                }
            }
        }

        foreach ($this->itemsProducts as $item) {
            if (!empty($item['product_id'])) {
                $qty = (int) $item['quantity'];
                CommandProduct::create([
                    'command_id' => $command->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $qty,
                    'price' => $this->parseCurrency($item['price']),
                ]);

                if ($targetStatus === 'finished') {
                    $pRevenda = Product::find($item['product_id']);
                    if ($pRevenda) {
                        StockMovement::create([
                            'tenant_id' => $tenantId,
                            'product_id' => $pRevenda->id,
                            'user_id' => auth()->id(),
                            'quantity' => $qty,
                            'type' => 'output',
                            'reason' => 'Ajuste Manual',
                            'description' => "Venda de balcão comanda {$command->code}",
                        ]);
                        $pRevenda->decrement('stock_quantity', $qty);
                    }
                }
            }
        }

        if($targetStatus === 'finished') {
            Appointment::where('command_id', $command->id)->update(['status' => 'finished']);
        }

        session()->flash('message', $targetStatus === 'finished' ? 'Comanda encerrada com sucesso!' : 'Comanda salva com sucesso!');
        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(string $id): void
    {
        $tenantId = auth()->user()->tenant_id;
        $command = Command::where('tenant_id', $tenantId)->findOrFail((int)$id);

        $hasPaidCommissions = Commission::where('tenant_id', $tenantId)
            ->where('source_type', Command::class)
            ->where('source_id', $command->id)
            ->where('status', 'paid')
            ->exists();

        if ($hasPaidCommissions) {
            session()->flash('error', "Bloqueio: A comanda {$command->code} não pode ser excluída.");
            return;
        }

        Transaction::where('tenant_id', $tenantId)->where('source_type', Command::class)->where('source_reference', $command->id)->delete();
        Commission::where('tenant_id', $tenantId)->where('source_type', Command::class)->where('source_id', $command->id)->delete();
        Appointment::where('command_id', $command->id)->update(['command_id' => null, 'status' => 'pending']);

        $command->delete();
        session()->flash('message', 'Comanda removida com sucesso!');
    }

    public function avancarParaPagamento(): void
    {
        $this->checkoutStep = 2;
    }

    private function resetForm(): void
    {
        $this->reset(['client_search', 'customer_id', 'professional_id', 'code', 'discount', 'payment_method_id', 'itemsServices', 'itemsProducts', 'selectedCommandId', 'checkoutStep', 'activeServiceDropdown', 'showClientDropdown', 'command_status', 'finished_date', 'saved_payment_method']);
        $this->subtotalServices = 0;
        $this->subtotalProducts = 0;
        $this->totalFinal = 0;
    }

    public function with(): array
    {
        $tenantId = auth()->user()->tenant_id;

        $filteredClients = User::where('tenant_id', $tenantId)
            ->where('role', 'customer')
            ->when($this->client_search, fn($q) => $q->where('name', 'like', "%{$this->client_search}%"))
            ->orderBy('name', 'asc')->take(5)->get();

        $servicesQuery = Service::where('tenant_id', $tenantId)->where('is_active', true);

        $filteredServices = [];
        if ($this->activeServiceDropdown !== null && $this->activeServiceDropdown >= 0 && isset($this->itemsServices[$this->activeServiceDropdown])) {
            $sQuery = $this->itemsServices[$this->activeServiceDropdown]['service_search'];
            $filteredServices = Service::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->when($sQuery, fn($q) => $q->where('name', 'like', "%{$sQuery}%"))
                ->take(5)->get();
        }

        $commandsQuery = Command::with('customer')
            ->where('tenant_id', $tenantId)
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('code', 'like', "%{$this->search}%")
                      ->orWhereHas('customer', fn($sub) => $sub->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->filter_status, fn($q) => $q->where('status', $this->filter_status))
            ->when($this->filter_date, fn($q) => $q->whereDate('created_at', $this->filter_date))
            ->orderBy('created_at', 'desc')->paginate(10);

        return [
            'commands' => $commandsQuery,
            'filteredClients' => $filteredClients,
            'filteredServices' => $filteredServices,
            'professionals' => Professional::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name', 'asc')->get(),
            'allProducts' => Product::where('tenant_id', $tenantId)->where('show_in_commands', true)->orderBy('name', 'asc')->get(),
            'paymentMethods' => PaymentMethod::where('is_active', true)->orderBy('name', 'asc')->get(),
        ];
    }
}; ?>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold text-gray-800 leading-tight">Painel de Comandas e Atendimentos</h2>
            <button wire:click="openCreateModal" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md shadow-sm">
                + Abrir Nova Comanda
            </button>
        </div>

        @if (session()->has('message'))
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded shadow-sm">
                {{ session('message') }}
            </div>
        @endif
        @if (session()->has('error'))
            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded shadow-sm font-semibold">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white shadow sm:rounded-lg p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 bg-gray-50 p-4 rounded-lg border border-gray-200">
                <div>
                    <x-input-label value="Buscar por código ou cliente" class="text-xs" />
                    <x-text-input wire:model.live="search" type="text" class="w-full text-sm mt-1" placeholder="Ex: #5041 ou Nome..." />
                </div>
                <div>
                    <x-input-label value="Tipo de Comanda" class="text-xs" />
                    <select wire:model.live="filter_status" class="w-full text-sm mt-1 border-gray-300 rounded-md shadow-sm">
                        <option value="">Todas as comandas</option>
                        <option value="open">Somente em aberto</option>
                        <option value="finished">Somente finalizadas</option>
                        <option value="canceled">Somente canceladas</option>
                    </select>
                </div>
                <div>
                    <x-input-label value="Filtrar por data" class="text-xs" />
                    <x-text-input wire:model.live="filter_date" type="date" class="w-full text-sm mt-1" />
                </div>
            </div>

            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 text-sm">
                    @forelse($commands as $c)
                        <tr>
                            <td class="px-6 py-4 font-medium text-gray-900">{{ $c->code }}</td>
                            <td class="px-6 py-4 text-gray-500">{{ $c->customer?->name ?? 'Consumidor Final' }}</td>
                            <td class="px-6 py-4 font-semibold text-gray-900">R$ {{ number_format($c->total_amount, 2, ',', '.') }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 inline-flex text-xs font-semibold rounded-full {{ $c->status === 'finished' ? 'bg-green-100 text-green-800' : ($c->status === 'canceled' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                    {{ $c->status === 'finished' ? 'Finalizada' : ($c->status === 'canceled' ? 'Cancelada' : 'Em Aberto') }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-400">{{ $c->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-6 py-4 font-medium space-x-3">
                                <button wire:click="edit({{ $c->id }})" class="text-indigo-600 hover:text-indigo-900">Editar</button>
                                <button wire:click="delete({{ $c->id }})" wire:confirm="Tem certeza que deseja remover esta comanda?" class="text-red-600 hover:text-red-900">Excluir</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-gray-500 text-center">Nenhuma comanda localizada com os filtros informados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $commands->links() }}</div>
        </div>
    </div>

    <!-- MODAL INTEGRADO EM DUAS COLUNAS IDENTICO A AGENDA -->
    @if($showModal)
        <div class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500 opacity-75" wire:click="$set('showModal', false)"></div>
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl p-6 z-50 border relative overflow-visible grid grid-cols-1 md:grid-cols-3 gap-6" wire:key="comandas-checkout-root">

                <!-- CONTAINER DA ESQUERDA (ITENS / INPUTS) -->
                <div class="md:col-span-2 overflow-visible">
                    @if($checkoutStep === 1)
                        <h3 class="text-lg font-bold text-gray-800 mb-1">
                            {{ $command_status === 'finished' ? 'Visualizando comanda' : 'Lançamento de Itens da Comanda' }}
                        </h3>
                        <p class="text-xs text-gray-400 mb-6">Controle operacional e faturamento dos serviços vinculados.</p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div>
                                <x-input-label value="Identificador / Número Comanda" class="text-gray-600 font-medium mb-1" />
                                <x-text-input type="text" wire:model="code" class="w-full text-sm font-semibold" disabled="{{ $command_status === 'finished' ? 'disabled' : '' }}" />
                            </div>
                            <div class="relative" x-data="{ open: @entangle('showClientDropdown') }" @click.away="open = false">
                                <x-input-label value="Cliente" class="text-gray-600 font-medium mb-1" />
                                <x-text-input type="text" wire:model.live="client_search" @focus="open = true" class="w-full text-sm font-semibold" disabled="{{ $command_status === 'finished' ? 'disabled' : '' }}" />
                                @if($showClientDropdown && count($filteredClients) > 0 && $command_status !== 'finished')
                                    <div class="absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-50 max-h-36 overflow-y-auto divide-y">
                                        @foreach($filteredClients as $client)
                                            <button type="button" wire:click="selectClient({{ $client->id }}, '{{ $client->name }}')" class="w-full text-left px-4 py-2 hover:bg-indigo-50 text-xs block text-gray-700 font-medium transition">{{ $client->name }}</button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- SERVIÇOS EXECUTADOS -->
                        <div class="mb-6 overflow-visible">
                            <div class="flex justify-between items-center mb-2">
                                <h4 class="text-sm font-bold text-indigo-600">Serviços Executados</h4>
                                @if($command_status !== 'finished')
                                    <button type="button" wire:click="addServiceRow" class="text-xs text-indigo-600 bg-indigo-50 border border-indigo-200 px-3 py-1 rounded-md font-bold hover:bg-indigo-100 transition">+ Serviço</button>
                                @endif
                            </div>
                            <div class="space-y-3 overflow-visible">
                                @foreach($itemsServices as $index => $item)
                                    <div class="flex items-center space-x-2 relative overflow-visible" wire:key="serv-row-{{ $index }}">
                                        <div class="flex-1 relative overflow-visible">
                                            <x-text-input type="text"
                                                          value="{{ $item['service_search'] }}"
                                                          wire:input.debounce.250ms="searchServiceItem({{ $index }}, $event.target.value)"
                                                          placeholder="Procure o serviço..."
                                                          class="w-full text-sm"
                                                          disabled="{{ $command_status === 'finished' ? 'disabled' : '' }}" />

                                            @if($activeServiceDropdown === $index && !empty($filteredServices))
                                                <div class="absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-xl z-[120] max-h-36 overflow-y-auto divide-y" wire:key="dropdown-serv-{{ $index }}">
                                                    @foreach($filteredServices as $serv)
                                                        <button type="button" wire:click="selectService({{ $index }}, {{ $serv->id }}, '{{ addslashes($serv->name) }}', {{ $serv->price }})" class="w-full text-left px-4 py-2 hover:bg-indigo-50 text-xs block text-gray-700 font-medium">
                                                            {{ $serv->name }} <span class="font-bold text-indigo-600 ml-1">(R$ {{ number_format($serv->price, 2, ',', '.') }})</span>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <div class="w-64">
                                            <select wire:model="itemsServices.{{ $index }}.professional_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm h-[38px]" disabled="{{ $command_status === 'finished' ? 'disabled' : '' }}">
                                                <option value="">-- Quem executou? --</option>
                                                @foreach($professionals as $p)
                                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="w-32">
                                            <x-text-input type="text" wire:model.live="itemsServices.{{ $index }}.price" class="w-full text-sm text-right font-semibold" placeholder="0,00" disabled="{{ $command_status === 'finished' ? 'disabled' : '' }}" />
                                        </div>
                                        @if($command_status !== 'finished')
                                            <button type="button" wire:click="removeServiceRow({{ $index }})" class="text-purple-500 hover:text-purple-700 text-lg px-2 font-bold">✕</button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- PRODUTOS PARA REVENDA -->
                        <div class="mb-6 overflow-visible pt-4 border-t border-gray-100">
                            <div class="flex justify-between items-center mb-2">
                                <h4 class="text-sm font-bold text-emerald-600">Produtos para Levar / Revenda</h4>
                                @if($command_status !== 'finished')
                                    <button type="button" wire:click="addProductRow" class="text-xs text-emerald-600 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-md font-bold hover:bg-emerald-100 transition">+ Produto</button>
                                @endif
                            </div>
                            <div class="space-y-3 overflow-visible">
                                @foreach($itemsProducts as $index => $item)
                                    <div class="flex items-center space-x-2 relative overflow-visible" wire:key="prod-row-{{ $index }}">
                                        <div class="flex-1">
                                            <select wire:model.live="itemsProducts.{{ $index }}.product_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm h-[38px]" disabled="{{ $command_status === 'finished' ? 'disabled' : '' }}">
                                                <option value="">-- Escolha o Produto --</option>
                                                @foreach($allProducts as $p)
                                                    <option value="{{ $p->id }}">{{ $p->name }} (Estoque: {{ $p->stock_quantity }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="w-24">
                                            <x-text-input type="number" min="1" wire:model.live="itemsProducts.{{ $index }}.quantity" class="w-full text-sm text-center" disabled="{{ $command_status === 'finished' ? 'disabled' : '' }}" />
                                        </div>
                                        <div class="w-32">
                                            <x-text-input type="text" wire:model.live="itemsProducts.{{ $index }}.price" class="w-full text-sm text-right font-semibold" placeholder="0,00" disabled="{{ $command_status === 'finished' ? 'disabled' : '' }}" />
                                        </div>
                                        @if($command_status !== 'finished')
                                            <button type="button" wire:click="removeProductRow({{ $index }})" class="text-purple-500 hover:text-purple-700 text-lg px-2 font-bold">✕</button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="bg-gray-50/70 border rounded-xl p-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                            <div class="text-sm font-medium text-gray-600">Subtotal Serviços: <span class="font-bold text-gray-800 ml-1">R$ {{ number_format($subtotalServices, 2, ',', '.') }}</span></div>
                            <div class="text-sm font-medium text-gray-600">Subtotal Produtos: <span class="font-bold text-gray-800 ml-1">R$ {{ number_format($subtotalProducts, 2, ',', '.') }}</span></div>
                            <div>
                                <x-input-label value="Desconto Especial (R$)" class="text-gray-500 text-xs mb-0.5" />
                                <x-text-input type="text" wire:model.live="discount" class="w-full text-sm text-right font-bold text-rose-600 h-[34px]" placeholder="0,00" disabled="{{ $command_status === 'finished' ? 'disabled' : '' }}" />
                            </div>
                        </div>
                    @else
                        <!-- PASSO 2: COMPONENT FINANCEIRO DA COBRANÇA -->
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-4">Concluir Pagamento</h3>
                            <div class="bg-gray-50/50 border border-gray-200 rounded-xl p-6 mb-6">
                                <span class="text-sm font-bold text-gray-500 uppercase tracking-wider block mb-1">Resumo da Cobrança:</span>
                                <div class="text-2xl font-black text-green-600">Total a Pagar: R$ {{ number_format($totalFinal, 2, ',', '.') }}</div>

                                <div class="mt-4 max-w-md">
                                    <x-input-label value="Escolha a Forma de Pagamento *" class="text-gray-700 font-medium text-sm mb-1" />
                                    <select wire:model="payment_method_id" class="w-full border-gray-300 rounded-lg shadow-sm text-sm h-[40px]">
                                        <option value="">-- Selecione uma forma activa --</option>
                                        @foreach($paymentMethods as $pm)
                                            <option value="{{ $pm->id }}">{{ $pm->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('payment_method_id') <span class="text-red-500 text-xs mt-1 block">A forma de pagamento é obrigatória.</span> @enderror
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- CONTAINER DA DIREITA (LATERAL FINANCEIRA) -->
                <div class="bg-gray-50/50 border-l p-4 flex flex-col justify-between rounded-r-xl min-h-[450px]">
                    <div>
                        <h4 class="text-sm font-bold text-gray-800 mb-4">Pagamentos</h4>

                        <div class="space-y-4 mb-6">
                            <span class="text-[10px] uppercase font-bold text-gray-400 block border-b pb-1">Transações Realizadas</span>

                            @if($selectedCommandId && $command_status === 'finished')
                                <div class="flex justify-between items-start bg-white p-3 rounded-lg border shadow-sm">
                                    <div>
                                        <div class="text-xs font-bold text-gray-800 uppercase">
                                            ✓ {{ $saved_payment_method }}
                                        </div>
                                        <span class="text-[10px] text-gray-400 block mt-0.5">{{ $finished_date }}</span>
                                        <span class="inline-flex items-center px-2 py-0.5 mt-1.5 rounded-full text-[9px] font-black bg-green-100 text-green-800 uppercase tracking-wide">✓ Pago</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-sm font-black text-gray-900">R$ {{ number_format($totalFinal, 2, ',', '.') }}</span>
                                    </div>
                                </div>
                            @else
                                <div class="text-xs text-gray-400 italic py-2 text-center">Nenhum pagamento liquidado ainda. Comanda em aberto.</div>
                            @endif
                        </div>

                        <div class="space-y-2 border-t pt-4">
                            <span class="text-[10px] uppercase font-bold text-gray-400 block mb-1">Resumo da compra</span>
                            <div class="flex justify-between text-xs text-gray-600">
                                <span>Descontos</span>
                                <span>R$ {{ number_format($this->parseCurrency($discount), 2, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-xs text-gray-600">
                                <span>Total</span>
                                <span>R$ {{ number_format($subtotalServices + $subtotalProducts, 2, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-sm font-bold text-gray-900 border-t pt-2 mt-1">
                                <span>Total pago</span>
                                <span class="font-black text-indigo-700">R$ {{ number_format(($selectedCommandId && $command_status === 'finished') ? $totalFinal : 0.00, 2, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- CONTROL FOOTER DE SELEÇÃO DO PASSO -->
                    <div class="pt-4 border-t border-gray-200 mt-6 flex flex-col space-y-2">
                        @if($selectedCommandId && $command_status === 'finished')
                            <button type="button" wire:click="reabrirComanda" class="w-full py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-black shadow-md transition text-center">
                                🔓 Reabrir Comanda / Corrigir
                            </button>
                            <button type="button" wire:click="$set('showModal', false)" class="w-full py-2 bg-gray-800 text-white rounded-lg text-xs font-bold shadow-sm hover:bg-gray-900 transition">Fechar Visualização</button>
                        @else
                            @if($checkoutStep === 1)
                                <button type="button" wire:click="$set('showModal', false)" class="w-full py-2 border rounded-md text-xs font-bold text-gray-600 bg-white">Cancelar</button>
                                <button type="button" wire:click="saveCommand('open')" class="w-full py-2 bg-slate-700 hover:bg-slate-800 text-white rounded-md text-xs font-bold shadow-sm">Deixar em Aberto</button>
                                <button type="button" wire:click="avancarParaPagamento" class="w-full py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs font-black shadow-md transition text-center">Ir para Pagamento ➜</button>
                            @else
                                <button type="button" wire:click="$set('checkoutStep', 1)" class="w-full py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-600 bg-white hover:bg-gray-50 shadow-sm">← Voltar aos itens</button>
                                <button type="button" wire:click="saveCommand('finished')" class="w-full py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs font-black shadow-md transition text-center">Finalizar e Salvar ✓</button>
                            @endif
                        @endif
                    </div>
                </div>

            </div>
        </div>
    @endif
</div>
