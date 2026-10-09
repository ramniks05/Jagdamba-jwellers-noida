<?php

namespace App\Support;

class IdentityRules
{
    public const GSTIN = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';

    public const PAN = '/^[A-Z]{5}[0-9]{4}[A-Z]$/';

    public const CODE = '/^[A-Z0-9]{2,20}$/';

    /**
     * Indian mobile: 98765 43210, 09876543210 or +91 98765-43210.
     */
    public const MOBILE = '/^(?:\+?91[\s-]?|0)?[6-9][0-9]{4}[\s-]?[0-9]{5}$/';

    /**
     * Any phone, landline included: digits with optional +, spaces and dashes.
     */
    public const PHONE = '/^\+?[0-9][0-9\s-]{5,18}[0-9]$/';

    public const IFSC = '/^[A-Z]{4}0[A-Z0-9]{6}$/';

    /**
     * Last ten digits of a mobile, so 09876543210 and +91 98765 43210 match.
     */
    public static function mobileKey(?string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', (string) $mobile) ?? '';

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'mobile.regex' => 'Enter a 10-digit mobile number, like 98765 43210.',
            'phone.regex' => 'Enter a phone number using digits only, like 0120 4567890.',
            'pan.regex' => 'Enter a valid 10-character PAN, like ABCDE1234F.',
            'gstin.regex' => 'Enter a valid 15-character GSTIN, like 09ABCDE1234F1Z5.',
            'ifsc.regex' => 'Enter a valid 11-character IFSC, like SBIN0001234.',
            'code.regex' => 'Use 2 to 20 letters or digits.',
        ];
    }
}
