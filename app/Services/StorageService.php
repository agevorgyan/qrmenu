<?php

namespace App\Services;

use App\Exceptions\InvalidFileException;
use App\Exceptions\InvalidStoragePathException;
use App\Exceptions\StorageQuotaExceededException;
use App\Models\Vendor;
use App\Models\VendorStorageFile;
use App\Services\Security\ImageProcessor;
use App\Services\Security\SvgSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService
{
    public const ALLOWED_NAMESPACES = [
        'products',
        'branding',
        'gallery',
        'qr',
        'documents',
        'temp',
    ];

    public const MAX_FILE_SIZE_BYTES = 15728640; // 15 MB

    public const RASTER_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/avif',
    ];

    public const IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/avif',
        'image/svg+xml',
    ];

    public const DOCUMENT_MIME_TYPES = [
        'application/pdf',
        'text/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
        'application/json',
    ];

    public const DISALLOWED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
        'exe', 'dll', 'so', 'bin', 'sh', 'bash', 'bat', 'cmd',
        'cgi', 'pl', 'py', 'js', 'vbs', 'html', 'htm',
    ];

    public function __construct(
        protected ?ImageProcessor $imageProcessor = null,
        protected ?SvgSanitizer $svgSanitizer = null
    ) {
        $this->imageProcessor = $imageProcessor ?? new ImageProcessor;
        $this->svgSanitizer = $svgSanitizer ?? new SvgSanitizer;
    }

    /**
     * Resolve and validate current vendor context.
     */
    public function determineVendor(?Vendor $vendor = null): Vendor
    {
        if ($vendor !== null) {
            if (Auth::check()) {
                $user = Auth::user();
                if ($user->role !== 'superadmin' && $user->vendor_id !== null && (int) $user->vendor_id !== (int) $vendor->id) {
                    throw new InvalidStoragePathException('Cross-vendor storage operation is strictly prohibited.');
                }
            }

            if (empty($vendor->uuid)) {
                $vendor->uuid = (string) Str::uuid();
                $vendor->saveQuietly();
            }

            return $vendor;
        }

        // Try TenantContext singleton
        $tenantId = app(TenantContext::class)->getVendorId();
        if ($tenantId) {
            $found = Vendor::find($tenantId);
            if ($found) {
                if (empty($found->uuid)) {
                    $found->uuid = (string) Str::uuid();
                    $found->saveQuietly();
                }

                return $found;
            }
        }

        // Try Authenticated user's vendor
        if (Auth::check() && Auth::user()->vendor) {
            $vendor = Auth::user()->vendor;
            if (empty($vendor->uuid)) {
                $vendor->uuid = (string) Str::uuid();
                $vendor->saveQuietly();
            }

            return $vendor;
        }

        throw new \InvalidArgumentException('No active vendor context could be determined for storage operation.');
    }

    /**
     * Store an uploaded file in the vendor's isolated storage namespace.
     */
    public function store(
        UploadedFile $file,
        string $namespace,
        ?Vendor $vendor = null,
        ?Model $entity = null,
        string $disk = 'public'
    ): VendorStorageFile {
        $vendor = $this->determineVendor($vendor);
        $this->validateNamespace($namespace);
        $this->validateUploadedFile($file, $namespace);

        $fileSize = $file->getSize();
        if (! $this->canUpload($vendor, $fileSize)) {
            $usedBytes = (int) ($vendor->storage_used_bytes ?? 0);
            $limitBytes = (int) ($vendor->storage_limit_bytes ?? $vendor->resolveStorageLimit());
            $usedMb = round($usedBytes / (1024 * 1024), 2);
            $limitMb = round($limitBytes / (1024 * 1024), 2);
            $attemptMb = round($fileSize / (1024 * 1024), 2);

            throw new StorageQuotaExceededException(
                "Vendor storage quota exceeded. [Limit: {$limitMb}MB, Used: {$usedMb}MB, Attempted: {$attemptMb}MB]",
                $limitBytes,
                $usedBytes,
                $fileSize
            );
        }

        $fileUuid = (string) Str::uuid();
        $ext = strtolower($file->getClientOriginalExtension());
        $mimeType = $this->detectRealMimeType($file);

        if (empty($ext) || ! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'avif', 'svg', 'pdf', 'csv', 'xlsx', 'xls', 'docx', 'doc', 'txt', 'json'], true)) {
            $ext = match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/avif' => 'avif',
                'image/svg+xml' => 'svg',
                'application/pdf' => 'pdf',
                'text/csv' => 'csv',
                default => 'bin',
            };
        }

        $filename = "{$fileUuid}.{$ext}";
        $relativeDir = "vendors/{$vendor->uuid}/{$namespace}";
        $relativePath = "{$relativeDir}/{$filename}";
        $originalName = basename(str_replace(["\0", '%00', '\\'], '', $file->getClientOriginalName()));

        $finalSize = $fileSize;
        $checksum = '';

        // 1. Re-encode raster images / sanitize SVG and physically put on disk
        if (in_array($mimeType, self::RASTER_IMAGE_MIME_TYPES, true) || $mimeType === 'image/svg+xml') {
            $tempCleanPath = tempnam(sys_get_temp_dir(), 'clean_');
            $meta = $this->imageProcessor->reencodeAndStripMetadata($file->getRealPath(), $mimeType, $tempCleanPath);
            $finalSize = $meta['size'];
            $checksum = hash_file('sha256', $tempCleanPath) ?: '';

            $stored = Storage::disk($disk)->putFileAs($relativeDir, new File($tempCleanPath), $filename);
            @unlink($tempCleanPath);
        } else {
            $checksum = hash_file('sha256', $file->getRealPath()) ?: '';
            $stored = Storage::disk($disk)->putFileAs($relativeDir, $file, $filename);
        }

        if (! $stored) {
            throw new \RuntimeException('Failed to write uploaded file to storage disk.');
        }

        // 2. Atomically create metadata and increment usage
        try {
            return DB::transaction(function () use (
                $vendor,
                $fileUuid,
                $disk,
                $relativePath,
                $originalName,
                $mimeType,
                $finalSize,
                $checksum,
                $entity
            ) {
                $storageFile = VendorStorageFile::create([
                    'vendor_id' => $vendor->id,
                    'uuid' => $fileUuid,
                    'disk' => $disk,
                    'path' => $relativePath,
                    'original_name' => $originalName,
                    'mime_type' => $mimeType,
                    'size_bytes' => $finalSize,
                    'checksum' => $checksum,
                    'entity_type' => $entity ? get_class($entity) : null,
                    'entity_id' => $entity ? $entity->getKey() : null,
                    'status' => 'active',
                ]);

                Vendor::where('id', $vendor->id)->update([
                    'storage_used_bytes' => DB::raw("storage_used_bytes + {$finalSize}"),
                    'storage_files_count' => DB::raw('storage_files_count + 1'),
                ]);
                $vendor->refresh();

                return $storageFile;
            });
        } catch (\Throwable $e) {
            // Rollback physical file on failure to prevent orphans
            Storage::disk($disk)->delete($relativePath);
            throw $e;
        }
    }

    /**
     * Replace an existing file with a newly uploaded file safely.
     */
    public function replace(
        ?string $oldPathOrUuid,
        UploadedFile $newFile,
        string $namespace,
        ?Vendor $vendor = null,
        ?Model $entity = null,
        string $disk = 'public'
    ): VendorStorageFile {
        $vendor = $this->determineVendor($vendor);
        $this->validateNamespace($namespace);
        $this->validateUploadedFile($newFile, $namespace);

        $oldRecord = null;
        if (! empty($oldPathOrUuid)) {
            $cleanOld = $this->cleanPath($oldPathOrUuid);
            $oldRecord = VendorStorageFile::where('vendor_id', $vendor->id)
                ->where(function ($q) use ($oldPathOrUuid, $cleanOld) {
                    if (Str::isUuid($oldPathOrUuid)) {
                        $q->where('uuid', $oldPathOrUuid)->orWhere('path', $cleanOld);
                    } else {
                        $q->where('path', $cleanOld)->orWhere('path', $oldPathOrUuid);
                    }
                })
                ->first();
        }

        $oldSizeBytes = $oldRecord?->size_bytes ?? 0;
        $newSizeBytes = $newFile->getSize();
        $netDeltaBytes = max(0, $newSizeBytes - $oldSizeBytes);

        if (! $this->canUpload($vendor, $netDeltaBytes)) {
            $usedBytes = (int) ($vendor->storage_used_bytes ?? 0);
            $limitBytes = (int) ($vendor->storage_limit_bytes ?? $vendor->resolveStorageLimit());
            $usedMb = round($usedBytes / (1024 * 1024), 2);
            $limitMb = round($limitBytes / (1024 * 1024), 2);
            $attemptMb = round($newSizeBytes / (1024 * 1024), 2);

            throw new StorageQuotaExceededException(
                "Vendor storage quota exceeded during replacement. [Limit: {$limitMb}MB, Used: {$usedMb}MB, Attempted: {$attemptMb}MB]",
                $limitBytes,
                $usedBytes,
                $newSizeBytes
            );
        }

        // Store new file first
        $newFileRecord = $this->store($newFile, $namespace, $vendor, $entity, $disk);

        // Delete old file if present
        if ($oldRecord) {
            $this->delete($oldRecord, $vendor, $disk);
        } elseif (! empty($oldPathOrUuid)) {
            $cleanOld = $this->cleanPath($oldPathOrUuid);
            if ($this->isVendorScopedPath($cleanOld, $vendor)) {
                if (Storage::disk($disk)->exists($cleanOld)) {
                    Storage::disk($disk)->delete($cleanOld);
                }
            }
        }

        return $newFileRecord;
    }

    /**
     * Delete a file belonging to the vendor.
     */
    public function delete(string|VendorStorageFile $fileOrPath, ?Vendor $vendor = null, string $disk = 'public'): bool
    {
        $vendor = $this->determineVendor($vendor);

        if ($fileOrPath instanceof VendorStorageFile) {
            if ((int) $fileOrPath->vendor_id !== (int) $vendor->id) {
                throw new InvalidStoragePathException('Cross-vendor storage deletion is strictly prohibited.');
            }
            $record = $fileOrPath;
            $path = $record->path;
        } else {
            $path = $this->cleanPath($fileOrPath);
            $this->assertVendorOwnsPath($path, $vendor);

            $record = VendorStorageFile::where('vendor_id', $vendor->id)
                ->where(function ($q) use ($fileOrPath, $path) {
                    if (Str::isUuid($fileOrPath)) {
                        $q->where('uuid', $fileOrPath)->orWhere('path', $path);
                    } else {
                        $q->where('path', $path)->orWhere('path', $fileOrPath);
                    }
                })
                ->first();
        }

        $size = $record?->size_bytes ?? 0;

        // Delete physical file from disk
        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }

        // Soft delete record and atomically update usage
        if ($record) {
            $record->update(['status' => 'deleted']);
            $record->delete();

            Vendor::where('id', $vendor->id)->update([
                'storage_used_bytes' => DB::raw("CASE WHEN storage_used_bytes >= {$size} THEN storage_used_bytes - {$size} ELSE 0 END"),
                'storage_files_count' => DB::raw('CASE WHEN storage_files_count >= 1 THEN storage_files_count - 1 ELSE 0 END'),
            ]);
            $vendor->refresh();
        }

        return true;
    }

    /**
     * Check if a file exists on disk within the vendor's storage.
     */
    public function exists(string|VendorStorageFile $fileOrPath, ?Vendor $vendor = null, string $disk = 'public'): bool
    {
        $vendor = $this->determineVendor($vendor);

        if ($fileOrPath instanceof VendorStorageFile) {
            if ((int) $fileOrPath->vendor_id !== (int) $vendor->id) {
                return false;
            }

            return Storage::disk($fileOrPath->disk)->exists($fileOrPath->path);
        }

        $path = $this->cleanPath($fileOrPath);
        if (! $this->isVendorScopedPath($path, $vendor)) {
            return false;
        }

        return Storage::disk($disk)->exists($path);
    }

    /**
     * Get current storage usage metrics for vendor.
     */
    public function usage(?Vendor $vendor = null): array
    {
        $vendor = $this->determineVendor($vendor);
        $used = (int) $vendor->storage_used_bytes;
        $limit = (int) ($vendor->storage_limit_bytes ?: $vendor->resolveStorageLimit());
        $count = (int) $vendor->storage_files_count;

        return [
            'vendor_id' => $vendor->id,
            'vendor_uuid' => $vendor->uuid,
            'used_bytes' => $used,
            'used_mb' => round($used / (1024 * 1024), 2),
            'limit_bytes' => $limit,
            'limit_mb' => round($limit / (1024 * 1024), 2),
            'files_count' => $count,
            'percentage' => $limit > 0 ? min(100, round(($used / $limit) * 100, 1)) : 0,
            'available_bytes' => max(0, $limit - $used),
        ];
    }

    /**
     * Get vendor's storage quota limit in bytes.
     */
    public function quota(?Vendor $vendor = null): int
    {
        $vendor = $this->determineVendor($vendor);

        return (int) ($vendor->storage_limit_bytes ?: $vendor->resolveStorageLimit());
    }

    /**
     * Determine if vendor has enough quota remaining to upload specified bytes.
     */
    public function canUpload(?Vendor $vendor, int $bytes): bool
    {
        $vendor = $this->determineVendor($vendor);
        $limit = (int) ($vendor->storage_limit_bytes ?: $vendor->resolveStorageLimit());

        return ((int) $vendor->storage_used_bytes + $bytes) <= $limit;
    }

    /**
     * Delete entire storage namespace for a vendor.
     */
    public function deleteVendorStorage(Vendor $vendor, string $disk = 'public'): bool
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->role !== 'superadmin' && (int) $user->vendor_id !== (int) $vendor->id) {
                throw new InvalidStoragePathException('Cannot delete another vendor storage namespace.');
            }
        }

        if (empty($vendor->uuid)) {
            return false;
        }

        $dir = "vendors/{$vendor->uuid}";
        Storage::disk($disk)->deleteDirectory($dir);

        VendorStorageFile::where('vendor_id', $vendor->id)->delete();

        Vendor::where('id', $vendor->id)->update([
            'storage_used_bytes' => 0,
            'storage_files_count' => 0,
        ]);
        $vendor->refresh();

        return true;
    }

    /**
     * Clean and normalize a path string.
     */
    public function cleanPath(string $path): string
    {
        $clean = trim($path);
        if (str_starts_with($clean, 'http://') || str_starts_with($clean, 'https://')) {
            $urlPath = parse_url($clean, PHP_URL_PATH);
            if ($urlPath !== false && $urlPath !== null) {
                $clean = $urlPath;
            }
        }

        if (str_starts_with($clean, '/storage/')) {
            $clean = substr($clean, 9);
        } elseif (str_starts_with($clean, 'storage/')) {
            $clean = substr($clean, 8);
        }

        $clean = ltrim($clean, '/');

        // Check for directory traversal and null bytes
        if (str_contains($clean, '..') || str_contains($clean, '\\') || str_contains($clean, "\0") || str_contains($clean, '%00')) {
            throw new InvalidStoragePathException('Path traversal is strictly prohibited.');
        }

        return $clean;
    }

    /**
     * Assert that a given path belongs strictly to the vendor namespace.
     */
    public function assertVendorOwnsPath(string $cleanPath, Vendor $vendor): void
    {
        $expectedPrefix = "vendors/{$vendor->uuid}/";
        if (! str_starts_with($cleanPath, $expectedPrefix)) {
            throw new InvalidStoragePathException("Storage path [{$cleanPath}] does not belong to vendor [{$vendor->uuid}].");
        }
    }

    /**
     * Check if path starts with vendor namespace prefix.
     */
    public function isVendorScopedPath(string $cleanPath, Vendor $vendor): bool
    {
        $expectedPrefix = "vendors/{$vendor->uuid}/";

        return str_starts_with($cleanPath, $expectedPrefix);
    }

    /**
     * Validate requested namespace against whitelist.
     */
    protected function validateNamespace(string $namespace): void
    {
        if (str_contains($namespace, '..') || str_contains($namespace, '/') || str_contains($namespace, '\\') || str_contains($namespace, "\0") || str_contains($namespace, '%00')) {
            throw new InvalidStoragePathException("Invalid storage namespace format [{$namespace}].");
        }

        if (! in_array($namespace, self::ALLOWED_NAMESPACES, true)) {
            throw new InvalidStoragePathException("Unsupported storage namespace [{$namespace}].");
        }
    }

    /**
     * Detect real MIME type of uploaded file using fileinfo.
     */
    public function detectRealMimeType(UploadedFile $file): string
    {
        $realPath = $file->getRealPath();
        if ($realPath && file_exists($realPath)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $realPath);
                if (! empty($mime)) {
                    return $mime;
                }
            }
        }

        return $file->getMimeType() ?: 'application/octet-stream';
    }

    /**
     * Validate uploaded file size, extension, and real MIME type.
     */
    protected function validateUploadedFile(UploadedFile $file, string $namespace): void
    {
        $size = $file->getSize();
        if ($size > self::MAX_FILE_SIZE_BYTES) {
            throw new InvalidFileException('File size exceeds maximum allowable limit of 15MB.');
        }

        $origName = $file->getClientOriginalName();
        if (
            str_contains($origName, '..') ||
            str_contains($origName, '/') ||
            str_contains($origName, '\\') ||
            str_contains($origName, "\0") ||
            str_contains($origName, '%00')
        ) {
            throw new InvalidStoragePathException('Path traversal detected in file name.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, self::DISALLOWED_EXTENSIONS, true)) {
            throw new InvalidFileException("Execution-risk file extension [{$extension}] is strictly prohibited.");
        }

        $realPath = $file->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            throw new InvalidFileException('Uploaded file does not exist on disk.');
        }

        // Content inspection: check for PHP/script injection regardless of extension
        $firstBytes = @file_get_contents($realPath, false, null, 0, 4096) ?: '';
        if (preg_match('/<\?php|<\?=|eval\s*\(|system\s*\(|shell_exec\s*\(|<script[\s>]/i', $firstBytes)) {
            throw new InvalidFileException('Executable or script content detected. Upload rejected.');
        }

        $mime = $this->detectRealMimeType($file);

        // Prevent MIME spoofing (e.g. PHP script sent with image extension)
        if (str_contains($mime, 'php') || str_contains($mime, 'script') || str_contains($mime, 'executable')) {
            throw new InvalidFileException('Executable script content detected. Upload rejected.');
        }

        // Product & Gallery images: SVG is disabled, only raster formats permitted
        if (in_array($namespace, ['products', 'gallery'], true)) {
            if ($mime === 'image/svg+xml' || $extension === 'svg') {
                throw new InvalidFileException('SVG format is not allowed for product or gallery images. Allowed formats: JPEG, PNG, WebP, AVIF.');
            }

            if (! in_array($mime, self::RASTER_IMAGE_MIME_TYPES, true)) {
                throw new InvalidFileException("MIME type [{$mime}] is not permitted for namespace [{$namespace}]. Only JPEG, PNG, WebP, and AVIF images are allowed.");
            }
        } elseif (in_array($namespace, ['branding', 'qr'], true)) {
            if (! in_array($mime, self::IMAGE_MIME_TYPES, true)) {
                throw new InvalidFileException("MIME type [{$mime}] is not permitted for namespace [{$namespace}]. Only valid images are allowed.");
            }

            // If SVG is uploaded for branding/qr, validate through SvgSanitizer
            if ($mime === 'image/svg+xml' || $extension === 'svg') {
                $svgContent = @file_get_contents($realPath) ?: '';
                $this->svgSanitizer->sanitize($svgContent);
            }
        } elseif ($namespace === 'documents') {
            if (! in_array($mime, self::DOCUMENT_MIME_TYPES, true)) {
                throw new InvalidFileException("MIME type [{$mime}] is not permitted for documents.");
            }
        }
    }
}
