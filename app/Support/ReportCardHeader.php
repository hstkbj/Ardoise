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

    /** Informations de l'élève que l'école peut afficher sous le titre, dans l'ordre d'affichage. */
    public const STUDENT_FIELDS = ['matricule', 'class', 'birth_date', 'term', 'year'];

    /**
     * @return array<string, mixed>
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
        $color = (string) (filled($reports['accent_color'] ?? null) ? $reports['accent_color'] : ($identity['primary_color'] ?? '#1D5C4D'));
        $logoPx = ['small' => 48, 'medium' => 72, 'large' => 100][$reports['logo_size'] ?? 'medium'] ?? 72;
        [$photoW, $photoH] = ['small' => [54, 68], 'medium' => [66, 82], 'large' => [84, 104]][$reports['photo_size'] ?? 'medium'] ?? [66, 82];
        $studentFields = array_values(array_intersect(self::STUDENT_FIELDS, (array) ($reports['student_fields'] ?? ['matricule', 'class', 'birth_date', 'term'])));

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
            // Éditeur de bulletin : style, en-tête, élève, filigrane
            'font' => ($reports['font_family'] ?? 'sans') === 'serif' ? 'DejaVu Serif, Georgia, serif' : 'DejaVu Sans, Arial, sans-serif',
            'border' => in_array($reports['page_border'] ?? 'none', ['thin', 'double'], true) ? $reports['page_border'] : 'none',
            'align' => ($reports['header_align'] ?? 'center') === 'left' ? 'left' : 'center',
            'logo_px' => $logoPx,
            'divider' => ($reports['header_divider'] ?? 'no') === 'yes',
            'title_style' => in_array($reports['title_style'] ?? 'band', ['band', 'line', 'plain'], true) ? $reports['title_style'] : 'band',
            'student_layout' => ($reports['student_layout'] ?? 'plain') === 'card' ? 'card' : 'plain',
            'student_fields' => $studentFields,
            'photo_position' => ($reports['photo_position'] ?? 'right') === 'left' ? 'left' : 'right',
            'photo_w' => $photoW,
            'photo_h' => $photoH,
            'watermark' => self::watermark($reports, $identity),
        ];
    }

    /**
     * Filigrane imprimé derrière chaque page : logo de l'école, image au choix ou texte.
     *
     * @return array{type: string, src: ?string, text: ?string, opacity: float, width: int}|null
     */
    protected static function watermark(array $reports, array $identity): ?array
    {
        $mode = $reports['watermark_mode'] ?? 'none';
        $opacity = max(3, min(30, (int) ($reports['watermark_opacity'] ?? 8))) / 100;
        $width = ($reports['watermark_size'] ?? 'large') === 'medium' ? 50 : 70;

        return match ($mode) {
            'logo' => ($src = self::image($identity['logo'] ?? null)) ? ['type' => 'image', 'src' => $src, 'text' => null, 'opacity' => $opacity, 'width' => $width] : null,
            'image' => ($src = self::image($reports['watermark_image'] ?? null)) ? ['type' => 'image', 'src' => $src, 'text' => null, 'opacity' => $opacity, 'width' => $width] : null,
            'text' => filled($reports['watermark_text'] ?? null) ? ['type' => 'text', 'src' => null, 'text' => mb_substr((string) $reports['watermark_text'], 0, 40), 'opacity' => $opacity, 'width' => $width] : null,
            default => null,
        };
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
