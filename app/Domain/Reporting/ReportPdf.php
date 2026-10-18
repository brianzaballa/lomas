<?php

namespace App\Domain\Reporting;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Http\Response;

/**
 * Renders a Blade report template to PDF through dompdf.
 *
 * Reports are server-rendered from dedicated, print-only templates, never by
 * printing the browser page. The renderer is intentionally a thin wrapper: it
 * fixes the shared options so every report is produced the same way, and keeps
 * controllers free of PDF plumbing. See AGENTS.md sections 36, 57 and 58.
 *
 * Remote resources are disabled so a branch can render a report while fully
 * offline. Inline PHP is enabled only so a template can draw page numbers with
 * dompdf's canvas; templates are ours, and all values they print are escaped by
 * Blade.
 */
class ReportPdf
{
    /**
     * Render the report for viewing in the browser, where it can be printed.
     *
     * @param  array<string, mixed>  $data
     */
    public function inline(string $view, array $data, string $filename, string $paper = 'a4', string $orientation = 'portrait'): Response
    {
        return $this->make($view, $data, $paper, $orientation)->stream($filename);
    }

    /**
     * Render the report as an attachment.
     *
     * @param  array<string, mixed>  $data
     */
    public function download(string $view, array $data, string $filename, string $paper = 'a4', string $orientation = 'portrait'): Response
    {
        return $this->make($view, $data, $paper, $orientation)->download($filename);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function make(string $view, array $data, string $paper, string $orientation): DomPdf
    {
        return Pdf::loadView($view, $data)
            ->setPaper($paper, $orientation)
            ->setOption('isRemoteEnabled', false)
            ->setOption('isPhpEnabled', true);
    }
}
