<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" x-data="paSections()">

        <!-- Header -->
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                    Public Announcement Sections
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Group caller stations into named PA zones. During an announcement the audio plays on all stations in that zone.
                </p>
            </div>
            @php
                $canManage = in_array('Manage Callers', auth()->user()->permissions ?? []);
                $canBroadcastAnnouncements = in_array('Broadcast Announcements', auth()->user()->permissions ?? []);
            @endphp
            @if($canManage)
            <div class="mt-4 flex flex-wrap gap-3 md:mt-0 md:ml-4">
                @if($canBroadcastAnnouncements)
                <a href="{{ route('pa.console') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="-ml-1 mr-2 h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15.536 8.464a5 5 0 010 7.072M12 18.364a9 9 0 000-12.728M6.343 15.657a5 5 0 010-7.072"/>
                    </svg>
                    Open PA Console
                </a>
                @endif
                <button @click="openCreate()"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Add Section
                </button>
            </div>
            @endif
        </div>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">{{ session('error') }}</div>
        @endif

        <!-- Create Modal -->
        @if($canManage)
        <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div @click.outside="closeModal()" class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-4 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4" x-text="editId ? 'Edit PA Section' : 'New PA Section'"></h3>

                <form method="POST"
                      @submit="$el.action = editId ? '{{ url('pa-sections') }}/' + editId : '{{ route('pa-sections.store') }}'">
                    @csrf
                    <template x-if="editId"><input type="hidden" name="_method" value="PUT"></template>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Section Name</label>
                        <input type="text" name="name" x-model="form.name" required
                               class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="e.g. Main Hall, Ground Floor, All Stations">
                    </div>

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Assign Caller Stations</label>
                        <div class="max-h-48 overflow-y-auto border border-gray-200 rounded-md divide-y divide-gray-100">
                            @forelse($callers as $caller)
                                <label class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" name="caller_ids[]" value="{{ $caller->id }}"
                                           :checked="form.callerIds.includes({{ $caller->id }})"
                                           @change="toggleCaller({{ $caller->id }})"
                                           class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
                                    <span class="text-sm text-gray-700">{{ $caller->name }}</span>
                                </label>
                            @empty
                                <p class="px-3 py-3 text-sm text-gray-400 italic">No caller stations configured yet.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button" @click="closeModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                            Save Section
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        <!-- Sections List -->
        <div class="mt-8">
            @if($sections->count() > 0)
                <div class="bg-white shadow overflow-hidden sm:rounded-md">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Caller Stations</th>
                                @if($canManage)
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($sections as $section)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <p class="text-sm font-semibold text-gray-900">{{ $section->name }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($section->callers->isNotEmpty())
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($section->callers as $c)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700">
                                                        {{ $c->name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400 italic">No stations assigned</span>
                                        @endif
                                    </td>
                                    @if($canManage)
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <div class="flex items-center justify-end gap-3">
                                            <button @click="openEdit({{ $section->id }}, '{{ addslashes($section->name) }}', {{ json_encode($section->callers->pluck('id')) }})"
                                                    class="text-blue-600 hover:text-blue-900 text-sm font-medium">
                                                Edit
                                            </button>
                                            <form action="{{ route('pa-sections.destroy', $section) }}" method="POST"
                                                  onsubmit="return confirm('Delete PA section \'{{ addslashes($section->name) }}\'?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-500 hover:text-red-700 text-sm font-medium">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15.536 8.464a5 5 0 010 7.072M12 18.364a9 9 0 000-12.728M6.343 15.657a5 5 0 010-7.072"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No PA sections yet</h3>
                    <p class="mt-1 text-sm text-gray-500">Create a section and assign caller stations to it.</p>
                    @if($canManage)
                    <div class="mt-6">
                        <button @click="openCreate()"
                                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Add Section
                        </button>
                    </div>
                    @endif
                </div>
            @endif
        </div>

    </div>
</div>

<script>
function paSections() {
    return {
        showModal: false,
        editId: null,
        form: { name: '', callerIds: [] },

        openCreate() {
            this.editId = null;
            this.form = { name: '', callerIds: [] };
            this.showModal = true;
        },

        openEdit(id, name, callerIds) {
            this.editId = id;
            this.form = { name, callerIds: callerIds };
            this.showModal = true;
        },

        closeModal() {
            this.showModal = false;
        },

        toggleCaller(id) {
            const idx = this.form.callerIds.indexOf(id);
            if (idx === -1) this.form.callerIds.push(id);
            else this.form.callerIds.splice(idx, 1);
        },
    };
}
</script>
</x-app-layout>
