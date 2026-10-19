<?php

namespace App\Http\Requests;

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
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
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
            'files.*.max' => 'Files can be 10 MB at most.',
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function uploadedFiles(): array
    {
        return array_filter((array) $this->file('files'), fn (mixed $file): bool => $file instanceof UploadedFile);
    }
}
