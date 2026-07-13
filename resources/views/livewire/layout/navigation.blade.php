<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </x-nav-link>

                     <x-nav-link :href="route('agenda.index')" :active="request()->routeIs('agenda.index')" wire:navigate>
                        Agenda
                    </x-nav-link>

                    <x-nav-link :href="route('commands.index')" :active="request()->routeIs('commands.index')" wire:navigate>
                        Comandas
                    </x-nav-link>

                    <x-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.index')" wire:navigate>
                        Clientes
                    </x-nav-link>

                    <div class="hidden sm:flex sm:items-center">
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center px-1 py-5 border-b-2 {{ request()->routeIs('products.*') || (request()->routeIs('categories.*') && request()->route('type') === 'product') ? 'border-indigo-400 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none">
                                    <div>Produtos</div>
                                    <div class="ms-1">
                                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <x-dropdown-link :href="route('products.index')" wire:navigate class="{{ request()->routeIs('products.index') ? 'bg-indigo-50 font-semibold text-indigo-700' : '' }}">
                                    Gerenciar Produtos
                                </x-dropdown-link>

                                <x-dropdown-link :href="route('categories.index', ['type' => 'product'])" wire:navigate class="{{ request()->routeIs('categories.index') && request()->route('type') === 'product' ? 'bg-indigo-50 font-semibold text-indigo-700' : '' }}">
                                    Categorias de Produtos
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    </div>

                    <div class="hidden sm:flex sm:items-center">
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center px-1 py-5 border-b-2 {{ request()->routeIs('services.*') || (request()->routeIs('categories.*') && request()->route('type') === 'service') ? 'border-indigo-400 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none">
                                    <div>Serviços</div>
                                    <div class="ms-1">
                                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <x-dropdown-link :href="route('services.index')" wire:navigate class="{{ request()->routeIs('services.index') ? 'bg-indigo-50 font-semibold text-indigo-700' : '' }}">
                                    Gerenciar Serviços
                                </x-dropdown-link>

                                <x-dropdown-link :href="route('categories.index', ['type' => 'service'])" wire:navigate class="{{ request()->routeIs('categories.index') && request()->route('type') === 'service' ? 'bg-indigo-50 font-semibold text-indigo-700' : '' }}">
                                    Categorias de Serviços
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    </div>

                    <div class="hidden sm:flex sm:items-center">
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center px-1 py-5 border-b-2 {{ request()->routeIs('financial.*') || request()->routeIs('payment-methods.*') ? 'border-indigo-400 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none">
                                    <div>Financeiro</div>
                                    <div class="ms-1">
                                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <x-dropdown-link :href="route('financial.transactions')" wire:navigate class="{{ request()->routeIs('financial.transactions') ? 'bg-indigo-50 font-semibold text-indigo-700' : '' }}">
                                    Fluxo de Caixa / Lançamentos
                                </x-dropdown-link>

                                <x-dropdown-link :href="route('financial.commissions')" wire:navigate class="{{ request()->routeIs('financial.commissions') ? 'bg-indigo-50 font-semibold text-indigo-700' : '' }}">
                                    Folha de Comissões
                                </x-dropdown-link>

                                <x-dropdown-link :href="route('financial.settings')" wire:navigate class="{{ request()->routeIs('financial.settings') ? 'bg-indigo-50 font-semibold text-indigo-700' : '' }}">
                                    Configurações Financeiras / Contas
                                </x-dropdown-link>

                                <x-dropdown-link :href="route('financial.categories')" wire:navigate class="{{ request()->routeIs('financial.categories') ? 'bg-indigo-50 font-semibold text-indigo-700' : '' }}">
                                    Categorias Financeiras
                                </x-dropdown-link>

                                <x-dropdown-link :href="route('payment-methods.index')" wire:navigate class="{{ request()->routeIs('payment-methods.index') ? 'bg-indigo-50 font-semibold text-indigo-700' : '' }}">
                                    Métodos de Pagamento
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    </div>

                    <x-nav-link :href="route('professionals.index')" :active="request()->routeIs('professionals.index')" wire:navigate>
                        Profissionais
                    </x-nav-link>

                    <div class="hidden sm:flex sm:items-center">
                        <x-dropdown align="left" width="48">
                            <x-slot name="trigger">
                                <button class="inline-flex items-center px-1 py-5 border-b-2 {{ request()->routeIs('pops.*') ? 'border-indigo-400 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none">
                                    <div>Documentação</div>
                                    <div class="ms-1">
                                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <x-dropdown-link :href="route('pops.index')" wire:navigate class="{{ request()->routeIs('pops.index') ? 'bg-indigo-50 font-semibold text-indigo-700' : '' }}">
                                    Procedimentos (POP)
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    </div>

                    <x-nav-link :href="route('settings.index')" :active="request()->routeIs('settings.index')" wire:navigate>
                        Configurações
                    </x-nav-link>
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.index')" wire:navigate>
                Clientes
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('products.index')" :active="request()->routeIs('products.index')" wire:navigate>
                Produtos
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('services.index')" :active="request()->routeIs('services.index')" wire:navigate>
                Serviços
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('financial.transactions')" :active="request()->routeIs('financial.transactions')" wire:navigate>
                Fluxo de Caixa
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('financial.commissions')" :active="request()->routeIs('financial.commissions')" wire:navigate>
                Folha de Comissões
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('financial.settings')" :active="request()->routeIs('financial.settings')" wire:navigate>
                Configurações Financeiras / Contas
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('payment-methods.index')" :active="request()->routeIs('payment-methods.index')" wire:navigate>
                Meios de Pagamento
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('professionals.index')" :active="request()->routeIs('professionals.index')" wire:navigate>
                Profissionais
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('pops.index')" :active="request()->routeIs('pops.index')" wire:navigate>
                Documentação - POPs
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('settings.index')" :active="request()->routeIs('settings.index')" wire:navigate>
                Configurações
            </x-responsive-nav-link>
        </div>

        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="font-medium text-sm text-gray-500">{{ auth()->user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile')" wire:navigate>
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link>
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
    </div>
</nav>
