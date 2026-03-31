<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'          => ['required', 'string', 'max:255'],
            // content is required ONLY when no PDF is uploaded
            'content'        => ['required_without:pdf', 'nullable', 'string', 'min:50'],
            'pdf'            => ['nullable', 'file', 'mimes:pdf', 'max:20480'], // max 20MB
            'attempt_limit'  => ['sometimes', 'integer', 'min:1'],
            'is_active'      => ['sometimes', 'boolean'],
            'question_count' => ['sometimes', 'integer', 'min:23', 'max:32'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required_without' => 'Konten materi wajib diisi jika tidak mengupload file PDF.',
            'content.min'              => 'Konten materi harus minimal 50 karakter agar AI bisa generate soal berkualitas.',
            'pdf.mimes'                => 'File yang diupload harus berformat PDF.',
            'pdf.max'                  => 'Ukuran file PDF maksimal 20MB.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'data'    => $validator->errors(),
        ], 422));
    }
}
