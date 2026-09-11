<x-app-layout>
    @php
        $initialLinks = old('website_links', $settings->website_links ?? []);
        if ($initialLinks === []) {
            $initialLinks = [['label' => '', 'url' => '']];
        }
    @endphp

    <div class="min-h-screen bg-gray-50 py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <p class="text-sm text-gray-500">Master settings</p>
                <h2 class="mt-1 text-2xl font-bold text-gray-900">Kashtre Settings</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Configure system contact information and website links shown in the application footer.
                </p>
            </div>

            @if(session('success'))
                <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <form action="{{ route('settings.kashtre.update') }}" method="POST"
                  x-data="{
                      links: @js($initialLinks),
                      addLink() { this.links.push({ label: '', url: '' }); },
                      removeLink(index) {
                          this.links.splice(index, 1);
                          if (this.links.length === 0) {
                              this.links.push({ label: '', url: '' });
                          }
                      }
                  }"
                  class="space-y-6">
                @csrf
                @method('PUT')

                <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                    <div class="px-4 py-5 sm:p-6 space-y-5">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900">Display</h3>
                                <p class="text-xs text-gray-500 mt-0.5">Turn off to hide the footer contact block across the platform.</p>
                            </div>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $settings->is_active))
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                Show in footer
                            </label>
                        </div>

                        <div class="border-t border-gray-100 pt-5">
                            <h3 class="text-sm font-semibold text-gray-900 mb-4">Contact information</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="contact_email" class="block text-sm font-medium text-gray-700">Email</label>
                                    <input type="email" name="contact_email" id="contact_email"
                                           value="{{ old('contact_email', $settings->contact_email) }}"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                    @error('contact_email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="contact_phone" class="block text-sm font-medium text-gray-700">Phone</label>
                                    <input type="text" name="contact_phone" id="contact_phone"
                                           value="{{ old('contact_phone', $settings->contact_phone) }}"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                    @error('contact_phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="contact_address" class="block text-sm font-medium text-gray-700">Address</label>
                                    <textarea name="contact_address" id="contact_address" rows="2"
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">{{ old('contact_address', $settings->contact_address) }}</textarea>
                                    @error('contact_address')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-100 pt-5">
                            <div class="flex items-center justify-between gap-3 mb-4">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900">Website links</h3>
                                    <p class="text-xs text-gray-500 mt-0.5">Shown as clickable links in the footer.</p>
                                </div>
                                <button type="button" @click="addLink()"
                                        class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                    + Add link
                                </button>
                            </div>

                            <div class="space-y-3">
                                <template x-for="(link, index) in links" :key="'link-' + index">
                                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                                        <div class="sm:col-span-4">
                                            <label class="block text-xs font-medium text-gray-600">Label</label>
                                            <input type="text" :name="'website_links[' + index + '][label]'" x-model="link.label"
                                                   placeholder="e.g. Website"
                                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                        </div>
                                        <div class="sm:col-span-7">
                                            <label class="block text-xs font-medium text-gray-600">URL</label>
                                            <input type="url" :name="'website_links[' + index + '][url]'" x-model="link.url"
                                                   placeholder="https://"
                                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                        </div>
                                        <div class="sm:col-span-1 flex justify-end pb-0.5">
                                            <button type="button" @click="removeLink(index)"
                                                    class="text-sm text-red-600 hover:text-red-800">
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="border-t border-gray-100 pt-5 grid grid-cols-1 gap-4">
                            <div>
                                <label for="powered_by_line" class="block text-sm font-medium text-gray-700">Powered by line (documents)</label>
                                <input type="text" name="powered_by_line" id="powered_by_line"
                                       value="{{ old('powered_by_line', $settings->powered_by_line) }}"
                                       placeholder="Powered by Kashtre"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                <p class="mt-1 text-xs text-gray-500">Shown on PDFs, exports, and the document letterhead preview. The first website link below is also shown there.</p>
                                @error('powered_by_line')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="tagline" class="block text-sm font-medium text-gray-700">Tagline</label>
                                <input type="text" name="tagline" id="tagline"
                                       value="{{ old('tagline', $settings->tagline) }}"
                                       placeholder="Kashtre is a product of Kashtre Ltd"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('tagline')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="copyright_line" class="block text-sm font-medium text-gray-700">Copyright line</label>
                                <input type="text" name="copyright_line" id="copyright_line"
                                       value="{{ old('copyright_line', $settings->copyright_line) }}"
                                       placeholder="© Copyright {year} Kashtre. All Rights Reserved"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500">
                                <p class="mt-1 text-xs text-gray-500">Use <code class="text-xs">{year}</code> for the current year.</p>
                                @error('copyright_line')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                            Save Kashtre settings
                        </button>
                    </div>
                </div>
            </form>

            <div class="mt-8">
                <h3 class="text-sm font-semibold text-gray-900 mb-3">Preview</h3>
                <x-kashtre.cash-tray :cash-tray-settings="$settings" class="rounded-lg border border-gray-200 overflow-hidden" />
            </div>
        </div>
    </div>
</x-app-layout>
