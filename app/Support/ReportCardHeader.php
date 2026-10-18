<?php

namespace App\Support;

use App\Models\Tenant\Setting;
use App\Models\Tenant\Student;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * En-tête et pied du bulletin PDF, tels que l'école les a réglés (Paramètres › Bulletins) :
 * lignes officielles, logos, coordonnées, titre, photo de l'élève, signature et cachet.
 *
 * Les images sont intégrées en base64 : dompdf n'a pas à aller les chercher par HTTP.
 */
class ReportCardHeader
{
    /** Coordonnées que l'école peut afficher sous son nom, dans l'ordre d'affichage. */
    public const DETAILS = ['motto', 'address', 'phone', 'email', 'legal_id'];

    /**
     * @return array{lines: list<string>, logo: ?string, logo_position: string, second_logo: ?string, school: string, details: list<string>, title: string, color: string, photo: ?string, show_photo: bool, signature: ?string, stamp: ?string, signature_title: string, signatory_name: ?string}
     */
    public static function for(?Student $student, ?string $school = null): array
    {
        $identity = Setting::section('identity');
        $contact = Setting::section('contact');
        $reports = Setting::section('reports');
        $shown = (array) ($reports['header_show'] ?? []);

        $details = collect([
            'motto' => $identity['motto'] ?? null,
            'address' => $contact['address'] ?? null,
            'phone' => filled($contact['phone'] ?? null) ? 'Tél. '.$contact['phone'] : null,
            'email' => $contact['email'] ?? null,
            'legal_id' => filled($contact['legal_id'] ?? null) ? 'Autorisation n° '.$contact['legal_id'] : null,
        ])->only(array_intersect(self::DETAILS, $shown))->filter()->values()->all();

        $position = $reports['header_logo'] ?? 'left';
        $color = (string) ($identity['primary_color'] ?? '#1D5C4D');

        return [
            'lines' => collect(preg_split('/\R/', (string) ($reports['header_lines'] ?? '')))->map(fn ($l) => trim($l))->filter()->take(6)->values()->all(),
            'logo' => $position === 'none' ? null : self::image($identity['logo'] ?? null),
            'logo_position' => $position,
            'second_logo' => self::image($reports['second_logo'] ?? null),
            'school' => $school ?? (string) ($identity['name'] ?? tenant()?->name),
            'details' => $details,
            'title' => filled($reports['header_title'] ?? null) ? $reports['header_title'] : 'Bulletin de notes',
            'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : '#1D5C4D',
            'show_photo' => ($reports['show_student_photo'] ?? 'yes') === 'yes',
            'photo' => ($reports['show_student_photo'] ?? 'yes') === 'yes' ? self::image($student?->photo_path, isPath: true) : null,
            'signature' => self::image($reports['signature'] ?? null),
            'stamp' => self::image($reports['stamp'] ?? null),
            'signature_title' => filled($reports['signature_title'] ?? null) ? $reports['signature_title'] : 'Le chef d’établissement',
            'signatory_name' => $reports['signatory_name'] ?? null,
        ];
    }

    /**
     * Image du disque « public » en data URI (PNG, JPG, GIF, WebP), ou null.
     * Les paramètres gardent l'URL publique : on retrouve le chemin du fichier à partir d'elle.
     */
    public static function image(?string $value, bool $isPath = false): ?string
    {
        if (blank($value)) {
            return null;
        }

        $disk = Storage::disk('public');
        $path = $isPath ? $value : self::pathFromUrl($value);

        if (! $path || str_contains($path, '..') || ! $disk->exists($path)) {
            return null;
        }

        $mime = $disk->mimeType($path);

        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
    }

    protected static function pathFromUrl(string $url): ?string
    {
        $base = rtrim(Storage::disk('public')->url(''), '/').'/';

        if (str_starts_with($url, $base)) {
            return rawurldecode(Str::after($url, $base));
        }

        // Adresse enregistrée avec un autre APP_URL : on se fie au segment /storage/
        $path = parse_url($url, PHP_URL_PATH) ?: '';

        return str_contains($path, '/storage/') ? rawurldecode(Str::after($path, '/storage/')) : null;
    }
}
