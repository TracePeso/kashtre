@props([
    'branding',
    'previewTitle' => 'SAMPLE DOCUMENT',
    'previewSubtitle' => 'DOC-PREVIEW-001',
])

@php
    /** @var \App\Support\BusinessBranding $branding */
    $generatedAt = now();
    $logoUrl = $branding->logoUrl();
@endphp

<div class="rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/40 p-4 sm:p-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h4 class="text-base font-semibold text-gray-900 dark:text-white">Document preview</h4>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Live preview of the header and footer applied to invoices, quotations, RFQs, LPOs, receipts, and exports.
            </p>
        </div>
        <a href="#name" class="text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
            Edit branding fields ↑
        </a>
    </div>

    <div class="mx-auto max-w-2xl rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
        {{-- Header (matches PDF inline layout) --}}
        <div class="p-6 sm:p-8 border-b border-gray-100">
            <div class="flex justify-between items-start gap-4">
                <div class="flex gap-4 items-start min-w-0">
                    <div id="letterhead-preview-logo-wrap" class="{{ $logoUrl ? '' : 'hidden' }} shrink-0">
                        <img
                            id="letterhead-preview-logo"
                            src="{{ $logoUrl }}"
                            alt="Institution logo"
                            class="max-h-16 max-w-[120px] object-contain"
                        >
                    </div>
                    <div id="letterhead-preview-logo-placeholder" class="{{ $logoUrl ? 'hidden' : '' }} h-16 w-16 shrink-0 rounded border border-dashed border-gray-300 bg-gray-50 flex items-center justify-center text-[10px] text-gray-400 text-center px-1">
                        No logo
                    </div>
                    <div class="min-w-0">
                        <div id="letterhead-preview-name" class="text-lg font-bold text-slate-800 break-words">
                            {{ $branding->name() ?: 'Company name' }}
                        </div>
                        <div id="letterhead-preview-address" class="text-sm text-slate-500 mt-1 break-words">
                            {{ $branding->address() ?: 'Company address' }}
                        </div>
                        <div id="letterhead-preview-phone" class="text-xs text-slate-500 mt-1 {{ $branding->phone() ? '' : 'hidden' }}">
                            Tel: <span id="letterhead-preview-phone-value">{{ $branding->phone() }}</span>
                        </div>
                        <div id="letterhead-preview-email" class="text-xs text-slate-500 mt-1 {{ $branding->email() ? '' : 'hidden' }}">
                            Email: <span id="letterhead-preview-email-value">{{ $branding->email() }}</span>
                        </div>
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-base font-bold text-slate-800">{{ $previewTitle }}</div>
                    <div class="text-sm text-slate-500 mt-1">{{ $previewSubtitle }}</div>
                    <div id="letterhead-preview-generated" class="text-xs text-slate-400 mt-2">
                        Generated: {{ $generatedAt->format('d M Y H:i') }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Sample body --}}
        <div class="px-6 sm:px-8 py-10 text-center">
            <div class="inline-block rounded-md border border-dashed border-gray-300 bg-gray-50 px-6 py-8 text-sm text-gray-400">
                Document content area<br>
                <span class="text-xs">(invoice lines, report tables, etc.)</span>
            </div>
        </div>

        {{-- Footer --}}
        <div class="px-6 sm:px-8 pb-6 sm:pb-8 pt-4 border-t border-gray-200 text-center text-[10px] text-gray-500 leading-relaxed">
            <div id="letterhead-preview-footer-name" class="font-semibold text-gray-700 text-[11px]">
                {{ $branding->name() ?: 'Company name' }}
            </div>
            <div id="letterhead-preview-footer-address" class="mt-1">
                {{ $branding->address() ?: 'Company address' }}
            </div>
            <div class="mt-2 italic">This is a system-generated document.</div>
            @include('partials.kashtre-document-credit', ['style' => 'preview'])
        </div>
    </div>

    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
        Changes to name, address, phone, email, and logo update here as you type. Save settings to apply them to new documents.
        The “Powered by” line and website link are configured in Kashtre Settings
        @if((int) auth()->user()?->business_id === 1)
            (<a href="{{ route('settings.kashtre.edit') }}" class="text-blue-600 hover:text-blue-800 dark:text-blue-400">edit</a>).
        @else
            .
        @endif
    </p>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const nameInput = document.getElementById('name');
        const addressInput = document.getElementById('address');
        const phoneInput = document.getElementById('phone');
        const emailInput = document.getElementById('email');
        const logoInput = document.getElementById('logo');

        if (!nameInput) {
            return;
        }

        const previewName = document.getElementById('letterhead-preview-name');
        const previewFooterName = document.getElementById('letterhead-preview-footer-name');
        const previewAddress = document.getElementById('letterhead-preview-address');
        const previewFooterAddress = document.getElementById('letterhead-preview-footer-address');
        const previewPhone = document.getElementById('letterhead-preview-phone');
        const previewPhoneValue = document.getElementById('letterhead-preview-phone-value');
        const previewEmail = document.getElementById('letterhead-preview-email');
        const previewEmailValue = document.getElementById('letterhead-preview-email-value');
        const previewLogo = document.getElementById('letterhead-preview-logo');
        const previewLogoWrap = document.getElementById('letterhead-preview-logo-wrap');
        const previewLogoPlaceholder = document.getElementById('letterhead-preview-logo-placeholder');
        const previewGenerated = document.getElementById('letterhead-preview-generated');

        function updateGeneratedLabel() {
            if (!previewGenerated) {
                return;
            }

            const now = new Date();
            const formatted = now.toLocaleString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false,
            }).replace(',', '');

            previewGenerated.textContent = 'Generated: ' + formatted;
        }

        function syncPreview() {
            const name = (nameInput.value || '').trim() || 'Company name';
            const address = (addressInput.value || '').trim() || 'Company address';
            const phone = (phoneInput.value || '').trim();
            const email = (emailInput.value || '').trim();

            previewName.textContent = name;
            previewFooterName.textContent = name;
            previewAddress.textContent = address;
            previewFooterAddress.textContent = address;

            if (phone) {
                previewPhone.classList.remove('hidden');
                previewPhoneValue.textContent = phone;
            } else {
                previewPhone.classList.add('hidden');
            }

            if (email) {
                previewEmail.classList.remove('hidden');
                previewEmailValue.textContent = email;
            } else {
                previewEmail.classList.add('hidden');
            }

            updateGeneratedLabel();
        }

        [nameInput, addressInput, phoneInput, emailInput].forEach(function (input) {
            input.addEventListener('input', syncPreview);
        });

        if (logoInput) {
            logoInput.addEventListener('change', function () {
                const file = logoInput.files && logoInput.files[0];

                if (!file) {
                    return;
                }

                const objectUrl = URL.createObjectURL(file);
                previewLogo.src = objectUrl;
                previewLogoWrap.classList.remove('hidden');
                previewLogoPlaceholder.classList.add('hidden');
            });
        }

        syncPreview();

        if (window.location.hash === '#document-letterhead' || window.location.hash === '#document-letterhead-preview') {
            const target = document.getElementById(window.location.hash.replace('#', ''));
            if (target) {
                setTimeout(function () {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 100);
            }
        }
    });
</script>
