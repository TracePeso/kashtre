<?php

namespace App\Http\Controllers;

use App\Contracts\Clinical\CareTransitionsGateway;
use App\Support\Clinical\ClinicalActor;
use Illuminate\Support\Facades\Auth;

/**
 * Streams a Care Transition discharge document's PDF (v6.1 Volume 8) —
 * same proxy shape as ClinicalFhirExportController: the browser cannot hold
 * the service key Clinical requires, so Main fetches the bytes server-side
 * and streams them through.
 */
class CareTransitionDocumentController extends Controller
{
    public function download(string $document, CareTransitionsGateway $gateway)
    {
        abort_unless(in_array('View Care Transitions', Auth::user()->permissions ?? []), 403);

        $result = $gateway->downloadDocument(ClinicalActor::fromUser(Auth::user()), $document);

        return response($result['body'], 200, [
            'Content-Type' => $result['content_type'],
            'Content-Disposition' => 'inline; filename="discharge-document-'.$document.'.pdf"',
        ]);
    }
}
