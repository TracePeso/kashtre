<?php

namespace App\Services;

use App\Models\Business;
use App\Support\BusinessBranding;
use App\Support\DocumentViewData;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfInstance;
use Illuminate\Http\Response;

class DocumentPdfService
{
    public function resolveBranding(?Business $business): ?BusinessBranding
    {
        return BusinessBranding::for($business);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function render(
        string $view,
        array $data = [],
        ?Business $business = null,
        string $paper = 'a4',
        string $orientation = 'portrait',
    ): PdfInstance {
        $data = DocumentViewData::merge($data, $business);
        $data['forPdf'] = true;

        return Pdf::loadView($view, $data)->setPaper($paper, $orientation);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function download(
        string $view,
        array $data,
        ?Business $business,
        string $filename,
        string $paper = 'a4',
        string $orientation = 'portrait',
    ): Response {
        return $this->render($view, $data, $business, $paper, $orientation)->download($filename);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function output(
        string $view,
        array $data = [],
        ?Business $business = null,
        string $paper = 'a4',
        string $orientation = 'portrait',
    ): string {
        return $this->render($view, $data, $business, $paper, $orientation)->output();
    }
}
