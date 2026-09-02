<?php

namespace App\Livewire\Profile;

use App\Services\SecurityQuestionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Laravel\Jetstream\ConfirmsPasswords;
use Livewire\Component;

class SecurityQuestionsForm extends Component
{
    use ConfirmsPasswords;

    /** @var array<int, array{question_key: string, answer: string}> */
    public array $questions = [];

    public bool $editing = false;

    public function mount(SecurityQuestionService $securityQuestions): void
    {
        $this->resetForm($securityQuestions);
    }

    public function startEditing(): void
    {
        $this->editing = true;
    }

    public function cancelEditing(SecurityQuestionService $securityQuestions): void
    {
        $this->editing = false;
        $this->resetForm($securityQuestions);
        $this->resetErrorBag();
    }

    public function save(SecurityQuestionService $securityQuestions): void
    {
        $this->ensurePasswordIsConfirmed();

        $bankKeys = array_keys($securityQuestions->questionBank());
        $required = $securityQuestions->requiredCount();
        $minLength = (int) config('security_questions.min_answer_length', 3);
        $maxLength = (int) config('security_questions.max_answer_length', 255);

        $this->validate([
            'questions' => ['required', 'array', 'size:'.$required],
            'questions.*.question_key' => ['required', 'string', Rule::in($bankKeys), 'distinct'],
            'questions.*.answer' => ['required', 'string', 'min:'.$minLength, 'max:'.$maxLength],
        ], [
            'questions.*.question_key.distinct' => __('Each security question must be unique.'),
            'questions.*.answer.required' => __('Please provide an answer for each question.'),
        ]);

        $securityQuestions->storeForUser(Auth::user(), $this->questions);

        $this->editing = false;
        $this->resetForm($securityQuestions);

        $this->dispatch('saved');
    }

    public function disable(SecurityQuestionService $securityQuestions): void
    {
        $this->ensurePasswordIsConfirmed();

        $securityQuestions->disableForUser(Auth::user());

        $this->editing = false;
        $this->resetForm($securityQuestions);

        $this->dispatch('saved');
    }

    public function render(SecurityQuestionService $securityQuestions)
    {
        return view('profile.security-questions-form', [
            'questionBank' => $securityQuestions->questionBank(),
            'requiredCount' => $securityQuestions->requiredCount(),
            'enabled' => Auth::user()->hasSecurityQuestionsConfigured(),
            'configuredQuestions' => $securityQuestions->configuredQuestions(Auth::user()),
        ]);
    }

    private function resetForm(SecurityQuestionService $securityQuestions): void
    {
        $required = $securityQuestions->requiredCount();
        $configured = $securityQuestions->configuredQuestions(Auth::user())->keyBy('question_key');

        $this->questions = [];

        for ($index = 0; $index < $required; $index++) {
            $existing = $configured->values()->get($index);

            $this->questions[] = [
                'question_key' => $existing?->question_key ?? '',
                'answer' => '',
            ];
        }
    }
}
