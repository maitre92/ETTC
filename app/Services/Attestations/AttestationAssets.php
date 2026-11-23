<?php

namespace App\Services\Attestations;

use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Support\SiteSettings;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Signataire, signature et sceau de l'attestation (demande du centre, 2026-11-22) :
 *  - TRAITEMENT d'une image déposée (ou d'une signature tracée à l'écran) pour qu'elle soit nette sur l'attestation :
 *    fond détouré en transparence (couleur du fond mesurée sur les bords), encre renforcée ou uniformisée (bleu, noir),
 *    recadrage, taille normalisée ;
 *  - APERÇU : l'attestation entière et un zoom sur la zone du signataire, composés comme le PDF (GD) ;
 *  - fichiers PRIVÉS (disque local), jamais publics ; chaque changement est audité ;
 *  - mêmes signature et sceau sur les reçus A5 et les fiches de rémunération (documentStampPath).
 * Positions en pixels du modèle (1280 × 853), communes à l'aperçu et au PDF (pdf/attestation).
 */
class AttestationAssets
{
    public const KINDS = ['signature' => 'Signature', 'stamp' => 'Sceau'];

    public const INKS = ['original' => 'Couleurs d\'origine, renforcées', 'bleu' => 'Encre bleue', 'noir' => 'Encre noire'];

    /** Zone du sceau du modèle (centre, diamètre) et zone de la signature. */
    public const STAMP = ['x' => 1125, 'y' => 746, 'size' => 140];

    public const SIGNATURE_BOX = [862, 714, 1012, 778];

    /** Titre (centre, ligne de base, soulignement) et nom (centre, ligne de base). */
    public const TITLE = ['x' => 940, 'base' => 703, 'line' => [828, 1052, 707], 'max' => 224];

    public const NAME = ['x' => 938, 'base' => 798, 'max' => 214];

    public function __construct(private readonly SiteSettings $settings, private readonly ActivityLogger $logger) {}

    /** Fichier de la signature : déposée ou tracée, sinon celle du modèle. */
    public function signaturePath(): string
    {
        return $this->customPath('signature') ?? resource_path('images/attestation-signature.png');
    }

    /** Fichier du sceau déposé ; null = sceau du modèle (déjà sur le fond). */
    public function stampPath(): ?string
    {
        return $this->customPath('stamp');
    }

    /** Sceau des documents (reçus A5, fiches de rémunération) : celui déposé, sinon celui du modèle. */
    public function documentStampPath(): string
    {
        return $this->stampPath() ?? resource_path('images/attestation-sceau.png');
    }

    public function isCustom(string $kind): bool
    {
        return $this->customPath($kind) !== null;
    }

