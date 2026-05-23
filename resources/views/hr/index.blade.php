<x-hr-layout>
    <div class="py-10 bg-gradient-to-b from-sky-50 to-transparent">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if(session('error'))
                <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 shadow-sm">
                    {{ session('error') }}
                </div>
            @endif

            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-gradient-to-r from-[#011478] to-sky-700 px-6 py-8 text-white">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-sky-100">HR Manager</p>
                    <h1 class="mt-2 text-3xl font-bold">HR is now hosted inside the main Kashtre app.</h1>
                    <p class="mt-3 max-w-3xl text-sm text-sky-100">
                        This route is the internal module entry point. Future HR pages and translated HR migrations should land under the same application and deploy through the main staging and production workflows.
                    </p>
                </div>

                <div class="grid gap-6 px-6 py-8 lg:grid-cols-[1.35fr,0.65fr]">
                    <div class="space-y-6">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                            <h2 class="text-lg font-semibold text-slate-900">What is wired now</h2>
                            <ul class="mt-3 space-y-2 text-sm text-slate-700">
                                <li>Internal route group mounted from <span class="font-mono text-xs">routes/hr.php</span>.</li>
                                <li>Dedicated <span class="font-mono text-xs">HrServiceProvider</span> registered in the main app.</li>
                                <li>HR migration path reserved at <span class="font-mono text-xs">database/migrations/hr</span>.</li>
                                <li>Existing deployment workflows will pick up translated HR migrations through the normal <span class="font-mono text-xs">artisan migrate --force</span> run.</li>
                            </ul>
                        </div>

                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                            <h2 class="text-lg font-semibold text-amber-900">Migration rule for the merge</h2>
                            <p class="mt-2 text-sm text-amber-900/90">
                                The standalone HR repo migration set should not be copied in raw form. It contains duplicate base tables and a separate organization model, so each HR table needs to be translated against this app&apos;s existing <span class="font-mono text-xs">users</span>, <span class="font-mono text-xs">businesses</span>, and <span class="font-mono text-xs">client-spaces</span> schema before deployment.
                            </p>
                        </div>
                    </div>

                    <aside class="space-y-6">
                        <div class="rounded-2xl border border-slate-200 p-5">
                            <h2 class="text-lg font-semibold text-slate-900">Your HR access</h2>
                            <ul class="mt-3 space-y-2 text-sm text-slate-700">
                                @forelse($hrPermissions as $permission)
                                    <li>{{ $permission }}</li>
                                @empty
                                    <li>No HR permissions detected.</li>
                                @endforelse
                            </ul>
                        </div>

                        <div class="rounded-2xl border border-slate-200 p-5">
                            <h2 class="text-lg font-semibold text-slate-900">Next merge target</h2>
                            <p class="mt-2 text-sm text-slate-700">
                                Port the first real HR feature slice into this module, then add its translated migrations into <span class="font-mono text-xs">database/migrations/hr</span> so staging deploys them automatically.
                            </p>
                        </div>
                    </aside>
                </div>
            </section>
        </div>
    </div>
</x-hr-layout>
