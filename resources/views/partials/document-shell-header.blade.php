@if(! empty($branding))
    <x-business.document-header
        :branding="$branding"
        :document-title="$documentTitle ?? null"
        :document-subtitle="$documentSubtitle ?? null"
        :branch-name="$branchName ?? null"
        :layout="$headerLayout ?? 'inline'"
        :generated-at="$generatedAt ?? now()"
    />
@endif
