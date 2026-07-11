<?php

use App\Models\Professional;
use App\Models\ProfessionalSchedule;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination, WithFileUploads;

    public string $search = '';
    public bool $showModal = false;
    public bool $isEditing = false;
    public ?string $selectedProfessionalId = null;
    public string $activeTab = 'general'; // general, address, schedule, commission, access

    // PROPRIEDADES DO FORMULÁRIO
    public $photo;
    public ?string $existingPhoto = null;
    public string $name = '';
    public string $nickname = '';
    public string $phone = '';
    public string $email = '';
    public string $profession = '';
    public string $birthday = '';
    public string $cpf = '';
    public string $rg = '';
    public string $cnpj = '';
    public string $notes = '';

    // Novas Flags de Negócio (Iniciando true por padrão)
    public bool $is_active = true;
    public bool $generate_schedule = true;
    public bool $earns_commission = true;

    // Endereço
    public string $cep = '';
    public string $street = '';
    public string $number = '';
    public string $complement = '';
    public string $neighborhood = '';
    public string $city = '';
    public string $state = '';

    // Comissão
    public string $commission_type = 'percentage';
    public float $default_commission = 0.00;
    public string $tax_deduction_rule = 'proportional';
    public string $discount_deduction_rule = 'proportional';
    public bool $deduct_additional_cost = true;
    public string $deduct_consumed_products = 'service';
    public string $consumed_product_price_type = 'professional';

    // Usuário de Acesso
    public bool $create_user_access = false;
    public string $username = '';
    public string $password = '';
    public string $role = 'professional';

    public array $weeklySchedules = [];

    public function mount(): void
    {
        $this->resetScheduleStructure();
    }

    private function resetScheduleStructure(): void
    {
        $days = [
            1 => 'Segunda-Feira', 2 => 'Terça-Feira', 3 => 'Quarta-Feira',
            4 => 'Quinta-Feira', 5 => 'Sexta-Feira', 6 => 'Sábado', 0 => 'Domingo'
        ];

        $this->weeklySchedules = [];
        foreach ($days as $key => $name) {
            $this->weeklySchedules[$key] = [
                'day_name' => $name,
                'is_working' => in_array($key, [1,2,3,4,5]),
                'start_time' => '09:00',
                'end_time' => '18:00',
                'lunch_start' => '12:00',
                'lunch_end' => '13:00'
            ];
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->resetScheduleStructure();
        $this->isEditing = false;
        $this->activeTab = 'general';
        $this->showModal = true;
    }

    public function edit(string $id): void
    {
        $this->resetForm();
        $this->activeTab = 'general';
        $this->isEditing = true;

        $p = Professional::where('tenant_id', auth()->user()->tenant_id)->with('schedules')->findOrFail($id);
        $this->selectedProfessionalId = $p->id;

        $this->existingPhoto = $p->photo ?? null;
        $this->name = $p->name;
        $this->nickname = $p->nickname ?? '';
        $this->phone = $p->phone;
        $this->email = $p->email ?? '';
        $this->profession = $p->profession ?? '';
        $this->birthday = $p->birthday ? $p->birthday->format('Y-m-d') : '';
        $this->cpf = $p->cpf ?? '';
        $this->rg = $p->rg ?? '';
        $this->cnpj = $p->cnpj ?? '';
        $this->notes = $p->notes ?? '';

        // Atribuição das novas flags vindo do banco
        $this->is_active = (bool) $p->is_active;
        $this->generate_schedule = (bool) $p->generate_schedule;
        $this->earns_commission = (bool) $p->earns_commission;

        $this->cep = $p->cep ?? '';
        $this->street = $p->street ?? '';
        $this->number = $p->number ?? '';
        $this->complement = $p->complement ?? '';
        $this->neighborhood = $p->neighborhood ?? '';
        $this->city = $p->city ?? '';
        $this->state = $p->state ?? '';

        $this->commission_type = $p->commission_type;
        $this->default_commission = (float) $p->default_commission;
        $this->tax_deduction_rule = $p->tax_deduction_rule;
        $this->discount_deduction_rule = $p->discount_deduction_rule;
        $this->deduct_additional_cost = (bool) $p->deduct_additional_cost;
        $this->deduct_consumed_products = $p->deduct_consumed_products;
        $this->consumed_product_price_type = $p->consumed_product_price_type;

        if($p->schedules->count() > 0) {
            foreach($p->schedules as $sched) {
                $this->weeklySchedules[$sched->day_of_week] = [
                    'day_name' => $this->weeklySchedules[$sched->day_of_week]['day_name'],
                    'is_working' => (bool)$sched->is_working,
                    'start_time' => $sched->start_time ? substr($sched->start_time, 0, 5) : '09:00',
                    'end_time' => $sched->end_time ? substr($sched->end_time, 0, 5) : '18:00',
                    'lunch_start' => $sched->lunch_start ? substr($sched->lunch_start, 0, 5) : '12:00',
                    'lunch_end' => $sched->lunch_end ? substr($sched->lunch_end, 0, 5) : '13:00'
                ];
            }
        }

        if ($p->user_id) {
            $user = User::find($p->user_id);
            if ($user) {
                $this->create_user_access = true;
                $this->username = $user->email;
                $this->role = $user->role ?? 'professional';
            }
        }

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'username' => ['nullable', 'string', 'email', 'max:255'],
            'password' => $this->isEditing ? ['nullable', 'string', 'min:6'] : ($this->create_user_access ? ['required', 'string', 'min:6'] : ['nullable']),
        ]);

        $tenantId = auth()->user()->tenant_id;
        $userId = null;

        if ($this->create_user_access && !empty($this->username)) {
            if ($this->isEditing && $this->selectedProfessionalId) {
                $p = Professional::find($this->selectedProfessionalId);
                if ($p && $p->user_id) {
                    $user = User::find($p->user_id);
                    $user->update([
                        'name' => $this->name,
                        'email' => $this->username,
                        'password' => $this->password ? Hash::make($this->password) : $user->password
                    ]);
                    $userId = $user->id;
                }
            }

            if (!$userId) {
                $exists = User::where('email', $this->username)->exists();
                if(!$exists) {
                    $user = User::create([
                        'name' => $this->name,
                        'email' => $this->username,
                        'tenant_id' => $tenantId,
                        'password' => Hash::make($this->password ?? '12345678'),
                    ]);
                    $userId = $user->id;
                }
            }
        }

        $photoPath = $this->existingPhoto;
        if ($this->photo) {
            $photoPath = $this->photo->store('professionals', 'public');
        }

        $data = [
            'name' => $this->name,
            'nickname' => $this->nickname,
            'photo' => $photoPath,
            'phone' => preg_replace('/[^0-9]/', '', $this->phone),
            'email' => $this->email,
            'profession' => $this->profession,
            'birthday' => $this->birthday ? $this->birthday : null,
            'cpf' => preg_replace('/[^0-9]/', '', $this->cpf),
            'rg' => preg_replace('/[^0-9]/', '', $this->rg),
            'cnpj' => preg_replace('/[^0-9]/', '', $this->cnpj),
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'generate_schedule' => $this->generate_schedule,
            'earns_commission' => $this->earns_commission,
            'cep' => preg_replace('/[^0-9]/', '', $this->cep),
            'street' => $this->street,
            'number' => $this->number,
            'complement' => $this->complement,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'commission_type' => $this->commission_type,
            'default_commission' => $this->default_commission,
            'tax_deduction_rule' => $this->tax_deduction_rule,
            'discount_deduction_rule' => $this->discount_deduction_rule,
            'deduct_additional_cost' => $this->deduct_additional_cost,
            'deduct_consumed_products' => $this->deduct_consumed_products,
            'consumed_product_price_type' => $this->consumed_product_price_type,
            'user_id' => $userId
        ];

        if ($this->isEditing) {
            $professional = Professional::where('tenant_id', $tenantId)->findOrFail($this->selectedProfessionalId);
            $professional->update($data);
        } else {
            $data['tenant_id'] = $tenantId;
            $professional = Professional::create($data);
        }

        // Só gera tabelas de expediente caso o profissional necessite de agenda
        ProfessionalSchedule::where('professional_id', $professional->id)->delete();
        if ($this->generate_schedule) {
            foreach ($this->weeklySchedules as $dayOfWeek => $sched) {
                ProfessionalSchedule::create([
                    'professional_id' => $professional->id,
                    'day_of_week' => $dayOfWeek,
                    'is_working' => $sched['is_working'],
                    'start_time' => $sched['is_working'] ? $sched['start_time'] : null,
                    'end_time' => $sched['is_working'] ? $sched['end_time'] : null,
                    'lunch_start' => $sched['is_working'] ? $sched['lunch_start'] : null,
                    'lunch_end' => $sched['is_working'] ? $sched['lunch_end'] : null,
                ]);
            }
        }

        session()->flash('message', $this->isEditing ? 'Profissional atualizado com sucesso!' : 'Profissional cadastrado com sucesso!');
        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(string $id): void
    {
        $p = Professional::where('tenant_id', auth()->user()->tenant_id)->findOrFail($id);
        $p->delete();
        session()->flash('message', 'Profissional removido com sucesso!');
    }

    private function resetForm(): void
    {
        $this->reset([
            'photo', 'existingPhoto', 'name', 'nickname', 'phone', 'email', 'profession', 'birthday', 'cpf', 'rg', 'cnpj', 'notes',
            'cep', 'street', 'number', 'complement', 'neighborhood', 'city', 'state',
            'commission_type', 'default_commission', 'tax_deduction_rule', 'discount_deduction_rule',
            'deduct_additional_cost', 'deduct_consumed_products', 'consumed_product_price_type',
            'create_user_access', 'username', 'password', 'selectedProfessionalId'
        ]);
        $this->is_active = true;
        $this->generate_schedule = true;
        $this->earns_commission = true;
    }

    public function with(): array
    {
        return [
            'professionals' => Professional::where('tenant_id', auth()->user()->tenant_id)
                ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('profession', 'like', "%{$this->search}%"))
                ->orderBy('name', 'asc')
                ->paginate(10)
        ];
    }
}; ?>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold text-gray-800 leading-tight">Profissionais e Colaboradores</h2>
            <button wire:click="openCreateModal" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm rounded-md shadow-sm">
                + Novo Profissional
            </button>
        </div>

        @if (session()->has('message'))
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded shadow-sm">
                {{ session('message') }}
            </div>
        @endif

        <div class="bg-white shadow sm:rounded-lg p-6">
            <div class="mb-4">
                <x-text-input wire:model.live="search" type="text" class="w-full md:w-1/3" placeholder="Buscar por nome ou profissão..." />
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Foto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Especialidade / Agenda</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Comissão</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($professionals as $p)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($p->photo)
                                        <img src="{{ asset('storage/' . $p->photo) }}" class="h-10 w-10 rounded-full object-cover border">
                                    @else
                                        <div class="h-10 w-10 rounded-full bg-indigo-50 border text-indigo-600 flex items-center justify-center font-bold text-xs">
                                            {{ strtoupper(substr($p->name, 0, 2)) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $p->name }} @if($p->nickname)<span class="text-xs text-gray-400">({{ $p->nickname }})</span>@endif</td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $p->profession ?? 'Geral' }}
                                    <div class="text-xs text-gray-400">{{ $p->generate_schedule ? '✓ Tem Agenda' : '❌ Sem Agenda' }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $p->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $p->is_active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    @if($p->earns_commission)
                                        {{ $p->default_commission }} {{ $p->commission_type === 'percentage' ? '%' : 'R$' }}
                                    @else
                                        <span class="text-xs text-gray-400 italic">Fixo Sem comissão</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm font-medium space-x-2">
                                    <button wire:click="edit({{ $p->id }})" class="text-indigo-600 hover:text-indigo-900">Editar</button>
                                    <button wire:click="delete({{ $p->id }})" wire:confirm="Excluir este profissional removerá sua agenda vinculada. Deseja continuar?" class="text-red-600 hover:text-red-900">Excluir</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-sm text-gray-500 text-center">Nenhum profissional cadastrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $professionals->links() }}</div>
        </div>
    </div>

    @if($showModal)
        <div class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500 opacity-75" wire:click="$set('showModal', false)"></div>

            <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all w-full max-w-4xl p-6 z-50 max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-medium text-gray-900 mb-4">{{ $isEditing ? 'Editar Cadastro de Profissional' : 'Cadastrar Novo Profissional' }}</h3>

                <div class="border-b border-gray-200 mb-6 flex space-x-4 overflow-x-auto text-sm">
                    <button type="button" wire:click="$set('activeTab', 'general')" class="pb-2 px-1 border-b-2 {{ $activeTab === 'general' ? 'border-indigo-500 text-indigo-600 font-medium' : 'border-transparent text-gray-500' }}">Dados Gerais</button>
                    <button type="button" wire:click="$set('activeTab', 'address')" class="pb-2 px-1 border-b-2 {{ $activeTab === 'address' ? 'border-indigo-500 text-indigo-600 font-medium' : 'border-transparent text-gray-500' }}">Endereço</button>
                    @if($generate_schedule)
                        <button type="button" wire:click="$set('activeTab', 'schedule')" class="pb-2 px-1 border-b-2 {{ $activeTab === 'schedule' ? 'border-indigo-500 text-indigo-600 font-medium' : 'border-transparent text-gray-500' }}">Expediente / Agenda</button>
                    @endif
                    @if($earns_commission)
                        <button type="button" wire:click="$set('activeTab', 'commission')" class="pb-2 px-1 border-b-2 {{ $activeTab === 'commission' ? 'border-indigo-500 text-indigo-600 font-medium' : 'border-transparent text-gray-500' }}">Configurações de Comissão</button>
                    @endif
                    <button type="button" wire:click="$set('activeTab', 'access')" class="pb-2 px-1 border-b-2 {{ $activeTab === 'access' ? 'border-indigo-500 text-indigo-600 font-medium' : 'border-transparent text-gray-500' }}">Permissões e Acesso</button>
                </div>

                <form wire:submit="save">
                    <div class="{{ $activeTab === 'general' ? 'block' : 'hidden' }} space-y-4">

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-gray-50 p-4 rounded-lg border items-center">
                            <div class="flex items-center space-x-4">
                                <div class="shrink-0">
                                    @if ($photo)
                                        <img src="{{ $photo->temporaryUrl() }}" class="h-16 w-16 object-cover rounded-full border-2 border-indigo-500">
                                    @elseif ($existingPhoto)
                                        <img src="{{ asset('storage/' . $existingPhoto) }}" class="h-16 w-16 object-cover rounded-full border-2 border-gray-300">
                                    @else
                                        <div class="h-16 w-16 rounded-full bg-gray-200 border flex items-center justify-center text-gray-400 text-xs text-center font-medium">Sem foto</div>
                                    @endif
                                </div>
                                <label class="block">
                                    <input type="file" wire:model="photo" class="block w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"/>
                                </label>
                            </div>

                            <div class="md:col-span-2 flex flex-wrap gap-6 justify-start">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model.live="is_active" class="sr-only peer">
                                    <div class="relative w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-green-600"></div>
                                    <span class="ms-2 text-xs font-semibold text-gray-700">Profissional Ativo</span>
                                </label>

                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model.live="generate_schedule" class="sr-only peer">
                                    <div class="relative w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                                    <span class="ms-2 text-xs font-semibold text-gray-700">Gerar Grade de Agenda</span>
                                </label>

                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" wire:model.live="earns_commission" class="sr-only peer">
                                    <div class="relative w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                                    <span class="ms-2 text-xs font-semibold text-gray-700">Recebe Comissão</span>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <x-input-label value="Nome Completo *" />
                                <x-text-input type="text" wire:model="name" class="w-full mt-1" required />
                            </div>
                            <div>
                                <x-input-label value="Apelido / Nome Comercial" />
                                <x-text-input type="text" wire:model="nickname" class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Profissão / Especialidade" />
                                <x-text-input type="text" wire:model="profession" class="w-full mt-1" placeholder="Ex: Manicure, Cabeleireiro" />
                            </div>
                            <div>
                                <x-input-label value="Celular / WhatsApp *" />
                                <x-text-input type="text" wire:model="phone" class="w-full mt-1" x-data x-on:input="$el.value = $el.value.replace(/\D/g, '').replace(/^(\d{2})(\d)/g, '($1) $2').replace(/(\d)(\d{4})$/, '$1-$2')" maxlength="15" placeholder="(11) 99999-9999" required />
                            </div>
                            <div>
                                <x-input-label value="E-mail" />
                                <x-text-input type="email" wire:model="email" class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Data de Aniversário" />
                                <x-text-input type="date" wire:model="birthday" class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label value="CPF" />
                                <x-text-input type="text" wire:model="cpf" class="w-full mt-1" x-data x-on:input="$el.value = $el.value.replace(/\D/g, '').replace(/(\d{3})(\d{3})(\d{3})(\d{2})/g, '$1.$2.$3-$4')" maxlength="14" placeholder="000.000.000-00" />
                            </div>
                            <div>
                                <x-input-label value="RG" />
                                <x-text-input type="text" wire:model="rg" class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label value="CNPJ" />
                                <x-text-input type="text" wire:model="cnpj" class="w-full mt-1" x-data x-on:input="$el.value = $el.value.replace(/\D/g, '').replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/g, '$1.$2.$3/$4-$5')" maxlength="18" placeholder="00.000.000/0000-00" />
                            </div>
                            <div class="md:col-span-3">
                                <x-input-label value="Observações Internas" />
                                <textarea wire:model="notes" rows="2" class="w-full mt-1 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="{{ $activeTab === 'address' ? 'block' : 'hidden' }}" x-data="{ async buscarCep() { let cepClean = @this.get('cep').replace(/\D/g, ''); if (cepClean.length === 8) { try { let res = await fetch(`https://viacep.com.br/ws/${cepClean}/json/`); let d = await res.json(); if (!d.erro) { @this.set('street', d.logradouro); @this.set('neighborhood', d.bairro); @this.set('city', d.localidade); @this.set('state', d.uf); document.getElementById('num').focus(); } } catch (e){} } } }">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <x-input-label value="CEP" />
                                <x-text-input type="text" wire:model="cep" class="w-full mt-1" x-on:input="$el.value = $el.value.replace(/\D/g, '').replace(/^(\d{5})(\d)/, '$1-$2'); buscarCep();" maxlength="9" placeholder="00000-000" />
                            </div>
                            <div class="md:col-span-2">
                                <x-input-label value="Logradouro / Rua" />
                                <x-text-input type="text" wire:model="street" class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Número" />
                                <x-text-input type="text" id="num" wire:model="number" class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Complemento" />
                                <x-text-input type="text" wire:model="complement" class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Bairro" />
                                <x-text-input type="text" wire:model="neighborhood" class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Cidade" />
                                <x-text-input type="text" wire:model="city" class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Estado (UF)" />
                                <x-text-input type="text" wire:model="state" class="w-full mt-1" maxlength="2" placeholder="EX: SP" />
                            </div>
                        </div>
                    </div>

                    @if($generate_schedule)
                        <div class="{{ $activeTab === 'schedule' ? 'block' : 'hidden' }} space-y-3">
                            @foreach($weeklySchedules as $day => $data)
                                <div class="flex flex-wrap items-center bg-gray-50 p-2 rounded-md gap-4 border border-gray-100">
                                    <label class="inline-flex items-center w-40 font-medium text-sm text-gray-700">
                                        <input type="checkbox" wire:model="weeklySchedules.{{ $day }}.is_working" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 mr-2">
                                        {{ $data['day_name'] }}
                                    </label>

                                    @if($weeklySchedules[$day]['is_working'])
                                        <div class="flex items-center space-x-2 text-sm">
                                            <span class="text-xs text-gray-400">Entrada:</span>
                                            <input type="time" wire:model="weeklySchedules.{{ $day }}.start_time" class="border-gray-300 rounded p-1 text-xs">
                                            <span class="text-xs text-gray-400">Saída:</span>
                                            <input type="time" wire:model="weeklySchedules.{{ $day }}.end_time" class="border-gray-300 rounded p-1 text-xs">
                                        </div>
                                        <div class="flex items-center space-x-2 text-sm border-l pl-4 border-gray-300">
                                            <span class="text-xs text-gray-400">Almoço Início:</span>
                                            <input type="time" wire:model="weeklySchedules.{{ $day }}.lunch_start" class="border-gray-300 rounded p-1 text-xs">
                                            <span class="text-xs text-gray-400">Fim:</span>
                                            <input type="time" wire:model="weeklySchedules.{{ $day }}.lunch_end" class="border-gray-300 rounded p-1 text-xs">
                                        </div>
                                    @else
                                        <span class="text-xs text-red-500 italic">Folga / Sem atendimentos</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($earns_commission)
                        <div class="{{ $activeTab === 'commission' ? 'block' : 'hidden' }} space-y-6">
                            <div class="bg-indigo-50/50 p-4 rounded-md border border-indigo-100">
                                <h4 class="font-semibold text-sm text-indigo-900 mb-3">Financeiro e Valores (Base)</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <x-input-label value="Tipo de Comissão" />
                                        <select wire:model="commission_type" class="w-full mt-1 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                            <option value="percentage">Porcentagem (%)</option>
                                            <option value="fixed">Valor Fixo (R$)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <x-input-label value="Comissão Padrão" />
                                        <x-text-input type="number" step="0.01" wire:model="default_commission" class="w-full mt-1" />
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                                <div class="space-y-4">
                                    <div>
                                        <span class="block font-medium text-sm text-gray-700">Taxas de Cartão/Administrativas</span>
                                        <div class="mt-2 space-y-1 text-sm">
                                            <label class="flex items-center"><input type="radio" value="proportional" wire:model="tax_deduction_rule" class="mr-2 text-indigo-600"> Proporcional ao comissionamento</label>
                                            <label class="flex items-center"><input type="radio" value="tenant_100" wire:model="tax_deduction_rule" class="mr-2 text-indigo-600"> Estabelecimento arca com 100%</label>
                                            <label class="flex items-center"><input type="radio" value="professional_100" wire:model="tax_deduction_rule" class="mr-2 text-indigo-600"> Profissional arca com 100%</label>
                                        </div>
                                    </div>

                                    <div>
                                        <span class="block font-medium text-sm text-gray-700">Descontos e Cupons</span>
                                        <div class="mt-2 space-y-1 text-sm">
                                            <label class="flex items-center"><input type="radio" value="proportional" wire:model="discount_deduction_rule" class="mr-2 text-indigo-600"> Proporcional ao comissionamento</label>
                                            <label class="flex items-center"><input type="radio" value="tenant_100" wire:model="discount_deduction_rule" class="mr-2 text-indigo-600"> Estabelecimento arca com 100%</label>
                                            <label class="flex items-center"><input type="radio" value="professional_100" wire:model="discount_deduction_rule" class="mr-2 text-indigo-600"> Profissional arca com 100%</label>
                                        </div>
                                    </div>

                                    <div>
                                        <span class="block font-medium text-sm text-gray-700">Custo Adicional dos Serviços</span>
                                        <div class="mt-2 space-y-1 text-sm">
                                            <label class="flex items-center"><input type="radio" :value="true" wire:model="deduct_additional_cost" class="mr-2 text-indigo-600"> Desconta das bases antes da comissão</label>
                                            <label class="flex items-center"><input type="radio" :value="false" wire:model="deduct_additional_cost" class="mr-2 text-indigo-600"> Não desconta</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div>
                                        <span class="block font-medium text-sm text-gray-700">Descontar Produtos Consumidos (Insumos)</span>
                                        <div class="mt-2 space-y-1 text-sm">
                                            <label class="flex items-center"><input type="radio" value="comission" wire:model="deduct_consumed_products" class="mr-2 text-indigo-600"> Direto da Comissão do Profissional</label>
                                            <label class="flex items-center"><input type="radio" value="service" wire:model="deduct_consumed_products" class="mr-2 text-indigo-600"> Do valor Bruto do Serviço</label>
                                        </div>
                                    </div>

                                    <div>
                                        <span class="block font-medium text-sm text-gray-700">Preço Base dos Produtos para Desconto</span>
                                        <div class="mt-2 space-y-1 text-sm">
                                            <label class="flex items-center"><input type="radio" value="cost" wire:model="consumed_product_price_type" class="mr-2 text-indigo-600"> Preço de Custo</label>
                                            <label class="flex items-center"><input type="radio" value="sale" wire:model="consumed_product_price_type" class="mr-2 text-indigo-600"> Preço de Venda</label>
                                            <label class="flex items-center"><input type="radio" value="professional" wire:model="consumed_product_price_type" class="mr-2 text-indigo-600"> Preço Definido para Profissional</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="{{ $activeTab === 'access' ? 'block' : 'hidden' }} space-y-4">
                        <label class="flex items-center font-medium text-sm text-gray-700">
                            <input type="checkbox" wire:model.live="create_user_access" class="rounded border-gray-300 text-indigo-600 shadow-sm mr-2">
                            Liberar usuário e senha para este profissional acessar o sistema
                        </label>

                        @if($create_user_access)
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-gray-50 rounded-md border border-gray-200 mt-2">
                                <div>
                                    <x-input-label value="Login / E-mail de Acesso *" />
                                    <x-text-input type="email" wire:model="username" class="w-full mt-1" placeholder="Ex: nome@seuapp.com" />
                                </div>
                                <div>
                                    <x-input-label value="Senha de Acesso *" />
                                    <x-text-input type="password" wire:model="password" class="w-full mt-1" placeholder="{{ $isEditing ? 'Preencha apenas se quiser alterar' : 'Senha inicial' }}" />
                                </div>
                                <div>
                                    <x-input-label value="Nível de Permissão" />
                                    <select wire:model="role" class="w-full mt-1 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                        <option value="professional">Profissional (Apenas Agenda Própria)</option>
                                        <option value="admin">Administrador Geral</option>
                                    </select>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="mt-6 flex justify-end space-x-3 border-t pt-4">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Cancelar
                        </button>
                        <x-primary-button type="submit">
                            Salvar Profissional
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
