<?php

namespace App\Http\Requests;

use App\Extraction\DocumentReader;
use App\Extraction\ExtractionFailed;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StoreInvoicesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'max:'.(config('invoices.demo.enabled') ? 1 : 20)],
            'files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:'.$this->maxKilobytes()],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (config('invoices.llm.vision')) {
                    return;
                }

                foreach ($this->uploadedFiles() as $i => $file) {
                    if (str_starts_with((string) $file->getMimeType(), 'image/')) {
                        $validator->errors()->add("files.{$i}", "{$file->getClientOriginalName()} is a photo, but the configured model can't read images. Upload the PDF instead.");
                    }
                }
            },
            function (Validator $validator): void {
                if (! config('invoices.demo.enabled') || $validator->errors()->isNotEmpty()) {
                    return;
                }

                // The job checks this too. Doing it here as well means a PDF
                // that can't pass leaves no row and spends nothing shared.
                foreach ($this->uploadedFiles() as $i => $file) {
                    if ($file->getMimeType() !== 'application/pdf') {
                        continue;
                    }

                    try {
                        app(DocumentReader::class)->pdfText($file->getContent());
                    } catch (ExtractionFailed $e) {
                        $validator->errors()->add("files.{$i}", $e->getMessage());
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => 'Pick at least one invoice.',
            'files.*.mimes' => 'Only PDF, JPG, PNG and WebP files are supported.',
            'files.*.max' => 'Files can be '.($this->maxKilobytes() / 1024).' MB at most.',
            ...(config('invoices.demo.enabled') ? ['files.max' => 'One file at a time on this demo.'] : []),
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function uploadedFiles(): array
    {
        return array_filter((array) $this->file('files'), fn (mixed $file): bool => $file instanceof UploadedFile);
    }

    private function maxKilobytes(): int
    {
        return config('invoices.demo.enabled') ? (int) config('invoices.demo.max_file_kb') : 10240;
    }
}
