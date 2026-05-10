<x-app-layout>
    <div class="min-h-screen bg-gray-50 py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <a href="{{ route('maturation-periods.index', ['tab' => 'system-defaults']) }}" class="text-sm text-blue-600 hover:text-blue-800">← Back to maturation periods</a>
                <h2 class="mt-2 text-2xl font-bold text-gray-900">Edit system defaults</h2>
                <p class="mt-1 text-sm text-gray-600">Per-entity rows override these where configured.</p>
            </div>

            @if(session('error'))
                <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-md text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <form action="{{ route('maturation-periods.system-defaults.update') }}" method="POST" class="px-4 py-5 sm:p-6">
                    @csrf
                    @method('PUT')

                    <div class="overflow-x-auto -mx-4 sm:mx-0">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">Payment method</th>
                                    <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">Invoice / entity (days)</th>
                                    <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700">Service charge (days)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach($methods as $method)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-900 whitespace-nowrap">
                                            {{ $labels[$method] ?? ucfirst(str_replace('_', ' ', $method)) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="number" name="entity[{{ $method }}]" id="entity_{{ $method }}"
                                                   value="{{ old('entity.'.$method, $entityMap[$method] ?? 0) }}"
                                                   min="0" max="365" required
                                                   class="block w-28 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('entity.'.$method) border-red-300 @enderror">
                                            @error('entity.'.$method)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="number" name="service_charge[{{ $method }}]" id="service_charge_{{ $method }}"
                                                   value="{{ old('service_charge.'.$method, $serviceChargeMap[$method] ?? 0) }}"
                                                   min="0" max="365" required
                                                   class="block w-28 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('service_charge.'.$method) border-red-300 @enderror">
                                            @error('service_charge.'.$method)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <a href="{{ route('maturation-periods.index', ['tab' => 'system-defaults']) }}" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">Cancel</a>
                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Save system defaults
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
