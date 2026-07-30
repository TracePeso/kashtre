<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SecurityQuestionService;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;

class SecurityQuestionLoginController extends Controller
{
    public function __construct(
        private readonly SecurityQuestionService $securityQuestions,
        private readonly StatefulGuard $guard,
    ) {
    }

    public function store(Request $request)
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        $user = User::query()->find($request->session()->get('login.id'));

        if (! $user || ! $this->securityQuestions->userHasConfigured($user)) {
            return redirect()->route('login');
        }

        $expectedKeys = $request->session()->get('login.security_question_keys', []);

        if ($expectedKeys === []) {
            $challenge = $this->securityQuestions->prepareLoginChallenge($user);
            $expectedKeys = collect($challenge)->pluck('key')->all();
            $request->session()->put('login.security_question_keys', $expectedKeys);
        }

        $minLength = (int) config('security_questions.min_answer_length', 3);
        $maxLength = (int) config('security_questions.max_answer_length', 255);

        $rules = [];
        foreach ($expectedKeys as $key) {
            $rules['security_answers.'.$key] = ['required', 'string', 'min:'.$minLength, 'max:'.$maxLength];
        }

        $validated = $request->validate($rules);

        $answers = [];
        foreach ($expectedKeys as $key) {
            $answers[$key] = $validated['security_answers'][$key] ?? '';
        }

        if (! $this->securityQuestions->verifyLoginChallenge($user, $answers, $expectedKeys)) {
            Log::warning('Security question login failed', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'security_answers' => [__('The provided security question answers were incorrect.')],
            ]);
        }

        $remember = $request->session()->pull('login.remember', false);

        $request->session()->forget([
            'login.id',
            'login.security_question_keys',
        ]);

        $this->guard->login($user, $remember);
        $request->session()->regenerate();

        return app(TwoFactorLoginResponse::class);
    }
}
