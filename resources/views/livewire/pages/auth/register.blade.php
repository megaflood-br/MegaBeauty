<?php

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    // Controle de Etapa
    public int $currentStep = 1;

    // Dados da Empresa (Tenant)
    public string $company_name = '';
    public string $company_type = 'pj'; // Padrão Pessoa Jurídica
    public string $document_cpf_cnpj = '';
    public string $rg = '';
    public string $postal_code = '';
    public string $address = '';
    public string $number = '';
    public string $complement = '';
    public string $district = '';
    public string $city = '';
    public string $state = '';

    // Dados do Usuário (Admin)
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $password_confirmation = '';

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

    public function nextStep(): void
    {
        if ($this->currentStep === 1) {
            $this->validate([
                'company_name' => ['required', 'string', 'max:255'],
                'company_type' => ['required', 'in:pf,pj'],
                'document_cpf_cnpj' => ['required', 'string', 'max:255', 'unique:tenants,document_cpf_cnpj'],
                'rg' => ['nullable', 'string', 'max:255'],
            ]);
            $this->currentStep = 2;
        } elseif ($this->currentStep === 2) {
            $this->validate([
                'postal_code' => ['required', 'string', 'max:255'],
                'address' => ['required', 'string', 'max:255'],
                'number' => ['required', 'string', 'max:255'],
                'complement' => ['nullable', 'string', 'max:255'],
                'district' => ['required', 'string', 'max:255'],
                'city' => ['required', 'string', 'max:255'],
                'state' => ['required', 'string', 'size:2'],
            ]);
            $this->currentStep = 3;
        }
    }

    public function previousStep(): void
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }

    public function register(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'min:14', 'max:15'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $tenant = Tenant::create([
            'name' => $this->company_name,
            'slug' => Str::slug($this->company_name),
            'status' => 'active',
            'company_type' => $this->company_type,
            'document_cpf_cnpj' => preg_replace('/[^0-9]/', '', $this->document_cpf_cnpj),
            'rg' => preg_replace('/[^0-9]/', '', $this->rg),
            'postal_code' => preg_replace('/[^0-9]/', '', $this->postal_code),
            'address' => $this->address,
            'number' => $this->number,
            'complement' => $this->complement,
            'district' => $this->district,
            'city' => $this->city,
            'state' => strtoupper($this->state),
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => preg_replace('/[^0-9]/', '', $this->phone),
            'password' => Hash::make($this->password),
            'role' => 'admin',
            'is_active' => true,
        ]);

        event(new Registered($user));
        Auth::login($user);
        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="max-w-xl mx-auto my-6 p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2 text-center">Abra sua Conta no Megabeauty</h2>

    <!-- Indicador Visual de Passos -->
    <div class="flex items-center justify-between mb-8 px-4">
        <div class="flex flex-col items-center">
            <span class="w-8 h-8 flex items-center justify-center rounded-full text-sm font-semibold {{ $currentStep >= 1 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600' }}">1</span>
            <span class="text-xs mt-1 font-medium {{ $currentStep == 1 ? 'text-indigo-600' : 'text-gray-400' }}">Empresa</span>
        </div>
        <div class="flex-1 h-0.5 mx-2 {{ $currentStep >= 2 ? 'bg-indigo-600' : 'bg-gray-200' }}"></div>
        <div class="flex flex-col items-center">
            <span class="w-8 h-8 flex items-center justify-center rounded-full text-sm font-semibold {{ $currentStep >= 2 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600' }}">2</span>
            <span class="text-xs mt-1 font-medium {{ $currentStep == 2 ? 'text-indigo-600' : 'text-gray-400' }}">Endereço</span>
        </div>
        <div class="flex-1 h-0.5 mx-2 {{ $currentStep >= 3 ? 'bg-indigo-600' : 'bg-gray-200' }}"></div>
        <div class="flex flex-col items-center">
            <span class="w-8 h-8 flex items-center justify-center rounded-full text-sm font-semibold {{ $currentStep >= 3 ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-600' }}">3</span>
            <span class="text-xs mt-1 font-medium {{ $currentStep == 3 ? 'text-indigo-600' : 'text-gray-400' }}">Gestor</span>
        </div>
    </div>

    <!-- Formulário Principal -->
    <form wire:submit.prevent>

        <!-- ETAPA 1: INFORMAÇÕES DO ESTABELECIMENTO -->
        @if ($currentStep === 1)
            <div class="space-y-4">
                <h3 class="text-md font-semibold text-indigo-600">1. Informações do Estabelecimento</h3>

                <div>
                    <x-input-label for="company_name" value="Nome do Salão / Clínica *" />
                    <x-text-input wire:model="company_name" id="company_name" class="block mt-1 w-full" type="text" required autofocus />
                    <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
                </div>

                <!-- Tipo de Empresa -->
                <div>
                    <x-input-label value="Tipo de Estabelecimento *" />
                    <div class="flex items-center space-x-6 mt-2">
                        <label class="inline-flex items-center">
                            <input type="radio" wire:model.live="company_type" value="pj" class="text-indigo-600 focus:ring-indigo-500 border-gray-300">
                            <span class="ms-2 text-sm text-gray-600">Pessoa Jurídica (Empresa)</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" wire:model.live="company_type" value="pf" class="text-indigo-600 focus:ring-indigo-500 border-gray-300">
                            <span class="ms-2 text-sm text-gray-600">Pessoa Física (Profissional Autônomo)</span>
                        </label>
                    </div>
                </div>

                <div>
                    <x-input-label for="document_cpf_cnpj" value="{{ $company_type === 'pj' ? 'CNPJ da Empresa *' : 'CPF do Profissional *' }}" />
                    <!-- Máscara Inteligente baseada na seleção do tipo de empresa -->
                    <x-text-input
                        wire:model="document_cpf_cnpj"
                        id="document_cpf_cnpj"
                        class="block mt-1 w-full"
                        type="text"
                        x-data="{ type: @entropy('company_type') }"
                        x-on:input="$el.value = @this.company_type === 'pf' ? $el.value.replace(/\D/g, '').replace(/(\d{3})(\d{3})(\d{3})(\d{2})/g, '$1.$2.$3-$4') : $el.value.replace(/\D/g, '').replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/g, '$1.$2.$3/$4-$5')"
                        maxlength="{{ $company_type === 'pf' ? '14' : '18' }}"
                        placeholder="{{ $company_type === 'pf' ? '000.000.000-00' : '00.000.000/0000-00' }}"
                        required
                    />
                    <x-input-error :messages="$errors->get('document_cpf_cnpj')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="rg" value="{{ $company_type === 'pj' ? 'Inscrição Estadual' : 'RG Geral' }}" />
                    <x-text-input wire:model="rg" id="rg" class="block mt-1 w-full" type="text" />
                    <x-input-error :messages="$errors->get('rg')" class="mt-2" />
                </div>
            </div>
        @endif

        <!-- ETAPA 2: ENDEREÇO DA EMPRESA -->
        @if ($currentStep === 2)
            <div class="space-y-4">
                <h3 class="text-md font-semibold text-indigo-600">2. Localização da Sede</h3>

                <div class="grid grid-cols-3 gap-4">
                    <div class="col-span-1">
                        <x-input-label for="postal_code" value="CEP *" />
                        <x-text-input
                            wire:model.live="postal_code"
                            id="postal_code"
                            class="block mt-1 w-full"
                            type="text"
                            x-data
                            x-on:input="$el.value = $el.value.replace(/\D/g, '').replace(/^(\d{5})(\d)/, '$1-$2')"
                            maxlength="9"
                            placeholder="00000-000"
                            required
                        />
                        <x-input-error :messages="$errors->get('postal_code')" class="mt-2" />
                    </div>

                    <div class="col-span-2">
                        <x-input-label for="address" value="Logradouro (Rua/Av.) *" />
                        <x-text-input wire:model="address" id="address" class="block mt-1 w-full" type="text" required />
                        <x-input-error :messages="$errors->get('address')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="number" value="Número *" />
                        <x-text-input wire:model="number" id="number" class="block mt-1 w-full" type="text" required />
                        <x-input-error :messages="$errors->get('number')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="complement" value="Complemento" />
                        <x-text-input wire:model="complement" id="complement" class="block mt-1 w-full" type="text" />
                        <x-input-error :messages="$errors->get('complement')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="district" value="Bairro *" />
                        <x-text-input wire:model="district" id="district" class="block mt-1 w-full" type="text" required />
                        <x-input-error :messages="$errors->get('district')" class="mt-2" />
                    </div>

                    <div class="col-span-2">
                        <x-input-label for="city" value="Cidade *" />
                        <x-text-input wire:model="city" id="city" class="block mt-1 w-full" type="text" required />
                        <x-input-error :messages="$errors->get('city')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="state" value="UF (Estado) *" />
                        <x-text-input wire:model="state" id="state" class="block mt-1 w-full" type="text" maxlength="2" placeholder="EX: SP" required />
                        <x-input-error :messages="$errors->get('state')" class="mt-2" />
                    </div>
                </div>
            </div>
        @endif

        <!-- ETAPA 3: CONTA DO ADMINISTRADOR -->
        @if ($currentStep === 3)
            <div class="space-y-4">
                <h3 class="text-md font-semibold text-indigo-600">3. Identificação do Gestor</h3>

                <div>
                    <x-input-label for="name" value="Nome Completo *" />
                    <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="phone" value="Telefone / Celular *" />
                        <x-text-input
                            wire:model="phone"
                            id="phone"
                            class="block mt-1 w-full"
                            type="text"
                            x-data
                            x-on:input="$el.value = $el.value.replace(/\D/g, '').replace(/^(\d{2})(\d)/g, '($1) $2').replace(/(\d)(\d{4})$/, '$1-$2')"
                            maxlength="15"
                            placeholder="(11) 99999-9999"
                            required
                        />
                        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="email" value="E-mail de Login *" />
                        <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" required />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="password" value="Senha *" />
                        <x-text-input wire:model="password" id="password" class="block mt-1 w-full" type="password" required />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="password_confirmation" value="Confirme a Senha *" />
                        <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full" type="password" required />
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                    </div>
                </div>
            </div>
        @endif

        <!-- BOTÕES DE NAVEGAÇÃO -->
        <div class="flex items-center justify-between mt-8 pt-4 border-t">
            <div>
                @if ($currentStep > 1)
                    <button type="button" wire:click="previousStep" class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300 active:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition ease-in-out duration-150">
                        Voltar
                    </button>
                @else
                    <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}" wire:navigate>
                        Já sou cadastrado
                    </a>
                @endif
            </div>

            <div>
                @if ($currentStep < 3)
                    <button type="button" wire:click="nextStep" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Avançar
                    </button>
                @else
                    <button type="button" wire:click="register" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition ease-in-out duration-150">
                        Concluir e Entrar
                    </button>
                @endif
            </div>
        </div>
    </form>
</div>
