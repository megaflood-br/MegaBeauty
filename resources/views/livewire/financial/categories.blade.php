<?php

use App\Models\FinancialCategory;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public ?int $editingId = null;

    // Propriedades do Formulário alinhadas com o banco
    public string $name = '';
    public string $type = 'expense'; // expense = despesa, revenue = receita
    public bool $is_active = true;

    protected function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:revenue,expense'],
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
        $cat = FinancialCategory::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id);

        $this->editingId = $cat->id;
        $this->name = $cat->name;
        $this->type = $cat->type;
        $this->is_active = $cat->is_active;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();
        $tenantId = auth()->user()->tenant_id;

        if ($this->editingId) {
            $cat = FinancialCategory::where('tenant_id', $tenantId)->findOrFail($this->editingId);
            $cat->update([
                'name' => $this->name,
                'type' => $this->type,
                'is_active' => $this->is_active,
            ]);
        } else {
            FinancialCategory::create([
                'tenant_id' => $tenantId,
                'name' => $this->name,
                'type' => $this->type,
                'is_active' => $this->is_active,
            ]);
        }

        session()->flash('message', 'Categoria financeira salva com sucesso!');
        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleStatus(int $id): void
    {
        $cat = FinancialCategory::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id);
        $cat->update(['is_active' => !$cat->is_active]);
    }

    private function resetForm(): void
    {
        $this->reset(['name', 'type', 'is_active', 'editingId']);
        $this->type = 'expense';
    }

    public function with(): array
    {
        return [
            'categories' => FinancialCategory::where('tenant_id', auth()->user()->tenant_id)
                ->orderBy('name', 'asc')
                ->paginate(15)
        ];
    }
}; ?>

<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold text-gray-800">Categorias Financeiras</h2>
            <button wire:click="openCreateModal" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md shadow-sm">
                + Nova Categoria
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome da Categoria</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fluxo</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 text-sm">
                    @forelse($categories as $cat)
                        <tr>
                            <td class="px-6 py-4 font-medium text-gray-900">{{ $cat->name }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-md {{ $cat->type === 'revenue' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $cat->type === 'revenue' ? 'Receita / Entrada' : 'Despesa / Saída' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button wire:click="toggleStatus({{ $cat->id }})" class="px-3 py-1 rounded-full text-xs font-bold {{ $cat->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $cat->is_active ? 'Ativo' : 'Inativo' }}
                                </button>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button wire:click="edit({{ $cat->id }})" class="text-indigo-600 hover:text-indigo-900 font-medium">Editar</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">Nenhuma categoria cadastrada ainda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $categories->links() }}</div>
        </div>
    </div>

    @if($showModal)
        <div class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500 opacity-75" wire:click="$set('showModal', false)"></div>
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6 z-50">
                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ $editingId ? 'Editar Categoria' : 'Nova Categoria Financeira' }}</h3>

                <form wire:submit.prevent="save" class="space-y-4">
                    <div>
                        <x-input-label value="Nome da Categoria" />
                        <x-text-input type="text" wire:model="name" class="w-full mt-1" placeholder="Ex: Aluguel, Produtos de Cabelo, Água..." required />
                        @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <x-input-label value="Tipo de Destinação" />
                        <select wire:model="type" class="w-full mt-1 border-gray-300 rounded-md shadow-sm text-sm" required>
                            <option value="expense">Despesa (Saída de Caixa / Contas a Pagar)</option>
                            <option value="revenue">Receita (Entrada de Caixa / Contas a Receber)</option>
                        </select>
                        @error('type') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" wire:model="is_active" id="is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <label for="is_active" class="ml-2 text-sm text-gray-600">Categoria ativa para lançamentos</label>
                    </div>

                    <div class="flex justify-end space-x-2 pt-4 border-t">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 border rounded-md text-sm text-gray-700 bg-white">Voltar</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md text-sm font-medium">Salvar Categoria</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
