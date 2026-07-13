<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>POP - {{ $template->title }}</title>
    <style>
        /* Margens normais, apenas com espaço na base para o rodapé */
        @page {
            margin: 50px 40px 70px 40px;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.6;
        }

        /* --- RODAPÉ FIXO (TODAS AS PÁGINAS) --- */
        footer {
            position: fixed;
            bottom: -40px;
            left: 0;
            right: 0;
            height: 30px;
            text-align: right;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 5px;
        }

        /* Contador de páginas (usando apenas a página atual para evitar o bug do "de 0") */
        .pagenum:before {
            content: counter(page);
        }

        /* --- ESTILOS DA CAPA --- */
        .capa {
            text-align: center;
            margin-top: 150px;
        }
        .capa-logo {
            max-width: 400px;
            max-height: 250px;
            margin-bottom: 50px;
            object-fit: contain;
        }
        .capa h1 {
            font-size: 26px;
            text-transform: uppercase;
            margin-bottom: 10px;
            color: #111;
        }
        .capa h2 {
            font-size: 18px;
            color: #555;
            font-weight: normal;
        }
        .capa-footer {
            margin-top: 100px;
            font-size: 14px;
        }

        .page-break {
            page-break-after: always;
        }

        /* --- ESTILOS DA TABELA (APENAS NA PÁGINA 2) --- */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            margin-bottom: 25px;
        }
        .header-table td {
            border: 1px solid #000;
            padding: 6px 10px;
            text-align: left;
            vertical-align: middle;
        }
        .logo-cell {
            width: 25%;
            text-align: center;
        }
        .logo-cell img {
            max-width: 130px;
            max-height: 75px;
            object-fit: contain;
        }
        .title-cell {
            width: 45%;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            text-transform: uppercase;
        }
        .meta-cell {
            width: 30%;
            font-size: 10px;
            line-height: 1.5;
        }

        /* --- ESTILOS DO CONTEÚDO --- */
        .content {
            margin-top: 10px;
        }
        .content h1, .content h2, .content h3 {
            font-size: 14px;
            color: #000;
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 10px;
        }
        .content p {
            margin-bottom: 10px;
            text-align: justify;
        }
        .content ul, .content ol {
            padding-left: 20px;
            margin-bottom: 15px;
        }
        .content li {
            margin-bottom: 6px;
            text-align: justify;
        }
    </style>
</head>
<body>

    @php
        $logoBase64 = null;
        if ($tenant->logo) {
            $path = storage_path('app/public/' . $tenant->logo);
            if (!file_exists($path)) {
                $path = public_path('storage/' . $tenant->logo);
            }
            if (file_exists($path)) {
                $type = pathinfo($path, PATHINFO_EXTENSION);
                $data = file_get_contents($path);
                $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
            }
        }
    @endphp

    <footer>
        Página <span class="pagenum"></span>
    </footer>

    <div class="capa">
        @if($logoBase64)
            <img src="{{ $logoBase64 }}" alt="Logo" class="capa-logo">
        @else
            <h1 style="font-size: 32px; margin-bottom: 40px;">{{ $tenant->name }}</h1>
        @endif

        <h1>Procedimento Operacional Padrão</h1>
        <h2>POP - {{ $template->title }}</h2>

        <div class="capa-footer">
            <p><strong>Empresa Licenciada:</strong><br>{{ $tenant->name }}<br>CNPJ: {{ $tenant->document_cpf_cnpj }}</p>
        </div>
    </div>

    <div class="page-break"></div>

    <table class="header-table">
        <tr>
            <td rowspan="3" class="logo-cell">
                @if($logoBase64)
                    <img src="{{ $logoBase64 }}" alt="Logo">
                @else
                    <strong>{{ $tenant->name }}</strong>
                @endif
            </td>
            <td rowspan="3" class="title-cell">
                <span style="font-size: 10px; font-weight: normal; display: block; margin-bottom: 5px; color: #555;">{{ $tenant->name }}</span>
                POP: {{ $template->title }}
            </td>
            <td class="meta-cell"><strong>Nº Identificação:</strong> POP {{ str_pad($template->id, 3, '0', STR_PAD_LEFT) }}</td>
        </tr>
        <tr>
            <td class="meta-cell"><strong>Data de Emissão:</strong> {{ $template->created_at ? $template->created_at->format('d/m/Y') : date('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="meta-cell"><strong>Data de Revisão:</strong> {{ $template->created_at ? $template->created_at->addYear()->format('d/m/Y') : date('d/m/Y', strtotime('+1 year')) }}</td>
        </tr>
    </table>

    <main class="content">
        {!! $content !!}
    </main>

</body>
</html>
