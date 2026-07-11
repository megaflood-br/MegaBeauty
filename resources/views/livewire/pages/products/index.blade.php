<?php

use App\Models\Product;
use App\Models\Category;
use App\Models\StockMovement;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';

    // Estados dos Modais
    public bool $showModal = false;
    public bool $isEditing = false;
    public bool $showStockModal = false;

    public ?string $selectedProductId = null;

    // Propriedades do Formulário de Produtos
    public bool $show_in_commands = true; // Flag adicionada para controle de exibição nas comandas
    public string $name = '';
    public ?string $category_id = '';
    public string $brand = '';
    public string $sku_code = '';
    public string $cost_price = '0,00';
    public string $sale_price = '0,00';
    public string $professional_price = '0,00';
    public string $default_commission_type = 'percentage';
    public string $default_commission_value = '0,00';

    // Estoque e Unidades
    public string $output_unit_type = 'unit';
    public string $output_unit_equivalent = '1';
    public string $stock_quantity = '0';
    public string $minimum_stock = '0';

    // Propriedades do Formulário de Movimentação Manual
    public string $movement_type = 'input';
    public string $movement_quantity = '';
    public string $movement_reason = 'Ajuste Manual';
    public string $movement_description = '';

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

    public function openStockModal(string $id): void
    {
        $this->resetForm();
        $product = Product::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id);
        $this->selectedProductId = $product->id;
        $this->name = $product->name;
        $this->stock_quantity = (string) $product->stock_quantity;
        $this->output_unit_type = $product->output_unit_type;

        $this->showStockModal = true;
    }

    public function saveStockMovement(): void
    {
        $this->validate([
            'movement_quantity' => ['required', 'numeric', 'min:0.01'],
            'movement_type' => ['required', 'in:input,output'],
            'movement_reason' => ['required', 'string', 'max:255'],
        ]);

        $product = Product::where('tenant_id', auth()->user()->tenant_id)->findOrFail($this->selectedProductId);
        $qty = (float) $this->movement_quantity;

        StockMovement::create([
            'tenant_id' => auth()->user()->tenant_id,
            'product_id' => $product->id,
            'user_id' => auth()->id(),
            'quantity' => $qty,
            'type' => $this->movement_type,
            'reason' => $this->movement_reason,
            'description' => $this->movement_description,
        ]);

        if ($this->movement_type === 'input') {
            $product->increment('stock_quantity', $qty);
        } else {
            $product->decrement('stock_quantity', $qty);
        }

        session()->flash('message', 'Movimentação de estoque lançada com sucesso!');
        $this->showStockModal = false;
        $this->resetForm();
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
        $product = Product::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id);

        $this->selectedProductId = $product->id;
        $this->show_in_commands = (bool) ($product->show_in_commands ?? true);
        $this->name = $product->name;
        $this->category_id = $product->category_id;
        $this->brand = $product->brand ?? '';
        $this->sku_code = $product->sku_code ?? '';
        $this->cost_price = number_format($product->cost_price, 2, ',', '.');
        $this->sale_price = number_format($product->sale_price, 2, ',', '.');
        $this->professional_price = number_format($product->professional_price, 2, ',', '.');
        $this->default_commission_type = $product->default_commission_type;
        $this->default_commission_value = number_format($product->default_commission_value, 2, ',', '.');
        $this->output_unit_type = $product->output_unit_type;
        $this->output_unit_equivalent = (string) $product->output_unit_equivalent;
        $this->stock_quantity = (string) $product->stock_quantity;
        $this->minimum_stock = (string) $product->minimum_stock;

        $this->isEditing = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'cost_price' => ['required'],
            'sale_price' => ['required'],
            'output_unit_type' => ['required', 'string'],
        ]);

        $data = [
            'name' => $this->name,
            'show_in_commands' => $this->show_in_commands,
            'category_id' => $this->category_id ?: null,
            'brand' => $this->brand,
            'sku_code' => $this->sku_code,
            'cost_price' => $this->parseCurrency($this->cost_price),
            'sale_price' => $this->parseCurrency($this->sale_price),
            'professional_price' => $this->parseCurrency($this->professional_price),
            'default_commission_type' => $this->default_commission_type,
            'default_commission_value' => $this->parseCurrency($this->default_commission_value),
            'output_unit_type' => $this->output_unit_type,
            'output_unit_equivalent' => (float) $this->output_unit_equivalent,
            'minimum_stock' => (int) $this->minimum_stock,
        ];

        if ($this->isEditing) {
            $product = Product::where('tenant_id', auth()->user()->tenant_id)->findOrFail($this->selectedProductId);
            $product->update($data);
            session()->flash('message', 'Produto atualizado com sucesso!');
        } else {
            $data['tenant_id'] = auth()->user()->tenant_id;
            $data['stock_quantity'] = (float) $this->stock_quantity;
            Product::create($data);
            session()->flash('message', 'Produto cadastrado com sucesso!');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(string $id): void
    {
        Product::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id)->delete();
        session()->flash('message', 'Produto removido com sucesso!');
    }

    private function resetForm(): void
    {
        $this->reset([
            'name', 'category_id', 'brand', 'sku_code', 'cost_price', 'sale_price', 'professional_price',
            'default_commission_value', 'stock_quantity', 'minimum_stock', 'selectedProductId',
            'movement_quantity', 'movement_description'
        ]);
        $this->show_in_commands = true;
        $this->output_unit_type = 'unit';
        $this->output_unit_equivalent = '1';
        $this->default_commission_type = 'percentage';
        $this->movement_type = 'input';
        $this->movement_reason = 'Ajuste Manual';
    }

    public function with(): array
    {
        return [
            'products' => Product::where('tenant_id', auth()->user()->tenant_id)
                ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('sku_code', 'like', "%{$this->search}%"))
                ->orderBy('name', 'asc')
                ->paginate(10),
            'categories' => Category::where('tenant_id', auth()->user()->tenant_id)
                ->where('type', 'product')
                ->orderBy('name', 'asc')
                ->get(),
            'movements' => $this->selectedProductId
                ? StockMovement::where('product_id', $this->selectedProductId)->orderBy('created_at', 'desc')->take(5)->get()
                : []
        ];
    }
}; ?>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold text-gray-800 leading-tight">Catálogo de Produtos</h2>
            <button wire:click="openCreateModal" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-indigo-700">
                + Novo Produto
            </button>
        </div>

        @if (session()->has('message'))
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded shadow-sm">
                {{ session('message') }}
            </div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="mb-4">
                <x-text-input wire:model.live="search" type="text" class="w-full md:w-1/3" placeholder="Buscar por produto ou código..." />
            </div>

            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Produto</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Marca / Código</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Preço Venda</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Comanda</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estoque Atual</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($products as $product)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $product->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $product->brand ?: '-' }} <span class="text-xs text-gray-400">({{ $product->sku_code ?: 'Sem código' }})</span></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">R$ {{ number_format($product->sale_price, 2, ',', '.') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $product->show_in_commands ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $product->show_in_commands ? 'Visível' : 'Oculto' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span class="{{ $product->stock_quantity <= $product->minimum_stock ? 'text-red-600 font-bold' : 'text-gray-600' }}">
                                    {{ $product->stock_quantity }} {{ $product->output_unit_type }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                <button wire:click="openStockModal('{{ $product->id }}')" class="text-green-600 hover:text-green-900 font-semibold mr-2">Estoque</button>
                                <button wire:click="edit('{{ $product->id }}')" class="text-indigo-600 hover:text-indigo-900">Editar</button>
                                <button wire:click="delete('{{ $product->id }}')" wire:confirm="Excluir este produto permanentemente?" class="text-red-600 hover:text-red-900">Excluir</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-sm text-gray-500 text-center">Nenhum produto cadastrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $products->links() }}</div>
        </div>
    </div>

    @if($showModal)
        <div class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center">
            <div class="fixed inset-0 bg-gray-500 opacity-75" wire:click="$set('showModal', false)"></div>
            <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-2xl sm:w-full p-6 z-50 max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ $isEditing ? 'Editar Produto' : 'Cadastrar Novo Produto' }}</h3>

                <form wire:submit="save" class="space-y-4">
                    <div class="bg-indigo-50/50 p-3 rounded-md border border-indigo-100 flex items-center">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model.live="show_in_commands" class="sr-only peer">
                            <div class="relative w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                            <span class="ms-2 text-xs font-semibold text-gray-700">Disponível para venda direta na Comanda de Balcão</span>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <x-input-label value="Nome do Produto *" />
                            <x-text-input wire:model="name" type="text" class="block mt-1 w-full" required />
                        </div>
                        <div>
                            <x-input-label value="Marca" />
                            <x-text-input wire:model="brand" type="text" class="block mt-1 w-full" />
                        </div>
                        <div>
                            <x-input-label value="Código de Barras / SKU" />
                            <x-text-input wire:model="sku_code" type="text" class="block mt-1 w-full" />
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

                    <div class="border-t pt-3">
                        <h4 class="text-sm font-semibold text-indigo-600 mb-2">Financeiro e Valores</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <x-input-label value="Preço de Compra (Custo)" />
                                <x-text-input wire:model.blur="cost_price" type="text" class="block mt-1 w-full" x-data x-on:input="let v = $el.value.replace(/\D/g,''); v = (v/100).toFixed(2).replace('.', ','); v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.'); $el.value = v;" />
                            </div>
                            <div>
                                <x-input-label value="Preço de Venda *" />
                                <x-text-input wire:model.blur="sale_price" type="text" class="block mt-1 w-full" x-data x-on:input="let v = $el.value.replace(/\D/g,''); v = (v/100).toFixed(2).replace('.', ','); v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.'); $el.value = v;" required />
                            </div>
                            <div>
                                <x-input-label value="Preço para Profissional" />
                                <x-text-input wire:model.blur="professional_price" type="text" class="block mt-1 w-full" x-data x-on:input="let v = $el.value.replace(/\D/g,''); v = (v/100).toFixed(2).replace('.', ','); v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.'); $el.value = v;" />
                            </div>
                            <div>
                                <x-input-label value="Tipo de Comissão" />
                                <select wire:model="default_commission_type" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 rounded-md shadow-sm">
                                    <option value="percentage">Porcentagem (%)</option>
                                    <option value="fixed">Valor Fixo (R$)</option>
                                </select>
                            </div>
                            <div>
                                <x-input-label value="Comissão Padrão" />
                                <x-text-input wire:model.blur="default_commission_value" type="text" class="block mt-1 w-full" x-data x-on:input="let v = $el.value.replace(/\D/g,''); v = (v/100).toFixed(2).replace('.', ','); v = v.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.'); $el.value = v;" />
                            </div>
                        </div>
                    </div>

                    <div class="border-t pt-3">
                        <h4 class="text-sm font-semibold text-indigo-600 mb-2">Estoque e Fracionamento</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label value="* Registro de Saída" />
                                <select wire:model="output_unit_type" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 rounded-md shadow-sm" required>
                                    <option value="unidade">em unidade</option>
                                    <option value="mililitros (ml)">em mililitros (ml)</option>
                                    <option value="gramas (g)">em gramas (g)</option>
                                    <option value="dosagem">em dosagem</option>
                                    <option value="litros (l)">em litros (l)</option>
                                    <option value="caixa">em caixa</option>
                                    <option value="pacote">em pacote</option>
                                    <option value="miligramas (mg)">em miligramas (mg)</option>
                                </select>
                            </div>
                            <div>
                                <x-input-label value="* Uma unidade equivale a" />
                                <x-text-input wire:model="output_unit_equivalent" type="number" step="0.01" class="block mt-1 w-full" required />
                            </div>
                            @if(!$isEditing)
                                <div>
                                    <x-input-label value="Estoque Inicial" />
                                    <x-text-input wire:model="stock_quantity" type="number" step="0.01" class="block mt-1 w-full" />
                                </div>
                            @endif
                            <div>
                                <x-input-label value="Estoque Mínimo (Alerta)" />
                                <x-text-input wire:model="minimum_stock" type="number" class="block mt-1 w-full" />
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3 border-t pt-3">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancelar</button>
                        <x-primary-button type="submit">Salvar Produto</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showStockModal)
        <div class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center">
            <div class="fixed inset-0 bg-gray-500 opacity-75" wire:click="$set('showStockModal', false)"></div>
            <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-xl sm:w-full p-6 z-50">
                <h3 class="text-lg font-medium text-gray-900 mb-2">Movimentação Manual de Estoque</h3>
                <p class="text-sm text-gray-500 mb-4">Produto: <span class="font-semibold text-indigo-600">{{ $this->name }}</span> (Saldo atual: {{ $this->stock_quantity }} {{ $this->output_unit_type }})</p>

                <form wire:submit="saveStockMovement" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Tipo de Ação *" />
                            <select wire:model="movement_type" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 rounded-md shadow-sm" required>
                                <option value="input">Entrada (+) Adicionar Estoque</option>
                                <option value="output">Saída (-) Remover Estoque</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label value="Quantidade *" />
                            <x-text-input wire:model="movement_quantity" type="number" step="0.01" class="block mt-1 w-full" placeholder="Ex: 5" required />
                        </div>
                    </div>

                    <div>
                        <x-input-label value="Motivo da Alteração *" />
                        <select wire:model="movement_reason" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 rounded-md shadow-sm" required>
                            <option value="Ajuste Manual">Ajuste de Saldo Manual</option>
                            <option value="Compra de Fornecedor">Compra / Entrada de Fornecedor</option>
                            <option value="Produto Danificado">Produto Danificado / Vencido</option>
                            <option value="Uso Interno">Consumo / Uso Interno</option>
                        </select>
                    </div>

                    <div>
                        <x-input-label value="Observação Extra (Opcional)" />
                        <textarea wire:model="movement_description" rows="2" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 rounded-md shadow-sm" placeholder="Digite detalhes da nota ou justificativa..."></textarea>
                    </div>

                    <div class="border-t pt-3">
                        <h4 class="text-xs font-bold uppercase text-gray-400 mb-2">Últimas 5 alterações deste item:</h4>
                        <div class="bg-gray-50 rounded p-2 text-xs max-h-32 overflow-y-auto space-y-1">
                            @forelse($movements as $mov)
                                <div class="flex justify-between border-b pb-1 last:border-0">
                                    <span class="{{ $mov->type === 'input' ? 'text-green-600 font-semibold' : 'text-red-600 font-semibold' }}">
                                        {{ $mov->type === 'input' ? '+' : '-' }} {{ $mov->quantity }} ({{ $mov->reason }})
                                    </span>
                                    <span class="text-gray-400">{{ $mov->created_at->format('d/m/Y H:i') }}</span>
                                </div>
                            @empty
                                <p class="text-center text-gray-400 py-2">Nenhuma movimentação registrada.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3 border-t pt-3">
                        <button type="button" wire:click="$set('showStockModal', false)" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Fechar</button>
                        <x-primary-button type="submit">Confirmar Lançamento</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
