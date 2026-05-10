<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('maturation-periods.index', ['tab' => 'service-charges']) }}" class="text-sm text-blue-600 hover:text-blue-800">← Back</a>
                <h2 class="mt-2 text-2xl font-bold text-gray-900">Service charge maturation</h2>
            </div>
            @if(in_array('Edit Maturation Periods', auth()->user()->permissions ?? []))
                <a href="{{ route('service-charge-maturation-periods.edit', $serviceChargeMaturationPeriod) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">Edit</a>
            @endif
        </div>

        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <dl class="divide-y divide-gray-200">
                <div class="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-gray-500">Business</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:col-span-2">{{ $serviceChargeMaturationPeriod->business->name ?? '—' }}</dd>
                </div>
                <div class="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-gray-500">Payment method</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:col-span-2">{{ $serviceChargeMaturationPeriod->payment_method_name }}</dd>
                </div>
                <div class="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-gray-500">Maturation</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:col-span-2">{{ $serviceChargeMaturationPeriod->formatted_maturation_period }}</dd>
                </div>
                <div class="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                    <dd class="mt-1 sm:col-span-2">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $serviceChargeMaturationPeriod->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $serviceChargeMaturationPeriod->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </dd>
                </div>
                @if($serviceChargeMaturationPeriod->description)
                <div class="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-gray-500">Description</dt>
                    <dd class="mt-1 text-sm text-gray-900 sm:col-span-2">{{ $serviceChargeMaturationPeriod->description }}</dd>
                </div>
                @endif
            </dl>
        </div>
    </div>
</div>
</x-app-layout>
