<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public string $activeTab = 'company';

    // Dados da Empresa
    public string $company_name = '';
    public string $company_type = 'pj'; // Adicionado
    public string $document_cpf_cnpj = '';
    public string $rg = '';
    public $logo;
    public ?string $currentLogo = null;

    // Endereço
    public string $postal_code = '';
    public string $address = '';
    public string $number = '';
    public string $complement = '';
    public string $district = '';
    public string $city = '';
    public string $state = '';

    // Dados do Perfil do Gestor
    public string $user_name = '';
    public string $user_email = '';
    public string $user_phone = '';
    public $avatar;
    public ?string $currentAvatar = null;

    public function mount(): void
    {
        $tenant = auth()->user()->tenant;
        $user = auth()->user();

        $this->company_name = $tenant->name;
        $this->company_type = $tenant->company_type ?? 'pj'; // Carrega do banco
        $this->document_cpf_cnpj = $tenant->document_cpf_cnpj ?? '';
        $this->rg = $tenant->rg ?? '';
        $this->currentLogo = $tenant->logo;

        $this->postal_code = $tenant->postal_code ?? '';
        $this->address = $tenant->address ?? '';
        $this->number = $tenant->number ?? '';
        $this->complement = $tenant->complement ?? '';
        $this->district = $tenant->district ?? '';
        $this->city = $tenant->city ?? '';
        $this->state = $tenant->state ?? '';

        $this->user_name = $user->name;
        $this->user_email = $user->email;
        $this->user_phone = $user->phone ?? '';
        $this->currentAvatar = $user->avatar;
    }

    public function updatedPostalCode(string $value): void
    {
        $cep = preg_replace('/[^0-9]/', '', $value);
        if (strlen($cep) === 8) {
            $response = Http::get("https://viacep.com.br/ws/{$cep}/json/");
            if ($response->successful() && !isset($response->json()['erro'])) {
                $data = $response->json();
                $this->address = $data['logradouro'] ?? '';
                $this->district = $data['bairro'] ?? '';
                $this->city = $data['localidade'] ?? '';
                $this->state = $data['uf'] ?? '';
            }
        }
    }

    public function saveCompany(): void
    {
        $tenant = auth()->user()->tenant;

        $this->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_type' => ['required', 'in:pf,pj'],
            'document_cpf_cnpj' => ['required', 'string', 'max:255', 'unique:tenants,document_cpf_cnpj,' . $tenant->id],
            'rg' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:1024'],
        ]);

        $logoPath = $tenant->logo;
        if ($this->logo) {
            if ($tenant->logo) { Storage::disk('public')->delete($tenant->logo); }
            $logoPath = $this->logo->store('logos', 'public');
        }

        $tenant->update([
            'name' => $this->company_name,
            'company_type' => $this->company_type,
            'document_cpf_cnpj' => preg_replace('/[^0-9]/', '', $this->document_cpf_cnpj),
            'rg' => $this->rg,
            'logo' => $logoPath
        ]);

        $this->currentLogo = $logoPath;
        session()->flash('message', 'Dados da empresa salvos com sucesso!');
    }

    public function saveAddress(): void
    {
        $tenant = auth()->user()->tenant;

        $this->validate([
            'postal_code' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'number' => ['required', 'string', 'max:255'],
            'complement' => ['nullable', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'size:2'],
        ]);

        $tenant->update([
            'postal_code' => preg_replace('/[^0-9]/', '', $this->postal_code),
            'address' => $this->address,
            'number' => $this->number,
            'complement' => $this->complement,
            'district' => $this->district,
            'city' => $this->city,
            'state' => strtoupper($this->state),
        ]);

        session()->flash('message', 'Endereço atualizado com sucesso!');
    }

    public function saveProfile(): void
    {
        $user = auth()->user();

        $this->validate([
            'user_name' => ['required', 'string', 'max:255'],
            'user_email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'user_phone' => ['required', 'string', 'min:14', 'max:15'],
            'avatar' => ['nullable', 'image', 'max:1024'],
        ]);

        $avatarPath = $user->avatar;
        if ($this->avatar) {
            if ($user->avatar) { Storage::disk('public')->delete($user->avatar); }
            $avatarPath = $this->avatar->store('avatars', 'public');
        }

        $user->update([
            'name' => $this->user_name,
            'email' => $this->user_email,
            'phone' => preg_replace('/[^0-9]/', '', $this->user_phone),
            'avatar' => $avatarPath
        ]);

        $this->currentAvatar = $avatarPath;
        session()->flash('message', 'Seu perfil foi atualizado com sucesso!');
    }
}; ?>

