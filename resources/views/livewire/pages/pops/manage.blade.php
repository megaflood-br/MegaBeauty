<?php
use App\Models\PopTemplate;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component {
    public $title = '';
    public $content = '';
    public $category = '';
    public ?PopTemplate $pop = null;

    public function mount($id = null)
    {
        if (!in_array(auth()->user()->role, ['admin', 'superadmin'])) {
            abort(403);
        }

        if ($id) {
            $this->pop = PopTemplate::findOrFail($id);
            $this->title = $this->pop->title;
            $this->content = $this->pop->content;
            $this->category = $this->pop->category;
        }
    }

    public function save()
    {
        // Validação básica
        if (empty($this->title)) {
            return; // Impede salvar se estiver vazio
        }

        $data = [
            'title' => $this->title,
            'content' => $this->content,
            'category' => $this->category,
            'tenant_id' => auth()->user()->tenant_id
        ];

        if ($this->pop) {
            $this->pop->update($data);
        } else {
            PopTemplate::create($data);
        }

        $this->redirect(route('pops.index'), navigate: true);
    }
}; ?>
<div>
    <style>
        trix-editor {
            display: block;
            min-height: 300px;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 10px;
            background-color: white;
        }
    </style>

<div class="py-12">
    <div class="max-w-4xl mx-auto bg-white p-8 rounded-lg shadow">
        <h1 class="text-2xl font-bold mb-6">{{ $pop ? 'Editar POP' : 'Cadastrar novo POP' }}</h1>

        <form wire:submit="save">
            <div class="mb-4">
                <label class="block font-medium">Título</label>
                <input wire:model="title" type="text" class="w-full border-gray-300 rounded-md" required>
            </div>

            <div class="mb-4">
                <label class="block font-medium mb-2">Conteúdo</label>
                <input id="pop_content" type="hidden" wire:model="content">
            <div class="mb-4">
                <label class="block font-medium">Categoria</label>
                <select wire:model="category" class="w-full border-gray-300 rounded-md">
                    <option value="Geral">Geral</option>
                    <option value="Manicures e Pedicures">Manicures e Pedicures</option>
                    <option value="Salão de Cabelos">Salão de Cabelos</option>
                    <option value="Beleza do Olhar">Beleza do Olhar</option>
                    <option value="Depilação à Laser">Depilação à Laser</option>
                </select>
            </div>
                <div wire:ignore>
                    <trix-editor input="pop_content" id="trix_instance"></trix-editor>
                </div>
            </div>

            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded">
                Salvar POP
            </button>
        </form>
    </div>
</div>

<script>
    // Inicialização do Trix e sincronização com Livewire
    document.addEventListener('trix-initialize', function(event) {
        const editor = document.getElementById('trix_instance');
        const initialContent = @js($content);
        if (initialContent) {
            editor.editor.loadHTML(initialContent);
        }
    });

    // Quando o usuário muda o conteúdo, atualizamos o Livewire
    document.addEventListener('trix-change', function(event) {
        @this.set('content', event.target.value);
    });
</script>
</div>

