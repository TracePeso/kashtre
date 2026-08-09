<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Medications &amp; MAR</h4>

    @if ($resultMessage)
        <div class="mb-4 text-xs rounded p-2 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ $resultMessage }}</div>
    @endif

    {{-- Refusals from the Clinical Module. These messages are written for a
         clinician to read and act on, so they are shown verbatim rather than
         collapsed into a generic failure notice. --}}
    @if ($errorMessage)
        <div class="mb-4 text-xs rounded p-2 bg-amber-50 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200">
            {{ $errorMessage }}
        </div>
    @endif

    {{-- Nothing in the catalogue matched. Rather than blocking the prescriber,
         confirming here generates an audited external referral instead. --}}
    @if ($awaitingExternalConfirmation)
        <div class="mb-4 bg-amber-50 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 rounded p-3">
            <div class="text-xs font-medium text-amber-800 dark:text-amber-200 mb-2">
                No internal stock matches &ldquo;{{ $drugSearch }}&rdquo;. Prescribe it as an external referral?
            </div>
            <div class="flex gap-2">
                <button wire:click="prescribe" class="text-xs text-white bg-amber-600 hover:bg-amber-700 rounded px-3 py-1">
                    Confirm External Referral
                </button>
                <button wire:click="$set('awaitingExternalConfirmation', false)" class="text-xs text-gray-600 dark:text-gray-300 hover:underline px-2">
                    Cancel &amp; edit
                </button>
            </div>
        </div>
    @endif

    @if ($pendingSafety)
        <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 rounded p-3">
            <div class="text-xs font-medium text-red-700 dark:text-red-300 mb-1">Safety Block — Override Required</div>
            <ul class="list-disc list-inside text-xs text-red-700 dark:text-red-300">
                @foreach ($pendingSafety['hard_blocks'] as $block)
                    <li>{{ $block['message'] }}</li>
                @endforeach
            </ul>
            <div class="mt-2 flex gap-2">
                <select wire:model="overrideReason" class="text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                    <option value="">Select override reason&hellip;</option>
                    @foreach ($overrideReasons as $reason)
                        {{-- The code, not the label: the Clinical API expects
                             cdss_override_reason_code, and an override recorded
                             as free-text prose cannot be reported on. --}}
                        <option value="{{ $reason->code }}">{{ $reason->display_label }}</option>
                    @endforeach
                </select>
                <button wire:click="prescribe" class="text-xs text-white bg-red-600 hover:bg-red-700 rounded px-3 py-1">
                    Override &amp; Prescribe
                </button>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 items-end">
        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Drug</label>
            <input type="text" wire:model="drugSearch" placeholder="Generic or trade name"
                class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('drugSearch') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Strength</label>
            <input type="text" wire:model="strength" placeholder="e.g. 500mg"
                class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Dose (mg)</label>
            <input type="number" step="any" wire:model="doseAmount" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
            @error('doseAmount') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Route</label>
            <select wire:model="routeCode" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                @foreach ($routes as $route)
                    <option value="{{ $route->code }}">{{ $route->display_label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Frequency</label>
            <select wire:model="frequencyCode" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                @foreach ($frequencies as $frequency)
                    <option value="{{ $frequency->code }}">{{ $frequency->display_label }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Clinical Indication</label>
            <input type="text" wire:model="clinicalIndication" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600" />
        </div>
        <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300">
            <input type="checkbox" wire:model="isNephrotoxic"> Nephrotoxic
        </label>
        <button wire:click="prescribe" class="text-sm text-white bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">
            Prescribe
        </button>
    </div>

    <div class="mt-6">
        <h5 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Active Orders</h5>
        @if ($activeOrders->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No active medication orders.</p>
        @else
            <table class="min-w-full text-sm">
                <tbody>
                    @foreach ($activeOrders as $order)
                        <tr wire:key="order-{{ $order->id }}" class="border-t border-gray-100 dark:border-gray-700">
                            <td class="py-1.5 text-gray-900 dark:text-gray-100">{{ $order->drug_display_name }}</td>
                            <td class="py-1.5 text-gray-500 dark:text-gray-400">{{ $order->dose_amount }} &middot; {{ $order->route_code }} &middot; {{ $order->frequency_code }}</td>
                            <td class="py-1.5">
                                @if ($order->is_external)
                                    <span class="text-[10px] px-2 py-0.5 rounded bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">External</span>
                                @endif
                                @if ($order->cdss_override_reason)
                                    <span class="text-[10px] px-2 py-0.5 rounded bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">Overridden</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mt-6">
        <h5 class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mb-2">Today's MAR</h5>
        @if ($dueDoses->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No doses due today.</p>
        @else
            <table class="min-w-full text-sm">
                <tbody>
                    @foreach ($dueDoses as $dose)
                        <tr wire:key="dose-{{ $dose->id }}" class="border-t border-gray-100 dark:border-gray-700">
                            <td class="py-1.5 text-gray-900 dark:text-gray-100">{{ $dose->medicationOrder->drug_display_name }}</td>
                            <td class="py-1.5 text-gray-500 dark:text-gray-400">{{ $dose->scheduled_at->format('H:i') }}</td>
                            <td class="py-1.5">
                                <div class="flex gap-2 items-center">
                                    <button wire:click="administerDose({{ $dose->id }})" class="text-xs text-white bg-green-600 hover:bg-green-700 rounded px-2 py-1">
                                        Administer
                                    </button>
                                    <select wire:model="holdReasonCode" class="text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                                        <option value="">Hold reason&hellip;</option>
                                        @foreach ($holdReasons as $reason)
                                            <option value="{{ $reason->reason_code }}">{{ $reason->display_label }}</option>
                                        @endforeach
                                    </select>
                                    <button wire:click="holdDose({{ $dose->id }})" class="text-xs text-amber-600 hover:underline">
                                        Hold
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
