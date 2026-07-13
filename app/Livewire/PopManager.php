<?php

namespace App\Livewire;

use Livewire\Component;

class PopManager extends Component
{
    public function downloadPop($popId)
{
    $pop = \App\Models\PopTemplate::findOrFail($popId); // O Scope filtra automaticamente o tenant
    $tenant = auth()->user()->tenant;

    $pdfService = new \App\Services\PdfGeneratorService();
    return $pdfService->generatePop($pop, $tenant)->stream("POP_{$pop->title}.pdf");
}
}
