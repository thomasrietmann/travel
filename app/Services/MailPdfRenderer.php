<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;

class MailPdfRenderer
{
    public function render(string $subject, string $body): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $pdf = new Dompdf($options);
        // Only application-owned markup reaches the renderer; mail HTML/CSS/URLs are text.
        $pdf->loadHtml('<!doctype html><html><head><meta charset="utf-8"><style>body { font-family: DejaVu Sans; font-size: 11px; } pre { white-space: pre-wrap; }</style></head><body><h1>'.e($subject).'</h1><pre>'.e(Str::limit($body, 12000, '')).'</pre></body></html>', 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}
