<x-app-layout>
    <x-slot name="header">AI Duty Roster Constraints</x-slot>

    <div class="space-y-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Constraints Collection</p>
                    <h2 class="mt-2 text-2xl font-bold text-slate-900">AI duty roster generation rules</h2>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">
                        This folder is reserved for authorized administrators to collect and manage AI duty roster constraints before they are applied to generation workflows.
                    </p>
                </div>
                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                    Admin only
                </span>
            </div>
        </section>

        <section class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-6">
            <h3 class="text-lg font-semibold text-slate-900">Collection ready</h3>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                The navigation entry and permission gate are in place. Constraint collections for AI roster generation can now be added here without exposing the section to non-authorized users.
            </p>
        </section>
    </div>
</x-app-layout>
