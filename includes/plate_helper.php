<?php
/**
 * Mombasa Mall Basement Parking - Kenyan License Plate Helper
 * Handles normalization, OCR confusion correction, validation, and display formatting.
 */

declare(strict_types=1);

class PlateHelper
{
    /**
     * Map characters commonly misrecognized by OCR in letter vs digit positions
     */
    private const DIGIT_TO_LETTER = [
        '0' => 'O',
        '1' => 'I',
        '8' => 'B',
        '5' => 'S',
        '2' => 'Z',
    ];

    private const LETTER_TO_DIGIT = [
        'O' => '0',
        'I' => '1',
        'B' => '8',
        'S' => '5',
        'Z' => '2',
        'Q' => '0',
        'D' => '0',
        'G' => '6',
    ];

    /**
     * Clean and normalize a raw license plate string.
     * Strips spaces, hyphens, dots, and special characters; converts to uppercase.
     */
    public static function clean(string $raw): string
    {
        // Strip everything except alphanumeric
        $clean = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $raw) ?? '');

        // If it looks like a standard Kenyan civilian plate (7 chars e.g. KDA123A or 8 chars for motorcycle KMDA123A)
        $len = strlen($clean);

        if ($len === 7 && str_starts_with($clean, 'K')) {
            // Pattern: LLL DDD L  (e.g., KDA 123 A)
            $letters1 = substr($clean, 0, 3);
            $digits   = substr($clean, 3, 3);
            $letter2  = substr($clean, 6, 1);

            $correctedLetters1 = self::correctToLetters($letters1);
            $correctedDigits   = self::correctToDigits($digits);
            $correctedLetter2  = self::correctToLetters($letter2);

            return $correctedLetters1 . $correctedDigits . $correctedLetter2;
        }

        if ($len === 8 && str_starts_with($clean, 'KM')) {
            // Pattern: LLLL DDD L (e.g. KMDA 123 A - motorcycle)
            $letters1 = substr($clean, 0, 4);
            $digits   = substr($clean, 4, 3);
            $letter2  = substr($clean, 7, 1);

            $correctedLetters1 = self::correctToLetters($letters1);
            $correctedDigits   = self::correctToDigits($digits);
            $correctedLetter2  = self::correctToLetters($letter2);

            return $correctedLetters1 . $correctedDigits . $correctedLetter2;
        }

        if ($len === 6 && str_starts_with($clean, 'GK')) {
            // Pattern: GK DDD L or GK L DDD (e.g., GK 123 A or GK A 123)
            $mid = substr($clean, 2, 3);
            $end = substr($clean, 5, 1);

            if (is_numeric($mid)) {
                return 'GK' . self::correctToDigits($mid) . self::correctToLetters($end);
            }
            return 'GK' . self::correctToLetters(substr($clean, 2, 1)) . self::correctToDigits(substr($clean, 3, 3));
        }

        return $clean;
    }

    /**
     * Format cleaned plate for UI display (e.g., 'KDA 123A', 'KMDA 123A', 'GK 123A')
     */
    public static function format(string $clean): string
    {
        $clean = self::clean($clean);
        $len = strlen($clean);

        // Standard: KDA123A -> KDA 123A
        if ($len === 7 && preg_match('/^([A-Z]{3})([0-9]{3})([A-Z])$/', $clean, $m)) {
            return "{$m[1]} {$m[2]}{$m[3]}";
        }

        // Motorcycle: KMDA123A -> KMDA 123A
        if ($len === 8 && preg_match('/^([A-Z]{4})([0-9]{3})([A-Z])$/', $clean, $m)) {
            return "{$m[1]} {$m[2]}{$m[3]}";
        }

        // Government: GK123A -> GK 123A or GKA123 -> GK A123
        if (preg_match('/^(GK)([0-9]{3}[A-Z]|[A-Z][0-9]{3})$/', $clean, $m)) {
            return "GK {$m[2]}";
        }

        // County: 47CG123A -> 47 CG 123A
        if (preg_match('/^([0-9]{2})(CG)([0-9]{3}[A-Z])$/', $clean, $m)) {
            return "{$m[1]} {$m[2]} {$m[3]}";
        }

        // Trailer: ZA1234 -> ZA 1234
        if (preg_match('/^(ZA)([0-9]{4})$/', $clean, $m)) {
            return "ZA {$m[2]}";
        }

        // Diplomatic / Foreign / Others fallback: insert space between letters and numbers if possible
        return preg_replace('/([A-Z]+)([0-9]+)/', '$1 $2', $clean) ?? $clean;
    }

    /**
     * Validate against Kenyan vehicle registration patterns
     */
    public static function validate(string $plate): bool
    {
        $clean = self::clean($plate);

        // 1. Standard Kenyan Civilian Plate (KAA 001A to KZZ 999Z, excluding letters I and O)
        if (preg_match('/^K[A-Z]{2}[0-9]{3}[A-Z]$/', $clean)) {
            return true;
        }

        // 2. Motorcycle Plate (KMCA 001A to KMDA 999Z)
        if (preg_match('/^KM[A-Z]{2}[0-9]{3}[A-Z]$/', $clean)) {
            return true;
        }

        // 3. Government Plate (GK 123A or GK A123)
        if (preg_match('/^GK([0-9]{3}[A-Z]|[A-Z][0-9]{3})$/', $clean)) {
            return true;
        }

        // 4. County Government (e.g., 47 CG 001A - 47 is Mombasa County code)
        if (preg_match('/^(0[1-9]|[1-4][0-9])CG[0-9]{3}[A-Z]$/', $clean)) {
            return true;
        }

        // 5. Diplomatic (e.g. 29 CD 12 K, 10 UN 123)
        if (preg_match('/^[0-9]{1,3}(CD|UN)[0-9]{1,4}[A-Z]?$/', $clean)) {
            return true;
        }

        // 6. Heavy Trailers / Tractors (e.g. ZA 1234, KT 1234)
        if (preg_match('/^(ZA|KT)[0-9]{4}$/', $clean)) {
            return true;
        }

        // Broad fallback: any 4 to 10 alphanumeric characters to allow foreign/special transit plates
        return (bool)preg_match('/^[A-Z0-9]{4,10}$/', $clean);
    }

    /**
     * Detect category/type of Kenyan vehicle plate
     */
    public static function detectType(string $plate): string
    {
        $clean = self::clean($plate);

        if (str_starts_with($clean, 'KM')) {
            return 'motorcycle';
        }
        if (str_starts_with($clean, 'GK')) {
            return 'government';
        }
        if (preg_match('/^(0[1-9]|[1-4][0-9])CG/', $clean)) {
            return 'government';
        }
        if (str_contains($clean, 'CD') || str_contains($clean, 'UN')) {
            return 'diplomatic';
        }
        if (str_starts_with($clean, 'ZA') || str_starts_with($clean, 'KT')) {
            return 'truck';
        }
        return 'car';
    }

    private static function correctToLetters(string $text): string
    {
        return strtr($text, self::DIGIT_TO_LETTER);
    }

    private static function correctToDigits(string $text): string
    {
        return strtr($text, self::LETTER_TO_DIGIT);
    }
}