<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <h2 class="text-2xl font-semibold text-gray-800 mb-6">Configurações do Sistema</h2>

        @if (session()->has('message'))
            <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded shadow-sm">
                {{ session('message') }}
            </div>
        @endif

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg flex flex-col md:flex-row">

            <div class="w-full md:w-1/4 bg-gray-50 p-4 border-r border-gray-200 space-y-2">
                <button wire:click="$set('activeTab', 'company')" class="w-full text-left px-3 py-2 rounded-md font-medium text-sm {{ $activeTab === 'company' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                    Minha Empresa
                </button>
                <button wire:click="$set('activeTab', 'address')" class="w-full text-left px-3 py-2 rounded-md font-medium text-sm {{ $activeTab === 'address' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                    Endereço/Sede
                </button>
                <button wire:click="$set('activeTab', 'profile')" class="w-full text-left px-3 py-2 rounded-md font-medium text-sm {{ $activeTab === 'profile' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100' }}">
                    Meu Perfil
                </button>
            </div>

            <div class="w-full md:w-3/4 p-6">

                @if($activeTab === 'company')
                    <form wire:submit="saveCompany" class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 border-b pb-2">Informações do Estabelecimento</h3>

                        <div class="flex items-center space-x-6 py-2">
                            <div class="shrink-0">
                                @if($logo)
                                    <img class="h-16 w-16 object-cover rounded-md" src="{{ $logo->temporaryUrl() }}">
                                @elseif($currentLogo)
                                    <img class="h-16 w-16 object-cover rounded-md" src="{{ asset('storage/' . $currentLogo) }}">
                                @else
                                    <div class="h-16 w-16 bg-gray-200 rounded-md flex items-center justify-center text-xs text-gray-500">Sem Logo</div>
                                @endif
                            </div>
                            <label class="block">
                                <span class="sr-only">Escolher Logo</span>
                                <input type="file" wire:model="logo" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"/>
                                <x-input-error :messages="$errors->get('logo')" class="mt-2" />
                            </label>
                        </div>

                        <div>
                            <x-input-label value="Nome Fantasia *" />
                            <x-text-input wire:model="company_name" type="text" class="block mt-1 w-full" required />
                        </div>

                        <!-- Tipo de Empresa no Painel -->
                        <div>
                            <x-input-label value="Tipo de Estabelecimento *" />
                            <div class="flex items-center space-x-6 mt-2">
                                <label class="inline-flex items-center">
                                    <input type="radio" wire:model.live="company_type" value="pj" class="text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                    <span class="ms-2 text-sm text-gray-600">Pessoa Jurídica</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" wire:model.live="company_type" value="pf" class="text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                    <span class="ms-2 text-sm text-gray-600">Pessoa Física</span>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label value="{{ $company_type === 'pj' ? 'CNPJ *' : 'CPF *' }}" />
                                <x-text-input wire:model="document_cpf_cnpj" type="text" class="block mt-1 w-full" x-data x-on:input="$el.value = @this.company_type === 'pf' ? $el.value.replace(/\D/g, '').replace(/(\d{3})(\d{3})(\d{3})(\d{2})/g, '$1.$2.$3-$4') : $el.value.replace(/\D/g, '').replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/g, '$1.$2.$3/$4-$5')" maxlength="{{ $company_type === 'pf' ? '14' : '18' }}" required />
                            </div>
                            <div>
                                <x-input-label value="{{ $company_type === 'pj' ? 'Inscrição Estadual' : 'RG Geral' }}" />
                                <x-text-input wire:model="rg" type="text" class="block mt-1 w-full" />
                            </div>
                        </div>

                        <x-primary-button type="submit">Salvar Alterações</x-primary-button>
                    </form>
                @endif

                @if($activeTab === 'address')
                    <form wire:submit="saveAddress" class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 border-b pb-2">Localização</h3>

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <x-input-label value="CEP *" />
                                <x-text-input wire:model.live="postal_code" type="text" class="block mt-1 w-full" x-data x-on:input="$el.value = $el.value.replace(/\D/g, '').replace(/^(\d{5})(\d)/, '$1-$2')" maxlength="9" required />
                            </div>
                            <div class="col-span-2">
                                <x-input-label value="Rua *" />
                                <x-text-input wire:model="address" type="text" class="block mt-1 w-full" required />
                            </div>
                            <div>
                                <x-input-label value="Número *" />
                                <x-text-input wire:model="number" type="text" class="block mt-1 w-full" required />
                            </div>
                            <div>
                                <x-input-label value="Complemento" />
                                <x-text-input wire:model="complement" type="text" class="block mt-1 w-full" />
                            </div>
                            <div>
                                <x-input-label value="Bairro *" />
                                <x-text-input wire:model="district" type="text" class="block mt-1 w-full" required />
                            </div>
                            <div class="col-span-2">
                                <x-input-label value="Cidade *" />
                                <x-text-input wire:model="city" type="text" class="block mt-1 w-full" required />
                            </div>
                            <div>
                                <x-input-label value="Estado (UF) *" />
                                <x-text-input wire:model="state" type="text" class="block mt-1 w-full" maxlength="2" required />
                            </div>
                        </div>

                        <x-primary-button type="submit">Salvar Endereço</x-primary-button>
                    </form>
                @endif

                @if($activeTab === 'profile')
                    <form wire:submit="saveProfile" class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 border-b pb-2">Dados do Administrador</h3>

                        <div class="flex items-center space-x-6 py-2">
                            <div class="shrink-0">
                                @if($avatar)
                                    <img class="h-16 w-16 object-cover rounded-full" src="{{ $avatar->temporaryUrl() }}">
                                @elseif($currentAvatar)
                                    <img class="h-16 w-16 object-cover rounded-full" src="{{ asset('storage/' . $currentAvatar) }}">
                                @else
                                    <div class="h-16 w-16 bg-gray-200 rounded-full flex items-center justify-center text-xs text-gray-500">Sem Foto</div>
                                @endif
                            </div>
                            <label class="block">
                                <span class="sr-only">Escolher Avatar</span>
                                <input type="file" wire:model="avatar" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"/>
                                <x-input-error :messages="$errors->get('avatar')" class="mt-2" />
                            </label>
                        </div>

                        <div>
                            <x-input-label value="Seu Nome Completo *" />
                            <x-text-input wire:model="user_name" type="text" class="block mt-1 w-full" required />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label value="Celular / WhatsApp *" />
                                <x-text-input wire:model="user_phone" type="text" class="block mt-1 w-full" x-data x-on:input="$el.value = $el.value.replace(/\D/g, '').replace(/^(\d{2})(\d)/g, '($1) $2').replace(/(\d)(\d{4})$/, '$1-$2')" maxlength="15" required />
                            </div>
                            <div>
                                <x-input-label value="E-mail de Login *" />
                                <x-text-input wire:model="user_email" type="email" class="block mt-1 w-full" required />
                            </div>
                        </div>

                        <x-primary-button type="submit">Salvar Meu Perfil</x-primary-button>
                    </form>
                @endif

            </div>
        </div>
    </div>
</div>
