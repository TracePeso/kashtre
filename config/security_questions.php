<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Security question bank
    |--------------------------------------------------------------------------
    |
    | Users pick from this curated list during setup. Keys must remain stable
    | so stored answers continue to match after deploys.
    |
    */

    'required_count' => 3,

    'challenge_count' => 2,

    'min_answer_length' => 3,

    'max_answer_length' => 255,

    'questions' => [
        'first_school' => 'What was the name of your first school?',
        'childhood_friend' => 'What was the name of your childhood best friend?',
        'first_pet' => 'What was the name of your first pet?',
        'birth_city' => 'In what city were you born?',
        'favorite_teacher' => 'What was the name of your favorite teacher?',
        'first_car' => 'What was the make or model of your first car?',
        'mothers_maiden_name' => "What is your mother's maiden name?",
        'favorite_book' => 'What is your favorite book?',
        'first_job' => 'What was your first job or employer?',
        'childhood_street' => 'What street did you grow up on?',
    ],

];
