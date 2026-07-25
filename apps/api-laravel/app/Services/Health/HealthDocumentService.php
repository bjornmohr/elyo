<?php

namespace App\Services\Health;

use App\Models\Health\UserDocument;
use App\Services\Privacy\MappingServiceContract;
use App\Services\Privacy\PurposeCode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Health-domain access to uploaded medical documents (ELYO-91 prompt 08a,
 * ADR-003 D8).
 *
 * Only metadata lives here; the bytes stay on the existing public disk with the
 * existing mechanics. ADR-001 §2.9 storage hardening follow-up (own bucket,
 * signed URLs, virus scan) is deliberately out of scope for this prompt.
 */
class HealthDocumentService
{
    use ResolvesOwnSubject;

    public function __construct(private readonly MappingServiceContract $mappingService) {}

    protected function mappingService(): MappingServiceContract
    {
        return $this->mappingService;
    }

    /**
     * Newest uploads first — the profile screen's document list.
     *
     * @return Collection<int, UserDocument>
     */
    public function documentsFor(int $userId): Collection
    {
        return UserDocument::query()
            ->where('health_subject_id', $this->resolveSubjectId($userId, PurposeCode::HEALTH_SELF_READ))
            ->orderByDesc('uploaded_at')
            ->get();
    }

    /**
     * Stores an uploaded document for the caller's own subject.
     *
     * The storage directory is the health subject, not the user: a health
     * document keyed on an identity in the file path would rebuild exactly the
     * link this domain split removes. `store()` keeps Laravel's random hash
     * name, so the client-supplied file name — which may contain a person's
     * name — never becomes part of a path. ADR-001 §2.9 storage hardening
     * follow-up covers the remaining bucket/signed-URL work.
     */
    public function storeUploadedDocument(int $userId, UploadedFile $file): UserDocument
    {
        $subjectId = $this->resolveSubjectId($userId, PurposeCode::HEALTH_SELF_WRITE);
        $path = $file->store("employee-documents/{$subjectId}", 'public');

        return UserDocument::create([
            'health_subject_id' => $subjectId,
            'file_name' => $file->getClientOriginalName(),
            'blob_url' => Storage::disk('public')->url($path),
            'blob_key' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/pdf',
            'size' => $file->getSize(),
            'uploaded_at' => now(),
        ]);
    }
}