    public function dataUri(?string $path): ?string
    {
        return $path && is_file($path) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path)) : null;
    }

    /**
     * Taille (mm) d'une image posée dans une boîte du modèle (pixels), proportions gardées, et décalage pour la centrer.
     *
     * @return array{w: float, h: float, dx: float, dy: float}|null
     */
    public function fitMm(?string $path, float $boxW, float $boxH): ?array
    {
        $size = $path && is_file($path) ? @getimagesize($path) : false;
        if (! $size || ! $size[0] || ! $size[1]) {
            return null;
        }
        $scale = min($boxW / $size[0], $boxH / $size[1]);
        $k = 297 / 1280;

        return ['w' => round($size[0] * $scale * $k, 2), 'h' => round($size[1] * $scale * $k, 2), 'dx' => round(($boxW - $size[0] * $scale) / 2 * $k, 2), 'dy' => round(($boxH - $size[1] * $scale) / 2 * $k, 2)];
    }

    public static function background(): string
    {
        return resource_path('images/attestation-fond.jpg');
    }

    /**
     * Rend une image lisible sur l'attestation. $ink : original (renforcée), bleu, noir ; $strong : nettoyage du fond
     * plus franc (photo d'un cachet sur papier, scan grisé).
     */
    public function process(string $binary, string $kind, string $ink = 'original', bool $strong = false): string
    {
        $src = @imagecreatefromstring($binary);
        if (! $src instanceof GdImage) {
            throw ValidationException::withMessages(['file' => 'Image illisible : déposez une photo ou un scan en JPEG, PNG ou WebP.']);
        }
        $src = $this->limit($src, 900);
        imagepalettetotruecolor($src);
        $w = imagesx($src);
        $h = imagesy($src);
        [$br, $bg, $bb] = $this->borderColor($src);
        [$t0, $t1] = $strong ? [55, 130] : [32, 105];
        $inkRgb = ['bleu' => [24, 58, 158], 'noir' => [18, 18, 28]][$ink] ?? null;

        $out = $this->canvas($w, $h);
        $minX = $w;
        $minY = $h;
        $maxX = -1;
        $maxY = -1;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($src, $x, $y);
                $srcAlpha = ($c >> 24) & 0x7F;
                if ($srcAlpha >= 100) {
                    continue;
                }
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;
                $dist = sqrt(($r - $br) ** 2 + ($g - $bg) ** 2 + ($b - $bb) ** 2);
                $a = max(0.0, min(1.0, ($dist - $t0) / ($t1 - $t0))) * (1 - $srcAlpha / 127);
                if ($a <= 0.04) {
                    continue;
                }
                if ($inkRgb) {
                    [$r, $g, $b] = $inkRgb;
                } else {
                    // Renforcée : plus foncée et plus saturée (un cachet pâle devient lisible).
                    $mean = ($r + $g + $b) / 3;
                    [$r, $g, $b] = array_map(fn ($v) => (int) max(0, min(255, ($mean + ($v - $mean) * 1.35) * 0.78)), [$r, $g, $b]);
                }
                imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $r, $g, $b, (int) round(127 - $a * 127)));
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }
        if ($maxX < 0) {
            throw ValidationException::withMessages(['file' => 'Rien de lisible sur cette image : le '.mb_strtolower(self::KINDS[$kind]).' doit se détacher nettement du fond.']);
        }

        // Recadrage au contenu (petite marge), taille normalisée.
        $pad = (int) round(max($maxX - $minX, $maxY - $minY) * 0.04);
        $cx = max(0, $minX - $pad);
        $cy = max(0, $minY - $pad);
        $cw = min($w, $maxX + $pad + 1) - $cx;
        $ch = min($h, $maxY + $pad + 1) - $cy;
        $scale = min(1, 700 / max($cw, $ch));
        $final = $this->canvas((int) max(1, round($cw * $scale)), (int) max(1, round($ch * $scale)));
        imagecopyresampled($final, $out, 0, 0, $cx, $cy, imagesx($final), imagesy($final), $cw, $ch);

        return $this->png($final);
    }

    /** Signature tracée à l'écran (PNG transparent, « data:image/png;base64,… »). */
    public function fromDrawing(string $dataUrl): string
    {
        if (! preg_match('~^data:image/png;base64,([A-Za-z0-9+/=]+)$~', $dataUrl, $m) || strlen($m[1]) > 3_000_000) {
            throw ValidationException::withMessages(['drawing' => 'Signature tracée illisible : recommencez.']);
        }

        return $this->process((string) base64_decode($m[1], true), 'signature', 'original');
    }

    /** Enregistre l'image traitée (fichier privé), remplace l'ancienne, audit. */
    public function save(string $kind, string $png, User $actor): void
    {
        $old = $this->settings->get("attestation.{$kind}_file");
        $path = 'attestation/'.$kind.'-'.Str::random(16).'.png';
        Storage::disk('local')->put($path, $png);
        $this->settings->save(["attestation.{$kind}_file" => $path]);
        if ($old && $old !== $path) {
            Storage::disk('local')->delete($old);
        }
        $this->logger->log('attestations.'.$kind.'_updated', 'attestations', self::KINDS[$kind].' de l\'attestation remplacé(e)', actor: $actor);
    }

    /** Retour à l'élément du modèle (signature ou sceau d'origine). */
    public function reset(string $kind, User $actor): void
    {
        $old = $this->settings->get("attestation.{$kind}_file");
        $this->settings->save(["attestation.{$kind}_file" => '']);
        if ($old) {
            Storage::disk('local')->delete($old);
        }
        $this->logger->log('attestations.'.$kind.'_reset', 'attestations', self::KINDS[$kind].' de l\'attestation : retour au modèle', actor: $actor);
    }

    /**
     * Aperçu de l'attestation (zone du signataire) avec les éléments enregistrés ou proposés.
     *
     * @param  array{signature?: ?string, stamp?: ?string, name?: ?string, title?: ?string}  $override  PNG binaires / textes proposés
     * @return array{full: string, zoom: string}
     */
    public function preview(array $override = []): array
    {
        $img = imagecreatefromjpeg(self::background());
        imagealphablending($img, true);

        $stamp = $override['stamp'] ?? ($this->stampPath() ? (string) file_get_contents($this->stampPath()) : null);
        if ($stamp !== null) {
            // Le nouveau sceau recouvre celui du modèle (disque de la couleur du fond).
            imagefilledellipse($img, self::STAMP['x'], self::STAMP['y'], 154, 154, imagecolorallocate($img, 245, 248, 252));
            $this->paste($img, $stamp, self::STAMP['x'] - self::STAMP['size'] / 2, self::STAMP['y'] - self::STAMP['size'] / 2, self::STAMP['size'], self::STAMP['size']);
        }
        $signature = $override['signature'] ?? (string) file_get_contents($this->signaturePath());
        [$x0, $y0, $x1, $y1] = self::SIGNATURE_BOX;
        $this->paste($img, $signature, $x0, $y0, $x1 - $x0, $y1 - $y0);

        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSerif-Bold.ttf');
        $ink = imagecolorallocate($img, 16, 16, 24);
        $title = mb_strtoupper($override['title'] ?? $this->title());
        $this->text($img, $font, $title, self::TITLE['x'], self::TITLE['base'], 13.5, self::TITLE['max'], $ink);
        [$lx0, $lx1, $ly] = self::TITLE['line'];
        imagefilledrectangle($img, $lx0, $ly, $lx1, $ly + 1, $ink);
        $this->text($img, $font, $override['name'] ?? $this->name(), self::NAME['x'], self::NAME['base'], 13.5, self::NAME['max'], $ink);

        // Coordonnées en haut à droite.
        $sans = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
        $navy = imagecolorallocate($img, 24, 34, 74);
        foreach ($this->contacts() as $i => $line) {
            if ($line !== '') {
                $size = 11.5;
                while ($size > 7 && (imagettfbbox($size, 0, $sans, $line)[2] - imagettfbbox($size, 0, $sans, $line)[0]) > 230) {
                    $size -= 0.5;
                }
                imagettftext($img, $size, 0, self::CONTACT_LEFT, (int) round(self::CONTACT_LINES[$i] + 5), $navy, $sans, $line);
            }
        }

        $zoom = imagecreatetruecolor(880, 360);
        imagecopyresampled($zoom, $img, 0, 0, 790, 650, 880, 360, 440, 180);
        $full = imagecreatetruecolor(960, 640);
        imagecopyresampled($full, $img, 0, 0, 0, 0, 960, 640, imagesx($img), imagesy($img));

        return ['full' => 'data:image/png;base64,'.base64_encode($this->png($full)), 'zoom' => 'data:image/png;base64,'.base64_encode($this->png($zoom))];
    }

    /**
     * Coordonnées en haut à droite (lieu, téléphone, e-mail, site), dans l'ordre des icônes du modèle : celles réglées
     * pour l'attestation, sinon celles du site.
     *
     * @return list<string>
     */
    public function contacts(): array
    {
        $get = fn (string $key, string $fallback) => trim($this->settings->get('attestation.contact_'.$key)) ?: trim($this->settings->get($fallback));

        return [$get('city', 'contact.city'), $get('phone', 'contact.phone_1'), $get('email', 'contact.email'), trim($this->settings->get('attestation.contact_website'))];
    }

    /** Lignes des coordonnées (pixels du modèle) : centre vertical de chaque ligne, bord gauche du texte. */
    public const CONTACT_LINES = [48.3, 75.0, 101.7, 126.7];

    public const CONTACT_LEFT = 963;

    public function name(): string
    {
        return trim($this->settings->get('attestation.director_name'));
    }

    public function title(): string
    {
        return trim($this->settings->get('attestation.director_title')) ?: 'The General Director';
    }

    private function customPath(string $kind): ?string
    {
        $path = trim($this->settings->get("attestation.{$kind}_file"));

        return $path !== '' && Storage::disk('local')->exists($path) ? Storage::disk('local')->path($path) : null;
    }

    /** Couleur du fond : moyenne des bords de l'image. @return array{0: int, 1: int, 2: int} */
    private function borderColor(GdImage $img): array
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $sum = [0, 0, 0];
        $n = 0;
        $step = max(1, (int) (($w + $h) / 200));
        foreach ([[0, 1], [1, 0]] as [$horizontal]) {
            for ($i = 0; $i < ($horizontal ? $w : $h); $i += $step) {
                foreach ($horizontal ? [[$i, 0], [$i, $h - 1]] : [[0, $i], [$w - 1, $i]] as [$x, $y]) {
                    $c = imagecolorat($img, $x, $y);
                    if ((($c >> 24) & 0x7F) >= 100) {
                        $sum = [$sum[0] + 255, $sum[1] + 255, $sum[2] + 255];
                    } else {
                        $sum = [$sum[0] + (($c >> 16) & 0xFF), $sum[1] + (($c >> 8) & 0xFF), $sum[2] + ($c & 0xFF)];
                    }
                    $n++;
                }
            }
        }

        return array_map(fn ($v) => (int) round($v / max(1, $n)), $sum);
    }

    private function limit(GdImage $img, int $max): GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        if (max($w, $h) <= $max) {
            return $img;
        }
        $scale = $max / max($w, $h);
        $small = $this->canvas((int) round($w * $scale), (int) round($h * $scale));
        imagecopyresampled($small, $img, 0, 0, 0, 0, imagesx($small), imagesy($small), $w, $h);

        return $small;
    }

    private function canvas(int $w, int $h): GdImage
    {
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));

        return $img;
    }

    /** Colle une image (PNG binaire) centrée dans une boîte, proportions gardées. */
    private function paste(GdImage $dest, string $png, float $x, float $y, float $w, float $h): void
    {
        $src = @imagecreatefromstring($png);
        if (! $src instanceof GdImage) {
            return;
        }
        $scale = min($w / imagesx($src), $h / imagesy($src));
        $dw = (int) round(imagesx($src) * $scale);
        $dh = (int) round(imagesy($src) * $scale);
        imagecopyresampled($dest, $src, (int) round($x + ($w - $dw) / 2), (int) round($y + ($h - $dh) / 2), 0, 0, $dw, $dh, imagesx($src), imagesy($src));
    }

    /** Texte centré sur une ligne de base, réduit s'il dépasse la largeur permise. */
    private function text(GdImage $img, string $font, string $text, int $cx, int $base, float $size, int $max, int $color): void
    {
        if ($text === '') {
            return;
        }
        do {
            $box = imagettfbbox($size, 0, $font, $text);
            $width = $box[2] - $box[0];
            $size -= 0.5;
        } while ($width > $max && $size > 6);
        imagettftext($img, $size + 0.5, 0, (int) round($cx - $width / 2), $base, $color, $font, $text);
    }

    private function png(GdImage $img): string
    {
        ob_start();
        imagepng($img, null, 6);

        return (string) ob_get_clean();
    }
}
