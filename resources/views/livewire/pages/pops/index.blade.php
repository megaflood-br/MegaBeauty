<?php

use App\Models\PopTemplate;
use App\Services\PdfGeneratorService;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    public int $perPage = 8;

    public function loadMore()
    {
        $this->perPage += 8;
    }

    public function download($id)
    {
        $template = PopTemplate::findOrFail($id);
        $tenant = auth()->user()->tenant;
        $pdfService = new PdfGeneratorService();

        return response()->streamDownload(function () use ($pdfService, $template, $tenant) {
            echo $pdfService->generatePop($template, $tenant)->output();
        }, "POP_{$template->title}.pdf");
    }

    public function with(): array
    {
        // 1. Primeiro buscamos os dados
        $pops = PopTemplate::whereNull('tenant_id')
                        ->orWhere('tenant_id', auth()->user()->tenant_id)
                        ->orderBy('created_at', 'desc')
                        ->take($this->perPage)
                        ->get();

        // 2. Agora retornamos tudo corretamente
        return [
            'popsList' => $pops,
            'totalCount' => PopTemplate::count(),
            'groupedPops' => $pops->groupBy('category'), // Agora o $pops existe!
        ];
    }
}; ?>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="flex flex-col md:flex-row justify-between items-center mb-8 space-y-4 md:space-y-0">
    <div>
        <h2 class="text-2xl font-bold text-gray-800 leading-tight">
            Procedimentos Operacionais Padrão
        </h2>

        <p class="text-sm text-gray-500 mt-1">Baixe os POPs oficiais com os dados da sua clínica.</p>
    </div>

    @if(in_array(auth()->user()->role, ['superadmin']))
    <a href="{{ route('pops.create') }}" wire:navigate class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
        + Novo POP Administrativo
    </a>
@endif
</div>

        @if($groupedPops->count() > 0)
   @foreach($groupedPops as $category => $pops)
    <div class="mb-12">
        <h3 class="text-xl font-bold text-gray-800 mb-6 border-b pb-2 flex items-center">
            <span class="bg-indigo-600 w-2 h-6 rounded mr-3"></span>
            {{ $category }}
        </h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            @foreach($pops as $pop)
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 flex flex-col overflow-hidden group">

                    <div class="p-6 flex-1 flex flex-col items-center text-center relative">
                        <div class="w-16 h-16 bg-indigo-50 text-indigo-500 rounded-full flex items-center justify-center mb-4 group-hover:scale-110 transition-transform duration-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h3 class="font-bold text-gray-800 text-lg mb-1 leading-snug line-clamp-2" title="{{ $pop->title }}">
                            {{ $pop->title }}
                        </h3>
                        <p class="text-xs text-gray-400 mt-2">Pronto para impressão</p>
                    </div>

                    <div class="bg-gray-50 p-4 border-t border-gray-100 mt-auto space-y-2">
                        <button wire:click="download({{ $pop->id }})" class="w-full flex items-center justify-center px-4 py-2.5 bg-indigo-600 rounded-lg font-bold text-xs text-white uppercase hover:bg-indigo-700 transition">
                            Baixar PDF
                        </button>

                        @if(in_array(auth()->user()->role, [ 'superadmin']))
                            <a href="{{ route('pops.edit', $pop->id) }}" wire:navigate class="w-full flex items-center justify-center px-4 py-2.5 bg-orange-500 rounded-lg font-bold text-xs text-white uppercase hover:bg-orange-600 transition">
                                Editar POP
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endforeach
@else
    @endif

    </div>
</div>
