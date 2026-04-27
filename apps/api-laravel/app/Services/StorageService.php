<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class StorageService
{
    /**
     * Store a file and return its public URL.
     */
    public function store(UploadedFile $file, string $directory = 'documents'): string
    {
        $path = $file->store($directory, 'public');
        return Storage::disk('public')->url($path);
    }

    /**
     * Delete a file by its URL.
     */
    public function delete(string $url): void
    {
        $path = Str::after($url, '/storage/');
        Storage::disk('public')->delete($path);
    }
}
