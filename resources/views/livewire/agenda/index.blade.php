<?php

use App\Models\Professional;
use App\Models\Appointment;
use App\Models\ScheduleBlock;
use App\Models\User;
use App\Models\Service;
use App\Models\Product;
use App\Models\Command;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\FinancialCategory;
use App\Models\Commission;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    // Controle de Data da Agenda
    public string $selectedDate = '';

    // Estado dos Modais
    public bool $showAppointmentModal = false;
    public bool $showBlockModal = false;
    public bool $showCommandCheckoutModal = false;
    public int $checkoutStep = 1;

    public bool $isEditingAppointment = false;
    public ?int $editingAppointmentId = null;
    public bool $isEditingBlock = false;
    public ?int $editingBlockId = null;
    public bool $hasCommand = false;

    // Cabeçalho do Agendamento
    public string $customer_id = '';
    public string $customer_search = '';
    public string $status = 'pending';
    public string $notes = '';

    // Lista de Itens do Agendamento
    public array $appointmentItems = [];
    public int $focusedItemIndex = -1;

    // Estado do Checkout da Comanda
    public ?Command $activeCommand = null;
    public array $checkoutServices = [];
    public array $checkoutProducts = [];
    public int $focusedCheckoutServiceIndex = -1;
    public int $focusedCheckoutProductIndex = -1;
    public ?string $payment_method_id = null;
    public float $discount = 0.00;

    // Autocompletes
    public array $autocompleteCustomers = [];
    public array $autocompleteServices = [];
    public array $autocompleteProducts = [];

    // CRM
    public ?User $selectedCustomerCrm = null;
    public float $customerTotalSpent = 0.00;

    // Bloqueio
    public string $block_professional_id = '';
    public string $block_title = 'Ocupado';
    public string $block_start_time = '08:00';
    public string $block_end_time = '09:00';
    public string $block_notes = '';

    public function mount(): void
    {
        $this->selectedDate = now()->format('Y-m-d');
    }

    public function changeDate(string $action): void
    {
        $date = \Carbon\Carbon::parse($this->selectedDate);
        if ($action === 'next') $date->addDay();
        if ($action === 'prev') $date->subDay();
        if ($action === 'today') $date = now();

        $this->selectedDate = $date->format('Y-m-d');
    }

    /* ---------------------------------------------------------------------------
     * CONTROLES EXPLÍCITOS DE AUTOCOMPLETE E DURAÇÃO (SEGUROS PARA ARRAYS)
     * --------------------------------------------------------------------------- */

    public function searchCustomer(string $value): void
    {
        $this->customer_search = $value;
        $this->customer_id = '';
        $this->selectedCustomerCrm = null;
        $this->customerTotalSpent = 0.00;

        if (strlen($value) > 1) {
            $this->autocompleteCustomers = User::where('tenant_id', auth()->user()->tenant_id)
                ->where('role', 'customer')
                ->where('name', 'like', '%' . $value . '%')
                ->take(5)->get()->toArray();
        } else {
            $this->autocompleteCustomers = [];
        }
    }

    public function selectCustomer(int $id, string $name): void
    {
        $this->customer_id = (string)$id;
        $this->customer_search = $name;
        $this->autocompleteCustomers = [];

        $this->selectedCustomerCrm = User::find($id);
        $this->customerTotalSpent = (float) Command::where('customer_id', $id)->where('status', 'finished')->sum('total_amount');
    }

    public function clearCustomer(): void
    {
        $this->customer_id = '';
        $this->customer_search = '';
        $this->selectedCustomerCrm = null;
        $this->customerTotalSpent = 0.00;
    }

    public function searchAgendaService(int $index, string $searchString): void
    {
        $this->focusedItemIndex = $index;
        $this->appointmentItems[$index]['service_search'] = $searchString;
        $this->appointmentItems[$index]['service_id'] = '';

        if (strlen($searchString) > 1) {
            $this->autocompleteServices = Service::where('tenant_id', auth()->user()->tenant_id)
                ->where('is_active', true)
                ->where('name', 'like', '%' . $searchString . '%')
                ->take(5)
                ->get()
                ->toArray();
        } else {
            $this->autocompleteServices = [];
        }
    }

    // Função auxiliar com inteligência para varrer colunas comuns de duração no banco
    private function getServiceDuration($serviceId): int
    {
        if (!$serviceId) return 30;
        $service = Service::find($serviceId);

        if ($service) {
            // Tenta adivinhar a coluna de tempo que você criou no banco
            $durVal = $service->duration ?? $service->time ?? $service->execution_time ?? $service->duration_minutes;

            if ($durVal !== null) {
                // Se for salvo no banco como H:i:s ou H:i (Ex: "01:30" ou "00:45")
                if (is_string($durVal) && str_contains($durVal, ':')) {
                    $parts = explode(':', $durVal);
                    return ((int)$parts[0] * 60) + (isset($parts[1]) ? (int)$parts[1] : 0);
                }

                // Se for salvo em minutos inteiros (Ex: 45, 60, 90)
                return (int) $durVal ?: 30;
            }
        }

        return 30; // Fallback padrão
    }

    public function selectServiceItem(int $index, int $id, string $name): void
    {
        // Copia o item para forçar a reatividade do array no Livewire 3
        $item = $this->appointmentItems[$index];

        $item['service_id'] = (string)$id;
        $item['service_search'] = $name;

        // Puxa a duração exata do banco
        $duration = $this->getServiceDuration($id);

        // Guarda a duração na memória do item
        $item['duration'] = $duration;

        // Calcula o horário final
        if (!empty($item['start_time'])) {
            $item['end_time'] = \Carbon\Carbon::parse($item['start_time'])
                ->addMinutes($duration)
                ->format('H:i');
        }

        // Devolve o array completo na posição, forçando a re-renderização da linha
        $this->appointmentItems[$index] = $item;

        $this->focusedItemIndex = -1;
        $this->autocompleteServices = [];
    }

    public function updatedAppointmentItems($value, $key): void
    {
        // Se o usuário alterar manualmente o campo do input de horário INICIAL
        if (str_ends_with($key, '.start_time')) {
            $parts = explode('.', $key);
            $index = $parts[0];

            if (!empty($value)) {
                $item = $this->appointmentItems[$index];

                // Usa a duração previamente resgatada do banco ou 30 min padrão
                $duration = $item['duration'] ?? $this->getServiceDuration($item['service_id'] ?? null);

                $item['end_time'] = \Carbon\Carbon::parse($value)
                    ->addMinutes($duration)
                    ->format('H:i');

                $this->appointmentItems[$index] = $item;
            }
        }
    }

    public function addItem(?int $profId = null, ?string $time = null): void
    {
        $this->appointmentItems[] = [
            'id' => null,
            'service_id' => '',
            'service_search' => '',
            'professional_id' => $profId ? (string)$profId : '',
            'start_time' => $time ? $time : '08:00',
            'end_time' => $time ? \Carbon\Carbon::parse($time)->addMinutes(30)->format('H:i') : '08:30',
            'duration' => 30, // Chave auxiliar que usaremos no cálculo
        ];
        $this->focusedItemIndex = count($this->appointmentItems) - 1;
    }

    public function removeItem(int $index): void
    {
        if (!empty($this->appointmentItems[$index]['id'])) {
            Appointment::destroy($this->appointmentItems[$index]['id']);
        }
        unset($this->appointmentItems[$index]);
        $this->appointmentItems = array_values($this->appointmentItems);
        $this->focusedItemIndex = -1;
    }

    public function searchCheckoutService(int $index, string $searchString): void
    {
        if ($this->activeCommand && $this->activeCommand->status === 'finished') return;

        $this->focusedCheckoutServiceIndex = $index;
        $this->checkoutServices[$index]['service_search'] = $searchString;
        $this->checkoutServices[$index]['service_id'] = '';

        if (strlen($searchString) > 1) {
            $this->autocompleteServices = Service::where('tenant_id', auth()->user()->tenant_id)
                ->where('is_active', true)
                ->where('name', 'like', '%' . $searchString . '%')
                ->take(5)
                ->get()
                ->toArray();
        } else {
            $this->autocompleteServices = [];
        }
    }

    public function selectCheckoutServiceItem(int $index, int $id, string $name, float $price): void
    {
        $this->checkoutServices[$index]['service_id'] = (string)$id;
        $this->checkoutServices[$index]['service_search'] = $name;
        $this->checkoutServices[$index]['price'] = $price;
        $this->focusedCheckoutServiceIndex = -1;
        $this->autocompleteServices = [];
    }

    public function addCheckoutService(): void
    {
        if ($this->activeCommand && $this->activeCommand->status === 'finished') return;

        $this->checkoutServices[] = [
            'appointment_id' => null,
            'service_id' => '',
            'service_search' => '',
            'professional_id' => '',
            'price' => 0.00,
        ];
        $this->focusedCheckoutServiceIndex = count($this->checkoutServices) - 1;
    }

    public function removeCheckoutService(int $index): void
    {
        if ($this->activeCommand && $this->activeCommand->status === 'finished') return;

        if (!empty($this->checkoutServices[$index]['appointment_id'])) {
            Appointment::destroy($this->checkoutServices[$index]['appointment_id']);
        }
        unset($this->checkoutServices[$index]);
        $this->checkoutServices = array_values($this->checkoutServices);
        $this->focusedCheckoutServiceIndex = -1;
    }

    public function searchCheckoutProduct(int $index, string $searchString): void
    {
        if ($this->activeCommand && $this->activeCommand->status === 'finished') return;

        $this->focusedCheckoutProductIndex = $index;
        $this->checkoutProducts[$index]['product_search'] = $searchString;
        $this->checkoutProducts[$index]['product_id'] = '';

        if (strlen($searchString) > 1) {
            $this->autocompleteProducts = Product::where('tenant_id', auth()->user()->tenant_id)
                ->where('is_active', true)
                ->where('name', 'like', '%' . $searchString . '%')
                ->take(5)
                ->get()
                ->toArray();
        } else {
            $this->autocompleteProducts = [];
        }
    }

    public function selectCheckoutProductItem(int $index, int $id, string $name, float $price): void
    {
        $this->checkoutProducts[$index]['product_id'] = (string)$id;
        $this->checkoutProducts[$index]['product_search'] = $name;
        $this->checkoutProducts[$index]['price'] = $price;
        $this->focusedCheckoutProductIndex = -1;
        $this->autocompleteProducts = [];
    }

    public function addCheckoutProduct(): void
    {
        if ($this->activeCommand && $this->activeCommand->status === 'finished') return;

        $this->checkoutProducts[] = [
            'product_id' => '',
            'product_search' => '',
            'quantity' => 1,
            'price' => 0.00,
        ];
        $this->focusedCheckoutProductIndex = count($this->checkoutProducts) - 1;
    }

    public function removeCheckoutProduct(int $index): void
    {
        if ($this->activeCommand && $this->activeCommand->status === 'finished') return;

        unset($this->checkoutProducts[$index]);
        $this->checkoutProducts = array_values($this->checkoutProducts);
        $this->focusedCheckoutProductIndex = -1;
    }

    public function openAppointment(int $profId, string $time): void
    {
        $this->resetForm();
        $this->isEditingAppointment = false;
        $this->addItem($profId, $time);
        $this->showAppointmentModal = true;
    }

    public function editAppointment(int $appointmentId): void
    {
        $this->resetForm();
        $app = Appointment::findOrFail($appointmentId);

        $this->editingAppointmentId = $app->id;
        $this->customer_id = (string)$app->customer_id;
        $this->customer_search = $app->customer?->name ?? '';
        $this->status = $app->status;
        $this->notes = $app->notes ?? '';

        $relatedAppointments = Appointment::where('tenant_id', auth()->user()->tenant_id)
            ->where('customer_id', $app->customer_id)
            ->where('date', $app->date)
            ->when($app->command_id, function($q) use ($app) {
                $q->where('command_id', $app->command_id);
            }, function($q) use ($app) {
                $q->where('start_time', $app->start_time);
            })
            ->get();

        $this->hasCommand = !empty($app->command_id);

        foreach ($relatedAppointments as $related) {
            $startC = \Carbon\Carbon::parse($related->start_time);
            $endC = \Carbon\Carbon::parse($related->end_time);
            $calcDur = $startC->diffInMinutes($endC);

            $this->appointmentItems[] = [
                'id' => $related->id,
                'service_id' => (string)$related->service_id,
                'service_search' => $related->service?->name ?? '',
                'professional_id' => (string)$related->professional_id,
                'start_time' => $startC->format('H:i'),
                'end_time' => $endC->format('H:i'),
                'duration' => $calcDur > 0 ? $calcDur : 30,
            ];
        }

        if (!empty($this->customer_id)) {
            $this->selectedCustomerCrm = User::find($this->customer_id);
            $this->customerTotalSpent = (float) Command::where('customer_id', $this->customer_id)->where('status', 'finished')->sum('total_amount');
        }

        $this->isEditingAppointment = true;
        $this->showAppointmentModal = true;
    }

    public function saveAppointment(): void
    {
        $this->validate([
            'customer_id' => ['required', 'exists:users,id'],
            'status' => ['required', 'in:pending,confirmed,checked_in,finished,canceled'],
            'appointmentItems' => ['required', 'array', 'min:1'],
            'appointmentItems.*.service_id' => ['required', 'exists:services,id'],
            'appointmentItems.*.professional_id' => ['required', 'exists:professionals,id'],
            'appointmentItems.*.start_time' => ['required'],
            'appointmentItems.*.end_time' => ['required']
        ]);

        $appOriginal = $this->isEditingAppointment ? Appointment::find($this->editingAppointmentId) : null;
        $commandId = $appOriginal ? $appOriginal->command_id : null;
        $targetDate = $appOriginal ? $appOriginal->date : $this->selectedDate;

        foreach ($this->appointmentItems as $item) {
            if (!empty($item['id'])) {
                $existingApp = Appointment::find($item['id']);
                if ($existingApp) {
                    $existingApp->update([
                        'customer_id' => $this->customer_id,
                        'professional_id' => $item['professional_id'],
                        'service_id' => $item['service_id'],
                        'date' => $targetDate,
                        'start_time' => $item['start_time'],
                        'end_time' => $item['end_time'],
                        'status' => $this->status,
                        'notes' => $this->notes,
                    ]);
                }
            } else {
                Appointment::create([
                    'tenant_id' => auth()->user()->tenant_id,
                    'customer_id' => $this->customer_id,
                    'professional_id' => $item['professional_id'],
                    'service_id' => $item['service_id'],
                    'command_id' => $commandId,
                    'date' => $targetDate,
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],
                    'status' => $commandId ? 'checked_in' : $this->status,
                    'notes' => $this->notes,
                ]);
            }
        }

        $this->showAppointmentModal = false;
        $this->resetForm();
    }

    public function faturarParaComanda(): void
    {
        if ($this->editingAppointmentId) {
            $app = Appointment::with(['service', 'professional', 'customer'])->findOrFail($this->editingAppointmentId);
            $tenantId = auth()->user()->tenant_id;
            $comanda = null;

            if ($app->command_id) {
                $comanda = Command::find($app->command_id);
            }

            if (!$comanda) {
                $comanda = Command::where('tenant_id', $tenantId)
                    ->where('customer_id', $app->customer_id)
                    ->where('status', 'open')
                    ->whereDate('created_at', now()->format('Y-m-d'))
                    ->first();

                if (!$comanda) {
                    $lastId = Command::where('tenant_id', $tenantId)->max('id') ?? 0;
                    $nextCode = $lastId + 1001;

                    $comanda = Command::create([
                        'tenant_id' => $tenantId,
                        'customer_id' => $app->customer_id,
                        'code' => (string)$nextCode,
                        'total_services' => $app->service?->price ?? 0.00,
                        'total_products' => 0.00,
                        'discount' => 0.00,
                        'total_amount' => $app->service?->price ?? 0.00,
                        'status' => 'open',
                    ]);
                } else {
                    $comanda->increment('total_services', $app->service?->price ?? 0.00);
                    $comanda->increment('total_amount', $app->service?->price ?? 0.00);
                }

                $app->update([
                    'command_id' => $comanda->id,
                    'status' => 'checked_in'
                ]);
            }

            $this->activeCommand = $comanda;
            $this->checkoutStep = 1;
            $this->discount = (float)($comanda->discount ?? 0.00);
            $this->payment_method_id = $comanda->payment_method_id;

            $this->checkoutServices = [];
            $currentApps = Appointment::with('service')->where('command_id', $comanda->id)->get();
            foreach ($currentApps as $cApp) {
                $this->checkoutServices[] = [
                    'appointment_id' => $cApp->id,
                    'service_id' => (string)$cApp->service_id,
                    'service_search' => $cApp->service?->name ?? '',
                    'professional_id' => (string)$cApp->professional_id,
                    'price' => (float)($cApp->service?->price ?? 0.00),
                ];
            }

            $this->checkoutProducts = [];
            $this->showAppointmentModal = false;
            $this->showCommandCheckoutModal = true;
        }
    }

    public function reabrirComanda(): void
    {
        if ($this->activeCommand && $this->activeCommand->status === 'finished') {
            $comanda = Command::findOrFail($this->activeCommand->id);

            $comanda->update([
                'status' => 'open',
                'payment_method_id' => null
            ]);

            Appointment::where('command_id', $comanda->id)->update(['status' => 'checked_in']);

            $this->activeCommand = $comanda;
            $this->checkoutStep = 1;
            $this->payment_method_id = null;

            session()->flash('message', 'Comanda reaberta com sucesso!');
        }
    }

    public function avancarParaPagamento(): void
    {
        if ($this->activeCommand) {
            if ($this->activeCommand->status === 'finished') {
                $this->checkoutStep = 2;
                return;
            }

            foreach ($this->checkoutServices as $sItem) {
                if (empty($sItem['appointment_id']) && !empty($sItem['service_id'])) {
                    Appointment::create([
                        'tenant_id' => auth()->user()->tenant_id,
                        'customer_id' => $this->activeCommand->customer_id,
                        'professional_id' => $sItem['professional_id'] ?: Professional::first()->id,
                        'service_id' => $sItem['service_id'],
                        'command_id' => $this->activeCommand->id,
                        'date' => $this->selectedDate,
                        'start_time' => '12:00',
                        'end_time' => '12:30',
                        'status' => 'checked_in'
                    ]);
                } elseif (!empty($sItem['appointment_id'])) {
                    $upApp = Appointment::find($sItem['appointment_id']);
                    if ($upApp) {
                        $upApp->update([
                            'service_id' => $sItem['service_id'],
                            'professional_id' => $sItem['professional_id'],
                            'price' => $sItem['price']
                        ]);
                    }
                }
            }
            $this->checkoutStep = 2;
        }
    }

    public function fecharComandaComoPaga(): void
    {
        if ($this->activeCommand) {
            if ($this->activeCommand->status === 'finished') {
                $this->showCommandCheckoutModal = false;
                return;
            }

            if (empty($this->payment_method_id)) {
                $this->addError('payment_method_id', 'Selecione uma forma ativa de pagamento.');
                return;
            }

            $comanda = Command::findOrFail($this->activeCommand->id);

            $totalServices = array_sum(array_column($this->checkoutServices, 'price'));
            $totalProducts = 0;
            foreach ($this->checkoutProducts as $pItem) {
                $totalProducts += ((float)$pItem['price'] * (int)$pItem['quantity']);
            }

            $finalAmount = ($totalServices + $totalProducts) - (float)$this->discount;

            $comanda->update([
                'total_services' => $totalServices,
                'total_products' => $totalProducts,
                'discount' => (float)$this->discount,
                'total_amount' => $finalAmount > 0 ? $finalAmount : 0.00,
                'payment_method_id' => $this->payment_method_id,
                'status' => 'finished'
            ]);

            $financialCategory = FinancialCategory::firstOrCreate(
                ['tenant_id' => auth()->user()->tenant_id, 'name' => 'Venda de Comanda'],
                ['type' => 'revenue', 'is_active' => true]
            );

            $feeAmount = 0.00;
            $accountId = null;
            $pm = PaymentMethod::find($this->payment_method_id);
            if ($pm) {
                $feeAmount = ($finalAmount * ($pm->fee_percentage / 100)) + $pm->fixed_fee;
                $accountId = $pm->account_id;
            }

            Transaction::create([
                'tenant_id' => auth()->user()->tenant_id,
                'source_type' => Command::class,
                'source_reference' => $comanda->id,
                'financial_category_id' => $financialCategory->id,
                'payment_method_id' => $this->payment_method_id,
                'account_id' => $accountId,
                'user_id' => auth()->id(),
                'gross_amount' => $finalAmount,
                'fee_amount' => $feeAmount,
                'net_amount' => max(0, $finalAmount - $feeAmount),
                'due_date' => now()->format('Y-m-d'),
                'payment_date' => now()->format('Y-m-d'),
                'status' => 'paid',
                'notes' => "Fechamento via Agenda da comanda código {$comanda->code}",
            ]);

            Appointment::where('command_id', $comanda->id)->update(['status' => 'finished']);

            session()->flash('message', 'Comanda #' . $comanda->code . ' liquidada com sucesso!');
            $this->showCommandCheckoutModal = false;
            $this->activeCommand = null;
        }
    }

    public function isProfessionalAvailable(Professional $prof, string $time): bool
    {
        return true;
    }

    private function resetForm(): void
    {
        $this->reset([
            'customer_id', 'customer_search', 'status', 'notes', 'isEditingAppointment', 'editingAppointmentId',
            'selectedCustomerCrm', 'customerTotalSpent', 'appointmentItems', 'focusedItemIndex', 'checkoutServices', 'checkoutProducts', 'checkoutStep', 'discount', 'payment_method_id', 'hasCommand',
            'autocompleteCustomers', 'autocompleteServices', 'autocompleteProducts'
        ]);
        $this->status = 'pending';
    }

    public function with(): array
    {
        $tenantId = auth()->user()->tenant_id;

        $timeSlots = [];
        $start = \Carbon\Carbon::createFromTimeString('07:00');
        $end = \Carbon\Carbon::createFromTimeString('21:00');
        while ($start->lte($end)) {
            $timeSlots[] = $start->format('H:i');
            $start->addMinutes(5);
        }

        $subtotalServices = array_sum(array_column($this->checkoutServices, 'price'));
        $subtotalProducts = 0;
        foreach ($this->checkoutProducts as $pItem) {
            $subtotalProducts += ((float)$pItem['price'] * (int)$pItem['quantity']);
        }
        $totalCalculadoBackend = ($subtotalServices + $subtotalProducts) - (float)$this->discount;

        $savedPaymentName = 'Recebimento';
        if ($this->activeCommand && !empty($this->activeCommand->payment_method_id)) {
            $pmSalvo = PaymentMethod::find($this->activeCommand->payment_method_id);
            if ($pmSalvo) $savedPaymentName = $pmSalvo->name;
        }

        return [
            'timeSlots' => $timeSlots,
            'professionals' => Professional::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name', 'asc')->get(),
            'appointments' => Appointment::with(['customer', 'service', 'command'])->where('date', $this->selectedDate)->get(),
            'blocks' => ScheduleBlock::where('date', $this->selectedDate)->get(),
            'allProfessionals' => Professional::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name', 'asc')->get(),
            'paymentMethods' => PaymentMethod::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name', 'asc')->get(),
            'subtotalServices' => $subtotalServices,
            'subtotalProducts' => $subtotalProducts,
            'totalCalculado' => $totalCalculadoBackend > 0 ? $totalCalculadoBackend : 0.00,
            'savedPaymentName' => $savedPaymentName
        ];
    }
}; ?>

