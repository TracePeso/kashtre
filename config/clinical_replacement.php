<?php

return [
    // Opt-in at deployment. Does not activate the old Clinical implementation.
    'enabled' => (bool) env('CLINICAL_REPLACEMENT_UI_ENABLED', false),
];
