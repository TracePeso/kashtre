@if(! empty($branding))
    <x-business.document-footer
        :branding="$branding"
        :extra-lines="$footerExtraLines ?? []"
        :show-disclaimer="$footerShowDisclaimer ?? true"
        :show-kashtre-credit="$showKashtreCredit ?? true"
    />
@endif