<div class="py-6 bg-gray-50 min-h-screen">
    <div class="max-w-[100fr] mx-auto px-4 sm:px-6 lg:px-8">

        <div class="flex flex-col md:flex-row justify-between items-center bg-white p-4 rounded-xl shadow-sm border mb-4">
            <div class="flex items-center space-x-2">
                <button wire:click="changeDate('prev')" class="p-2 border rounded-lg bg-white font-bold">&lt;</button>
                <button wire:click="changeDate('today')" class="px-4 py-2 border rounded-lg bg-white text-xs font-bold uppercase">Hoje</button>
                <button wire:click="changeDate('next')" class="p-2 border rounded-lg bg-white font-bold">&gt;</button>
                <span class="text-lg font-black text-gray-800 pl-2">{{ \Carbon\Carbon::parse($selectedDate)->translatedFormat('d \d\e F \d\e Y') }}</span>
            </div>
            <input type="date" wire:model.live="selectedDate" class="text-xs border-gray-300 rounded-lg shadow-sm">
        </div>

        @if(session()->has('message'))
            <div class="mb-4 p-3 bg-green-100 text-green-700 rounded-lg text-xs font-bold shadow-sm border border-green-200">
                {{ session('message') }}
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border overflow-x-auto max-h-[80vh] overflow-y-auto">
            <div class="min-w-[800px]" style="display: grid; grid-template-columns: 80px repeat({{ count($professionals) }}, minmax(180px, 1fr));">
                <div class="bg-gray-50 p-3 text-center text-[10px] font-bold text-gray-400 uppercase border-r border-b sticky top-0 left-0 z-30">Hora</div>
                @foreach($professionals as $prof)
                    <div class="p-3 text-center border-b border-r bg-white sticky top-0 z-20 flex flex-col items-center">
                        <span class="text-xs font-bold text-gray-800">{{ $prof->name }}</span>
                    </div>
                @endforeach

                @foreach($timeSlots as $index => $slot)
                    <div class="bg-gray-50 text-center font-mono text-xs border-r border-b border-gray-200 p-2 flex items-center justify-center h-10 sticky left-0 z-10" style="grid-column: 1; grid-row: {{ $index + 2 }};">{{ $slot }}</div>
                    @foreach($professionals as $pIndex => $prof)
                        <div wire:click="openAppointment({{ $prof->id }}, '{{ $slot }}')" class="border-r border-b border-dashed border-gray-200 cursor-pointer hover:bg-gray-50/50 h-10" style="grid-column: {{ $pIndex + 2 }}; grid-row: {{ $index + 2 }};"></div>
                    @endforeach
                @endforeach

                @foreach($appointments as $app)
                    @php
                        $pIndex = $professionals->pluck('id')->search($app->professional_id);
                        $startRow = array_search(\Carbon\Carbon::parse($app->start_time)->format('H:i'), $timeSlots);
                        $endRow = array_search(\Carbon\Carbon::parse($app->end_time)->format('H:i'), $timeSlots);

                        if ($endRow === false && $startRow !== false) {
                            $mins = \Carbon\Carbon::parse($app->start_time)->diffInMinutes(\Carbon\Carbon::parse($app->end_time));
                            $endRow = $startRow + ($mins / 5);
                        }

                        $currentStatus = ($app->command && $app->command->status === 'finished') ? 'finished' : $app->status;
                        $statusClasses = match($currentStatus) {
                            'confirmed' => 'bg-emerald-50 border-emerald-500 text-emerald-900',
                            'checked_in'=> 'bg-sky-50 border-sky-500 text-sky-900',
                            'finished'  => 'bg-purple-50 border-purple-400 text-purple-900 opacity-85 line-through',
                            'canceled'  => 'bg-rose-50 border-rose-400 text-rose-900 opacity-70 line-through',
                            default     => 'bg-amber-50 border-amber-400 text-amber-900',
                        };
                    @endphp
                    @if($pIndex !== false && $startRow !== false && $startRow < $endRow)
                        <div wire:click="editAppointment({{ $app->id }})" class="border-l-4 p-2 rounded-md m-0.5 shadow-sm overflow-hidden flex flex-col justify-between cursor-pointer {{ $statusClasses }}" style="grid-column: {{ $pIndex + 2 }}; grid-row: {{ $startRow + 2 }} / {{ $endRow + 2 }}; z-index: 10;">
                            <div class="font-bold text-[11px] truncate">{{ $app->customer?->name }}</div>
                            <div class="text-[10px] opacity-80 truncate">{{ $app->service?->name }}</div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    @if($showAppointmentModal)
        <div class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500 opacity-75" wire:click="$set('showAppointmentModal', false)"></div>
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl p-6 z-50 overflow-visible" wire:key="agenda-main-modal">
                <h3 class="text-base font-black mb-4">Gerenciamento de Agendamento</h3>
                <form wire:submit.prevent="saveAppointment" class="space-y-4">
                    <div class="relative">
                        <x-input-label value="Cliente *" />
                        <x-text-input type="text" wire:model="customer_search" wire:input.debounce.300ms="searchCustomer($event.target.value)" placeholder="Buscar cliente..." class="w-full text-xs mt-1" required />
                        @if(!empty($autocompleteCustomers))
                            <div class="absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-[150] max-h-36 overflow-y-auto divide-y">
                                @foreach($autocompleteCustomers as $cust)
                                    <button type="button" wire:click="selectCustomer({{ $cust['id'] }}, '{{ addslashes($cust['name']) }}')" class="w-full text-left px-4 py-2 hover:bg-indigo-50 text-xs block text-gray-700 font-medium transition">{{ $cust['name'] }}</button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="space-y-2 overflow-visible">
                        <div class="grid grid-cols-12 gap-2 font-bold text-[10px] text-gray-500 uppercase tracking-wide px-1">
                            <div class="col-span-4">Serviço</div>
                            <div class="col-span-3">Profissional</div>
                            <div class="col-span-2">Início</div>
                            <div class="col-span-2">Fim</div>
                            <div class="col-span-1"></div>
                        </div>

                        @foreach($appointmentItems as $index => $item)
                            <div class="grid grid-cols-12 gap-2 items-end relative overflow-visible" wire:key="item-row-{{ $index }}">
                                <div class="col-span-4 relative overflow-visible">
                                    <x-text-input type="text"
                                                  wire:model="appointmentItems.{{ $index }}.service_search"
                                                  wire:input.debounce.300ms="searchAgendaService({{ $index }}, $event.target.value)"
                                                  wire:focus="$set('focusedItemIndex', {{ $index }})"
                                                  placeholder="Selecionar serviço"
                                                  class="w-full text-xs" required />

                                    @if($focusedItemIndex === $index && !empty($autocompleteServices))
                                        <div class="absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-xl z-[100] max-h-32 overflow-y-auto divide-y" wire:key="agenda-serv-dropdown-{{ $index }}">
                                            @foreach($autocompleteServices as $serv)
                                                <button type="button" wire:click="selectServiceItem({{ $index }}, {{ $serv['id'] }}, '{{ addslashes($serv['name']) }}')" class="w-full text-left px-3 py-1.5 hover:bg-indigo-50 text-xs block text-gray-700 font-medium transition">
                                                    {{ $serv['name'] }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="col-span-3">
                                    <select wire:model.live="appointmentItems.{{ $index }}.professional_id" class="w-full border-gray-300 rounded-lg shadow-sm text-xs" required>
                                        <option value="">-- Escolha --</option>
                                        @foreach($allProfessionals as $p)
                                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <x-text-input type="time" wire:model.live="appointmentItems.{{ $index }}.start_time" class="w-full text-xs" required />
                                </div>
                                <div class="col-span-2">
                                    <x-text-input type="time" wire:model.live="appointmentItems.{{ $index }}.end_time" class="w-full text-xs font-bold text-indigo-700 bg-indigo-50" required />
                                </div>
                                <div class="col-span-1 text-center">
                                    <button type="button" wire:click="removeItem({{ $index }})" class="text-red-500 hover:bg-red-50 rounded-md p-1.5 transition">✕</button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex justify-between items-center pt-4 border-t mt-4">
                        <button type="button" wire:click="addItem" class="text-xs font-bold text-indigo-600 px-3 py-1 bg-indigo-50 rounded hover:bg-indigo-100">+ Adicionar Serviço</button>
                        <div class="flex space-x-2">
                            <button type="button" wire:click="$set('showAppointmentModal', false)" class="px-4 py-2 border rounded-md text-xs bg-white shadow-sm">Cancelar</button>
                            @if($isEditingAppointment)
                                <button type="button" wire:click="faturarParaComanda" class="px-4 py-2 {{ $hasCommand ? 'bg-blue-600' : 'bg-green-600' }} text-white rounded-md text-xs font-black shadow-sm">
                                    {{ $hasCommand ? 'Abrir Comanda' : 'Criar Comanda' }}
                                </button>
                            @endif
                            <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md text-xs font-bold shadow-sm transition">Salvar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showCommandCheckoutModal && $activeCommand)
        <div class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500 opacity-75" wire:click="$set('showCommandCheckoutModal', false)"></div>
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl p-6 z-50 border border-gray-200 relative overflow-visible grid grid-cols-1 md:grid-cols-3 gap-6" wire:key="checkout-modal-root">

                <div class="md:col-span-2 overflow-visible">
                    @if($checkoutStep === 1)
                        <h3 class="text-lg font-bold text-gray-800 mb-1">
                            {{ $activeCommand->status === 'finished' ? 'Visualizando comanda' : 'Lançamento de Itens da Comanda' }} #{{ $activeCommand->code }}
                        </h3>
                        <p class="text-xs text-gray-400 mb-6">Controle operacional e faturamento dos serviços vinculados.</p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div>
                                <x-input-label value="Identificador / Número Comanda" class="text-gray-600 font-medium mb-1" />
                                <x-text-input type="text" value="{{ $activeCommand->code }}" class="w-full text-sm bg-gray-50 text-gray-500 font-semibold" disabled />
                            </div>
                            <div>
                                <x-input-label value="Cliente" class="text-gray-600 font-medium mb-1" />
                                <x-text-input type="text" value="{{ $selectedCustomerCrm?->name ?? 'Cliente' }}" class="w-full text-sm bg-gray-50 text-gray-500 font-semibold" disabled />
                            </div>
                        </div>

                        <div class="mb-6 overflow-visible">
                            <div class="flex justify-between items-center mb-2">
                                <h4 class="text-sm font-bold text-indigo-600">Serviços Executados</h4>
                                @if($activeCommand->status !== 'finished')
                                    <button type="button" wire:click="addCheckoutService" class="text-xs text-indigo-600 bg-indigo-50 border border-indigo-200 px-3 py-1 rounded-md font-bold hover:bg-indigo-100 transition">+ Serviço</button>
                                @endif
                            </div>
                            <div class="space-y-3 overflow-visible">
                                @foreach($checkoutServices as $sIndex => $sItem)
                                    <div class="flex items-center space-x-2 relative overflow-visible" wire:key="checkout-serv-row-{{ $sIndex }}">
                                        <div class="flex-1 relative overflow-visible">
                                            <x-text-input type="text"
                                                         wire:model="checkoutServices.{{ $sIndex }}.service_search"
                                                         wire:input.debounce.300ms="searchCheckoutService({{ $sIndex }}, $event.target.value)"
                                                         placeholder="Procure o serviço..."
                                                         class="w-full text-sm"
                                                         disabled="{{ $activeCommand->status === 'finished' ? 'disabled' : '' }}" />

                                            @if($focusedCheckoutServiceIndex === $sIndex && !empty($autocompleteServices))
                                                <div class="absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-xl z-[150] max-h-36 overflow-y-auto divide-y">
                                                    @foreach($autocompleteServices as $serv)
                                                        <button type="button" wire:click="selectCheckoutServiceItem({{ $sIndex }}, {{ $serv['id'] }}, '{{ addslashes($serv['name']) }}', {{ $serv['price'] }})" class="w-full text-left px-4 py-2 hover:bg-indigo-50 text-xs block text-gray-700 transition">{{ $serv['name'] }}</button>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <div class="w-64">
                                            <select wire:model="checkoutServices.{{ $sIndex }}.professional_id" class="w-full border-gray-300 rounded-md shadow-sm text-sm h-[38px]" disabled="{{ $activeCommand->status === 'finished' ? 'disabled' : '' }}">
                                                <option value="">-- Quem executou? --</option>
                                                @foreach($allProfessionals as $p)
                                                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="w-32">
                                            <x-text-input type="text" wire:model.live="checkoutServices.{{ $sIndex }}.price" class="w-full text-sm text-right font-semibold" placeholder="0,00" disabled="{{ $activeCommand->status === 'finished' ? 'disabled' : '' }}" />
                                        </div>
                                        @if($activeCommand->status !== 'finished')
                                            <button type="button" wire:click="removeCheckoutService({{ $sIndex }})" class="text-purple-500 hover:text-purple-700 text-lg px-2 font-bold">✕</button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-6 overflow-visible pt-4 border-t border-gray-100">
                            <div class="flex justify-between items-center mb-2">
                                <h4 class="text-sm font-bold text-emerald-600">Produtos para Levar / Revenda</h4>
                                @if($activeCommand->status !== 'finished')
                                    <button type="button" wire:click="addCheckoutProduct" class="text-xs text-emerald-600 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-md font-bold hover:bg-emerald-100 transition">+ Produto</button>
                                @endif
                            </div>
                            <div class="space-y-3 overflow-visible">
                                @foreach($checkoutProducts as $pIndex => $pItem)
                                    <div class="flex items-center space-x-2 relative overflow-visible" wire:key="checkout-prod-row-{{ $pIndex }}">
                                        <div class="flex-1 relative overflow-visible">
                                            <x-text-input type="text" wire:model="checkoutProducts.{{ $pIndex }}.product_search" wire:input.debounce.300ms="searchCheckoutProduct({{ $pIndex }}, $event.target.value)" wire:focus="$set('focusedCheckoutProductIndex', {{ $pIndex }})" placeholder="Procure o produto..." class="w-full text-sm" disabled="{{ $activeCommand->status === 'finished' ? 'disabled' : '' }}" />
                                            @if($focusedCheckoutProductIndex === $pIndex && !empty($autocompleteProducts))
                                                <div class="absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-xl z-[150] max-h-36 overflow-y-auto divide-y">
                                                    @foreach($autocompleteProducts as $prod)
                                                        <button type="button" wire:click="selectCheckoutProductItem({{ $pIndex }}, {{ $prod['id'] }}, '{{ addslashes($prod['name']) }}', {{ $prod['price'] }})" class="w-full text-left px-4 py-2 hover:bg-indigo-50 text-xs block text-gray-700 transition">{{ $prod['name'] }}</button>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <div class="w-24">
                                            <x-text-input type="number" min="1" wire:model.live="checkoutProducts.{{ $pIndex }}.quantity" class="w-full text-sm text-center" disabled="{{ $activeCommand->status === 'finished' ? 'disabled' : '' }}" />
                                        </div>
                                        <div class="w-32">
                                            <x-text-input type="text" wire:model.live="checkoutProducts.{{ $pIndex }}.price" class="w-full text-sm text-right font-semibold" placeholder="0,00" disabled="{{ $activeCommand->status === 'finished' ? 'disabled' : '' }}" />
                                        </div>
                                        @if($activeCommand->status !== 'finished')
                                            <button type="button" wire:click="removeCheckoutProduct({{ $pIndex }})" class="text-purple-500 hover:text-purple-700 text-lg px-2 font-bold">✕</button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="bg-gray-50/70 border rounded-xl p-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                            <div class="text-sm font-medium text-gray-600">Subtotal Serviços: <span class="font-bold text-gray-800 ml-1">R$ {{ number_format($subtotalServices, 2, ',', '.') }}</span></div>
                            <div class="text-sm font-medium text-gray-600">Subtotal Produtos: <span class="font-bold text-gray-800 ml-1">R$ {{ number_format($subtotalProducts, 2, ',', '.') }}</span></div>
                            <div>
                                <x-input-label value="Desconto Especial (R$)" class="text-gray-500 text-xs mb-0.5" />
                                <x-text-input type="number" step="0.01" wire:model.live="discount" class="w-full text-sm text-right font-bold text-rose-600 h-[34px]" placeholder="0,00" disabled="{{ $activeCommand->status === 'finished' ? 'disabled' : '' }}" />
                            </div>
                        </div>
                    @else
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-4">Concluir Pagamento</h3>
                            <div class="bg-gray-50/50 border border-gray-200 rounded-xl p-6 mb-6">
                                <span class="text-sm font-bold text-gray-500 uppercase tracking-wider block mb-1">Resumo da Cobrança:</span>
                                <div class="text-2xl font-black text-green-600">Total a Pagar: R$ {{ number_format($totalCalculado, 2, ',', '.') }}</div>

                                <div class="mt-4 max-w-md">
                                    <x-input-label value="Escolha a Forma de Pagamento *" class="text-gray-700 font-medium text-sm mb-1" />
                                    <select wire:model="payment_method_id" class="w-full border-gray-300 rounded-lg shadow-sm text-sm h-[40px]">
                                        <option value="">-- Selecione uma forma ativa --</option>
                                        @foreach($paymentMethods as $pm)
                                            <option value="{{ $pm->id }}">{{ $pm->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('payment_method_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="bg-gray-50/50 border-l p-4 flex flex-col justify-between rounded-r-xl min-h-[450px]">
                    <div>
                        <h4 class="text-sm font-bold text-gray-800 mb-4">Pagamentos</h4>

                        <div class="space-y-4 mb-6">
                            <span class="text-[10px] uppercase font-bold text-gray-400 block border-b pb-1">Transações Realizadas</span>

                            @if($activeCommand->status === 'finished')
                                <div class="flex justify-between items-start bg-white p-3 rounded-lg border shadow-sm">
                                    <div>
                                        <div class="text-xs font-bold text-gray-800 uppercase">
                                            ✓ {{ $savedPaymentName }}
                                        </div>
                                        <span class="text-[10px] text-gray-400 block mt-0.5">{{ \Carbon\Carbon::parse($activeCommand->updated_at)->format('d/m/Y') }}</span>
                                        <span class="inline-flex items-center px-2 py-0.5 mt-1.5 rounded-full text-[9px] font-black bg-green-100 text-green-800 uppercase tracking-wide">✓ Pago</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-sm font-black text-gray-900">R$ {{ number_format($activeCommand->total_amount, 2, ',', '.') }}</span>
                                    </div>
                                </div>
                            @else
                                <div class="text-xs text-gray-400 italic py-2 text-center">Nenhum pagamento liquidado ainda. Comanda em aberto.</div>
                            @endif
                        </div>

                        <div class="space-y-2 border-t pt-4">
                            <span class="text-[10px] uppercase font-bold text-gray-400 block mb-1">Resumo da compra</span>
                            <div class="flex justify-between text-xs text-gray-600">
                                <span>Descontos</span>
                                <span>R$ {{ number_format($discount, 2, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-xs text-gray-600">
                                <span>Total</span>
                                <span>R$ {{ number_format($subtotalServices + $subtotalProducts, 2, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-sm font-bold text-gray-900 border-t pt-2 mt-1">
                                <span>Total pago</span>
                                <span class="font-black text-indigo-700">R$ {{ number_format($activeCommand->status === 'finished' ? $activeCommand->total_amount : 0.00, 2, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-200 mt-6 flex flex-col space-y-2">
                        @if($activeCommand->status === 'finished')
                            <button type="button" wire:click="reabrirComanda" class="w-full py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-black shadow-md transition text-center">
                                🔓 Reabrir Comanda / Corrigir
                            </button>
                            <button type="button" wire:click="$set('showCommandCheckoutModal', false)" class="w-full py-2 bg-gray-800 text-white rounded-lg text-xs font-bold shadow-sm hover:bg-gray-900 transition">Fechar Visualização</button>
                        @else
                            @if($checkoutStep === 1)
                                <button type="button" wire:click="$set('showCommandCheckoutModal', false)" class="px-4 py-2 border rounded-md text-sm font-bold text-gray-700 bg-white hover:bg-gray-50 shadow-sm">Cancelar</button>
                                <button type="button" wire:click="$set('showCommandCheckoutModal', false)" class="px-4 py-2 bg-slate-700 hover:bg-slate-800 text-white rounded-md text-sm font-bold shadow-sm">Deixar em Aberto</button>
                                <button type="button" wire:click="avancarParaPagamento" class="px-5 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md text-sm font-black shadow-md transition text-center">Ir para Pagamento ➜</button>
                            @else
                                <button type="button" wire:click="$set('checkoutStep', 1)" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-600 bg-white hover:bg-gray-50 shadow-sm">← Voltar aos itens</button>
                                <button type="button" wire:click="fecharComandaComoPaga" class="px-5 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md text-sm font-black shadow-sm transition text-center">Finalizar e Salvar ✓</button>
                            @endif
                        @endif
                    </div>
                </div>

            </div>
        </div>
    @endif

    @if($showBlockModal)
        <div class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500 opacity-75" wire:click="$set('showBlockModal', false)"></div>
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 z-50">
                <h3 class="text-base font-black text-gray-900 mb-4">🔒 Bloquear Agenda</h3>
                <form wire:submit.prevent="saveBlock" class="space-y-4 text-sm">
                    <div>
                        <x-input-label value="Profissional Afetado *" />
                        <select wire:model="block_professional_id" class="w-full mt-1 border-gray-300 rounded-lg shadow-sm text-sm" required>
                            <option value="">-- Escolha o Colaborador --</option>
                            @foreach($professionals as $prof)
                                <option value="{{ $prof->id }}">{{ $prof->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Motivo / Título *" />
                        <x-text-input type="text" class="w-full mt-1 text-sm" wire:model="block_title" required />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Início" />
                            <x-text-input type="time" wire:model="block_start_time" class="w-full mt-1 text-sm" required />
                        </div>
                        <div>
                            <x-text-input type="time" wire:model="block_end_time" class="w-full mt-1 text-sm" required />
                        </div>
                    </div>
                    <div class="flex justify-end space-x-2 pt-4 border-t mt-6">
                        <button type="button" wire:click="$set('showBlockModal', false)" class="px-4 py-2 border rounded-md text-sm text-gray-700 bg-white">Voltar</button>
                        <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-md text-sm font-medium">Bloquear Horário</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
