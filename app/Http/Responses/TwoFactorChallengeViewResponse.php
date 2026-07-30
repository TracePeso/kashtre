<?php

namespace App\Http\Responses;

use App\Models\User;
use App\Services\SecurityQuestionService;
use Laravel\Fortify\Contracts\TwoFactorChallengeViewResponse as TwoFactorChallengeViewResponseContract;

class TwoFactorChallengeViewResponse implements TwoFactorChallengeViewResponseContract
{
    public function __construct(
        private readonly SecurityQuestionService $securityQuestions,
    ) {
    }

    public function toResponse($request)
    {
        $canUseSecurityQuestions = false;
        $challengeQuestions = [];

        if ($request->session()->has('login.id')) {
            $user = User::query()->find($request->session()->get('login.id'));

            if ($user && $this->securityQuestions->userHasConfigured($user)) {
                $canUseSecurityQuestions = true;
                $challengeQuestions = $this->challengeQuestionsForSession($request, $user);
            } else {
                $request->session()->forget('login.security_question_keys');
            }
        }

        return view('auth.two-factor-challenge', [
            'canUseSecurityQuestions' => $canUseSecurityQuestions,
            'challengeQuestions' => $challengeQuestions,
        ]);
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function challengeQuestionsForSession($request, User $user): array
    {
        $keys = $request->session()->get('login.security_question_keys');

        if (! is_array($keys) || $keys === []) {
            $challenge = $this->securityQuestions->prepareLoginChallenge($user);
            $keys = collect($challenge)->pluck('key')->all();
            $request->session()->put('login.security_question_keys', $keys);
        }

        return collect($keys)->map(fn (string $key) => [
            'key' => $key,
            'label' => $this->securityQuestions->questionLabel($key),
        ])->values()->all();
    }
}
