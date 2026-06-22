<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single uploaded EDS document for a tax year (GID XML/HTML/PDF or IIN XML).
 *
 * @property int $year
 * @property string $kind
 * @property string $filename
 * @property string $path
 * @property string|null $mime
 * @property int $size
 * @property array<string,mixed>|null $meta
 */
class GidDocument extends Model
{
    protected $fillable = ['year', 'kind', 'filename', 'path', 'mime', 'size', 'meta'];

    protected $casts = [
        'year' => 'integer',
        'size' => 'integer',
        'meta' => 'array',
    ];

    public const KINDS = [
        'gid_xml'  => ['Gada deklarācija (XML)', 'heroicon-o-code-bracket', 'emerald'],
        'gid_html' => ['Gada deklarācija (HTML)', 'heroicon-o-document-text', 'blue'],
        'gid_pdf'  => ['Gada deklarācija (PDF)', 'heroicon-o-document', 'red'],
        'iin_xml'  => ['IIN avansa aprēķins (XML)', 'heroicon-o-calculator', 'violet'],
    ];

    /** Human label for the document kind. */
    public function kindLabel(): string
    {
        return self::KINDS[$this->kind][0] ?? $this->kind;
    }

    /** Heroicon name for the document kind. */
    public function kindIcon(): string
    {
        return self::KINDS[$this->kind][1] ?? 'heroicon-o-document';
    }

    /** Tailwind colour token for the kind badge. */
    public function kindColor(): string
    {
        return self::KINDS[$this->kind][2] ?? 'gray';
    }

    /** Whether the file is meant to open inline in the browser (HTML/PDF). */
    public function opensInline(): bool
    {
        return in_array($this->kind, ['gid_html', 'gid_pdf'], true);
    }
}
