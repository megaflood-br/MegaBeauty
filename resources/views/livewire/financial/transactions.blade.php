<?php

use App\Models\Transaction;
use App\Models\PaymentMethod;
use App\Models\FinancialCategory;
use App\Models\Professional;
use App\Models\Commission;
use App\Models\Account;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    // Filtros do Painel Financeiro
    public string $search = '';
    public string $filter_type = '';   // '', 'revenue', 'expense'
    public string $filter_status = ''; // '', 'pending', 'paid', 'canceled'
    public string $filter_category_id = ''; // Novo Filtro por Categoria Financeira
    public string $filter_start_date = '';
    public string $filter_end_date = '';
    public string $filter_account_id = '';

    // Controle do Modal de Novo Lançamento Manual
    public bool $showModal = false;
    public ?int $editingId = null;

    // Propriedades do Formulário
    public ?string $financial_category_id = null;
    public ?string $payment_method_id = null;
    public ?string $account_id = null;
    public ?string $professional_id = null;
    public string $gross_amount = '0,00';
    public string $due_date = '';
    public string $payment_date = '';
    public string $status = 'pending';
    public string $notes = '';

    // Propriedade auxiliar para controlar se o campo de profissional deve aparecer
    public bool $isValeCategory = false;

    // Reseta paginação ao filtrar
    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedFilterType(): void { $this->resetPage(); }
    public function updatedFilterStatus(): void { $this->resetPage(); }
    public function updatedFilterCategoryId(): void { $this->resetPage(); } // Reseta ao mudar categoria
    public function updatedFilterStartDate(): void { $this->resetPage(); }
    public function updatedFilterEndDate(): void { $this->resetPage(); }
    public function updatedFilterAccountId(): void { $this->resetPage(); }

    // Quando o usuário seleciona um meio de pagamento, herda a conta bancária vinculada automaticamente
    public function updatedPaymentMethodId($value): void
    {
        if (!empty($value)) {
            $pm = PaymentMethod::find($value);
            if ($pm && $pm->account_id) {
                $this->account_id = (string) $pm->account_id;
            }
        }
    }

    // Monitora a categoria selecionada para saber se é um Vale
    public function updatedFinancialCategoryId($value): void
    {
        if (!empty($value)) {
            $category = FinancialCategory::find($value);
            $this->isValeCategory = $category && (str_contains(strtolower($category->name), 'vale') || str_contains(strtolower($category->name), 'adiantamento'));
        } else {
            $this->isValeCategory = false;
        }
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->due_date = now()->format('Y-m-d');
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->resetForm();
        $t = Transaction::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id);

        $this->editingId = $t->id;
        $this->financial_category_id = $t->financial_category_id;
        $this->payment_method_id = $t->payment_method_id;
        $this->account_id = $t->account_id;
        $this->professional_id = $t->professional_id;
        $this->gross_amount = number_format($t->gross_amount, 2, ',', '.');
        $this->due_date = $t->due_date;
        $this->payment_date = $t->payment_date ?? '';
        $this->status = $t->status;
        $this->notes = $t->notes ?? '';

        $this->updatedFinancialCategoryId($t->financial_category_id);

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'financial_category_id' => ['required', 'exists:financial_categories,id'],
            'gross_amount' => ['required'],
            'due_date' => ['required', 'date'],
            'status' => ['required', 'in:pending,paid,canceled'],
            'professional_id' => $this->isValeCategory ? ['required', 'exists:professionals,id'] : ['nullable'],
            'account_id' => ['nullable', 'exists:accounts,id'],
        ]);

        $tenantId = auth()->user()->tenant_id;
        $gross = $this->parseCurrency($this->gross_amount);
        $category = FinancialCategory::findOrFail($this->financial_category_id);

        $fee = 0.00;
        $determinedAccountId = $this->account_id;

        if (!empty($this->payment_method_id)) {
            $pm = PaymentMethod::find($this->payment_method_id);
            if ($pm) {
                if ($category->type === 'revenue') {
                    $fee = ($gross * ($pm->fee_percentage / 100)) + $pm->fixed_fee;
                }
                if (empty($determinedAccountId)) {
                    $determinedAccountId = $pm->account_id;
                }
            }
        }

        $net = $category->type === 'revenue' ? ($gross - $fee) : $gross;

        $transactionData = [
            'financial_category_id' => $this->financial_category_id,
            'payment_method_id' => $this->payment_method_id,
            'account_id' => $determinedAccountId ?: null,
            'professional_id' => $this->isValeCategory ? $this->professional_id : null,
            'gross_amount' => $gross,
            'fee_amount' => $fee,
            'net_amount' => $net,
            'due_date' => $this->due_date,
            'payment_date' => $this->status === 'paid' ? ($this->payment_date ?: now()->format('Y-m-d')) : null,
            'status' => $this->status,
            'notes' => $this->notes,
        ];

        if ($this->editingId) {
            $transaction = Transaction::where('tenant_id', $tenantId)->findOrFail($this->editingId);
            $transaction->update($transactionData);
        } else {
            $transactionData['tenant_id'] = $tenantId;
            $transactionData['user_id'] = auth()->id();
            $transaction = Transaction::create($transactionData);
        }

        if ($this->isValeCategory && $this->status === 'paid') {
            Commission::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'transaction_id' => $transaction->id,
                ],
                [
                    'user_id' => $this->professional_id,
                    'source_type' => Transaction::class,
                    'source_id' => $transaction->id,
                    'base_amount' => $gross,
                    'commission_percentage' => 100.00,
                    'calculated_amount' => -$gross,
                    'status' => 'pending',
                    'accrued_date' => now()->format('Y-m-d'),
                    'notes' => "Abatimento de Vale/Adiantamento: " . ($this->notes ?: 'Sem observações adicionais'),
                ]
            );
        } else {
            Commission::where('tenant_id', $tenantId)
                ->where('transaction_id', $this->editingId ?: $transaction->id)
                ->delete();
        }

        session()->flash('message', 'Movimentação financeira e regras de comissão atualizadas!');
        $this->showModal = false;
        $this->resetForm();
    }

    public function markAsPaid(int $id): void
    {
        $t = Transaction::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id);

        $determinedAccountId = $t->account_id;

        if (empty($determinedAccountId) && !empty($t->payment_method_id)) {
            $pm = PaymentMethod::find($t->payment_method_id);
            if ($pm && $pm->account_id) {
                $determinedAccountId = $pm->account_id;
            }
        }

        $t->update([
            'status' => 'paid',
            'account_id' => $determinedAccountId,
            'payment_date' => now()->format('Y-m-d')
        ]);

        $this->updatedFinancialCategoryId($t->financial_category_id);
        if ($this->isValeCategory) {
            Commission::updateOrCreate(
                [
                    'tenant_id' => $t->tenant_id,
                    'transaction_id' => $t->id
                ],
                [
                    'user_id' => $t->professional_id,
                    'source_type' => Transaction::class,
                    'source_id' => $t->id,
                    'base_amount' => $t->gross_amount,
                    'commission_percentage' => 100.00,
                    'calculated_amount' => -$t->gross_amount,
                    'status' => 'pending',
                    'accrued_date' => now()->format('Y-m-d'),
                    'notes' => "Abatimento de Vale/Adiantamento: " . $t->notes,
                ]
            );
        }

        session()->flash('message', 'Lançamento baixado com sucesso!');
    }

    private function parseCurrency(string $value): float
    {
        if (empty($value)) return 0.00;
        if (is_numeric($value) && strpos($value, ',') === false) return (float) $value;
        $cleaned = str_replace('.', '', $value);
        $cleaned = str_replace(',', '.', $cleaned);
        return (float) $cleaned;
    }

    private function resetForm(): void
    {
        $this->reset(['financial_category_id', 'payment_method_id', 'account_id', 'professional_id', 'gross_amount', 'due_date', 'payment_date', 'status', 'notes', 'editingId', 'isValeCategory']);
    }

    public function with(): array
    {
        $tenantId = auth()->user()->tenant_id;

        $query = Transaction::with(['paymentMethod', 'financialCategory', 'professional', 'account'])
            ->where('tenant_id', $tenantId)
            ->whereBetween('due_date', [
                $this->filter_start_date ?: now()->startOfMonth()->format('Y-m-d'),
                $this->filter_end_date ?: now()->endOfMonth()->format('Y-m-d')
            ])
            ->when($this->filter_type, function($q) {
                $q->whereHas('financialCategory', fn($sub) => $sub->where('type', $this->filter_type));
            })
            ->when($this->filter_status, fn($q) => $q->where('status', $this->filter_status))
            ->when($this->filter_account_id, fn($q) => $q->where('account_id', $this->filter_account_id))
            ->when($this->filter_category_id, fn($q) => $q->where('financial_category_id', $this->filter_category_id)) // Cláusula do filtro adicionada
            ->when($this->search, fn($q) => $q->where('notes', 'like', "%{$this->search}%"))
            ->orderByRaw('COALESCE(payment_date, due_date) DESC')
            ->orderBy('id', 'desc');

        $totalRevenue = (clone $query)->where('status', 'paid')->whereHas('financialCategory', fn($sub) => $sub->where('type', 'revenue'))->sum('gross_amount');
        $totalExpense = (clone $query)->where('status', 'paid')->whereHas('financialCategory', fn($sub) => $sub->where('type', 'expense'))->sum('gross_amount');

        $rawAccounts = Account::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name', 'asc')->get();
        $calculatedAccounts = [];

        foreach ($rawAccounts as $acc) {
            $revenueSum = Transaction::where('tenant_id', $tenantId)
                ->where('account_id', $acc->id)
                ->where('status', 'paid')
                ->whereHas('financialCategory', fn($sub) => $sub->where('type', 'revenue'))
                ->sum('gross_amount');

            $expenseSum = Transaction::where('tenant_id', $tenantId)
                ->where('account_id', $acc->id)
                ->where('status', 'paid')
                ->whereHas('financialCategory', fn($sub) => $sub->where('type', 'expense'))
                ->sum('gross_amount');

            $currentBalance = $acc->initial_balance + ($revenueSum - $expenseSum);

            $calculatedAccounts[] = [
                'id' => $acc->id,
                'name' => $acc->name,
                'type' => $acc->type,
                'balance' => $currentBalance
            ];
        }

        // Filtra as categorias disponíveis dinamicamente se o tipo (Entrada/Saída) estiver selecionado
        $availableCategories = FinancialCategory::where('is_active', true)
            ->when($this->filter_type, fn($q) => $q->where('type', $this->filter_type))
            ->orderBy('name', 'asc')
            ->get();

        return [
            'transactions' => $query->paginate(15),
            'paymentMethods' => PaymentMethod::where('is_active', true)->orderBy('name', 'asc')->get(),
            'categories' => FinancialCategory::where('is_active', true)->orderBy('name', 'asc')->get(),
            'availableCategories' => $availableCategories, // Passa para renderizar no select de filtros
            'professionals' => Professional::where('is_active', true)->orderBy('name', 'asc')->get(),
            'accounts' => $rawAccounts,
            'calculatedAccounts' => $calculatedAccounts,
            'metaBalance' => $totalRevenue - $totalExpense,
            'metaRevenue' => $totalRevenue,
            'metaExpense' => $totalExpense,
        ];
    }
}; ?>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-6">
            <h3 class="text-xs font-bold text-gray-400 uppercase mb-3 tracking-wider">Saldos Disponíveis em Conta</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                @forelse($calculatedAccounts as $cAcc)
                    <div class="bg-white p-4 rounded-lg shadow border-l-4 {{ $cAcc['balance'] >= 0 ? 'border-green-500' : 'border-red-500' }} transition hover:shadow-md">
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-bold text-gray-500 uppercase tracking-tight truncate w-40">{{ $cAcc['name'] }}</span>
                            <span class="text-[10px] bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full uppercase font-mono">
                                {{ $cAcc['type'] === 'bank' ? 'Banco' : ($cAcc['type'] === 'cash' ? 'Caixa Físico' : 'Reserva') }}
                            </span>
                        </div>
                        <div class="text-xl font-extrabold mt-2 {{ $cAcc['balance'] >= 0 ? 'text-gray-800' : 'text-red-600' }}">
                            R$ {{ number_format($cAcc['balance'], 2, ',', '.') }}
                        </div>
                    </div>
                @empty
                    <div class="col-span-4 bg-gray-50 border border-dashed rounded-lg p-4 text-center text-xs text-gray-500">
                        Nenhuma conta financeira ativa encontrada para listar saldos.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white p-6 rounded-lg shadow border-l-4 border-emerald-500">
                <div class="text-xs font-bold text-gray-400 uppercase">Total Recebido (No Período)</div>
                <div class="text-2xl font-bold text-gray-800">R$ {{ number_format($metaRevenue, 2, ',', '.') }}</div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow border-l-4 border-rose-500">
                <div class="text-xs font-bold text-gray-400 uppercase">Total Pago (No Período)</div>
                <div class="text-2xl font-bold text-gray-800">R$ {{ number_format($metaExpense, 2, ',', '.') }}</div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow border-l-4 border-blue-500">
                <div class="text-xs font-bold text-gray-400 uppercase">Balanço do Período Filtrado</div>
                <div class="text-2xl font-bold {{ $metaBalance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    R$ {{ number_format($metaBalance, 2, ',', '.') }}
                </div>
            </div>
        </div>

        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold text-gray-800 leading-tight">Fluxo de Caixa & Lançamentos</h2>
            <button wire:click="openCreateModal" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md shadow-sm">
                + Novo Lançamento Manual
            </button>
        </div>

        @if (session()->has('message'))
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded shadow-sm">
                {{ session('message') }}
            </div>
        @endif

        <div class="bg-white shadow sm:rounded-lg p-6">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6 bg-gray-50 p-4 rounded-lg border border-gray-200">
                <div>
                    <x-input-label value="Tipo" class="text-xs" />
                    <select wire:model.live="filter_type" class="w-full text-sm mt-1 border-gray-300 rounded-md shadow-sm">
                        <option value="">Todos</option>
                        <option value="revenue">Entradas</option>
                        <option value="expense">Saídas</option>
                    </select>
                </div>
                <div>
                    <x-input-label value="Status" class="text-xs" />
                    <select wire:model.live="filter_status" class="w-full text-sm mt-1 border-gray-300 rounded-md shadow-sm">
                        <option value="">Todos</option>
                        <option value="pending">Pendente</option>
                        <option value="paid">Liquidado</option>
                        <option value="canceled">Cancelado</option>
                    </select>
                </div>
                <div>
                    <x-input-label value="Filtrar por Conta" class="text-xs" />
                    <select wire:model.live="filter_account_id" class="w-full text-sm mt-1 border-gray-300 rounded-md shadow-sm">
                        <option value="">Todas as Contas</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label value="Filtrar por Categoria" class="text-xs" />
                    <select wire:model.live="filter_category_id" class="w-full text-sm mt-1 border-gray-300 rounded-md shadow-sm">
                        <option value="">Todas as Categorias</option>
                        @foreach($availableCategories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }} ({{ $cat->type === 'revenue' ? 'Receita' : 'Despesa' }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label value="Histórico / Notas" class="text-xs" />
                    <x-text-input wire:model.live="search" type="text" class="w-full text-sm mt-1" placeholder="Buscar histórico..." />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 text-xs text-gray-500 bg-gray-50/50 p-3 rounded-md border border-dashed border-gray-200">
                <div class="flex items-center space-x-2">
                    <span>Período de Vencimento: de</span>
                    <x-text-input wire:model.live="filter_start_date" type="date" class="text-xs py-1" />
                    <span>até</span>
                    <x-text-input wire:model.live="filter_end_date" type="date" class="text-xs py-1" />
                </div>
            </div>

            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vencimento</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Conta</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Histórico / Notas</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Meio</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor Bruto</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 text-sm">
                    @forelse($transactions as $t)
                        <tr class="{{ $t->financialCategory?->type === 'expense' ? 'bg-red-50/30' : '' }}">
                            <td class="px-6 py-4 text-gray-600 whitespace-nowrap">{{ date('d/m/Y', strtotime($t->due_date)) }}</td>
                            <td class="px-6 py-4 font-bold text-indigo-600 whitespace-nowrap">{{ $t->account?->name ?? '-' }}</td>
                            <td class="px-6 py-4 font-semibold text-gray-700">{{ $t->financialCategory?->name ?? 'Sem Categoria' }}</td>
                            <td class="px-6 py-4 font-medium text-gray-900">
                                {{ $t->notes ?: 'Lançamento sem descrição' }}
                                @if($t->professional_id)
                                    <span class="block text-xs font-bold text-red-600">Profissional: {{ $t->professional?->name }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-gray-500">{{ $t->paymentMethod?->name ?? 'Manual' }}</td>
                            <td class="px-6 py-4 text-right font-semibold {{ $t->financialCategory?->type === 'expense' ? 'text-red-600' : 'text-green-700' }}">
                                {{ $t->financialCategory?->type === 'expense' ? '-' : '+' }} R$ {{ number_format($t->gross_amount, 2, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                <span class="px-2 py-1 inline-flex text-xs font-semibold rounded-full {{ $t->status === 'paid' ? 'bg-green-100 text-green-800' : ($t->status === 'canceled' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                    {{ $t->status === 'paid' ? 'Pago' : ($t->status === 'canceled' ? 'Cancelado' : 'Pendente') }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right font-medium space-x-2 whitespace-nowrap">
                                @if($t->status === 'pending')
                                    <button wire:click="markAsPaid({{ $t->id }})" class="text-green-600 hover:text-green-900 mr-2">Liquidar</button>
                                @endif
                                <button wire:click="edit({{ $t->id }})" class="text-indigo-600 hover:text-indigo-900">Editar</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center text-gray-500">Nenhum lançamento localizado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $transactions->links() }}</div>
        </div>
    </div>

    @if($showModal)
        <div class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500 opacity-75" wire:click="$set('showModal', false)"></div>
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 z-50">
                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ $editingId ? 'Editar Lançamento' : 'Novo Lançamento Financeiro' }}</h3>

                <form wire:submit.prevent="save" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Categoria Financeira" />
                            <select wire:model.live="financial_category_id" class="w-full mt-1 border-gray-300 rounded-md text-sm shadow-sm" required>
                                <option value="">-- Escolha a Categoria --</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }} ({{ $cat->type === 'revenue' ? 'Receita' : 'Despesa' }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label value="Valor Bruto (R$)" />
                            <x-text-input type="text" wire:model="gross_amount" class="w-full mt-1 text-right font-bold" placeholder="0,00" required />
                        </div>
                    </div>

                    @if($isValeCategory)
                        <div class="bg-red-50 p-3 rounded-md border border-red-200">
                            <x-input-label value="Vincular ao Profissional (Para Abatimento) *" class="text-red-800 font-bold" />
                            <select wire:model="professional_id" class="w-full mt-1 border-gray-300 rounded-md text-sm shadow-sm focus:border-red-500 focus:ring-red-500" required>
                                <option value="">-- Selecione o colaborador --</option>
                                @foreach($professionals as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Data de Vencimento" />
                            <x-text-input type="date" wire:model="due_date" class="w-full mt-1 text-sm" required />
                        </div>
                        <div>
                            <x-input-label value="Forma de Pagamento" />
                            <select wire:model.live="payment_method_id" class="w-full mt-1 border-gray-300 rounded-md text-sm shadow-sm">
                                <option value="">-- Escolha o meio --</option>
                                @foreach($paymentMethods as $pm)
                                    <option value="{{ $pm->id }}">{{ $pm->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Conta Bancária / Caixa Destino" />
                            <select wire:model="account_id" class="w-full mt-1 border-gray-300 rounded-md text-sm shadow-sm">
                                <option value="">-- Escolha a Conta Destino --</option>
                                @foreach($accounts as $acc)
                                    <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label value="Status Inicial" />
                            <select wire:model.live="status" class="w-full mt-1 border-gray-300 rounded-md text-sm shadow-sm">
                                <option value="pending">Em Aberto / Pendente</option>
                                <option value="paid">Liquidado / Pago</option>
                                <option value="canceled">Cancelado</option>
                            </select>
                        </div>
                    </div>

                    @if($status === 'paid')
                        <div>
                            <x-input-label value="Data da Liquidação" />
                            <x-text-input type="date" wire:model="payment_date" class="w-full mt-1 text-sm" />
                        </div>
                    @endif

                    <div>
                        <x-input-label value="Histórico / Notas" />
                        <textarea wire:model="notes" rows="2" class="w-full mt-1 border-gray-300 rounded-md text-sm shadow-sm" placeholder="Descrição do lançamento..."></textarea>
                    </div>

                    <div class="flex justify-end space-x-2 pt-4 border-t">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 border rounded-md text-sm text-gray-700 bg-white">Voltar</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md text-sm font-medium">Salvar Registro</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
