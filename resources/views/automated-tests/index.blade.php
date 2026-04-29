<x-app-layout>
    <div class="py-12 bg-gradient-to-b from-[#011478]/10 to-transparent">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h1 class="text-4xl font-bold text-[#011478]">Automated Test</h1>
                    <p class="text-gray-600 mt-2">Complete user journey: Register → Order Items → Make Payment → Queue Items</p>
                </div>
                <a href="{{ route('dashboard') }}" class="text-blue-600 hover:text-blue-800 underline">← Back to Dashboard</a>
            </div>

            <!-- Test Setup Section -->
            <div class="bg-white rounded-xl shadow-sm p-8 mb-6">
                <div class="mb-6">
                    <h2 class="text-2xl font-semibold text-gray-900 mb-4">Configure Test</h2>
                    
                    <!-- Payment Phone Input -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Phone Number</label>
                        <input type="tel" 
                               id="payment-phone" 
                               placeholder="e.g., 0777123456"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <!-- Item Types Selection -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-3">Select Item Types to Include</label>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <label class="flex items-center space-x-2 p-3 border border-gray-300 rounded-lg hover:bg-blue-50 cursor-pointer">
                                <input type="checkbox" value="service" class="item-type-checkbox" checked>
                                <span class="text-sm font-medium text-gray-700">Service</span>
                            </label>
                            <label class="flex items-center space-x-2 p-3 border border-gray-300 rounded-lg hover:bg-blue-50 cursor-pointer">
                                <input type="checkbox" value="good" class="item-type-checkbox" checked>
                                <span class="text-sm font-medium text-gray-700">Good</span>
                            </label>
                            <label class="flex items-center space-x-2 p-3 border border-gray-300 rounded-lg hover:bg-blue-50 cursor-pointer">
                                <input type="checkbox" value="package" class="item-type-checkbox" checked>
                                <span class="text-sm font-medium text-gray-700">Package</span>
                            </label>
                            <label class="flex items-center space-x-2 p-3 border border-gray-300 rounded-lg hover:bg-blue-50 cursor-pointer">
                                <input type="checkbox" value="bulk" class="item-type-checkbox" checked>
                                <span class="text-sm font-medium text-gray-700">Bulk</span>
                            </label>
                        </div>
                    </div>

                    <!-- Number of Items -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Number of Items to Order</label>
                        <div class="flex items-center space-x-4">
                            <input type="number" 
                                   id="item-count" 
                                   value="3"
                                   min="1"
                                   max="10"
                                   class="w-32 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <span class="text-sm text-gray-600">(Random items will be selected from your inventory)</span>
                        </div>
                    </div>

                    <!-- Maximum Total Amount -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Maximum Total Amount (Budget)</label>
                        <div class="flex items-center space-x-4">
                            <input type="number" 
                                   id="max-amount" 
                                   value="100000"
                                   min="1000"
                                   step="1000"
                                   class="w-40 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <span class="text-sm text-gray-600">UGX (Items will be selected until budget is reached)</span>
                        </div>
                    </div>

                    <!-- Suite/Payment Mode -->
                    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="flex items-center space-x-3 p-3 border border-gray-300 rounded-lg hover:bg-blue-50 cursor-pointer">
                            <input type="checkbox" id="full-suite" checked>
                            <span class="text-sm font-medium text-gray-700">Run full non-insurance scenario suite</span>
                        </label>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Payment Mode</label>
                            <select id="payment-mode" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="simulated" selected>Simulated (recommended for full coverage)</option>
                                <option value="live">Live YoAPI request</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="border-t pt-6 flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        <strong>What This Test Does:</strong> Registers a user → Orders randomly selected items → Processes payment → Queues items for delivery
                    </div>
                    <button onclick="runTests()" 
                            id="run-button"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-lg font-semibold transition duration-200 flex items-center space-x-2 ml-4">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <span>Run Test</span>
                    </button>
                </div>
            </div>

            <!-- Test Output Section -->
            <div id="test-output" class="bg-white rounded-xl shadow-sm p-6" style="display:none;">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-semibold text-gray-900">Test Progress</h2>
                    <button onclick="clearOutput()" class="text-sm text-gray-600 hover:text-gray-900">Clear</button>
                </div>
                <div id="output-content" class="bg-gray-900 text-green-400 p-4 rounded font-mono text-sm overflow-auto max-h-96 whitespace-pre-wrap break-words border border-gray-700" style="line-height: 1.6;">
                    <!-- Test output will appear here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        function runTests() {
            const button = document.getElementById('run-button');
            const outputSection = document.getElementById('test-output');
            const outputContent = document.getElementById('output-content');
            const paymentPhone = document.getElementById('payment-phone').value;
            const itemCount = parseInt(document.getElementById('item-count').value) || 3;
            const maxAmount = parseInt(document.getElementById('max-amount').value) || 100000;
            const fullSuite = document.getElementById('full-suite').checked;
            const paymentMode = document.getElementById('payment-mode').value || 'simulated';
            
            // Get selected item types
            const selectedTypes = Array.from(document.querySelectorAll('.item-type-checkbox:checked'))
                .map(checkbox => checkbox.value);
            
            if (!paymentPhone.trim()) {
                alert('Please enter a payment phone number');
                return;
            }

            if (itemCount < 1 || itemCount > 10) {
                alert('Please select between 1 and 10 items');
                return;
            }

            if (maxAmount < 1000) {
                alert('Budget must be at least 1,000 UGX');
                return;
            }

            if (selectedTypes.length === 0) {
                alert('Please select at least one item type');
                return;
            }

            if (paymentMode === 'live' && fullSuite) {
                alert('Live mode sends a real Yo prompt. Full suite is disabled in live mode to avoid multiple prompts.');
                document.getElementById('full-suite').checked = false;
            }

            // Disable button and show output section
            button.disabled = true;
            button.classList.add('opacity-50', 'cursor-not-allowed');
            outputSection.style.display = 'block';
            outputContent.innerHTML = '<span class="text-yellow-400">🔄 Starting test...\n</span>';

            // Call the backend to run tests
            fetch('{{ route("automated-tests.run") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    payment_phone: paymentPhone,
                    item_count: itemCount,
                    max_amount: maxAmount,
                    item_types: selectedTypes,
                    full_suite: paymentMode === 'live' ? false : fullSuite,
                    payment_mode: paymentMode
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const lines = data.output.split('\n');
                    let html = '';
                    
                    for (const line of lines) {
                        if (line.includes('✅')) {
                            html += '<div class="text-green-400">' + escapeHtml(line) + '</div>';
                        } else if (line.includes('❌')) {
                            html += '<div class="text-red-400">' + escapeHtml(line) + '</div>';
                        } else if (line.includes('STEP') || line.includes('🧪') || line.includes('📍') || line.includes('📊')) {
                            html += '<div class="text-cyan-400">' + escapeHtml(line) + '</div>';
                        } else if (line.includes('---') || line.includes('===')) {
                            html += '<div class="text-gray-500">' + escapeHtml(line) + '</div>';
                        } else if (line.includes('🔐') || line.includes('PIN')) {
                            html += '<div class="text-purple-400">' + escapeHtml(line) + '</div>';
                        } else if (line.includes('•') || line.includes('Total') || line.includes('Price') || line.includes('Budget')) {
                            html += '<div class="text-yellow-300">' + escapeHtml(line) + '</div>';
                        } else if (line.trim()) {
                            html += '<div>' + escapeHtml(line) + '</div>';
                        } else {
                            html += '<div>&nbsp;</div>';
                        }
                    }
                    
                    outputContent.innerHTML = html;
                } else {
                    outputContent.innerHTML = '<span class="text-red-400">❌ Error: ' + escapeHtml(data.message || 'Unknown error') + '</span>\n' + (data.output || '');
                }
                
                // Re-enable button
                button.disabled = false;
                button.classList.remove('opacity-50', 'cursor-not-allowed');
            })
            .catch(error => {
                outputContent.innerHTML = '<span class="text-red-400">❌ Error: ' + escapeHtml(error.message) + '</span>';
                button.disabled = false;
                button.classList.remove('opacity-50', 'cursor-not-allowed');
            });
        }

        function clearOutput() {
            document.getElementById('test-output').style.display = 'none';
            document.getElementById('output-content').innerHTML = '';
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const paymentModeEl = document.getElementById('payment-mode');
            const fullSuiteEl = document.getElementById('full-suite');
            if (!paymentModeEl || !fullSuiteEl) return;

            const syncLiveSafety = () => {
                const isLive = paymentModeEl.value === 'live';
                if (isLive) {
                    fullSuiteEl.checked = false;
                    fullSuiteEl.disabled = true;
                    fullSuiteEl.closest('label')?.classList.add('opacity-60');
                } else {
                    fullSuiteEl.disabled = false;
                    fullSuiteEl.closest('label')?.classList.remove('opacity-60');
                }
            };

            paymentModeEl.addEventListener('change', syncLiveSafety);
            syncLiveSafety();
        });
    </script>
</x-app-layout>
