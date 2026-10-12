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
        $len = strlen($clean);

        if ($len === 0) {
            return '';
        }

        // Standard Kenyan civilian / tuktuk plate (7 chars: KDA123A) -> LLL DDD L
        if ($len === 7 && str_starts_with($clean, 'K')) {
            $letters1 = substr($clean, 0, 3);
            $digits   = substr($clean, 3, 3);
            $letter2  = substr($clean, 6, 1);

            $correctedLetters1 = self::correctToLetters($letters1);
            $correctedDigits   = self::correctToDigits($digits);
            $correctedLetter2  = self::correctToLetters($letter2);

            return $correctedLetters1 . $correctedDigits . $correctedLetter2;
        }

        // TukTuk 4-letter series (8 chars: KTWA123A) -> LLLL DDD L
        if ($len === 8 && str_starts_with($clean, 'KTW')) {
            $letters1 = substr($clean, 0, 4);
            $digits   = substr($clean, 4, 3);
            $letter2  = substr($clean, 7, 1);

            $correctedLetters1 = self::correctToLetters($letters1);
            $correctedDigits   = self::correctToDigits($digits);
            $correctedLetter2  = self::correctToLetters($letter2);

            return $correctedLetters1 . $correctedDigits . $correctedLetter2;
        }

        // Government (6 chars: GK 123A or GK A123)
        if ($len === 6 && str_starts_with($clean, 'GK')) {
            $mid = substr($clean, 2, 3);
            $end = substr($clean, 5, 1);

            if (is_numeric($mid)) {
                return 'GK' . self::correctToDigits($mid) . self::correctToLetters($end);
            }
            return 'GK' . self::correctToLetters(substr($clean, 2, 1)) . self::correctToDigits(substr($clean, 3, 3));
        }

        // Government (7 chars: GKA 123A)
        if ($len === 7 && str_starts_with($clean, 'G')) {
            $l1 = self::correctToLetters(substr($clean, 0, 3));
            $d  = self::correctToDigits(substr($clean, 3, 3));
            $l2 = self::correctToLetters(substr($clean, 6, 1));
            return $l1 . $d . $l2;
        }

        // County Government (e.g., 47CG123A, 01CG001A - 8 chars)
        if ($len === 8 && preg_match('/^[0-9OIZSB]{2}(CG|CP|CV)[0-9OIZSB]{3}[A-Z0-9]$/i', $clean)) {
            $countyCode = self::correctToDigits(substr($clean, 0, 2));
            $prefix     = 'CG';
            $digits     = self::correctToDigits(substr($clean, 4, 3));
            $suffix     = self::correctToLetters(substr($clean, 7, 1));
            return $countyCode . $prefix . $digits . $suffix;
        }

        return $clean;
    }

    /**
     * Format cleaned plate for UI display (e.g., 'KDA 123A', 'GK 123A', '47 CG 123A')
     */
    public static function format(string $clean): string
    {
        $clean = self::clean($clean);
        $len = strlen($clean);

        // Standard: KDA123A -> KDA 123A
        if ($len === 7 && preg_match('/^([A-Z]{3})([0-9]{3})([A-Z])$/', $clean, $m)) {
            return "{$m[1]} {$m[2]}{$m[3]}";
        }

        // TukTuk 4-letter: KTWA123A -> KTWA 123A
        if ($len === 8 && preg_match('/^(KTW[A-Z])([0-9]{3})([A-Z])$/', $clean, $m)) {
            return "{$m[1]} {$m[2]}{$m[3]}";
        }

        // County Government: 47CG123A -> 47 CG 123A
        if (preg_match('/^([0-9]{2})(CG|GP|GV)([0-9]{1,4})([A-Z]?)$/', $clean, $m)) {
            return trim("{$m[1]} {$m[2]} {$m[3]}{$m[4]}");
        }

        // County Government prefixed: CG47123A -> CG 47 123A
        if (preg_match('/^(CG)([0-9]{2})([0-9]{3})([A-Z]?)$/', $clean, $m)) {
            return trim("CG {$m[2]} {$m[3]}{$m[4]}");
        }

        // Government: GK123A -> GK 123A or GKA123B -> GKA 123B
        if (preg_match('/^(GK)([0-9]{3}[A-Z]|[A-Z][0-9]{3}|[0-9]{1,4})$/', $clean, $m)) {
            return "GK {$m[2]}";
        }
        if (preg_match('/^(G[A-Z]{1,2})([0-9]{3})([A-Z]?)$/', $clean, $m)) {
            return trim("{$m[1]} {$m[2]}{$m[3]}");
        }

        // Older Kenyan civilian: KAA 123A or KAA 123
        if (preg_match('/^(K[A-Z]{1,2})([0-9]{1,4})([A-Z]?)$/', $clean, $m)) {
            return trim("{$m[1]} {$m[2]}{$m[3]}");
        }

        // Fallback: insert space between letters and numbers if possible
        return preg_replace('/([A-Z]+)([0-9]+)/', '$1 $2', $clean) ?? $clean;
    }

    /**
     * Validate against Kenyan vehicle registration patterns strictly for:
     * - Cars and TukTuks starting with 'K'
     * - Government vehicles starting with 'G' (GK, GKA, GP, GV)
     * - County Government vehicles across all 47 counties ((01-47)CG, (01-47)GP, CG)
     * Rejects motorcycles (KM...), garbage text, and non-qualifying formats.
     */
    public static function validate(string $plate): bool
    {
        $clean = self::clean($plate);

        if (strlen($clean) < 4 || strlen($clean) > 10) {
            return false;
        }

        // 1. REJECT 4-letter motorcycle plates (KMCA 001A to KMZZ 999Z - boda boda)
        if (preg_match('/^KM[A-Z]{2}[0-9]{3}[A-Z]$/', $clean)) {
            return false;
        }

        // 2. Standard Civilian Cars & TukTuks (KAA 001A to KZZ 999Z)
        if (preg_match('/^K[A-Z]{2}[0-9]{3}[A-Z]$/', $clean)) {
            return true;
        }

        // 3. TukTuk specific series (KTW 123A or KTWA 123A)
        if (preg_match('/^KTW[A-Z]?[0-9]{3}[A-Z]$/', $clean)) {
            return true;
        }

        // 4. Older Kenyan civilian formats starting with K (KAA 123A, KAA 123, KB 1234, K 1234)
        if (preg_match('/^K[A-Z]{1,2}[0-9]{1,4}[A-Z]?$/', $clean)) {
            return true;
        }

        // 5. Government Plates (GK 123A, GK A123, GK 1234, GKA 123A, GKB 123A)
        if (preg_match('/^GK([0-9]{3}[A-Z]|[A-Z][0-9]{3}|[0-9]{1,4}|[A-Z]{1,2}[0-9]{1,4}[A-Z]?)$/', $clean)) {
            return true;
        }

        // 6. General Government starting with G (GKA 123A, GP 123A, GV 123A)
        if (preg_match('/^G[A-Z]{1,3}[0-9]{1,4}[A-Z]?$/', $clean)) {
            return true;
        }

        // 7. County Government (all 47 counties: 01 CG to 47 CG, e.g., 01CG001A, 47CG001A, 22CG123B)
        if (preg_match('/^(0[1-9]|[1-4][0-9])(CG|GP|GV)[0-9]{1,4}[A-Z]?$/', $clean)) {
            return true;
        }

        // 8. County Government starting with CG (CG 01 001A, CG 47 001A, CG 123A)
        if (preg_match('/^CG(0[1-9]|[1-4][0-9])?[0-9]{1,4}[A-Z]?$/', $clean)) {
            return true;
        }

        // Reject everything else (no loose alphanumeric fallback)
        return false;
    }

    /**
     * Detect category/type of Kenyan vehicle plate
     */
    public static function detectType(string $plate): string
    {
        $clean = self::clean($plate);

        if (str_starts_with($clean, 'KTW')) {
            return 'tuktuk';
        }
        if (str_starts_with($clean, 'GK') || str_starts_with($clean, 'G')) {
            return 'government';
        }
        if (preg_match('/^(0[1-9]|[1-4][0-9])(CG|GP|GV)/', $clean) || str_starts_with($clean, 'CG')) {
            return 'government';
        }
        if (str_starts_with($clean, 'K')) {
            return 'car';
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
