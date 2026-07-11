<?php

use App\Models\Service;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination, WithFileUploads;

    public string $search = '';

    // Estados do Modal
    public bool $showModal = false;
    public bool $isEditing = false;
    public ?string $selectedServiceId = null;

    // Campos do Formulário
    public string $name = '';
    public ?string $category_id = '';
    public string $price = '0,00';
    public string $additional_cost = '0,00';
    public string $commission_percentage = '0,00';
    public int $duration_minutes = 30;
    public string $description = '';
    public bool $is_active = true;
    public $image;
    public ?string $existing_image_path = null;

    // Array para Produtos Relacionados Dinâmicos
    // Formato: [['product_id' => '', 'consumed_quantity' => '']]
    public array $selectedProducts = [];

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

    public function addProductRow(): void
    {
        $this->selectedProducts[] = ['product_id' => '', 'consumed_quantity' => '1'];
    }

    public function removeProductRow(int $index): void
    {
        unset($this->selectedProducts[$index]);
        $this->selectedProducts = array_values($this->selectedProducts);
    }

    private function parseCurrency(string $value): float
    {
        if (empty($value)) return 0.00;
        if (is_numeric($value) && strpos($value, ',') === false) return (float) $value;
        $cleaned = str_replace('.', '', $value);
        $cleaned = str_replace(',', '.', $cleaned);
        return (float) $cleaned;
    }

    public function edit(string $id): void
    {
        $this->resetForm();
        $service = Service::where('tenant_id', auth()->user()->tenant_id)->with('products')->findOrFail($id);

        $this->selectedServiceId = $service->id;
        $this->name = $service->name;
        $this->category_id = $service->category_id;
        $this->price = number_format($service->price, 2, ',', '.');
        $this->additional_cost = number_format($service->additional_cost, 2, ',', '.');
        $this->commission_percentage = number_format($service->commission_percentage, 2, ',', '.');
        $this->duration_minutes = $service->duration_minutes;
        $this->description = $service->description ?? '';
        $this->is_active = $service->is_active;
        $this->existing_image_path = $service->image_path;

        foreach ($service->products as $prod) {
            $this->selectedProducts[] = [
                'product_id' => $prod->id,
                'consumed_quantity' => $prod->pivot->consumed_quantity
            ];
        }

        $this->isEditing = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'image' => ['nullable', 'image', 'max:2048']
        ]);

        $imagePath = $this->existing_image_path;
        if ($this->image) {
            $imagePath = $this->image->store('services', 'public');
        }

        $data = [
            'name' => $this->name,
            'slug' => Str::slug($this->name),
            'category_id' => $this->category_id ?: null,
            'price' => $this->parseCurrency($this->price),
            'additional_cost' => $this->parseCurrency($this->additional_cost),
            'commission_percentage' => $this->parseCurrency($this->commission_percentage),
            'duration_minutes' => $this->duration_minutes,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'image_path' => $imagePath,
        ];

        if ($this->isEditing) {
            $service = Service::where('tenant_id', auth()->user()->tenant_id)->findOrFail($this->selectedServiceId);
            $service->update($data);

            // Sincroniza produtos na tabela pivô
            $syncData = [];
            foreach ($this->selectedProducts as $item) {
                if (!empty($item['product_id'])) {
                    $syncData[$item['product_id']] = ['consumed_quantity' => $item['consumed_quantity']];
                }
            }
            $service->products()->sync($syncData);

            session()->flash('message', 'Serviço atualizado com sucesso!');
        } else {
            $data['tenant_id'] = auth()->user()->tenant_id;
            $service = Service::create($data);

            // Anexa produtos na tabela pivô
            foreach ($this->selectedProducts as $item) {
                if (!empty($item['product_id'])) {
                    $service->products()->attach($item['product_id'], ['consumed_quantity' => $item['consumed_quantity']]);
                }
            }

            session()->flash('message', 'Serviço cadastrado com sucesso!');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(string $id): void
    {
        Service::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id)->delete();
        session()->flash('message', 'Serviço removido com sucesso!');
    }

    private function resetForm(): void
    {
        $this->reset([
            'name', 'category_id', 'price', 'additional_cost', 'commission_percentage',
            'duration_minutes', 'description', 'image', 'existing_image_path', 'selectedServiceId', 'selectedProducts'
        ]);
        $this->is_active = true;
    }

    public function with(): array
    {
        return [
            'services' => Service::where('tenant_id', auth()->user()->tenant_id)
                ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name', 'asc')
                ->paginate(10),
            // CORREÇÃO AQUI: Filtrando estritamente por tipo 'service'
            'categories' => Category::where('tenant_id', auth()->user()->tenant_id)
                ->where('type', 'service')
                ->orderBy('name', 'asc')
                ->get(),
            'allProducts' => Product::where('tenant_id', auth()->user()->tenant_id)->orderBy('name', 'asc')->get()
        ];
    }
}; ?>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold text-gray-800 leading-tight">Gestão de Serviços</h2>
            <button wire:click="openCreateModal" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-indigo-700">
                + Novo Serviço
            </button>
        </div>

        @if (session()->has('message'))
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded shadow-sm">
                {{ session('message') }}
            </div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="mb-4">
                <x-text-input wire:model.live="search" type="text" class="w-full md:w-1/3" placeholder="Buscar por serviço..." />
            </div>

            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Imagem</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Serviço</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Preço</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duração</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($services as $service)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($service->image_path)
                                    <img src="{{ asset('storage/' . $service->image_path) }}" class="h-10 w-10 rounded-full object-cover border">
                                @else
                                    <div class="h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 font-bold text-xs">S/I</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $service->name }}
                                <div class="text-xs text-gray-400">{{ $service->category?->name ?? 'Sem categoria' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">R$ {{ number_format($service->price, 2, ',', '.') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $service->duration_minutes }} min</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $service->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $service->is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                <button wire:click="edit({{ $service->id }})" class="text-indigo-600 hover:text-indigo-900">Editar</button>
                                <button wire:click="delete({{ $service->id }})" wire:confirm="Deseja excluir permanentemente?" class="text-red-600 hover:text-red-900">Excluir</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-sm text-gray-500 text-center">Nenhum serviço cadastrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $services->links() }}</div>
        </div>
    </div>

    @if($showModal)
        <div class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center">
            <div class="fixed inset-0 bg-gray-500 opacity-75" wire:click="$set('showModal', false)"></div>
            <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-3xl sm:w-full p-6 z-50 max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ $isEditing ? 'Editar Serviço' : 'Cadastrar Novo Serviço' }}</h3>

                <form wire:submit="save" class="space-y-4" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Nome do Serviço *" />
                            <x-text-input wire:model="name" type="text" class="block mt-1 w-full" required />
                        </div>
                        <div>
                            <x-input-label value="Categoria" />
                            <select wire:model="category_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 rounded-md shadow-sm">
                                <option value="">Sem Categoria</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <x-input-label value="Preço do Serviço *" />
                            <x-text-input wire:model.blur="price" type="text" class="block mt-1 w-full" x-data x-on:input="let v = $el.value.replace(/\D/g,''); v = (v/100).toFixed(2).replace('.', ','); v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.'); $el.value = v;" required />
                        </div>
                        <div>
                            <x-input-label value="Custo Adicional" />
                            <x-text-input wire:model.blur="additional_cost" type="text" class="block mt-1 w-full" x-data x-on:input="let v = $el.value.replace(/\D/g,''); v = (v/100).toFixed(2).replace('.', ','); v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.'); $el.value = v;" />
                        </div>
                        <div>
                            <x-input-label value="Comissão (%)" />
                            <x-text-input wire:model.blur="commission_percentage" type="text" class="block mt-1 w-full" x-data x-on:input="let v = $el.value.replace(/\D/g,''); v = (v/100).toFixed(2).replace('.', ','); v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.'); $el.value = v;" />
                        </div>
                        <div>
                            <x-input-label value="Duração (Minutos) *" />
                            <x-text-input wire:model="duration_minutes" type="number" class="block mt-1 w-full" required />
                        </div>
                    </div>

                    <div>
                        <x-input-label value="Descrição" />
                        <textarea wire:model="description" rows="2" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 rounded-md shadow-sm"></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                        <div>
                            <x-input-label value="Imagem do Serviço" />
                            <input type="file" wire:model="image" class="block mt-1 text-sm text-gray-500">
                        </div>
                        <div class="flex items-center">
                            <label class="inline-flex items-center cursor-pointer mt-5">
                                <input type="checkbox" wire:model="is_active" class="sr-only peer">
                                <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after: Josephson after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                <span class="ms-3 text-sm font-medium text-gray-700">Serviço Ativo</span>
                            </label>
                        </div>
                    </div>

                    <div class="border-t pt-3">
                        <div class="flex justify-between items-center mb-2">
                            <h4 class="text-sm font-semibold text-indigo-600">Produtos Consumidos (Baixa Automática)</h4>
                            <button type="button" wire:click="addProductRow" class="text-xs bg-indigo-50 text-indigo-600 px-2 py-1 rounded border border-indigo-200 hover:bg-indigo-100">+ Vincular Produto</button>
                        </div>

                        <div class="space-y-2">
                            @foreach($selectedProducts as $index => $item)
                                <div class="flex items-center gap-4 bg-gray-50 p-2 rounded border">
                                    <div class="flex-1">
                                        <select wire:model="selectedProducts.{{ $index }}.product_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm" required>
                                            <option value="">-- Selecione o Produto --</option>
                                            @foreach($allProducts as $p)
                                                <option value="{{ $p->id }}">{{ $p->name }} (Mudar saída por {{ $p->output_unit_type }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="w-32">
                                        <x-text-input wire:model="selectedProducts.{{ $index }}.consumed_quantity" type="number" step="0.01" class="w-full text-sm" placeholder="Qtd dose" required />
                                    </div>
                                    <button type="button" wire:click="removeProductRow({{ $index }})" class="text-red-500 hover:text-red-700 text-sm">✖</button>
                                </div>
                            @endforeach
                            @if(empty($selectedProducts))
                                <p class="text-xs text-gray-400 text-center py-2">Nenhum produto vinculado. Este serviço não dará baixa em estoque.</p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3 border-t pt-3">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancelar</button>
                        <x-primary-button type="submit">Salvar Serviço</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
