<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Tenant;

class PdfGeneratorService
{
    public function generatePop($template, Tenant $tenant)
    {
        // Substituição básica de variáveis no conteúdo
        $content = str_replace(
            ['{{tenant_name}}', '{{tenant_cnpj}}'],
            [$tenant->name, $tenant->document_cpf_cnpj],
            $template->content
        );

        // ATENÇÃO AQUI: Passando o $template para a view também
        $html = view('pdfs.pop_template', [
            'content' => $content,
            'tenant' => $tenant,
            'template' => $template
        ])->render();

        return Pdf::loadHtml($html)
            ->setPaper('a4')
            ->setOptions(['isRemoteEnabled' => true, 'isHtml5ParserEnabled' => true]);
    }
}
