<?php

use App\Models\PaymentMethod;
use App\Models\Account;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public ?int $editingId = null;

    // Propriedades do Formulário mapeadas perfeitamente para seu Model
    public string $name = '';
    public string $type = 'money';
    public ?string $account_id = null; // Adicionado propriedade para vincular a conta bancária
    public string $fee_percentage = '0,00';
    public string $fixed_fee = '0,00';
    public int $payout_days_interval = 0;
    public bool $is_active = true;

    protected function rules()
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string'],
            'account_id' => ['nullable', 'exists:accounts,id'], // Validação da conta vinculada
            'fee_percentage' => ['required'],
            'fixed_fee' => ['required'],
            'payout_days_interval' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->resetForm();
        $pm = PaymentMethod::findOrFail($id);
        $this->editingId = $pm->id;
        $this->name = $pm->name;
        $this->type = $pm->type ?? 'money';
        $this->account_id = $pm->account_id ? (string) $pm->account_id : null;
        $this->fee_percentage = number_format($pm->fee_percentage, 2, ',', '.');
        $this->fixed_fee = number_format($pm->fixed_fee, 2, ',', '.');
        $this->payout_days_interval = $pm->payout_days_interval;
        $this->is_active = $pm->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $cleanPercentage = (float) str_replace(',', '.', str_replace('.', '', $this->fee_percentage));
        $cleanFixed = (float) str_replace(',', '.', str_replace('.', '', $this->fixed_fee));

        $data = [
            'name' => $this->name,
            'type' => $this->type,
            'account_id' => $this->account_id ?: null, // Salva o ID da conta bancária escolhida
            'fee_percentage' => $cleanPercentage,
            'fixed_fee' => $cleanFixed,
            'payout_days_interval' => $this->payout_days_interval,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            $pm = PaymentMethod::findOrFail($this->editingId);
            $pm->update($data);
        } else {
            $data['tenant_id'] = auth()->user()->tenant_id;
            PaymentMethod::create($data);
        }

        session()->flash('message', 'Regras da forma de pagamento salvas com sucesso!');
        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleStatus(int $id): void
    {
        $pm = PaymentMethod::findOrFail($id);
        $pm->update(['is_active' => !$pm->is_active]);
    }

    private function resetForm(): void
    {
        $this->reset(['name', 'type', 'account_id', 'fee_percentage', 'fixed_fee', 'payout_days_interval', 'is_active', 'editingId']);
        $this->type = 'money';
    }

    public function with(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            // Eager loading do relacionamento account que você acabou de criar no Model
            'methods' => PaymentMethod::with('account')->orderBy('name', 'asc')->paginate(10),
            // Carrega as contas ativas do tenant para preencher o Select do formulário
            'accounts' => Account::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name', 'asc')->get()
        ];
    }
}; ?>

<div class="py-12">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold text-gray-800">Formas de Pagamento e Configuração de Taxas</h2>
            <button wire:click="openCreateModal" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md shadow-sm">
                + Nova Forma
            </button>
        </div>

        @if (session()->has('message'))
            <div class="mb-4 p-4 bg-green-100 text-green-700 rounded shadow-sm">
                {{ session('message') }}
            </div>
        @endif

        <div class="bg-white shadow sm:rounded-lg p-6">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Conta Bancária Vínculo</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Taxa (%)</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Taxa Fixa (R$)</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Prazo</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 text-sm">
                    @forelse($methods as $m)
                        <tr>
                            <td class="px-6 py-4 font-medium text-gray-900">{{ $m->name }}</td>
                            <td class="px-6 py-4 text-gray-500 capitalize">{{ $m->type }}</td>
                            <td class="px-6 py-4 font-bold text-indigo-600">{{ $m->account?->name ?? '⚠️ Não Vinculada' }}</td>
                            <td class="px-6 py-4 text-right font-semibold text-gray-700">{{ number_format($m->fee_percentage, 2, ',', '.') }} %</td>
                            <td class="px-6 py-4 text-right text-gray-700">R$ {{ number_format($m->fixed_fee, 2, ',', '.') }}</td>
                            <td class="px-6 py-4 text-center text-gray-600">{{ $m->payout_days_interval }} dias</td>
                            <td class="px-6 py-4 text-center">
                                <button wire:click="toggleStatus({{ $m->id }})" class="px-3 py-1 rounded-full text-xs font-bold {{ $m->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $m->is_active ? 'Ativo' : 'Inativo' }}
                                </button>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button wire:click="edit({{ $m->id }})" class="text-indigo-600 hover:text-indigo-900 font-medium">Editar</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-center text-gray-500">Nenhuma forma de pagamento configurada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $methods->links() }}</div>
        </div>
    </div>

    @if($showModal)
        <div class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500 opacity-75" wire:click="$set('showModal', false)"></div>
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 z-50">
                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ $editingId ? 'Editar Regras de Pagamento' : 'Cadastrar Forma de Pagamento' }}</h3>

                <form wire:submit.prevent="save" class="space-y-4">
                    <div>
                        <x-input-label value="Nome da Forma de Pagamento" />
                        <x-text-input type="text" wire:model="name" class="w-full mt-1" placeholder="Ex: Visa Crédito - Maquininha X" />
                        @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <x-input-label value="Tipo de Gateway/Meio" />
                        <select wire:model="type" class="w-full mt-1 border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="money">Dinheiro</option>
                            <option value="pix">Pix</option>
                            <option value="credit_card">Cartão de Crédito</option>
                            <option value="debit_card">Cartão de Débito</option>
                            <option value="voucher">Vale/Voucher</option>
                        </select>
                        @error('type') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <x-input-label value="Conta Bancária / Caixa de Destino Padrão" />
                        <select wire:model="account_id" class="w-full mt-1 border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">-- Deixar sem conta (Não recomendado) --</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                            @endforeach
                        </select>
                        <small class="text-gray-400 block mt-1">Vincula esta forma a um banco para lançar automaticamente no Fluxo de Caixa.</small>
                        @error('account_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Taxa Cobrada (%)" />
                            <x-text-input type="text" wire:model="fee_percentage" class="w-full mt-1 text-right" placeholder="0,00" />
                            @error('fee_percentage') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <x-input-label value="Custo Fixo por Transação (R$)" />
                            <x-text-input type="text" wire:model="fixed_fee" class="w-full mt-1 text-right" placeholder="0,00" />
                            @error('fixed_fee') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <x-input-label value="Dias para Recebimento (Intervalo)" />
                        <x-text-input type="number" wire:model="payout_days_interval" class="w-full mt-1 text-right" min="0" placeholder="Ex: 30" />
                        @error('payout_days_interval') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" wire:model="is_active" id="is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <label for="is_active" class="ml-2 text-sm text-gray-600">Disponível para uso imediato no caixa</label>
                    </div>

                    <div class="flex justify-end space-x-2 pt-4 border-t">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 border rounded-md text-sm text-gray-700 bg-white">Voltar</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md text-sm font-medium">Salvar Configuração</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
