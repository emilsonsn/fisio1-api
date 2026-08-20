<?php

return [
    'code_length' => 6,
    'code_expire_minutes' => (int) env('PASSWORD_RECOVERY_CODE_EXPIRE_MINUTES', 10),
    'max_attempts' => (int) env('PASSWORD_RECOVERY_MAX_ATTEMPTS', 5),
];
