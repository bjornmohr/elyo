<?php

namespace App\Http\Resources\Employee;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Metadata of an uploaded medical document. `id` is the document's opaque ULID
 * (ELYO-91 prompt 08a); the owning health subject and the storage key are never
 * exposed.
 */
class EmployeeDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fileName' => $this->file_name,
            'mimeType' => $this->mime_type,
            'size' => $this->size,
            'uploadedAt' => $this->uploaded_at?->toIso8601String(),
        ];
    }
}
