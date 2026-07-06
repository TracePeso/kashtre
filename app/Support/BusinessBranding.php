<?php

namespace App\Support;

use App\Models\Business;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Central resolver for business identity on forms, PDFs, and UI chrome.
 */
class BusinessBranding
{
    public function __construct(public Business $business)
    {
    }

    public static function for(?Business $business): ?self
    {
        return $business ? new self($business) : null;
    }

    public function name(): string
    {
        return (string) ($this->business->name ?? '');
    }

    public function email(): ?string
    {
        return $this->business->email ?: null;
    }

    public function phone(): ?string
    {
        return $this->business->phone ?: null;
    }

    public function address(): ?string
    {
        return $this->business->address ?: null;
    }

    /**
     * @return array<int, string>
     */
    public function contactLines(): array
    {
        return array_values(array_filter([
            $this->address(),
            $this->phone() ? 'Tel: '.$this->phone() : null,
            $this->email() ? 'Email: '.$this->email() : null,
        ]));
    }

    public function logoStoragePath(): ?string
    {
        $logo = $this->business->logo;

        if (! $logo) {
            return null;
        }

        if (Storage::disk('public')->exists($logo)) {
            return $logo;
        }

        return null;
    }

    public function hasLogo(): bool
    {
        return $this->logoStoragePath() !== null;
    }

    public function logoUrl(): ?string
    {
        $path = $this->logoStoragePath();

        return $path ? asset('storage/'.$path) : null;
    }

    /**
     * Absolute filesystem path for DomPDF and similar renderers.
     */
    public function logoPathForPdf(): ?string
    {
        $path = $this->logoStoragePath();

        return $path ? Storage::disk('public')->path($path) : null;
    }

    /**
     * Base64 data URI so logos render reliably inside PDF engines.
     */
    public function logoDataUri(): ?string
    {
        $fullPath = $this->logoPathForPdf();

        if (! $fullPath || ! is_file($fullPath)) {
            return null;
        }

        $mime = mime_content_type($fullPath) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($fullPath));
    }

    public static function logoDirectoryFor(Business $business): string
    {
        return 'business-logos/'.$business->id;
    }

    public function storeLogo(UploadedFile $file): string
    {
        return $file->store(self::logoDirectoryFor($this->business), 'public');
    }

    public static function storeLogoForNewBusiness(UploadedFile $file): string
    {
        return $file->store('business-logos/incoming', 'public');
    }

    public static function validationRules(?int $ignoreBusinessId = null, bool $logoRequired = false): array
    {
        $emailRule = 'required|email|max:255';

        if ($ignoreBusinessId) {
            $emailRule .= '|unique:businesses,email,'.$ignoreBusinessId;
        } else {
            $emailRule .= '|unique:businesses,email';
        }

        $logoRule = ($logoRequired ? 'required' : 'nullable').'|image|mimes:jpeg,png,jpg,gif,svg|max:2048';

        return [
            'name' => 'required|string|max:255',
            'email' => $emailRule,
            'phone' => 'required|string|max:50',
            'address' => 'required|string|max:500',
            'logo' => $logoRule,
        ];
    }

    public function deleteStoredLogo(): void
    {
        $path = $this->logoStoragePath();

        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
