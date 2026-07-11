<?php

use App\Models\User;
use App\Models\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads; // Adicionado para suportar o upload de imagens
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination, WithFileUploads; // Trait ativada aqui

    // Propriedades para a Busca e Filtros
    public string $search = '';

    // Controle do Modal Principal (Clientes)
    public bool $showModal = false;
    public bool $isEditing = false;
    public ?string $selectedCustomerId = null;

    // Controle de Abas Internas do Modal de CRM
    public string $activeModalTab = 'form';

    // Controle do Sub-Modal de Detalhes da Comanda Histórica
    public bool $showCommandDetailsModal = false;
    public ?Command $selectedCommandDetails = null;

    // Propriedades do Formulário de Cadastro/Edição
    public string $name = '';
    public string $nick = '';
    public string $email = '';
    public string $phone = '';
    public string $user_document_cpf_cnpj = '';
    public string $instagram = '';
    public string $observations = '';
    public $photo; // Upload temporário
    public ?string $currentPhotoUrl = null; // Caminho da foto salva

    // Propriedades calculadas para o CRM do Cliente
    public float $totalSpent = 0.00;
    public string $daysSinceLastVisit = 'Nenhum atendimento registrado';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Prepara o modal para um novo cadastro
     */
    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->isEditing = false;
        $this->activeModalTab = 'form';
        $this->showModal = true;
    }

    /**
     * Carrega os dados do cliente no formulário e processa estatísticas financeiras/CRM
     */
    public function edit(string $id): void
    {
        $this->resetForm();
        $this->activeModalTab = 'form';

        $customer = User::where('tenant_id', auth()->user()->tenant_id)
            ->where('role', 'customer')
            ->findOrFail($id);

        $this->selectedCustomerId = $customer->id;
        $this->name = $customer->name;
        $this->nick = $customer->nick ?? '';
        $this->email = $customer->email;
        $this->phone = $customer->phone ?? '';
        $this->user_document_cpf_cnpj = $customer->user_document_cpf_cnpj ?? '';
        $this->instagram = $customer->instagram ?? '';
        $this->observations = $customer->observations ?? '';
        $this->currentPhotoUrl = $customer->photo_path ? Storage::url($customer->photo_path) : null;

        // --- CÁLCULO CRM DO CLIENTE ---
        $this->totalSpent = (float) Command::where('customer_id', $customer->id)
            ->where('status', 'finished')
            ->sum('total_amount');

        $lastCommand = Command::where('customer_id', $customer->id)
            ->where('status', 'finished')
            ->whereNotNull('finished_at')
            ->orderBy('finished_at', 'desc')
            ->first();

        if ($lastCommand && $lastCommand->finished_at) {
            $lastVisitDate = \Carbon\Carbon::parse($lastCommand->finished_at)->startOfDay();
            $todayDate = now()->startOfDay();
            $diff = $todayDate->diffInDays($lastVisitDate);
            $this->daysSinceLastVisit = $diff === 0 ? 'Veio hoje ✓' : $diff . ' dia(s) atrás';
        } else {
            $this->daysSinceLastVisit = 'Nunca compareceu';
        }

        $this->isEditing = true;
        $this->showModal = true;
    }

    /**
     * Abre e carrega a comanda completa para exibição detalhada dos itens consumidos
     */
    public function viewCommand(int $commandId): void
    {
        $this->selectedCommandDetails = Command::with([
            'services.service',
            'services.professional',
            'products.product'
        ])
        ->where('tenant_id', auth()->user()->tenant_id)
        ->findOrFail($commandId);

        $this->showCommandDetailsModal = true;
    }

    /**
     * Salva ou atualiza o cliente
     */
    public function save(): void
    {
        $emailRule = $this->isEditing
            ? 'required|string|email|max:255|unique:users,email,' . $this->selectedCustomerId
            : 'required|string|email|max:255|unique:users,email';

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'nick' => ['nullable', 'string', 'max:255'],
            'email' => $emailRule,
            'phone' => ['nullable', 'string', 'max:255'],
            'user_document_cpf_cnpj' => ['nullable', 'string', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:2048']
        ]);

        $photoPath = null;
        if ($this->photo) {
            $photoPath = $this->photo->store('avatars', 'public');
        }

        if ($this->isEditing) {
            $customer = User::where('tenant_id', auth()->user()->tenant_id)
                ->where('role', 'customer')
                ->findOrFail($this->selectedCustomerId);

            $updateData = [
                'name' => $this->name,
                'nick' => $this->nick,
                'email' => $this->email,
                'phone' => $this->phone,
                'user_document_cpf_cnpj' => $this->user_document_cpf_cnpj,
                'instagram' => $this->instagram,
                'observations' => $this->observations,
            ];

            if ($photoPath) {
                if ($customer->photo_path) {
                    Storage::disk('public')->delete($customer->photo_path);
                }
                $updateData['photo_path'] = $photoPath;
            }

            $customer->update($updateData);
            session()->flash('message', 'Cliente atualizado com sucesso!');
        } else {
            User::create([
                'tenant_id' => auth()->user()->tenant_id,
                'name' => $this->name,
                'nick' => $this->nick,
                'email' => $this->email,
                'password' => bcrypt(Str::random(16)),
                'phone' => $this->phone,
                'user_document_cpf_cnpj' => $this->user_document_cpf_cnpj,
                'instagram' => $this->instagram,
                'observations' => $this->observations,
                'photo_path' => $photoPath,
                'role' => 'customer',
                'is_active' => true,
            ]);

            session()->flash('message', 'Cliente cadastrado com sucesso!');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    /**
     * Remove o cliente do banco de dados
     */
    public function delete(string $id): void
    {
        $customer = User::where('tenant_id', auth()->user()->tenant_id)
            ->where('role', 'customer')
            ->findOrFail($id);

        if ($customer->photo_path) {
            Storage::disk('public')->delete($customer->photo_path);
        }

        $customer->delete();
        session()->flash('message', 'Cliente removido com sucesso!');
    }

    private function resetForm(): void
    {
        $this->reset([
            'name', 'nick', 'email', 'phone', 'user_document_cpf_cnpj', 'instagram',
            'observations', 'selectedCustomerId', 'totalSpent', 'daysSinceLastVisit',
            'showCommandDetailsModal', 'selectedCommandDetails', 'photo', 'currentPhotoUrl'
        ]);
        $this->activeModalTab = 'form';
    }

    public function with(): array
    {
        $tenantId = auth()->user()->tenant_id;

        $customerCommands = [];
        if ($this->selectedCustomerId && $this->activeModalTab === 'commands') {
            $customerCommands = Command::where('customer_id', $this->selectedCustomerId)
                ->orderBy('created_at', 'desc')
                ->paginate(5, pageName: 'commands-page');
        }

        return [
            'customers' => User::query()
                ->where('tenant_id', $tenantId)
                ->where('role', 'customer')

                // 🔥 MÁGICA DO EAGER LOADING AQUI 🔥
                // 1. Conta quantas comandas o cliente tem no total
                ->withCount('commands')

                // 2. Soma o total gasto (total_amount) apenas das comandas 'finished'
                ->withSum(['commands as total_spent' => function($query) {
                    $query->where('status', 'finished');
                }], 'total_amount')

                ->when($this->search, function ($query) {
                    $query->where(function ($q) {
                        $q->where('name', 'like', '%' . $this->search . '%')
                          ->orWhere('email', 'like', '%' . $this->search . '%')
                          ->orWhere('phone', 'like', '%' . $this->search . '%');
                    });
                })
                ->orderBy('name', 'asc')
                ->paginate(10, pageName: 'customers-page'),

            'customerCommands' => $customerCommands,
        ];
    }
}; ?>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold text-gray-800 leading-tight">
                Gestão de Clientes
            </h2>
            <button wire:click="openCreateModal" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 shadow-sm transition">
                + Novo Cliente
            </button>
        </div>

        @if (session()->has('message'))
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded shadow-sm">
                {{ session('message') }}
            </div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            <div class="mb-4">
                <x-text-input wire:model.live="search" type="text" class="w-full md:w-1/3" placeholder="Buscar por nome, e-mail ou telefone..." />
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-gray-500 font-medium uppercase tracking-wider text-xs">
                        <tr>
                            <th class="px-6 py-3 text-left">Foto / Nome / Apelido</th>
                            <th class="px-6 py-3 text-left">Contato</th>
                            <th class="px-6 py-3 text-left">Instagram</th>
                            <th class="px-6 py-3 text-left">Faturamento</th>
                            <th class="px-6 py-3 text-right">Ações</th>

                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 text-gray-700">
                        @forelse($customers as $customer)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 whitespace-nowrap flex items-center space-x-3">
                                    @if($customer->photo_path)
                                        <img src="{{ Storage::url($customer->photo_path) }}" class="w-9 h-9 rounded-full object-cover border shadow-sm">
                                    @else
                                        <div class="w-9 h-9 rounded-full bg-indigo-100 text-indigo-700 font-black flex items-center justify-center text-xs border shadow-sm uppercase">
                                            {{ substr($customer->name, 0, 2) }}
                                        </div>
                                    @endif

                                    <div>
                                        <button wire:click="edit('{{ $customer->id }}')" class="text-sm font-bold text-indigo-600 hover:text-indigo-900 text-left focus:outline-none hover:underline">
                                            {{ $customer->name }}
                                        </button>
                                        @if($customer->nick)<div class="text-xs text-gray-400">( {{ $customer->nick }} )</div>@endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm">{{ $customer->email }}</div>
                                    <div class="text-xs text-gray-400">{{ $customer->phone ?? 'Sem telefone' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                    {{ $customer->instagram ? '@'.$customer->instagram : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-black text-indigo-700">
                                        R$ {{ number_format($customer->total_spent ?? 0, 2, ',', '.') }}
                                    </div>
                                    <div class="text-xs text-gray-500 font-medium">
                                        {{ $customer->commands_count ?? 0 }} Atendimentos
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right font-medium space-x-3">
                                    <!-- Botão Editar -->
                                    <button wire:click="edit({{ $customer->id }})" class="text-indigo-600 hover:text-indigo-900 transition-colors" title="Editar">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <!-- Divisor vertical discreto -->
                                    <span class="text-gray-300">|</span>

                                    <!-- Botão Excluir -->
                                    <button wire:click="delete({{ $customer->id }})" wire:confirm="Tem certeza que deseja excluir este cliente?" class="text-red-500 hover:text-red-700 transition-colors" title="Excluir">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-gray-500 text-center">Nenhum cliente encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $customers->links() }}
            </div>
        </div>
    </div>

    @if($showModal)
        <div class="fixed inset-0 overflow-y-auto z-40 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500 opacity-75" wire:click="$set('showModal', false)"></div>

            <div class="bg-white rounded-lg shadow-xl transform transition-all w-full max-w-2xl p-6 z-40 max-h-[92vh] overflow-y-auto">

                @if($isEditing)
                    <div class="grid grid-cols-2 gap-4 mb-5 bg-indigo-50/70 p-4 border border-indigo-100 rounded-lg text-xs">
                        <div class="border-r border-indigo-200/60 pr-2 flex items-center space-x-3">
                            @if($currentPhotoUrl)
                                <img src="{{ $currentPhotoUrl }}" class="w-11 h-11 rounded-full object-cover border-2 border-indigo-300 shadow-sm">
                            @else
                                <div class="w-11 h-11 rounded-full bg-indigo-200 text-indigo-800 font-extrabold flex items-center justify-center text-sm border-2 border-indigo-300 uppercase">
                                    {{ substr($name, 0, 2) }}
                                </div>
                            @endif
                            <div>
                                <span class="text-gray-500 uppercase font-semibold block tracking-tight">Total Consumido</span>
                                <span class="text-lg font-black text-indigo-700 block mt-0.5">R$ {{ number_format($totalSpent, 2, ',', '.') }}</span>
                            </div>
                        </div>
                        <div class="pl-2 flex flex-col justify-center">
                            <span class="text-gray-500 uppercase font-semibold block tracking-tight">Ausente há</span>
                            <span class="text-lg font-black text-amber-600 block mt-0.5">{{ $daysSinceLastVisit }}</span>
                        </div>
                    </div>

                    <div class="border-b border-gray-200 mb-4 flex space-x-6 text-xs">
                        <button type="button" wire:click="$set('activeModalTab', 'form')" class="pb-2 font-bold uppercase {{ $activeModalTab === 'form' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-400' }}">
                            📋 Cadastro / Dados
                        </button>
                        <button type="button" wire:click="$set('activeModalTab', 'commands')" class="pb-2 font-bold uppercase {{ $activeModalTab === 'commands' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-400' }}">
                            🧾 Histórico de Comandas
                        </button>
                    </div>
                @else
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Cadastrar Novo Cliente</h3>
                @endif

                @if($activeModalTab === 'form')
                    <form wire:submit="save">
                        <div class="grid grid-cols-1 gap-4 text-sm">

                            <div class="flex items-center space-x-4 bg-gray-50 p-3 rounded-lg border">
                                <div class="shrink-0">
                                    @if ($photo)
                                        <img src="{{ $photo->temporaryUrl() }}" class="h-14 w-14 rounded-full object-cover border border-indigo-500 shadow">
                                    @elseif ($currentPhotoUrl)
                                        <img src="{{ $currentPhotoUrl }}" class="h-14 w-14 rounded-full object-cover border">
                                    @else
                                        <div class="h-14 w-14 rounded-full bg-gray-200 border flex items-center justify-center text-gray-400">📷</div>
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <x-input-label value="Foto de Perfil / Avatar (Opcional)" class="text-xs" />
                                    <input type="file" wire:model="photo" class="mt-1 block w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" accept="image/*" />
                                    <div wire:loading wire:target="photo" class="text-xs text-indigo-600 font-bold mt-1">Carregando imagem...</div>
                                    <x-input-error :messages="$errors->get('photo')" class="mt-1" />
                                </div>
                            </div>

                            <div>
                                <x-input-label value="Nome Completo *" />
                                <x-text-input type="text" class="block mt-1 w-full" wire:model="name" required />
                                <x-input-error :messages="$errors->get('name')" class="mt-1" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <x-input-label value="Apelido" />
                                    <x-text-input type="text" class="block mt-1 w-full" wire:model="nick" />
                                </div>
                                <div>
                                    <x-input-label value="Telefone / WhatsApp" />
                                    <x-text-input type="text" class="block mt-1 w-full" wire:model="phone" placeholder="(00) 00000-0000" />
                                </div>
                            </div>

                            <div>
                                <x-input-label value="E-mail *" />
                                <x-text-input type="email" class="block mt-1 w-full" wire:model="email" required />
                                <x-input-error :messages="$errors->get('email')" class="mt-1" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <x-input-label value="CPF / CNPJ" />
                                    <x-text-input type="text" class="block mt-1 w-full" wire:model="user_document_cpf_cnpj" />
                                </div>
                                <div>
                                    <x-input-label value="Instagram (sem @)" />
                                    <x-text-input type="text" class="block mt-1 w-full" wire:model="instagram" placeholder="megabeauty" />
                                </div>
                            </div>

                            <div>
                                <x-input-label value="Observações Internas (Alergias, preferências, etc.)" />
                                <textarea rows="3" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" wire:model="observations" placeholder="Insira notas sobre o perfil do cliente..."></textarea>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end space-x-2 border-t pt-4">
                            <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 border rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Cancelar</button>
                            <x-primary-button type="submit">{{ $isEditing ? 'Atualizar Dados' : 'Salvar Cliente' }}</x-primary-button>
                        </div>
                    </form>
                @endif

                @if($activeModalTab === 'commands')
                    <div class="space-y-4">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-xs">
                                <thead class="bg-gray-50 text-gray-500 font-semibold uppercase">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Cód / Data</th>
                                        <th class="px-3 py-2 text-right">Serviços</th>
                                        <th class="px-3 py-2 text-right">Produtos</th>
                                        <th class="px-3 py-2 text-right">Dcto</th>
                                        <th class="px-3 py-2 text-right">Total Final</th>
                                        <th class="px-3 py-2 text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-gray-700">
                                    @forelse($customerCommands as $cmd)
                                        <tr wire:click="viewCommand({{ $cmd->id }})" class="hover:bg-indigo-50/50 cursor-pointer transition">
                                            <td class="px-3 py-3">
                                                <span class="font-bold text-indigo-600 hover:underline block">#{{ $cmd->code }}</span>
                                                <small class="text-gray-400">{{ \Carbon\Carbon::parse($cmd->created_at)->format('d/m/Y H:i') }}</small>
                                            </td>
                                            <td class="px-3 py-3 text-right">R$ {{ number_format($cmd->total_services, 2, ',', '.') }}</td>
                                            <td class="px-3 py-3 text-right">R$ {{ number_format($cmd->total_products, 2, ',', '.') }}</td>
                                            <td class="px-3 py-3 text-right text-red-500">- R$ {{ number_format($cmd->discount, 2, ',', '.') }}</td>
                                            <td class="px-3 py-3 text-right font-bold text-gray-900">R$ {{ number_format($cmd->total_amount, 2, ',', '.') }}</td>
                                            <td class="px-3 py-3 text-center">
                                                <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $cmd->status === 'finished' ? 'bg-green-100 text-green-800' : ($cmd->status === 'open' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                                    {{ $cmd->status === 'finished' ? 'Paga' : ($cmd->status === 'open' ? 'Aberta' : 'Cancelada') }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-4 py-6 text-center text-gray-400">Nenhum atendimento ou comanda vinculada a este cliente.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-2 text-xs">
                            {{ $customerCommands->links() }}
                        </div>

                        <div class="flex justify-end border-t pt-4">
                            <button type="button" wire:click="$set('showModal', false)" class="px-4 py-1.5 bg-gray-800 hover:bg-gray-900 text-white rounded text-xs font-semibold">
                                Fechar Ficha
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if($showCommandDetailsModal && $selectedCommandDetails)
        <div class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-900 opacity-60" wire:click="$set('showCommandDetailsModal', false)"></div>

            <div class="bg-white rounded-xl shadow-2xl transform transition-all w-full max-w-lg p-6 z-50 border border-gray-100">
                <div class="flex justify-between items-center pb-3 border-b mb-4">
                    <div>
                        <h4 class="text-base font-black text-gray-900">Comanda #{{ $selectedCommandDetails->code }}</h4>
                        <span class="text-xs text-gray-400">Data: {{ \Carbon\Carbon::parse($selectedCommandDetails->created_at)->format('d/m/Y H:i') }}</span>
                    </div>
                    <span class="px-3 py-1 text-xs font-bold rounded-full {{ $selectedCommandDetails->status === 'finished' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                        {{ $selectedCommandDetails->status === 'finished' ? 'Finalizada / Paga' : 'Aberta' }}
                    </span>
                </div>

                <div class="mb-4">
                    <h5 class="text-xs font-bold text-gray-400 uppercase tracking-tight mb-2">Serviços Prestados</h5>
                    <div class="bg-gray-50 rounded-lg p-3 space-y-2 text-xs">
                        @forelse($selectedCommandDetails->services as $sPivot)
                            <div class="flex justify-between items-start border-b border-gray-200/60 pb-1 last:border-0 last:pb-0">
                                <div>
                                    <span class="font-bold text-gray-900 block">{{ $sPivot->service?->name ?? 'Serviço Deletado' }}</span>
                                    <small class="text-indigo-600 font-medium">Profissional: {{ $sPivot->professional?->name ?? 'Não definido' }}</small>
                                </div>
                                <span class="font-semibold text-gray-800">R$ {{ number_format($sPivot->price, 2, ',', '.') }}</span>
                            </div>
                        @empty
                            <span class="text-gray-400 block text-center py-1">Nenhum serviço nesta comanda.</span>
                        @endforelse
                    </div>
                </div>

                <div class="mb-4">
                    <h5 class="text-xs font-bold text-gray-400 uppercase tracking-tight mb-2">Produtos Adquiridos</h5>
                    <div class="bg-gray-50 rounded-lg p-3 space-y-2 text-xs">
                        @forelse($selectedCommandDetails->products as $pPivot)
                            <div class="flex justify-between items-center border-b border-gray-200/60 pb-1 last:border-0 last:pb-0">
                                <span class="text-gray-900 font-medium">
                                    {{ $pPivot->product?->name ?? 'Produto Deletado' }}
                                    <b class="text-gray-400 text-[11px] ml-1">x{{ $pPivot->quantity }}</b>
                                </span>
                                <span class="font-semibold text-gray-800">R$ {{ number_format($pPivot->price * $pPivot->quantity, 2, ',', '.') }}</span>
                            </div>
                        @empty
                            <span class="text-gray-400 block text-center py-1 text-[11px]">Nenhum produto levado nesta comanda.</span>
                        @endforelse
                    </div>
                </div>

                <div class="border-t pt-3 text-xs space-y-1.5 text-gray-600">
                    <div class="flex justify-between">
                        <span>Subtotal de Serviços:</span>
                        <span>R$ {{ number_format($selectedCommandDetails->total_services, 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Subtotal de Produtos:</span>
                        <span>R$ {{ number_format($selectedCommandDetails->total_products, 2, ',', '.') }}</span>
                    </div>
                    @if($selectedCommandDetails->discount > 0)
                        <div class="flex justify-between text-red-600 font-medium">
                            <span>Desconto Aplicado:</span>
                            <span>(-) R$ {{ number_format($selectedCommandDetails->discount, 2, ',', '.') }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between text-sm font-black text-gray-900 bg-indigo-50 p-2 rounded mt-2">
                        <span>Valor Total Final:</span>
                        <span>R$ {{ number_format($selectedCommandDetails->total_amount, 2, ',', '.') }}</span>
                    </div>
                </div>

                <div class="flex justify-end mt-5 pt-3 border-t">
                    <button type="button" wire:click="$set('showCommandDetailsModal', false)" class="px-4 py-1.5 bg-gray-800 hover:bg-gray-900 text-white rounded text-xs font-bold shadow-sm">
                        Fechar Detalhes
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
