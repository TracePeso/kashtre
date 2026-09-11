<?php

namespace App\Contracts;

use App\Models\ImagingStudy;

/**
 * Pillars 7 & 8: PACS Integration + Image Viewing. Bound to a stub
 * (StubPacsClient) in AppServiceProvider until a real PACS exists.
 */
interface PacsClient
{
    /**
     * URL of the embedded/external viewer for this study, or null if PACS
     * isn't configured — callers should treat null as "not available yet",
     * not an error.
     */
    public function viewerUrl(ImagingStudy $study): ?string;

    public function archive(ImagingStudy $study): bool;

    public function retrieve(ImagingStudy $study): bool;
}
