<?php

use App\Models\FinancialCategory;
use App\Models\PaymentMethod;
use App\Models\Account;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    // Controle de Abas
    public string $activeTab = 'accounts';

    // Modais e Estados
    public bool $showAccountModal = false;
    public ?int $editingAccountId = null;

    // Formulário de Contas
    public string $account_name = '';
    public string $account_type = 'bank';
    public string $account_balance = '0,00';
    public bool $account_is_active = true;

    // Formulário de Formas de Pagamento
    public bool $showMethodModal = false;
    public ?int $editingMethodId = null;
    public string $method_name = '';
    public ?string $method_account_id = null;
    public string $method_fee = '0,00';
    public string $method_fixed = '0,00';
    public bool $method_is_active = true;

    public function resetAccountForm(): void
    {
        $this->reset(['account_name', 'account_type', 'account_balance', 'account_is_active', 'editingAccountId']);
    }

    public function openCreateAccount(): void
    {
        $this->resetAccountForm();
        $this->showAccountModal = true;
    }

    public function editAccount(int $id): void
    {
        $this->resetAccountForm();
        $acc = Account::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id);

        $this->editingAccountId = $acc->id;
        $this->account_name = $acc->name;
        $this->account_type = $acc->type;
        $this->account_balance = number_format($acc->initial_balance, 2, ',', '.');
        $this->account_is_active = (bool)$acc->is_active;

        $this->showAccountModal = true;
    }

    public function saveAccount(): void
    {
        $this->validate([
            'account_name' => ['required', 'string', 'max:100'],
            'account_type' => ['required', 'in:bank,cash,savings'],
            'account_balance' => ['required'],
        ]);

        $tenantId = auth()->user()->tenant_id;
        $balance = (float) str_replace(',', '.', str_replace('.', '', $this->account_balance));

        $data = [
            'name' => $this->account_name,
            'type' => $this->account_type,
            'initial_balance' => $balance,
            'is_active' => $this->account_is_active,
        ];

        if ($this->editingAccountId) {
            Account::where('tenant_id', $tenantId)->findOrFail($this->editingAccountId)->update($data);
        } else {
            $data['tenant_id'] = $tenantId;
            Account::create($data);
        }

        session()->flash('message', 'Conta bancária atualizada com sucesso!');
        $this->showAccountModal = false;
        $this->resetAccountForm();
    }

    public function resetMethodForm(): void
    {
        $this->reset(['method_name', 'method_account_id', 'method_fee', 'method_fixed', 'method_is_active', 'editingMethodId']);
    }

    public function openCreateMethod(): void
    {
        $this->resetMethodForm();
        $this->showMethodModal = true;
    }

    public function editMethod(int $id): void
    {
        $this->resetMethodForm();
        $pm = PaymentMethod::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id);

        $this->editingMethodId = $pm->id;
        $this->method_name = $pm->name;
        $this->method_account_id = $pm->account_id;
        $this->method_fee = number_format($pm->fee_percentage, 2, ',', '.');
        $this->method_fixed = number_format($pm->fixed_fee, 2, ',', '.');
        $this->method_is_active = (bool)$pm->is_active;

        $this->showMethodModal = true;
    }

    public function saveMethod(): void
    {
        $this->validate([
            'method_name' => ['required', 'string'],
            'method_account_id' => ['nullable', 'exists:accounts,id'],
        ]);

        $tenantId = auth()->user()->tenant_id;
        $fee = (float) str_replace(',', '.', str_replace('.', '', $this->method_fee));
        $fixed = (float) str_replace(',', '.', str_replace('.', '', $this->method_fixed));

        $data = [
            'name' => $this->method_name,
            'account_id' => $this->method_account_id ?: null,
            'fee_percentage' => $fee,
            'fixed_fee' => $fixed,
            'is_active' => $this->method_is_active,
        ];

        if ($this->editingMethodId) {
            PaymentMethod::where('tenant_id', $tenantId)->findOrFail($this->editingMethodId)->update($data);
        } else {
            $data['tenant_id'] = $tenantId;
            PaymentMethod::create($data);
        }

        session()->flash('message', 'Forma de pagamento atualizada!');
        $this->showMethodModal = false;
        $this->resetMethodForm();
    }

    public function with(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'accounts' => Account::where('tenant_id', $tenantId)->orderBy('name', 'asc')->get(),
            'paymentMethods' => PaymentMethod::with('account')->where('tenant_id', $tenantId)->orderBy('name', 'asc')->get(),
            'categories' => FinancialCategory::where('tenant_id', $tenantId)->orderBy('name', 'asc')->get(),
        ];
    }
}; ?>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Configurações Financeiras</h2>

        @if (session()->has('message'))
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded shadow-sm">
                {{ session('message') }}
            </div>
        @endif

        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex space-x-8">
                <button wire:click="$set('activeTab', 'accounts')" class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'accounts' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    Contas Bancárias / Caixas
                </button>
                <button wire:click="$set('activeTab', 'methods')" class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'methods' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    Formas de Pagamento e Vínculos
                </button>
            </nav>
        </div>

        @if($activeTab === 'accounts')
            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Suas Contas Financeiras</h3>
                    <button wire:click="openCreateAccount" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded shadow-sm">
                        + Nova Conta / Caixa
                    </button>
                </div>

                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome da Conta</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Saldo Inicial</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($accounts as $acc)
                            <tr>
                                <td class="px-6 py-4 font-bold text-gray-900">{{ $acc->name }}</td>
                                <td class="px-6 py-4 text-gray-500 uppercase text-xs">
                                    {{ $acc->type === 'bank' ? 'Conta Corrente' : ($acc->type === 'cash' ? 'Caixa Físico' : 'Poupança') }}
                                </td>
                                <td class="px-6 py-4 text-right font-semibold text-gray-700">R$ {{ number_format($acc->initial_balance, 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-2 py-0.5 text-xs rounded-full {{ $acc->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $acc->is_active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button wire:click="editAccount({{ $acc->id }})" class="text-indigo-600 hover:text-indigo-900 font-medium">Editar</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">Nenhuma conta cadastrada. Cadastre sua primeira conta clicando no botão acima.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        @if($activeTab === 'methods')
            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Formas de Pagamento Cadastradas</h3>
                    <button wire:click="openCreateMethod" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded shadow-sm">
                        + Nova Forma
                    </button>
                </div>

                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Forma</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Conta Bancária Vinculada</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Taxa (%)</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Taxa Fixa</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach($paymentMethods as $pm)
                            <tr>
                                <td class="px-6 py-4 font-semibold text-gray-900">{{ $pm->name }}</td>
                                <td class="px-6 py-4 font-bold text-indigo-600">
                                    {{ $pm->account?->name ?? '⚠️ Não Vinculada (Sem conta destino)' }}
                                </td>
                                <td class="px-6 py-4 text-right">{{ number_format($pm->fee_percentage, 2, ',', '.') }}%</td>
                                <td class="px-6 py-4 text-right">R$ {{ number_format($pm->fixed_fee, 2, ',', '.') }}</td>
                                <td class="px-6 py-4 text-right">
                                    <button wire:click="editMethod({{ $pm->id }})" class="text-indigo-600 hover:text-indigo-900 font-medium">Editar / Vincular</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($showAccountModal)
        <div class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500 opacity-75" wire:click="$set('showAccountModal', false)"></div>
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 z-50">
                <h3 class="text-base font-bold text-gray-900 mb-4">{{ $editingAccountId ? 'Editar Conta' : 'Nova Conta Bancária / Caixa' }}</h3>

                <form wire:submit.prevent="saveAccount" class="space-y-4 text-sm">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase">Nome da Conta / Identificador *</label>
                        <input type="text" wire:model="account_name" class="w-full mt-1 border-gray-300 rounded-md shadow-sm text-sm" placeholder="Ex: Pix Banco C6, Caixa Físico Balcão" required />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase">Tipo de Conta</label>
                            <select wire:model="account_type" class="w-full mt-1 border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="bank">Conta Corrente / Banco</option>
                                <option value="cash">Caixa Físico / Dinheiro</option>
                                <option value="savings">Poupança / Reserva</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase">Saldo Inicial (R$)</label>
                            <input type="text" wire:model="account_balance" class="w-full mt-1 text-right font-semibold border-gray-300 rounded-md shadow-sm text-sm" placeholder="0,00" required />
                        </div>
                    </div>

                    <div class="flex items-center space-x-2 pt-2">
                        <input type="checkbox" wire:model="account_is_active" id="acc_active" class="rounded border-gray-300 text-indigo-600">
                        <label for="acc_active" class="text-xs font-medium text-gray-700">Conta ativa para movimentações</label>
                    </div>

                    <div class="flex justify-end space-x-2 pt-4 border-t">
                        <button type="button" wire:click="$set('showAccountModal', false)" class="px-4 py-2 border rounded-md text-xs text-gray-700 bg-white">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-xs font-bold hover:bg-indigo-700 shadow-sm">Salvar Conta</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showMethodModal)
        <div class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500 opacity-75" wire:click="$set('showMethodModal', false)"></div>
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 z-50">
                <h3 class="text-base font-bold text-gray-900 mb-4">Configurar Meio de Pagamento</h3>

                <form wire:submit.prevent="saveMethod" class="space-y-4 text-sm">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase">Nome da Forma *</label>
                        <input type="text" wire:model="method_name" class="w-full mt-1 border-gray-300 rounded-md shadow-sm text-sm" required />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 uppercase">Conta Bancária de Destino Padrão</label>
                        <select wire:model="method_account_id" class="w-full mt-1 border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">-- Deixar sem conta (Não recomendado) --</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                            @endforeach
                        </select>
                        <small class="text-gray-400 block mt-1">Toda vez que fechar uma comanda com esse meio, o dinheiro cai nessa conta automaticamente.</small>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase">Taxa Desconto (%)</label>
                            <input type="text" wire:model="method_fee" class="w-full mt-1 border-gray-300 rounded-md shadow-sm text-sm text-right" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 uppercase">Taxa Fixa (R$)</label>
                            <input type="text" wire:model="method_fixed" class="w-full mt-1 border-gray-300 rounded-md shadow-sm text-sm text-right" />
                        </div>
                    </div>

                    <div class="flex justify-end space-x-2 pt-4 border-t">
                        <button type="button" wire:click="$set('showMethodModal', false)" class="px-4 py-2 border rounded-md text-xs text-gray-700 bg-white">Voltar</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-xs font-bold hover:bg-indigo-700 shadow-sm">Atualizar Vínculo</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
