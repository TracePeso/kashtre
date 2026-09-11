<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSecurityQuestion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SecurityQuestionService
{
    public function questionBank(): array
    {
        return config('security_questions.questions', []);
    }

    public function questionLabel(string $key): string
    {
        return $this->questionBank()[$key] ?? $key;
    }

    public function requiredCount(): int
    {
        return (int) config('security_questions.required_count', 3);
    }

    public function challengeCount(): int
    {
        return (int) config('security_questions.challenge_count', 2);
    }

    public function userHasConfigured(User $user): bool
    {
        if ($user->security_questions_enabled_at === null) {
            return false;
        }

        return $user->securityQuestions()->count() >= $this->requiredCount();
    }

    /**
     * @param  array<int, array{question_key: string, answer: string}>  $items
     */
    public function storeForUser(User $user, array $items): void
    {
        $required = $this->requiredCount();

        if (count($items) !== $required) {
            throw ValidationException::withMessages([
                'questions' => [__('You must set exactly :count security questions.', ['count' => $required])],
            ]);
        }

        $keys = collect($items)->pluck('question_key');

        if ($keys->unique()->count() !== $required) {
            throw ValidationException::withMessages([
                'questions' => [__('Each security question must be unique.')],
            ]);
        }

        $bank = $this->questionBank();

        foreach ($keys as $key) {
            if (! array_key_exists($key, $bank)) {
                throw ValidationException::withMessages([
                    'questions' => [__('One or more security questions are invalid.')],
                ]);
            }
        }

        DB::transaction(function () use ($user, $items): void {
            $user->securityQuestions()->delete();

            foreach (array_values($items) as $index => $item) {
                $user->securityQuestions()->create([
                    'question_key' => $item['question_key'],
                    'answer_hash' => $this->hashAnswer($item['answer']),
                    'sort_order' => $index + 1,
                ]);
            }

            $user->forceFill([
                'security_questions_enabled_at' => now(),
            ])->save();
        });
    }

    public function disableForUser(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->securityQuestions()->delete();

            $user->forceFill([
                'security_questions_enabled_at' => null,
                'primary_two_factor_method' => 'authenticator',
            ])->save();
        });
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function prepareLoginChallenge(User $user): array
    {
        $questions = $user->securityQuestions()
            ->orderBy('sort_order')
            ->get();

        $count = min($this->challengeCount(), $questions->count());

        return $questions->shuffle()->take($count)->values()->map(fn (UserSecurityQuestion $question) => [
            'key' => $question->question_key,
            'label' => $this->questionLabel($question->question_key),
        ])->all();
    }

    /**
     * @param  array<string, string>  $answersByKey
     * @param  array<int, string>  $expectedKeys
     */
    public function verifyLoginChallenge(User $user, array $answersByKey, array $expectedKeys): bool
    {
        $stored = $user->securityQuestions()
            ->whereIn('question_key', $expectedKeys)
            ->get()
            ->keyBy('question_key');

        if ($stored->count() !== count($expectedKeys)) {
            return false;
        }

        foreach ($expectedKeys as $key) {
            $answer = $answersByKey[$key] ?? null;

            if (! is_string($answer) || trim($answer) === '') {
                return false;
            }

            $record = $stored->get($key);

            if (! $record || ! Hash::check($this->normalizeAnswer($answer), $record->answer_hash)) {
                return false;
            }
        }

        return true;
    }

    public function hashAnswer(string $answer): string
    {
        return Hash::make($this->normalizeAnswer($answer));
    }

    public function normalizeAnswer(string $answer): string
    {
        return Str::lower(trim($answer));
    }

    public function configuredQuestions(User $user): Collection
    {
        return $user->securityQuestions()
            ->orderBy('sort_order')
            ->get();
    }
}
