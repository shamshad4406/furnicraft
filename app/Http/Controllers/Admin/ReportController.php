<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller implements HasMiddleware
{
    /** The ranges people actually ask for. */
    public const PERIODS = [
        'today' => 'Today',
        'week' => 'This week',
        'month' => 'This month',
        'last_month' => 'Last month',
        'quarter' => 'This quarter',
        'year' => 'This financial year',
        'custom' => 'Pick dates',
    ];

    public function __construct(protected ReportService $reports) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:reports.view')];
    }

    public function index(Request $request): View
    {
        [$from, $to, $period] = $this->range($request);

        return view('admin.reports.index', [
            'from' => $from,
            'to' => $to,
            'period' => $period,
            'periods' => self::PERIODS,
            'summary' => $this->reports->summary($from, $to),
            'daily' => $this->reports->daily($from, $to),
            'topProducts' => $this->reports->topProducts($from, $to),
            'topCustomers' => $this->reports->topCustomers($from, $to),
            'valuation' => $this->reports->stockValuation(),
        ]);
    }

    public function gst(Request $request): View
    {
        [$from, $to, $period] = $this->range($request);

        return view('admin.reports.gst', [
            'from' => $from,
            'to' => $to,
            'period' => $period,
            'periods' => self::PERIODS,
            'rows' => $this->reports->gstSummary($from, $to),
        ]);
    }

    /**
     * Whichever table is on screen, as a CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        $which = $request->input('report', 'sales');

        [$filename, $headers, $rows] = match ($which) {
            'products' => [
                'top-products',
                ['product', 'units', 'orders', 'revenue'],
                $this->reports->topProducts($from, $to, 500)
                    ->map(fn (array $r) => [$r['name'], $r['units'], $r['orders'], $r['revenue']]),
            ],
            'customers' => [
                'top-customers',
                ['name', 'email', 'orders', 'spent'],
                $this->reports->topCustomers($from, $to, 500)
                    ->map(fn (array $r) => [$r['name'], $r['email'], $r['orders'], $r['spent']]),
            ],
            'gst' => [
                'gst-summary',
                ['rate', 'supply', 'taxable', 'cgst', 'sgst', 'igst', 'total_tax'],
                $this->reports->gstSummary($from, $to)
                    ->map(fn (array $r) => [
                        $r['rate'], $r['interstate'] ? 'Inter-state' : 'Intra-state',
                        $r['taxable'], $r['cgst'], $r['sgst'], $r['igst'], $r['total'],
                    ]),
            ],
            default => [
                'sales',
                ['date', 'orders', 'invoiced', 'received'],
                $this->reports->daily($from, $to)
                    ->map(fn (array $r) => [
                        $r['day']->toDateString(), $r['orders'], $r['invoiced'], $r['received'],
                    ]),
            ],
        };

        $name = 'furnicraft-'.$filename.'-'.$from->format('Y-m-d').'-to-'.$to->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, $headers);

            foreach ($rows as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The dates to report on.
     *
     * The financial year runs April to March in India, which is what "this
     * year" has to mean on an Indian shop's reports.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    protected function range(Request $request): array
    {
        $period = $request->input('period', 'month');

        if ($period === 'custom' && $request->filled('from')) {
            $from = Carbon::parse($request->input('from'))->startOfDay();
            $to = $request->filled('to')
                ? Carbon::parse($request->input('to'))->endOfDay()
                : now()->endOfDay();

            // A range the wrong way round is a typo, not a request for
            // nothing — swap it rather than showing an empty report.
            return $from->gt($to)
                ? [$to->copy()->startOfDay(), $from->copy()->endOfDay(), 'custom']
                : [$from, $to, 'custom'];
        }

        [$from, $to] = match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->startOfWeek(), now()->endOfDay()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [now()->startOfQuarter(), now()->endOfDay()],
            'year' => [$this->financialYearStart(), now()->endOfDay()],
            default => [now()->startOfMonth(), now()->endOfDay()],
        };

        return [$from, $to, $period];
    }

    protected function financialYearStart(): Carbon
    {
        $year = now()->month >= 4 ? now()->year : now()->year - 1;

        return Carbon::create($year, 4, 1)->startOfDay();
    }
}
