<?php

use App\Services\SettingService;

if (! function_exists('settings')) {
    /**
     * Resolve the setting service, or read a single setting value.
     */
    function settings(?string $key = null, mixed $default = null): mixed
    {
        /** @var SettingService $service */
        $service = app(SettingService::class);

        if ($key === null) {
            return $service;
        }

        return $service->get($key, $default);
    }
}

if (! function_exists('setting_file')) {
    function setting_file(string $key): ?string
    {
        return app(SettingService::class)->fileUrl($key);
    }
}

if (! function_exists('asset_v')) {
    /**
     * Asset URL with a cache-busting stamp taken from the file's mtime.
     */
    function asset_v(string $path): string
    {
        $full = public_path($path);
        $version = is_file($full) ? filemtime($full) : null;

        return asset($path).($version ? '?v='.$version : '');
    }
}

if (! function_exists('money')) {
    /**
     * Format an amount using the configured currency symbol.
     */
    function money(float|int|string|null $amount, int $decimals = 0): string
    {
        $symbol = (string) settings('currency_symbol', '₹');

        return $symbol.' '.indian_number_format((float) $amount, $decimals);
    }
}

if (! function_exists('indian_number_format')) {
    /**
     * Group digits the Indian way: 1,79,499 rather than 179,499.
     *
     * The last three digits stay together and everything above them is cut
     * into pairs, which is what lakh and crore mean. number_format() groups
     * in thousands, so an invoice was printing "₹ 179,499" in figures next
     * to "One Lakh Seventy Nine Thousand" in words — the same amount said
     * two different ways on one legal document.
     *
     * Only the grouping changes; the value and the rounding do not.
     */
    function indian_number_format(float $amount, int $decimals = 0): string
    {
        $negative = $amount < 0;

        // Round first, so 99,999.6 carries into the lakh group rather than
        // being grouped as 99,999 and then rounded.
        $rounded = number_format(abs($amount), $decimals, '.', '');

        [$whole, $fraction] = array_pad(explode('.', $rounded), 2, null);

        if (strlen($whole) > 3) {
            $last = substr($whole, -3);
            $rest = substr($whole, 0, -3);

            // Pairs from the right: 12345 -> "12", 1234567 -> "12,34".
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);

            $whole = $rest.','.$last;
        }

        return ($negative ? '-' : '').$whole.($fraction !== null ? '.'.$fraction : '');
    }
}

if (! function_exists('amount_in_words')) {
    /**
     * An amount spelled out, the way an Indian invoice says it.
     *
     * Not number_format plus a dictionary: the Indian system groups in lakh
     * and crore rather than million and billion, so 150000 is "One Lakh Fifty
     * Thousand", never "One Hundred Fifty Thousand". Paise are spelled out
     * too, because an invoice total of 1250.50 has to say so.
     */
    function amount_in_words(float $amount, ?string $currency = null, ?string $fraction = null): string
    {
        $currency ??= 'Rupees';
        $fraction ??= 'Paise';

        $negative = $amount < 0;
        $amount = abs(round($amount, 2));

        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        // Rounding 0.995 upwards can carry into the rupees.
        if ($paise === 100) {
            $rupees++;
            $paise = 0;
        }

        $words = trim($currency.' '.indian_number_to_words($rupees));

        if ($paise > 0) {
            $words .= ' and '.$fraction.' '.indian_number_to_words($paise);
        }

        return ($negative ? 'Minus ' : '').$words.' Only';
    }
}

if (! function_exists('indian_number_to_words')) {
    /**
     * Spell out a whole number in the Indian system.
     */
    function indian_number_to_words(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $ones = [
            1 => 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen',
            'Eighteen', 'Nineteen',
        ];

        $tens = [
            2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
            6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety',
        ];

        /** Spells 0–99. */
        $below100 = function (int $n) use ($ones, $tens): string {
            if ($n === 0) {
                return '';
            }

            if ($n < 20) {
                return $ones[$n];
            }

            return $tens[intdiv($n, 10)].($n % 10 ? ' '.$ones[$n % 10] : '');
        };

        /** Spells 0–999. */
        $below1000 = function (int $n) use ($ones, $below100): string {
            $parts = [];

            if ($n >= 100) {
                $parts[] = $ones[intdiv($n, 100)].' Hundred';
                $n %= 100;
            }

            if ($n > 0) {
                $parts[] = $below100($n);
            }

            return implode(' ', $parts);
        };

        $parts = [];

        // Crore has no upper bound: 150 crore is "One Hundred Fifty Crore".
        if ($number >= 10000000) {
            $crore = intdiv($number, 10000000);
            $parts[] = indian_number_to_words($crore).' Crore';
            $number %= 10000000;
        }

        if ($number >= 100000) {
            $parts[] = $below100(intdiv($number, 100000)).' Lakh';
            $number %= 100000;
        }

        if ($number >= 1000) {
            $parts[] = $below100(intdiv($number, 1000)).' Thousand';
            $number %= 1000;
        }

        if ($number > 0) {
            $parts[] = $below1000($number);
        }

        return implode(' ', array_filter($parts));
    }
}

if (! function_exists('indian_states')) {
    /**
     * The states and union territories, in GST-code order.
     *
     * Checkout and the address book both offer this list, so the delivery
     * state arrives spelled the way InvoiceService expects. Free text here
     * is how a Delhi-to-UP sale ends up taxed as CGST + SGST — the wrong tax
     * on a legal document — so the list is the fix, and the matcher's
     * abbreviation handling is only there for addresses saved before it.
     *
     * @return array<string, string> name => GST state code
     */
    function indian_states(): array
    {
        return [
            'Jammu and Kashmir' => '01',
            'Himachal Pradesh' => '02',
            'Punjab' => '03',
            'Chandigarh' => '04',
            'Uttarakhand' => '05',
            'Haryana' => '06',
            'Delhi' => '07',
            'Rajasthan' => '08',
            'Uttar Pradesh' => '09',
            'Bihar' => '10',
            'Sikkim' => '11',
            'Arunachal Pradesh' => '12',
            'Nagaland' => '13',
            'Manipur' => '14',
            'Mizoram' => '15',
            'Tripura' => '16',
            'Meghalaya' => '17',
            'Assam' => '18',
            'West Bengal' => '19',
            'Jharkhand' => '20',
            'Odisha' => '21',
            'Chhattisgarh' => '22',
            'Madhya Pradesh' => '23',
            'Gujarat' => '24',
            'Dadra and Nagar Haveli and Daman and Diu' => '26',
            'Maharashtra' => '27',
            'Karnataka' => '29',
            'Goa' => '30',
            'Lakshadweep' => '31',
            'Kerala' => '32',
            'Tamil Nadu' => '33',
            'Puducherry' => '34',
            'Andaman and Nicobar Islands' => '35',
            'Telangana' => '36',
            'Andhra Pradesh' => '37',
            'Ladakh' => '38',
        ];
    }
}
