<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <h1 class="text-xl font-bold text-gray-900 mb-6">HR — Payroll</h1>

            @if(isset($response['error']))
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                    {{ $response['error'] }}
                </div>
            @endif

            <div class="bg-white shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Employee</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Period</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Gross</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Deductions</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Net Pay</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($response['data'] ?? [] as $slip)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $slip['employee'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $slip['pay_period'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ number_format($slip['gross_pay'] ?? 0, 2) }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ number_format($slip['total_deductions'] ?? 0, 2) }}</td>
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ number_format($slip['net_pay'] ?? 0, 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                    {{ $slip['status'] ?? '—' }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-400">No payslips found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>
