<?php
/**
 * Mombasa Mall Basement Parking - Kenyan Phone Number Helper
 * Standardizes Kenyan mobile numbers to 254XXXXXXXXX for WhatsApp Cloud API and internal storage.
 */

declare(strict_types=1);

class PhoneHelper
{
    /**
     * Standardize phone to format: 254XXXXXXXXX (12 digits)
     */
    public static function normalize(string $phone): string
    {
        // Strip non-digits
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';

        // Case: 07XXXXXXXX or 01XXXXXXXX (10 digits)
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return '254' . substr($digits, 1);
        }

        // Case: 7XXXXXXXX or 1XXXXXXXX (9 digits)
        if (strlen($digits) === 9 && (str_starts_with($digits, '7') || str_starts_with($digits, '1'))) {
            return '254' . $digits;
        }

        // Case: 254XXXXXXXXX (12 digits)
        if (strlen($digits) === 12 && str_starts_with($digits, '254')) {
            return $digits;
        }

        // Case: +254... (already stripped leading plus, so check again)
        if (strlen($digits) > 12 && str_starts_with($digits, '254')) {
            return substr($digits, 0, 12);
        }

        return $digits;
    }

    /**
     * Validate whether normalized phone is a valid Kenyan mobile number
     * (Safaricom, Airtel, Telkom Kenya start with 2547 or 2541)
     */
    public static function validate(string $phone): bool
    {
        $normalized = self::normalize($phone);
        return (bool)preg_match('/^254(7[0-9]|1[0-9])[0-9]{7}$/', $normalized);
    }

    /**
     * Format for user-friendly UI display: e.g. +254 712 345 678
     */
    public static function formatDisplay(string $phone): string
    {
        $norm = self::normalize($phone);
        if (strlen($norm) === 12) {
            return sprintf('+%s %s %s %s',
                substr($norm, 0, 3),
                substr($norm, 3, 3),
                substr($norm, 6, 3),
                substr($norm, 9, 3)
            );
        }
        return $phone;
    }
}
