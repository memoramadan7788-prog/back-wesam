<?php

return [
    'cv' => [
        'max_file_kb' => (int) env('ATS_CV_MAX_KB', 5120),
        'allowed_extensions' => ['pdf', 'docx', 'txt', 'md'],
        'min_chars' => 50,
        'max_chars_for_model' => (int) env('ATS_CV_MAX_CHARS', 12000),
    ],

    'ollama' => [
        'base_url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 120),

        
        'default_model' => env('OLLAMA_MODEL', 'llama3.2'),
        'allowed_models' => array_filter(array_map('trim', explode(',', env('OLLAMA_ALLOWED_MODELS', 'llama3.2')))),

       
        'max_attempts' => (int) env('OLLAMA_MAX_ATTEMPTS', 3),

        
        'options' => [
            'temperature' => 0,
            'top_p' => 0.9,
            'seed' => 42,
            'num_ctx' => (int) env('OLLAMA_NUM_CTX', 8192),
        ],
    ],

    'scoring' => [
       
        'weights' => ['skills' => 0.6, 'experience' => 0.3, 'education' => 0.1],

        
        'levels' => ['strong' => 1.0, 'partial' => 0.6, 'weak' => 0.25, 'none' => 0.0],

        'recommendation' => ['strong' => 75, 'moderate' => 50],
    ],
];