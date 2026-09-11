@php
    $checkbox = fn (string $name, bool $default = true) => old($name, $config->{$name} ?? $default);
@endphp

<div class="space-y-6">
    <div>
        <h4 class="text-sm font-semibold text-gray-900">Order &amp; RFQ approval emails</h4>
        <p class="text-xs text-gray-500 mt-0.5">Control which emails are sent during internal and external order approval.</p>
    </div>

    <div class="space-y-3">
        <label class="flex items-start gap-3 text-sm text-gray-700">
            <input type="hidden" name="notify_approvers_on_order_submitted" value="0">
            <input type="checkbox" name="notify_approvers_on_order_submitted" value="1"
                   @checked($checkbox('notify_approvers_on_order_submitted'))
                   class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span>
                <span class="font-medium text-gray-900">Notify approvers when an order is submitted</span>
                <span class="block text-xs text-gray-500">Emails all pending approvers that approval is required.</span>
            </span>
        </label>

        <label class="flex items-start gap-3 text-sm text-gray-700">
            <input type="hidden" name="notify_finance_on_order_submitted" value="0">
            <input type="checkbox" name="notify_finance_on_order_submitted" value="1"
                   @checked($checkbox('notify_finance_on_order_submitted'))
                   class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span>
                <span class="font-medium text-gray-900">Notify finance when an order is submitted</span>
                <span class="block text-xs text-gray-500">Uses the finance notification addresses below.</span>
            </span>
        </label>

        <label class="flex items-start gap-3 text-sm text-gray-700">
            <input type="hidden" name="notify_next_approver_on_approval" value="0">
            <input type="checkbox" name="notify_next_approver_on_approval" value="1"
                   @checked($checkbox('notify_next_approver_on_approval'))
                   class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span>
                <span class="font-medium text-gray-900">Notify the next approver after each approval step</span>
                <span class="block text-xs text-gray-500">Sends the approval request to whoever is next in the chain.</span>
            </span>
        </label>

        <label class="flex items-start gap-3 text-sm text-gray-700">
            <input type="hidden" name="notify_on_order_fully_approved" value="0">
            <input type="checkbox" name="notify_on_order_fully_approved" value="1"
                   @checked($checkbox('notify_on_order_fully_approved'))
                   class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span>
                <span class="font-medium text-gray-900">Notify finance and approvers when an order is fully approved</span>
                <span class="block text-xs text-gray-500">Confirmation email after the final approval step.</span>
            </span>
        </label>

        <label class="flex items-start gap-3 text-sm text-gray-700">
            <input type="hidden" name="notify_suppliers_on_rfq_approved" value="0">
            <input type="checkbox" name="notify_suppliers_on_rfq_approved" value="1"
                   @checked($checkbox('notify_suppliers_on_rfq_approved'))
                   class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span>
                <span class="font-medium text-gray-900">Email RFQ to suppliers when an external order is approved</span>
                <span class="block text-xs text-gray-500">Sends the RFQ PDF to invited suppliers (or the primary supplier).</span>
            </span>
        </label>
    </div>

    <div class="border-t border-gray-200 pt-6">
        <h4 class="text-sm font-semibold text-gray-900">LPO emails</h4>
        <p class="text-xs text-gray-500 mt-0.5">When a local purchase order is issued, PDF copies can be emailed.</p>
    </div>

    <div class="space-y-3">
        <label class="flex items-start gap-3 text-sm text-gray-700">
            <input type="hidden" name="notify_on_lpo_issued" value="0">
            <input type="checkbox" name="notify_on_lpo_issued" value="1"
                   @checked($checkbox('notify_on_lpo_issued'))
                   class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span>
                <span class="font-medium text-gray-900">Send LPO emails when an LPO is issued</span>
                <span class="block text-xs text-gray-500">Master switch for all LPO notification emails.</span>
            </span>
        </label>

        <div>
            <label for="finance_notification_emails" class="block text-sm font-medium text-gray-700">Finance notification emails</label>
            <p class="text-xs text-gray-500 mt-0.5">Comma or newline separated. Used for order submission, full approval, and LPO copies.</p>
            <textarea name="finance_notification_emails" id="finance_notification_emails" rows="3"
                      class="mt-2 block w-full rounded-md border-gray-300 shadow-sm text-sm"
                      placeholder="finance@example.com, accounts@example.com">{{ old('finance_notification_emails', $config->finance_notification_emails) }}</textarea>
        </div>

        <label class="flex items-start gap-3 text-sm text-gray-700">
            <input type="hidden" name="lpo_email_copy_to_approvers" value="0">
            <input type="checkbox" name="lpo_email_copy_to_approvers" value="1"
                   @checked($checkbox('lpo_email_copy_to_approvers'))
                   class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <span>
                <span class="font-medium text-gray-900">Also email LPO copies to RFQ and goods receive note approvers</span>
            </span>
        </label>
    </div>
</div>
