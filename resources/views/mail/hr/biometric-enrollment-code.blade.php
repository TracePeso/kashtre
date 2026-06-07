<x-mail::message>
# HR Biometric Enrollment Code

A secure {{ $enrollmentSession->purpose === 're-enrollment' ? 're-enrollment' : 'enrollment' }} was started for **{{ $enrollmentSession->staff_name }}**.

Use this secret code to authorize the personal device:

<x-mail::panel>
{{ $secretCode }}
</x-mail::panel>

This code expires at **{{ optional($enrollmentSession->secret_code_expires_at)->format('M j, Y H:i') }}**.

After the code is confirmed, face capture and mobile fingerprint enrollment must both finish within **2 minutes**.

If you did not expect this code, ignore this email and contact HR.

{{ config('app.name') }}
</x-mail::message>
