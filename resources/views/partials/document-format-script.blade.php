@php
    $documentBranding = $documentBranding ?? \App\Support\BusinessBranding::for($business ?? auth()->user()?->business);
@endphp
@if($documentBranding)
<script>
(function () {
    const branding = {
        name: @json($documentBranding->name()),
        address: @json($documentBranding->address()),
        phone: @json($documentBranding->phone()),
        email: @json($documentBranding->email()),
        logoUrl: @json($documentBranding->logoUrl()),
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function generatedLabel() {
        const now = new Date();
        return now.toLocaleString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        }).replace(',', '');
    }

    window.KashtreDocumentFormat = {
        branding,
        headerHtml(documentTitle, documentSubtitle) {
            const logo = branding.logoUrl
                ? `<img src="${escapeHtml(branding.logoUrl)}" alt="" style="max-height:48px;max-width:96px;object-fit:contain;">`
                : '';
            const phone = branding.phone ? `<div style="font-size:11px;color:#6b7280;">Tel: ${escapeHtml(branding.phone)}</div>` : '';
            const email = branding.email ? `<div style="font-size:11px;color:#6b7280;">Email: ${escapeHtml(branding.email)}</div>` : '';
            const title = documentTitle ? `<div style="font-size:14px;font-weight:bold;color:#1f2937;">${escapeHtml(documentTitle)}</div>` : '';
            const subtitle = documentSubtitle ? `<div style="font-size:12px;color:#6b7280;margin-top:4px;">${escapeHtml(documentSubtitle)}</div>` : '';

            return `<div style="display:flex;justify-content:space-between;gap:12px;padding-bottom:12px;margin-bottom:12px;border-bottom:1px solid #e5e7eb;">`
                + `<div style="display:flex;gap:12px;align-items:flex-start;">${logo}`
                + `<div><div style="font-size:15px;font-weight:bold;color:#1f2937;">${escapeHtml(branding.name)}</div>`
                + `<div style="font-size:12px;color:#6b7280;margin-top:2px;">${escapeHtml(branding.address || '')}</div>${phone}${email}</div></div>`
                + `<div style="text-align:right;min-width:120px;">${title}${subtitle}`
                + `<div style="font-size:10px;color:#9ca3af;margin-top:6px;">Generated: ${generatedLabel()}</div></div></div>`;
        },
        footerHtml(extraLines) {
            const lines = Array.isArray(extraLines) ? extraLines : (extraLines ? [extraLines] : []);
            const extras = lines.map((line) => `<div style="margin-top:4px;">${escapeHtml(line)}</div>`).join('');

            return `<div style="margin-top:16px;padding-top:10px;border-top:1px solid #e5e7eb;text-align:center;font-size:10px;color:#6b7280;">`
                + `<div style="font-weight:600;color:#374151;">${escapeHtml(branding.name)}</div>`
                + `<div>${escapeHtml(branding.address || '')}</div>${extras}`
                + `<div style="margin-top:8px;font-style:italic;">This is a system-generated document.</div>`
                + `<div style="margin-top:8px;font-size:8px;color:#9ca3af;">Powered by Kashtre</div></div>`;
        },
    };
})();
</script>
@endif
