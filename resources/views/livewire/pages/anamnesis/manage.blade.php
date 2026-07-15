<?php

use App\Models\AnamnesisTemplate;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    public $templates = [];
    public bool $isFormOpen = false;
    public ?int $editingId = null;

    public string $name = '';
    public array $form_schema = [];

    public ?int $editingFieldIndex = null;

    public array $newField = [
        'label' => '',
        'type' => 'text',
        'required' => false,
        'options' => ''
    ];

    public function mount(): void
    {
        $this->loadTemplates();
    }

    public function loadTemplates(): void
    {
        // CORREÇÃO: Filtrando explicitamente pelo tenant logado
        $this->templates = AnamnesisTemplate::where('tenant_id', auth()->user()->tenant_id)
            ->orderBy('name')
            ->get();
    }

    public function openForm(?int $id = null): void
    {
        $this->resetValidation();
        $this->isFormOpen = true;
        $this->editingId = $id;

        if ($id) {
            $template = AnamnesisTemplate::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id);
            $this->name = $template->name;
            $this->form_schema = $template->form_schema ?? [];
        } else {
            $this->name = '';
            $this->form_schema = [];
        }

        $this->resetNewField();
    }

    public function closeForm(): void
    {
        $this->isFormOpen = false;
        $this->editingId = null;
    }

    public function resetNewField(): void
    {
        $this->newField = [
            'label' => '',
            'type' => 'text',
            'required' => false,
            'options' => ''
        ];
        $this->editingFieldIndex = null;
    }

    public function editField(int $index): void
    {
        $field = $this->form_schema[$index];
        $this->newField = [
            'label' => $field['label'],
            'type' => $field['type'],
            'required' => $field['required'],
            'options' => isset($field['options']) ? implode(', ', $field['options']) : ''
        ];
        $this->editingFieldIndex = $index;
    }

    public function addField(): void
    {
        $this->validate([
            'newField.label' => 'required|string|max:255',
            'newField.type' => 'required|in:text,textarea,radio',
        ]);

        $fieldName = Str::slug($this->newField['label'], '_') . '_' . time();

        $fieldData = [
            'name' => $this->editingFieldIndex !== null ? $this->form_schema[$this->editingFieldIndex]['name'] : $fieldName,
            'label' => $this->newField['label'],
            'type' => $this->newField['type'],
            'required' => (bool) $this->newField['required'],
        ];

        if ($this->newField['type'] === 'radio') {
            $options = array_filter(array_map('trim', explode(',', $this->newField['options'])));
            $fieldData['options'] = empty($options) ? ['Sim', 'Não'] : array_values($options);
        }

        if ($this->editingFieldIndex !== null) {
            $this->form_schema[$this->editingFieldIndex] = $fieldData;
        } else {
            $this->form_schema[] = $fieldData;
        }

        $this->resetNewField();
    }

    public function removeField(int $index): void
    {
        unset($this->form_schema[$index]);
        $this->form_schema = array_values($this->form_schema);
    }

    public function reorderQuestions(int $fromIndex, int $toIndex): void
    {
        if ($fromIndex === $toIndex) return;

        $schema = $this->form_schema;
        $item = array_splice($schema, $fromIndex, 1)[0];
        array_splice($schema, $toIndex, 0, [$item]);

        $this->form_schema = array_values($schema);
    }

    public function saveTemplate(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'form_schema' => 'required|array|min:1',
        ], [
            'form_schema.min' => 'Adicione pelo menos uma pergunta à ficha de anamnese.'
        ]);

        if ($this->editingId) {
            $template = AnamnesisTemplate::where('tenant_id', auth()->user()->tenant_id)->findOrFail($this->editingId);
            $template->update([
                'name' => $this->name,
                'form_schema' => $this->form_schema,
            ]);
        } else {
            // CORREÇÃO: Injetando o tenant_id do usuário logado na criação
            AnamnesisTemplate::create([
                'tenant_id' => auth()->user()->tenant_id,
                'name' => $this->name,
                'form_schema' => $this->form_schema,
            ]);
        }

        $this->loadTemplates();
        $this->closeForm();
    }

    public function deleteTemplate(int $id): void
    {
        AnamnesisTemplate::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id)->delete();
        $this->loadTemplates();
    }
}; ?>

