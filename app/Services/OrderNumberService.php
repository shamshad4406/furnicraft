<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * Issues order, invoice and quotation numbers in the format the admin
 * configured, using counters held in the settings table and incremented
 * inside a transaction so two simultaneous checkouts cannot share a number.
 *
 * Each kind counts separately. A tax invoice has to run in an unbroken
 * sequence of its own — an auditor reading INV/2611/00001, 00004, 00009 will
 * want to know what happened to the numbers in between, and "they were orders"
 * is not an answer.
 */
class OrderNumberService
{
    /** Which settings key holds the counter for each kind. */
    protected const COUNTERS = [
        'order' => 'order_next_sequence',
        'invoice' => 'invoice_next_sequence',
        'quotation' => 'quotation_next_sequence',
        'enquiry' => 'enquiry_next_sequence',
    ];

    public function next(string $kind = 'order'): string
    {
        $counter = self::COUNTERS[$kind] ?? self::COUNTERS['order'];

        return DB::transaction(function () use ($kind, $counter) {
            $row = Setting::query()
                ->where('key', $counter)
                ->lockForUpdate()
                ->first();

            $sequence = (int) ($row->value ?? 1);

            if ($row) {
                $row->update(['value' => (string) ($sequence + 1)]);
            } else {
                Setting::create([
                    'key' => $counter,
                    'group' => 'order',
                    'value' => '2',
                    'type' => 'number',
                ]);
            }

            app(SettingService::class)->flush();

            return $this->format($sequence, $kind);
        });
    }

    protected function format(int $sequence, string $kind): string
    {
        $prefix = match ($kind) {
            'invoice' => (string) settings('invoice_prefix', 'INV'),
            'quotation' => (string) settings('quotation_prefix', 'QT'),
            'enquiry' => (string) settings('enquiry_prefix', 'ENQ'),
            default => (string) settings('order_prefix', 'FC'),
        };

        $padding = max(1, (int) settings('order_sequence_padding', 5));
        $format = (string) settings('order_number_format', '{PREFIX}/{YY}{MM}/{SEQ}');

        return str_replace(
            ['{PREFIX}', '{YYYY}', '{YY}', '{MM}', '{DD}', '{SEQ}'],
            [
                $prefix,
                now()->format('Y'),
                now()->format('y'),
                now()->format('m'),
                now()->format('d'),
                str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT),
            ],
            $format
        );
    }
}
