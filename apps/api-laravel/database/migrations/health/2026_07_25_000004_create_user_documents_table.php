<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uploaded medical-document metadata in the health domain (ELYO-91 prompt 08a,
 * ADR-003 D8).
 *
 * Replaces the identity-side `user_documents` table. Classification decision
 * (prompt 08a): the table is health data. `POST /employee/documents` is the only
 * writer, it accepts PDFs only, the employee portal labels the section
 * "Medizinische PDFs" and the upload awards the `medical_document_upload`
 * points reason — these are medical documents, not general attachments, so the
 * table moves instead of staying in identity.
 *
 * The table name is kept so the move stays a pure relocation; it is distinct
 * from the dormant `health_documents` catalogue table.
 *
 * `blob_key` / `blob_url` still point at the existing public disk — the DB
 * reference side only. ADR-001 §2.9 storage hardening follow-up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('health_subject_id')
                ->constrained('health_subjects')
                ->cascadeOnDelete();
            $table->string('file_name');
            $table->string('blob_url');
            $table->string('blob_key');
            $table->string('mime_type');
            $table->integer('size');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();

            $table->index(['health_subject_id', 'uploaded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_documents');
    }
};
