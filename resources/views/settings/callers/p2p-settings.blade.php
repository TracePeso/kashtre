<x-app-layout>
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Header -->
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                    Point-to-Point Call Settings
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Staff directory for direct audio calls. Green dot means the user is currently online.
                </p>
            </div>
        </div>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded relative">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative">
                {{ session('error') }}
            </div>
        @endif

        <!-- My Settings -->
        <div class="mt-8 bg-white shadow sm:rounded-lg mb-8">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    My Calling Options
                </h3>
                <div class="mt-2 max-w-xl text-sm text-gray-500">
                    <p>Customize how your name appears to others and choose your preferred incoming ringtone.</p>
                </div>
                <form action="{{ route('service-point-callers.save-p2p-settings') }}" method="POST" class="mt-5 sm:flex sm:items-center">
                    @csrf
                    <div class="w-full sm:max-w-xs me-4">
                        <label for="p2p_display_name" class="sr-only">Display Name</label>
                        <input type="text" name="p2p_display_name" id="p2p_display_name"
                               class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md"
                               placeholder="{{ auth()->user()->name }}"
                               value="{{ auth()->user()->p2p_display_name }}">
                    </div>

                    <div class="w-full sm:max-w-xs mt-3 sm:mt-0 me-4">
                        <label for="p2p_ringtone" class="sr-only">Incoming Ringtone</label>
                        <select id="p2p_ringtone" name="p2p_ringtone" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                            <option value="default" {{ auth()->user()->p2p_ringtone === 'default' ? 'selected' : '' }}>Default Beeps</option>
                            <option value="rapid" {{ auth()->user()->p2p_ringtone === 'rapid' ? 'selected' : '' }}>Rapid Pulse</option>
                            <option value="deep" {{ auth()->user()->p2p_ringtone === 'deep' ? 'selected' : '' }}>Deep Tone</option>
                            <option value="urgent" {{ auth()->user()->p2p_ringtone === 'urgent' ? 'selected' : '' }}>High Alert</option>
                        </select>
                    </div>
                    <button type="submit" class="mt-3 w-full inline-flex items-center justify-center px-4 py-2 border border-transparent shadow-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Save Preferences
                    </button>
                    <!-- Preview logic handled by calling.js ringtone map -->
                </form>
            </div>
        </div>

        <!-- Staff Directory -->
        <div class="mt-8" x-data="p2pStaffDirectory()">

            @if($users->count() > 0)
                <div class="bg-white shadow overflow-hidden sm:rounded-md">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Staff Member
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Email
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($users as $user)
                                <tr class="hover:bg-gray-50">
                                    <!-- Online/Offline indicator -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <template x-if="isOnline('{{ $user->uuid }}')">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                                Online
                                            </span>
                                        </template>
                                        <template x-if="!isOnline('{{ $user->uuid }}')">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                                Offline
                                            </span>
                                        </template>
                                    </td>

                                    <!-- Avatar + Name -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            @if($user->profile_photo_path)
                                                <img src="{{ $user->profile_photo_url }}"
                                                     alt="{{ $user->name }}"
                                                     class="w-9 h-9 rounded-full object-cover">
                                            @else
                                                <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center">
                                                    <span class="text-sm font-semibold text-indigo-600">
                                                        {{ strtoupper(substr($user->p2p_name, 0, 1)) }}
                                                    </span>
                                                </div>
                                            @endif
                                            <p class="text-sm font-medium text-gray-900">{{ $user->p2p_name }}</p>
                                        </div>
                                    </td>

                                    <!-- Email -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <p class="text-sm text-gray-500">{{ $user->email }}</p>
                                    </td>

                                    <!-- Call Button -->
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <template x-if="isOnline('{{ $user->uuid }}')">
                                            <button
                                                @click="$dispatch('initiate-call', { uuid: '{{ $user->uuid }}' })"
                                                title="Call {{ $user->p2p_name }}"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 8V5z"/>
                                                </svg>
                                                Call
                                            </button>
                                        </template>
                                        <template x-if="!isOnline('{{ $user->uuid }}')">
                                            <span class="text-xs text-gray-400 italic">Unavailable</span>
                                        </template>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            @else
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No other staff members</h3>
                    <p class="mt-1 text-sm text-gray-500">There are no other active users in your organisation.</p>
                </div>
            @endif

        </div>

    </div>
</div>

<script>
function p2pStaffDirectory() {
    return {
        onlineUuids: [],

        init() {
            const businessId = document.querySelector('meta[name="business-id"]')?.content;
            const userId     = document.querySelector('meta[name="user-id"]')?.content;

            if (!businessId || !window.Echo) return;

            // Join the same presence channel used by callingSystem().
            // Echo de-duplicates the subscription so no double connection is opened.
            // This is the ground truth — a user is only here while their browser is connected.
            window.Echo.join(`presence-business.${businessId}`)
                .here((users) => {
                    this.onlineUuids = users
                        .filter(u => String(u.id) !== String(userId))
                        .map(u => String(u.uuid));
                })
                .joining((user) => {
                    if (!this.onlineUuids.includes(String(user.uuid))) {
                        this.onlineUuids.push(String(user.uuid));
                    }
                })
                .leaving((user) => {
                    this.onlineUuids = this.onlineUuids.filter(id => id !== String(user.uuid));
                });
        },

        isOnline(uuid) {
            return this.onlineUuids.includes(String(uuid));
        },
    };
}
</script>
</x-app-layout>
