<?php

namespace App\Services;

use App\Models\KashtreHrModuleSetting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HrModuleSyncService
{
    /**
     * Shape a user payload for HR Module list/show/sync APIs.
     *
     * @return array<string, mixed>
     */
    public function userPayload(User $user): array
    {
        $user->loadMissing([
            'business:id,uuid,name',
            'branch:id,uuid,name',
            'department:id,uuid,name',
            'qualification:id,uuid,name',
            'staffCategory:id,uuid,name',
            'title:id,uuid,name',
        ]);

        return [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'gender' => $user->gender,
            'business_id' => $user->business_id,
            'branch_id' => $user->branch_id,
            'department_id' => $user->department_id,
            'qualification_id' => $user->qualification_id,
            'staff_category_id' => $user->staff_category_id,
            'title_id' => $user->title_id,
            'status' => $user->status,
            'hire_date' => $user->hire_date?->toDateString(),
            'birth_date' => $user->birth_date?->toDateString(),
            'employment_type' => $user->employment_type,
            'employee_code' => $user->employee_code,
            'nin' => $user->nin,
            'marital_status' => $user->marital_status,
            'business' => $user->business ? [
                'id' => $user->business->id,
                'uuid' => $user->business->uuid,
                'name' => $user->business->name,
            ] : null,
            'branch' => $user->branch ? [
                'id' => $user->branch->id,
                'uuid' => $user->branch->uuid,
                'name' => $user->branch->name,
            ] : null,
            'department' => $user->department ? [
                'id' => $user->department->id,
                'uuid' => $user->department->uuid,
                'name' => $user->department->name,
            ] : null,
            'qualification' => $user->qualification ? [
                'id' => $user->qualification->id,
                'uuid' => $user->qualification->uuid,
                'name' => $user->qualification->name,
            ] : null,
            'staff_category' => $user->staffCategory ? [
                'id' => $user->staffCategory->id,
                'uuid' => $user->staffCategory->uuid,
                'name' => $user->staffCategory->name,
            ] : null,
            'title' => $user->title ? [
                'id' => $user->title->id,
                'uuid' => $user->title->uuid,
                'name' => $user->title->name,
            ] : null,
            // Nested employee identity block for HR Module consumers.
            'employee_identity' => [
                'employee_code' => $user->employee_code,
                'nin' => $user->nin,
                'hire_date' => $user->hire_date?->toDateString(),
                'birth_date' => $user->birth_date?->toDateString(),
                'employment_type' => $user->employment_type,
                'marital_status' => $user->marital_status,
                'gender' => $user->gender,
                'title_id' => $user->title_id,
                'qualification_id' => $user->qualification_id,
                'staff_category_id' => $user->staff_category_id,
            ],
        ];
    }

    /**
     * @param  Collection<int, User>|array<int, User>|User  $users
     */
    public function pushUsers(Collection|array|User $users): void
    {
        $settings = KashtreHrModuleSetting::resolved();

        if (! $settings->isSyncEnabled() || ! $settings->isConfigured()) {
            return;
        }

        $collection = $users instanceof User
            ? collect([$users])
            : collect($users);

        $payload = $collection
            ->filter(fn ($user) => $user instanceof User && (int) $user->business_id !== 1)
            ->map(fn (User $user) => $this->userPayload($user))
            ->values()
            ->all();

        if ($payload === []) {
            return;
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'X-API-Key' => $settings->apiKey(),
                    'Accept' => 'application/json',
                ])
                ->post($settings->baseUrl().'/api/sync/users', [
                    'users' => $payload,
                ]);

            if ($response->failed()) {
                Log::warning('HR Module user sync failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'count' => count($payload),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('HR Module user sync exception', [
                'message' => $e->getMessage(),
                'count' => count($payload),
            ]);
        }
    }

    /**
     * Build a short-lived SSO token: base64(email|business_id|unix_timestamp)
     */
    public function makeSsoToken(User $user): string
    {
        $raw = implode('|', [
            (string) $user->email,
            (string) (int) $user->business_id,
            (string) now()->timestamp,
        ]);

        return base64_encode($raw);
    }

    public function ssoRedirectUrl(User $user): ?string
    {
        $baseUrl = KashtreHrModuleSetting::resolved()->baseUrl();

        if ($baseUrl === '') {
            return null;
        }

        return $baseUrl.'/auth/sso?token='.urlencode($this->makeSsoToken($user));
    }
}
