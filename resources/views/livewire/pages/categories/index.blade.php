<?php

use App\Models\Category;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $type = 'product'; // Tipo padrão vindo da rota

    // Controle do Modal e Estados
    public bool $showModal = false;
    public bool $isEditing = false;
    public ?string $selectedCategoryId = null;

    // Propriedades do Formulário
    public string $name = '';

    public function mount(string $type = 'product'): void
    {
        // Garante que o tipo seja válido
        $this->type = in_array($type, ['product', 'service']) ? $type : 'product';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->isEditing = false;
        $this->showModal = true;
    }

    public function edit(string $id): void
    {
        $this->resetForm();

        $category = Category::where('tenant_id', auth()->user()->tenant_id)
            ->where('type', $this->type)
            ->findOrFail($id);

        $this->selectedCategoryId = $category->id;
        $this->name = $category->name;

        $this->isEditing = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $data = [
            'name' => $this->name,
            'slug' => Str::slug($this->name),
            'type' => $this->type,
        ];

        if ($this->isEditing) {
            $category = Category::where('tenant_id', auth()->user()->tenant_id)->findOrFail($this->selectedCategoryId);
            $category->update($data);
            session()->flash('message', 'Categoria atualizada com sucesso!');
        } else {
            $data['tenant_id'] = auth()->user()->tenant_id;
            Category::create($data);
            session()->flash('message', 'Categoria cadastrada com sucesso!');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(string $id): void
    {
        $category = Category::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id);
        $category->delete();
        session()->flash('message', 'Categoria removida com sucesso!');
    }

    private function resetForm(): void
    {
        $this->reset(['name', 'selectedCategoryId']);
    }

    public function with(): array
    {
        return [
            'categories' => Category::query()
                ->where('tenant_id', auth()->user()->tenant_id)
                ->where('type', $this->type)
                ->when($this->search, function ($query) {
                    $query->where('name', 'like', '%' . $this->search . '%');
                })
                ->orderBy('name', 'asc')
                ->paginate(10),
        ];
    }
}; ?>

<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold text-gray-800 leading-tight">
                Categorias de {{ $type === 'product' ? 'Produtos' : 'Serviços' }}
            </h2>
            <button wire:click="openCreateModal" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                + Nova Categoria
            </button>
        </div>

        @if (session()->has('message'))
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded shadow-sm">
                {{ session('message') }}
            </div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="mb-4">
                <x-text-input wire:model.live="search" type="text" class="w-full md:w-1/2" placeholder="Buscar categoria..." />
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome da Categoria</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Identificador (Slug)</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($categories as $category)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $category->name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $category->slug }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <button wire:click="edit({{ $category->id }})" class="text-indigo-600 hover:text-indigo-900">Editar</button>
                                    <button wire:click="delete({{ $category->id }})" wire:confirm="Tem certeza que deseja excluir esta categoria?" class="text-red-600 hover:text-red-900">Excluir</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">Nenhuma categoria encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $categories->links() }}
            </div>
        </div>
    </div>

    @if($showModal)
        <div class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center">
            <div class="fixed inset-0 transition-opacity" wire:click="$set('showModal', false)">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>

            <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-md sm:w-full p-6 z-50">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    {{ $isEditing ? 'Editar Categoria' : 'Cadastrar Nova Categoria' }}
                </h3>

                <form wire:submit="save">
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <x-input-label for="category_name" value="Nome da Categoria *" />
                            <x-text-input id="category_name" type="text" class="block mt-1 w-full" wire:model="name" placeholder="Ex: Cabelo, Unhas, Estética..." required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Cancelar
                        </button>
                        <x-primary-button type="submit">
                            {{ $isEditing ? 'Atualizar' : 'Salvar' }}
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
