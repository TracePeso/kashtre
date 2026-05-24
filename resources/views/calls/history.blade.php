<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">

        <!-- Page header -->
        <div class="sm:flex sm:justify-between sm:items-center mb-8">
            <div class="mb-4 sm:mb-0">
                <h1 class="text-2xl md:text-3xl text-slate-800 dark:text-slate-100 font-bold">Call History ✨</h1>
            </div>

            <div class="grid grid-flow-col sm:auto-cols-max justify-start sm:justify-end gap-2">
                <!-- If you needed a global 'Start Call' button it would go here -->
            </div>
        </div>

        <div class="bg-white dark:bg-slate-800 shadow-sm rounded-xl border border-slate-200 dark:border-slate-700 relative">
            <div class="p-3 border-b border-slate-200 dark:border-slate-700">
                <h2 class="font-semibold text-slate-800 dark:text-slate-100">Recent Calls</h2>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="table-auto w-full dark:text-slate-300">
                    <thead class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/20 border-t border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="px-5 py-3 whitespace-nowrap">
                                <div class="font-semibold text-left">Type</div>
                            </th>
                            <th class="px-5 py-3 whitespace-nowrap">
                                <div class="font-semibold text-left">Contact</div>
                            </th>
                            <th class="px-5 py-3 whitespace-nowrap">
                                <div class="font-semibold text-left">Date & Time</div>
                            </th>
                            <th class="px-5 py-3 whitespace-nowrap">
                                <div class="font-semibold text-left">Duration</div>
                            </th>
                            <th class="px-5 py-3 whitespace-nowrap">
                                <div class="font-semibold text-center">Status</div>
                            </th>
                            <th class="px-5 py-3 whitespace-nowrap">
                                <div class="font-semibold text-center">Action</div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-slate-200 dark:divide-slate-700">
                        @forelse ($calls as $call)
                            @php
                                $isCaller = $call->caller_id === $user->id;
                                $otherUser = $isCaller ? $call->callee : $call->caller;
                            @endphp
                            <tr>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <!-- Outgoing Arrow -->
                                        @if($isCaller)
                                            <svg class="w-4 h-4 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                                        @else
                                        <!-- Incoming Arrow -->
                                            <svg class="w-4 h-4 mr-2 @if($call->status==='missed') text-rose-500 @else text-emerald-500 @endif" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                                        @endif
                                        <span>Audio</span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full overflow-hidden shrink-0 bg-slate-100">
                                            @if($otherUser)
                                                <img src="{{ $otherUser->profile_photo_url }}" alt="{{ $otherUser->name }}" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center text-slate-400 border border-slate-200 rounded-full">?</div>
                                            @endif
                                        </div>
                                        <div class="font-medium text-slate-800 dark:text-slate-100">
                                            {{ $otherUser ? $otherUser->name : 'Unknown User' }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="text-slate-500 dark:text-slate-400">
                                        {{ $call->created_at->format('M j, Y') }} at {{ $call->created_at->format('g:i A') }}
                                    </div>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <div class="text-slate-500 dark:text-slate-400">
                                        {{ $call->formatted_duration }}
                                    </div>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-center">
                                    @if($call->status === 'completed')
                                        <div class="inline-flex font-medium bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 rounded-full text-center px-2.5 py-0.5">Completed</div>
                                    @elseif($call->status === 'missed')
                                        <div class="inline-flex font-medium bg-rose-100 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 rounded-full text-center px-2.5 py-0.5">Missed</div>
                                    @elseif($call->status === 'rejected')
                                        <div class="inline-flex font-medium bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 rounded-full text-center px-2.5 py-0.5">Declined</div>
                                    @elseif($call->status === 'cancelled')
                                        <div class="inline-flex font-medium bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 rounded-full text-center px-2.5 py-0.5">Cancelled</div>
                                    @else
                                        <div class="inline-flex font-medium bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 rounded-full text-center px-2.5 py-0.5">{{ ucfirst($call->status) }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap text-center">
                                    @if($otherUser)
                                        <x-calling.call-button :user="$otherUser" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">
                                    No calls found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 px-4 pb-4">
                {{ $calls->links() }}
            </div>
        </div>

    </div>
</x-app-layout>
