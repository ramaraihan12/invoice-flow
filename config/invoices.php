<?php

return [

    'llm' => [
        // Any provider Prism supports. Ollama is the default so the app runs without an API key.
        'provider' => env('INVOICE_LLM_PROVIDER', 'ollama'),
        'model' => env('INVOICE_LLM_MODEL', 'qwen3.5:9b'),

        // Photos are only sent to the model when it can read images.
        'vision' => (bool) env('INVOICE_LLM_VISION', false),

        // Seconds. A 9B model on a laptop GPU can take a couple of minutes per invoice.
        'timeout' => (int) env('INVOICE_LLM_TIMEOUT', 300),
    ],

    // Thinking models like qwen3.5 reason for minutes before answering when this
    // is left on. Set OLLAMA_THINK=null to keep the model's own default.
    'ollama_think' => env('OLLAMA_THINK', false),

    'webhook' => [
        'url' => env('INVOICE_WEBHOOK_URL'),
        'secret' => env('INVOICE_WEBHOOK_SECRET'),
    ],

];