<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <div class="px-4 py-6 sm:px-0">

        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-semibold text-gray-900">Modelos de Anamnese</h1>
            @if(!$isFormOpen)
                <button wire:click="openForm" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 shadow-sm">
                    Novo Modelo
                </button>
            @endif
        </div>

        @if(!$isFormOpen)
            <div class="bg-white shadow overflow-hidden sm:rounded-md">
                <ul role="list" class="divide-y divide-gray-200">
                    @forelse($templates as $template)
                        <li>
                            <div class="px-4 py-4 flex items-center justify-between sm:px-6 hover:bg-gray-50 transition-colors duration-150">
                                <div class="flex-1 min-w-0">
                                    <h2 class="text-lg font-medium text-indigo-600 truncate">{{ $template->name }}</h2>
                                    <p class="text-sm text-gray-500">
                                        {{ count($template->form_schema) }} perguntas configuradas
                                    </p>
                                </div>
                                <div class="ml-4 flex-shrink-0 flex gap-4">
                                    <button wire:click="openForm({{ $template->id }})" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">Editar</button>
                                    <button wire:click="deleteTemplate({{ $template->id }})" wire:confirm="Tem certeza que deseja excluir este modelo?" class="text-sm font-medium text-red-600 hover:text-red-900">Excluir</button>
                                </div>
                            </div>
                        </li>
                    @empty
                        <li class="px-4 py-8 text-center text-gray-500">
                            Nenhum modelo de anamnese criado ainda.
                        </li>
                    @endforelse
                </ul>
            </div>
        @else
            <div class="bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        {{ $editingId ? 'Editar Modelo' : 'Criar Novo Modelo' }}
                    </h3>

                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nome da Ficha (Ex: Ficha Corporal, Ficha Facial)</label>
                            <input type="text" wire:model="name" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="border-t border-gray-200 pt-6">
                            <div class="flex justify-between items-center mb-4">
                                <h4 class="text-md font-medium text-gray-900">
                                    {{ $editingFieldIndex !== null ? 'Editar Pergunta' : 'Adicionar Nova Pergunta' }}
                                </h4>
                                @if($editingFieldIndex !== null)
                                    <button type="button" wire:click="resetNewField" class="text-sm text-gray-500 hover:text-gray-700 underline">
                                        Cancelar Edição
                                    </button>
                                @endif
                            </div>

                            <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6 items-end bg-gray-50 p-4 rounded-md border border-gray-200">

                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700">Pergunta</label>
                                    <input type="text" wire:model="newField.label" placeholder="Ex: Possui alergias?" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                </div>

                                <div class="sm:col-span-1">
                                    <label class="block text-sm font-medium text-gray-700">Tipo</label>
                                    <select wire:model.live="newField.type" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        <option value="text">Texto Curto</option>
                                        <option value="textarea">Texto Longo</option>
                                        <option value="radio">Múltipla Escolha</option>
                                    </select>
                                </div>

                                @if($newField['type'] === 'radio')
                                    <div class="sm:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700">Opções (separadas por vírgula)</label>
                                        <input type="text" wire:model="newField.options" placeholder="Sim, Não, Não sei" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                    </div>
                                @endif

                                <div class="sm:col-span-1 flex items-center h-10 mb-1">
                                    <input id="required_checkbox" type="checkbox" wire:model="newField.required" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                    <label for="required_checkbox" class="ml-2 block text-sm text-gray-900">Obrigatório</label>
                                </div>

                                <div class="sm:col-span-6 text-right mt-2">
                                    <button type="button" wire:click="addField" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white {{ $editingFieldIndex !== null ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-800 hover:bg-gray-900' }} focus:outline-none">
                                        {{ $editingFieldIndex !== null ? '✔ Atualizar Pergunta' : '+ Inserir na Ficha' }}
                                    </button>
                                </div>
                            </div>
                            @error('newField.label') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
                        </div>

                        @if(count($form_schema) > 0)
                            <div class="mt-8">
                                <h4 class="text-sm font-medium text-gray-700 uppercase tracking-wider mb-4">Estrutura da Ficha (Arraste para reordenar)</h4>

                                <ul class="space-y-3" x-data="{ dragging: null, dropping: null }">
                                    @foreach($form_schema as $index => $field)
                                        <li draggable="true"
                                            @dragstart="dragging = {{ $index }}; $event.dataTransfer.effectAllowed='move';"
                                            @dragenter.prevent="if(dragging !== {{ $index }}) dropping = {{ $index }}"
                                            @dragover.prevent="$event.dataTransfer.dropEffect='move'"
                                            @drop.prevent="if(dragging !== null && dropping !== null) { $wire.reorderQuestions(dragging, dropping); } dragging = null; dropping = null;"
                                            @dragend="dragging = null; dropping = null;"
                                            :class="{
                                                'opacity-40 border-dashed': dragging === {{ $index }},
                                                'border-t-4 border-t-indigo-500 scale-[1.01] shadow-md transition-all': dropping === {{ $index }} && dragging !== {{ $index }}
                                            }"
                                            class="flex justify-between items-center bg-white p-3 border border-gray-200 rounded-md shadow-sm hover:shadow transition-shadow cursor-move">

                                            <div class="flex items-center gap-3">
                                                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                                                </svg>

                                                <div>
                                                    <span class="font-medium text-gray-900">{{ $field['label'] }}</span>
                                                    <span class="ml-2 text-xs text-gray-500">
                                                        ({{ $field['type'] }} @if(isset($field['options'])) - {{ implode(', ', $field['options']) }} @endif)
                                                    </span>
                                                    @if($field['required'])
                                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">Obrigatório</span>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-3">
                                                <button type="button" wire:click="editField({{ $index }})" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                                                    Editar
                                                </button>
                                                <button type="button" wire:click="removeField({{ $index }})" class="text-red-500 hover:text-red-700 text-sm font-medium">
                                                    Excluir
                                                </button>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @error('form_schema') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror

                        <div class="pt-5 border-t border-gray-200 flex justify-end gap-3">
                            <button type="button" wire:click="closeForm" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none">
                                Cancelar
                            </button>
                            <button type="button" wire:click="saveTemplate" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none">
                                Salvar Modelo
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
