@php
    $business = $config->business;
    $fyService = app(\App\Services\FinancialYearService::class);
    $financeEmails = $config->financeNotificationEmailList();
@endphp

<div class="space-y-6">
  <section class="border border-gray-200 rounded-lg overflow-hidden">
    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
      <h4 class="text-sm font-semibold text-gray-900">Overview</h4>
    </div>
    <dl class="px-4 py-4 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
      <div>
        <dt class="text-gray-500">Business</dt>
        <dd class="mt-1 font-medium text-gray-900">{{ $business?->name ?? '—' }}</dd>
      </div>
      <div>
        <dt class="text-gray-500">Module status</dt>
        <dd class="mt-1">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $config->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
            {{ $config->is_active ? 'Active' : 'Inactive' }}
          </span>
        </dd>
      </div>
      <div class="sm:col-span-2">
        <dt class="text-gray-500">Description</dt>
        <dd class="mt-1 text-gray-900 whitespace-pre-wrap">{{ $config->description ?: '—' }}</dd>
      </div>
      <div>
        <dt class="text-gray-500">Created</dt>
        <dd class="mt-1 text-gray-900">
          {{ $config->created_at?->format('M d, Y H:i') ?? '—' }}
          @if($config->createdBy)
            <span class="text-gray-500">by {{ $config->createdBy->name }}</span>
          @endif
        </dd>
      </div>
      <div>
        <dt class="text-gray-500">Last updated</dt>
        <dd class="mt-1 text-gray-900">
          {{ $config->updated_at?->format('M d, Y H:i') ?? '—' }}
          @if($config->updatedBy)
            <span class="text-gray-500">by {{ $config->updatedBy->name }}</span>
          @endif
        </dd>
      </div>
    </dl>
  </section>

  @if($business)
    <section class="border border-gray-200 rounded-lg overflow-hidden">
      <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
        <h4 class="text-sm font-semibold text-gray-900">Financial year</h4>
        <p class="text-xs text-gray-500 mt-0.5">Configured in Business Settings for this organisation.</p>
      </div>
      <dl class="px-4 py-4 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
        <div>
          <dt class="text-gray-500">Starts on</dt>
          <dd class="mt-1 font-medium text-gray-900">
            {{ \Carbon\Carbon::create(null, (int) ($business->financial_year_start_month ?? 1), (int) ($business->financial_year_start_day ?? 1))->format('F j') }}
            <span class="text-gray-500 font-normal">each year</span>
          </dd>
        </div>
        <div>
          <dt class="text-gray-500">Current period</dt>
          <dd class="mt-1 font-medium text-gray-900">{{ $fyService->periodLabel($business) }}</dd>
        </div>
      </dl>
    </section>
  @endif

  <section class="border border-gray-200 rounded-lg overflow-hidden">
    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
      <h4 class="text-sm font-semibold text-gray-900">Stock monitoring</h4>
      <p class="text-xs text-gray-500 mt-0.5">Used on Monitor Stock, reports, and order calculations.</p>
    </div>
    <dl class="px-4 py-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4 text-sm">
      <div>
        <dt class="text-gray-500">Fixed daily average</dt>
        <dd class="mt-1 font-medium text-gray-900">{{ number_format((float) $config->fixed_daily_average_suom, 4) }} sale units</dd>
      </div>
      <div>
        <dt class="text-gray-500">Safety stock days</dt>
        <dd class="mt-1 font-medium text-gray-900">{{ number_format((float) $config->safety_stock_days, 1) }}</dd>
      </div>
      <div>
        <dt class="text-gray-500">Buffer stock days</dt>
        <dd class="mt-1 font-medium text-gray-900">{{ number_format((float) $config->buffer_stock_days, 1) }}</dd>
      </div>
      <div>
        <dt class="text-gray-500">Notification to order</dt>
        <dd class="mt-1 font-medium text-gray-900">{{ number_format((float) $config->notification_to_order_days, 1) }} days</dd>
      </div>
    </dl>
  </section>

  <section class="border border-gray-200 rounded-lg overflow-hidden">
    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
      <h4 class="text-sm font-semibold text-gray-900">Goods receive note &amp; RFQ approvers</h4>
      <p class="text-xs text-gray-500 mt-0.5">Same approval matrix the organisation sees under Inventory → Goods receive note approvers.</p>
    </div>
    <div class="px-4 py-4">
      @if($config->approvers->count() > 0)
        <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200">
          @foreach($config->approvers as $approver)
            <li class="px-4 py-3 flex items-center justify-between gap-4">
              <div>
                <p class="text-sm font-medium text-gray-900">
                  Approver {{ $approver->approval_order }}: {{ $approver->user->name ?? '—' }}
                </p>
                <p class="text-xs text-gray-500">{{ $approver->user->email ?? '' }}</p>
              </div>
            </li>
          @endforeach
        </ul>
      @else
        <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-4 py-3">
          No goods receive note approvers assigned yet.
        </p>
      @endif
    </div>
  </section>

  <section class="border border-gray-200 rounded-lg overflow-hidden">
    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
      <h4 class="text-sm font-semibold text-gray-900">LPO email notifications</h4>
      <p class="text-xs text-gray-500 mt-0.5">Managed by the organisation on their Goods receive note approvers page.</p>
    </div>
    <dl class="px-4 py-4 space-y-4 text-sm">
      <div>
        <dt class="text-gray-500">Finance notification emails</dt>
        <dd class="mt-1 text-gray-900">
          @if($financeEmails !== [])
            <ul class="list-disc list-inside space-y-0.5">
              @foreach($financeEmails as $email)
                <li>{{ $email }}</li>
              @endforeach
            </ul>
          @else
            <span class="text-gray-400">—</span>
          @endif
        </dd>
      </div>
      <div>
        <dt class="text-gray-500">Copy LPO to approvers</dt>
        <dd class="mt-1 font-medium text-gray-900">
          {{ ($config->lpo_email_copy_to_approvers ?? true) ? 'Yes' : 'No' }}
        </dd>
      </div>
    </dl>
  </section>
</div>
