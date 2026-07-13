<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

// Rota de Dashboard via Volt
Volt::route('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Perfil de usuário
Volt::route('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('customers', 'pages.customers.index')->name('customers.index');
    Volt::route('settings', 'pages.settings.index')->name('settings.index');
    Volt::route('products', 'pages.products.index')->name('products.index');
    Volt::route('categories/{type?}', 'pages.categories.index')->name('categories.index');
    Volt::route('services', 'pages.services.index')->name('services.index');
    Volt::route('professionals', 'pages.professionals.index')->name('professionals.index');
    Volt::route('commands', 'pages.commands.index')->name('commands.index');

    // Nova rota para o gerenciador de POPs
    Volt::route('pops', 'pages.pops.index')->name('pops.index');
    Volt::route('/pops/create', 'pages.pops.manage')->name('pops.create');
    Volt::route('/pops/{id}/edit', 'pages.pops.manage')->name('pops.edit');

    Volt::route('/formas-pagamento', 'payment-methods.index')->name('payment-methods.index');
    Volt::route('/financeiro/transacoes', 'financial.transactions')->name('financial.transactions');
    Volt::route('/financeiro/categorias', 'financial.categories')->name('financial.categories');
    Volt::route('/financeiro/comissoes', 'financial.commissions')->name('financial.commissions');
    Volt::route('/financeiro/configuracoes', 'financial.settings')->name('financial.settings');
    Volt::route('/agenda', 'agenda.index')->name('agenda.index');
});

require __DIR__.'/auth.php';
