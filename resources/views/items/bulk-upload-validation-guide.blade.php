<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-white">Bulk Upload Validation Guide</h2>
                    <a href="{{ route('items.bulk-upload') }}" class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-800 text-sm font-semibold rounded-md hover:bg-gray-400 transition duration-150">
                        Back to Bulk Upload
                    </a>
                </div>

                <div class="space-y-6">
                    <!-- General Validation Rules -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                        <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200 mb-3">General Validation Rules</h3>
                        <ul class="space-y-2 text-sm text-blue-700 dark:text-blue-300">
                            <li class="flex items-start">
                                <span class="text-red-500 mr-2">•</span>
                                <span><strong>Required Fields:</strong> Name, Type, Default Price, Hospital Share (for goods/services)</span>
                            </li>
                            <li class="flex items-start">
                                <span class="text-red-500 mr-2">•</span>
                                <span><strong>Optional Fields:</strong> Code (auto-generated if empty), Generic Name, Category, Description, VAT Rate, Other Names</span>
                            </li>
                            <li class="flex items-start">
                                <span class="text-red-500 mr-2">•</span>
                                <span><strong>Type Values:</strong> For goods/services template: "service" or "good"</span>
                            </li>
                            <li class="flex items-start">
                                <span class="text-red-500 mr-2">•</span>
                                <span><strong>Type Values:</strong> For packages/bulk template: "package" or "bulk"</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Contractor Validation Rules -->
                    <div class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg p-4">
                        <h3 class="text-lg font-semibold text-orange-800 dark:text-orange-200 mb-3">Contractor Validation Rules</h3>
                        <div class="space-y-3">
                            <div class="bg-white dark:bg-gray-700 rounded p-3">
                                <h4 class="font-semibold text-orange-700 dark:text-orange-300 mb-2">When Hospital Share < 100%:</h4>
                                <ul class="text-sm text-orange-600 dark:text-orange-400 space-y-1">
                                    <li>• <strong>Contractor is REQUIRED</strong></li>
                                    <li>• Must select a valid contractor from the dropdown</li>
                                    <li>• Contractor must belong to the same business</li>
                                </ul>
                            </div>
                            <div class="bg-white dark:bg-gray-700 rounded p-3">
                                <h4 class="font-semibold text-orange-700 dark:text-orange-300 mb-2">When Hospital Share = 100%:</h4>
                                <ul class="text-sm text-orange-600 dark:text-orange-400 space-y-1">
                                    <li>• <strong>Contractor should NOT be selected</strong></li>
                                    <li>• Leave contractor field empty</li>
                                    <li>• 100% hospital share means no contractor involvement</li>
                                </ul>
                            </div>
                            <div class="bg-white dark:bg-gray-700 rounded p-3">
                                <h4 class="font-semibold text-orange-700 dark:text-orange-300 mb-2">Available Contractors:</h4>
                                <p class="text-sm text-orange-600 dark:text-orange-400">
                                    Only contractors associated with your business will appear in the dropdown. 
                                    If no contractors are available, you must set hospital share to 100%.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Field-Specific Validation -->
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                        <h3 class="text-lg font-semibold text-green-800 dark:text-green-200 mb-3">Field-Specific Validation</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-3">
                                <div>
                                    <h4 class="font-semibold text-green-700 dark:text-green-300">Name</h4>
                                    <p class="text-sm text-green-600 dark:text-green-400">Required, max 255 characters</p>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-green-700 dark:text-green-300">Code</h4>
                                    <p class="text-sm text-green-600 dark:text-green-400">Optional, auto-generated if empty, must be unique</p>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-green-700 dark:text-green-300">Default Price</h4>
                                    <p class="text-sm text-green-600 dark:text-green-400">Required, numeric, minimum 0</p>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-green-700 dark:text-green-300">VAT Rate</h4>
                                    <p class="text-sm text-green-600 dark:text-green-400">Optional, numeric, 0-100%, default 0%</p>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div>
                                    <h4 class="font-semibold text-green-700 dark:text-green-300">Hospital Share</h4>
                                    <p class="text-sm text-green-600 dark:text-green-400">Required, integer, 0-100%</p>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-green-700 dark:text-green-300">Group/Subgroup/Department</h4>
                                    <p class="text-sm text-green-600 dark:text-green-400">Required for goods/services, must exist in business</p>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-green-700 dark:text-green-300">Unit of Measure</h4>
                                    <p class="text-sm text-green-600 dark:text-green-400">Required for goods/services, must exist in business</p>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-green-700 dark:text-green-300">Validity Days</h4>
                                    <p class="text-sm text-green-600 dark:text-green-400">Required for packages, integer, minimum 1</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Common Errors -->
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                        <h3 class="text-lg font-semibold text-red-800 dark:text-red-200 mb-3">Common Validation Errors</h3>
                        <div class="space-y-2 text-sm text-red-700 dark:text-red-300">
                            <div class="flex items-start">
                                <span class="text-red-500 mr-2">•</span>
                                <span><strong>"Contractor is required when hospital share is less than 100%"</strong> - Set hospital share to 100% or select a contractor</span>
                            </div>
                            <div class="flex items-start">
                                <span class="text-red-500 mr-2">•</span>
                                <span><strong>"Contractor should not be selected when hospital share is 100%"</strong> - Remove contractor or reduce hospital share</span>
                            </div>
                            <div class="flex items-start">
                                <span class="text-red-500 mr-2">•</span>
                                <span><strong>"Code already exists"</strong> - Use a different code or leave empty for auto-generation</span>
                            </div>
                            <div class="flex items-start">
                                <span class="text-red-500 mr-2">•</span>
                                <span><strong>"Group/Department/Unit not found"</strong> - Select from dropdown or create the item first</span>
                            </div>
                            <div class="flex items-start">
                                <span class="text-red-500 mr-2">•</span>
                                <span><strong>"Invalid type"</strong> - Use exact values: "service", "good", "package", or "bulk"</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tips -->
                    <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg p-4">
                        <h3 class="text-lg font-semibold text-purple-800 dark:text-purple-200 mb-3">Pro Tips</h3>
                        <ul class="space-y-2 text-sm text-purple-700 dark:text-purple-300">
                            <li class="flex items-start">
                                <span class="text-purple-500 mr-2">💡</span>
                                <span>Use the template dropdowns to ensure valid selections</span>
                            </li>
                            <li class="flex items-start">
                                <span class="text-purple-500 mr-2">💡</span>
                                <span>Test with a small batch first before uploading large files</span>
                            </li>
                            <li class="flex items-start">
                                <span class="text-purple-500 mr-2">💡</span>
                                <span>Ensure all referenced groups, departments, and units exist before importing</span>
                            </li>
                            <li class="flex items-start">
                                <span class="text-purple-500 mr-2">💡</span>
                                <span>For packages/bulk items, ensure constituent items exist first</span>
                            </li>
                            <li class="flex items-start">
                                <span class="text-purple-500 mr-2">💡</span>
                                <span>Check the import results for detailed error messages</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
