<?php

namespace App\Jobs;

use App\Models\HrDutyRoster;
use App\Models\User;
use App\Services\DutyRosterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Validation\ValidationException;
use Throwable;

class GenerateAiRosterDraft implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly int $rosterId,
        public readonly int $userId,
        public readonly array $payload,
        public readonly string $generationToken
    ) {
    }

    public function handle(DutyRosterService $dutyRosterService): void
    {
        $roster = HrDutyRoster::query()->find($this->rosterId);
        $user = User::query()->find($this->userId);

        if (! $roster || ! $user) {
            $this->markFailed('Gemini roster generation could not start because the roster or user record is no longer available.');

            return;
        }

        try {
            $dutyRosterService->generateAiDraft($roster, $user, array_merge($this->payload, [
                'ai_generation_token' => $this->generationToken,
                'fallback_on_gemini_failure' => false,
            ]));

            $this->markCompleted('Gemini roster generation completed and the roster draft was updated.');

            return;
        } catch (ValidationException|Throwable $exception) {
            $fallbackMessage = $this->attemptAutomaticFallback($dutyRosterService, $user);

            if ($fallbackMessage !== null) {
                $this->markCompleted($fallbackMessage);

                return;
            }

            $this->markFailed($exception instanceof ValidationException
                ? $this->validationMessage($exception)
                : $exception->getMessage());
        }
    }

    private function attemptAutomaticFallback(DutyRosterService $dutyRosterService, User $user): ?string
    {
        $roster = HrDutyRoster::query()->find($this->rosterId);

        if (! $roster) {
            return null;
        }

        try {
            $dutyRosterService->generateAutomaticFallbackForAiDraft($roster, $user, array_merge($this->payload, [
                'ai_generation_token' => $this->generationToken,
            ]));
        } catch (ValidationException|Throwable) {
            return null;
        }

        return 'Gemini roster generation failed, so the automatic fallback completed and the roster draft was updated.';
    }

    private function markCompleted(string $message): void
    {
        $roster = HrDutyRoster::query()->find($this->rosterId);
        $attempts = max(1, (int) ($roster?->ai_generation_attempts ?? 1));

        HrDutyRoster::query()
            ->whereKey($this->rosterId)
            ->where('ai_generation_token', $this->generationToken)
            ->update([
                'ai_generation_status' => HrDutyRoster::AI_GENERATION_COMPLETED,
                'ai_generation_source' => HrDutyRoster::AI_GENERATION_SOURCE_GEMINI,
                'ai_generation_message' => $message,
                'ai_generation_attempts' => $attempts,
                'ai_generation_heartbeat_at' => now(),
                'ai_generation_completed_at' => now(),
                'ai_generation_failed_at' => null,
            ]);
    }

    private function markFailed(string $message): void
    {
        $roster = HrDutyRoster::query()->find($this->rosterId);
        $attempts = max(1, (int) ($roster?->ai_generation_attempts ?? 1));

        HrDutyRoster::query()
            ->whereKey($this->rosterId)
            ->where('ai_generation_token', $this->generationToken)
            ->update([
                'ai_generation_status' => HrDutyRoster::AI_GENERATION_FAILED,
                'ai_generation_source' => HrDutyRoster::AI_GENERATION_SOURCE_GEMINI,
                'ai_generation_message' => $message !== '' ? $message : 'Gemini roster generation failed.',
                'ai_generation_attempts' => $attempts,
                'ai_generation_heartbeat_at' => now(),
                'ai_generation_completed_at' => null,
                'ai_generation_failed_at' => now(),
            ]);
    }

    private function validationMessage(ValidationException $exception): string
    {
        return collect($exception->errors())
            ->flatten()
            ->map(fn ($message): string => trim((string) $message))
            ->filter()
            ->implode(' ');
    }
}
