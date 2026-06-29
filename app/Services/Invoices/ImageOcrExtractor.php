<?php

namespace App\Services\Invoices;

class ImageOcrExtractor
{
    private string $convertBin   = '';
    private string $tesseractBin = '';

    public function extract(string $imagePath): string
    {
        return $this->extractAll([$imagePath]);
    }

    /**
     * Process one or more image paths and concatenate OCR output.
     *
     * @param string[] $imagePaths
     */
    public function extractAll(array $imagePaths): string
    {
        $this->convertBin   = $this->locateBinary(config('invoices.imagemagick_binary', 'convert'));
        $this->tesseractBin = config('invoices.tesseract_binary', 'tesseract');

        $parts   = [];
        $isMulti = count($imagePaths) > 1;

        foreach ($imagePaths as $i => $path) {
            $text = $this->processImage($path);
            if ($text === '') {
                continue;
            }
            $parts[] = $isMulti
                ? sprintf("--- Página %d ---\n%s", $i + 1, $text)
                : $text;
        }

        return implode("\n\n", $parts);
    }

    // ─────────────────── Per-image pipeline ───────────────────

    private function processImage(string $path): string
    {
        $lang       = config('invoices.tesseract_language', 'por+eng');
        $candidates = [];

        if ($this->convertBin !== '') {
            // Strategy A: grayscale + normalize + sharpen + 50% threshold
            // Best for printed invoices with clear black-on-white text
            $tmpA = tempnam(sys_get_temp_dir(), 'ocr_a_') . '.png';
            if ($this->runConvert($path, $tmpA, threshold: true)) {
                $candidates['threshold'] = $this->tesseractBestPsm($tmpA, $lang);
            }
            if (file_exists($tmpA)) {
                @unlink($tmpA);
            }

            // Strategy B: same pipeline without threshold
            // Better for low-quality photos where binarisation destroys detail
            $tmpB = tempnam(sys_get_temp_dir(), 'ocr_b_') . '.png';
            if ($this->runConvert($path, $tmpB, threshold: false)) {
                $candidates['normalized'] = $this->tesseractBestPsm($tmpB, $lang);
            }
            if (file_exists($tmpB)) {
                @unlink($tmpB);
            }
        }

        // Always keep the raw original as a safety net
        $candidates['original'] = $this->tesseractBestPsm($path, $lang);

        return $this->bestResult($candidates);
    }

    // ─────────────────── ImageMagick ───────────────────

    /**
     * Run ImageMagick convert to produce a preprocessed PNG ready for Tesseract.
     *
     * Pipeline:
     *   -auto-orient      fix EXIF rotation from phone cameras
     *   -resize 2480x<    upscale only if narrower than 2480 px (~300 dpi A4)
     *   -colorspace Gray  convert to greyscale
     *   -normalize        stretch histogram to full 0-255 range
     *   -sharpen 0x1      unsharp mask, sigma=1 — sharpens text edges
     *   -threshold 50%    (optional) binarise to pure black/white
     */
    private function runConvert(string $input, string $output, bool $threshold): bool
    {
        $threshold_args = $threshold ? '-threshold 50% ' : '';

        $cmd = sprintf(
            '%s %s -auto-orient -resize %s -colorspace Gray -normalize -sharpen %s %s%s 2>/dev/null',
            escapeshellarg($this->convertBin),
            escapeshellarg($input),
            escapeshellarg('2480x<'),  // only upscale; already-large images untouched
            escapeshellarg('0x1'),
            $threshold_args,
            escapeshellarg($output)
        );

        exec($cmd, $ignored, $code);

        return $code === 0 && file_exists($output) && filesize($output) > 0;
    }

    // ─────────────────── Tesseract ───────────────────

    /**
     * Try PSM 6 → 4 → 3 in order.  Stop as soon as a run yields ≥100 useful
     * characters; otherwise return whichever mode scored highest.
     */
    private function tesseractBestPsm(string $path, string $lang): string
    {
        $best      = '';
        $bestScore = 0;

        foreach ([6, 4, 3] as $psm) {
            $text  = $this->tesseractCli($path, $lang, $psm);
            $score = $this->scoreText($text);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best      = $text;
            }

            if ($score >= 100) {
                break;
            }
        }

        return $best;
    }

    /**
     * Run Tesseract CLI directly (avoids PHP library overhead and gives full flag control).
     *
     * --oem 1   LSTM neural net engine (best quality for printed text)
     * --psm N   page segmentation mode
     */
    private function tesseractCli(string $path, string $lang, int $psm): string
    {
        $cmd = sprintf(
            '%s %s - -l %s --oem 1 --psm %d 2>/dev/null',
            escapeshellarg($this->tesseractBin),
            escapeshellarg($path),
            escapeshellarg($lang),
            $psm
        );

        return trim((string) shell_exec($cmd));
    }

    // ─────────────────── Helpers ───────────────────

    /**
     * Count alphanumeric characters as a proxy for "useful text".
     */
    private function scoreText(string $text): int
    {
        preg_match_all('/[a-zA-ZÀ-ÿ0-9]/', $text, $m);

        return count($m[0]);
    }

    /**
     * Return the candidate string that has the highest useful-character score.
     */
    private function bestResult(array $candidates): string
    {
        $best      = '';
        $bestScore = -1;

        foreach ($candidates as $text) {
            $score = $this->scoreText($text);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best      = $text;
            }
        }

        return $best;
    }

    /**
     * Find a binary by name.  Returns '' if not found (no shell on Windows dev).
     */
    private function locateBinary(string $name): string
    {
        if ($name === '') {
            return '';
        }

        // Absolute path given explicitly
        if (str_starts_with($name, '/') && is_executable($name)) {
            return $name;
        }

        $found = trim((string) shell_exec('which ' . escapeshellarg($name) . ' 2>/dev/null'));

        return $found !== '' ? $found : '';
    }
}
