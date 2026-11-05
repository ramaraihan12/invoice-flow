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

    // The public demo: strangers, one shared free model key, a host with no
    // worker and no disk. Off everywhere else, and nothing here applies then.
    'demo' => [
        'enabled' => (bool) env('DEMO_MODE', false),

        // Well under the 4.5 MB Vercel takes in a request. Stored in Postgres,
        // where the free plan has 1 GB.
        'max_file_kb' => 2048,
        'max_pdf_pages' => 3,
        'max_pdf_chars' => 12000,

        // Every extraction is a model call. The sample invoices need four to
        // try them all, so the per-minute limit leaves room for that.
        'limits' => [
            'ip_minute' => 5,
            'ip_day' => 12,
            'all_day' => 40,
        ],

        'samples' => [
            '01-sinar-kemas-abadi.pdf' => ['title' => 'PT Sinar Kemas Abadi', 'hint' => 'Clean, unless another visitor already sent it'],
            '02-mitra-teknik-mandiri.pdf' => ['title' => 'CV Mitra Teknik Mandiri', 'hint' => 'A line total that is not 3 x 1.450.000'],
            '03-higienis-prima.pdf' => ['title' => 'PT Higienis Prima Sejahtera', 'hint' => 'PPN that does not match the rate'],
            '04-sinar-kemas-abadi-resend.pdf' => ['title' => 'PT Sinar Kemas Abadi, again', 'hint' => 'A resend, caught as a duplicate of the first one'],
        ],
    ],

];
