<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\CustomerAnamnesis;
use App\Models\AnamnesisTemplate;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.guest')] class extends Component
{
    public string $token;
    public ?CustomerAnamnesis $anamnesis = null;
    public ?AnamnesisTemplate $template = null;
    public array $responses = [];
    public bool $isCompleted = false;

    // Variável para armazenar a URL do Logo da Clínica
    public ?string $companyLogoUrl = null;

    public function mount(string $token): void
    {
        $this->token = $token;

        $this->anamnesis = CustomerAnamnesis::withoutGlobalScopes()
            ->with('service')
            ->where('token', $token)
            ->firstOrFail();

        // BUSCA ÚNICA: Apenas pelo Tenant
        $tenant = \App\Models\Tenant::find($this->anamnesis->tenant_id);
        if ($tenant && $tenant->logo) {
            $this->companyLogoUrl = asset('storage/' . $tenant->logo);
        }

        $this->isCompleted = $this->anamnesis->is_completed;

        // Se ainda não foi preenchida, carrega o template e prepara o formulário
        if (!$this->isCompleted) {
            $this->template = AnamnesisTemplate::withoutGlobalScopes()
                ->find($this->anamnesis->service->anamnesis_template_id);

            if ($this->template && $this->template->form_schema) {
                foreach ($this->template->form_schema as $field) {
                    $this->responses[$field['name']] = '';
                }
            }
        }
    }

    public function submit(): void
    {
        // Salva todas as respostas no banco de dados e marca como concluído
        $this->anamnesis->update([
            'responses' => $this->responses,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $this->isCompleted = true;
    }
}; ?>

<div class="min-h-screen flex flex-col justify-center items-center pt-6 sm:pt-0 bg-gray-50">
    <div class="w-full sm:max-w-md mt-6 px-6 py-8 bg-white shadow-xl overflow-hidden sm:rounded-2xl border border-gray-100">

        <div class="text-center mb-6">
            @if($companyLogoUrl)
                <img src="{{ $companyLogoUrl }}" alt="Logo da Clínica" class="h-16 mx-auto mb-4 object-contain">
            @endif

            <h2 class="text-2xl font-black text-gray-900 tracking-tight">
                Ficha de Anamnese
            </h2>

            @if($template && !$isCompleted)
                <p class="mt-3 text-xs text-indigo-700 font-bold bg-indigo-50 py-1.5 px-4 rounded-full inline-block border border-indigo-100">
                    Procedimento: {{ $anamnesis->service->name ?? 'Serviço' }}
                </p>
            @endif
        </div>

        @if($isCompleted)
            <div class="rounded-xl bg-emerald-50 p-6 border border-emerald-100 mt-4">
                <div class="flex flex-col items-center text-center">
                    <div class="flex-shrink-0 mb-4 bg-emerald-100 p-3 rounded-full">
                        <svg class="h-10 w-10 text-emerald-600" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-black text-emerald-800 mb-2">
                            Tudo certo!
                        </h3>
                        <div class="text-sm text-emerald-700 space-y-2">
                            <p>Sua ficha de anamnese foi enviada com sucesso e já está anexada de forma segura ao seu perfil na clínica.</p>
                            <p class="font-bold pt-2">Aguardamos você para o seu atendimento!</p>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <form wire:submit="submit" class="space-y-6 mt-4">
                @if($template && $template->form_schema)
                    @foreach($template->form_schema as $field)
                        <div class="bg-gray-50/50 p-4 rounded-xl border border-gray-100">
                            <label for="{{ $field['name'] }}" class="block font-bold text-sm text-gray-800 mb-2">
                                {{ $field['label'] }}
                                @if(isset($field['required']) && $field['required'])
                                    <span class="text-red-500 font-black ml-1">*</span>
                                @endif
                            </label>

                            <div class="mt-1">
                                @if($field['type'] === 'text')
                                    <x-text-input type="text"
                                           wire:model="responses.{{ $field['name'] }}"
                                           id="{{ $field['name'] }}"
                                           class="block w-full text-sm rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 transition-colors"
                                           placeholder="Sua resposta..."
                                           required="{{ isset($field['required']) && $field['required'] ? 'required' : '' }}" />

                                @elseif($field['type'] === 'radio')
                                    <div class="space-y-3 mt-3">
                                        @foreach($field['options'] as $option)
                                            <label for="{{ $field['name'] }}_{{ $loop->index }}" class="flex items-center p-3 bg-white border border-gray-200 rounded-lg cursor-pointer hover:bg-indigo-50 hover:border-indigo-200 transition-colors">
                                                <input type="radio"
                                                       wire:model="responses.{{ $field['name'] }}"
                                                       id="{{ $field['name'] }}_{{ $loop->index }}"
                                                       value="{{ $option }}"
                                                       class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300"
                                                       {{ isset($field['required']) && $field['required'] ? 'required' : '' }}>
                                                <span class="ml-3 block text-sm font-medium text-gray-700">
                                                    {{ $option }}
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>

                                @elseif($field['type'] === 'textarea')
                                    <textarea wire:model="responses.{{ $field['name'] }}"
                                              id="{{ $field['name'] }}"
                                              rows="4"
                                              class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-lg shadow-sm block w-full text-sm transition-colors"
                                              placeholder="Detalhe sua resposta aqui..."
                                              required="{{ isset($field['required']) && $field['required'] ? 'required' : '' }}"></textarea>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="p-4 bg-red-50 text-red-700 rounded-lg text-sm border border-red-200 font-medium text-center">
                        Estrutura do formulário não encontrada. Por favor, contate a clínica.
                    </div>
                @endif

                <div class="pt-6">
                    <button type="submit" class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-sm text-sm font-black text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 uppercase tracking-wide transition-all transform hover:scale-[1.02]">
                        Enviar Ficha de Anamnese
                    </button>
                </div>
            </form>
        @endif

    </div>
</div>
