<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Finances;
use Illuminate\Http\Request;

/** Money across every event: per-event table, totals, monthly bars and a CSV. */
class FinanceController extends Controller
{
    private function range(Request $request): array
    {
        $year = $request->query('year');

        return preg_match('/^\d{4}$/', (string) $year) ? [$year.'-01-01', $year.'-12-31', (int) $year] : [null, null, null];
    }

    public function index(Request $request)
    {
        [$from, $to, $year] = $this->range($request);

        return view('admin.finances', Finances::forOrganizer($from, $to) + ['year' => $year]);
    }

    public function csv(Request $request)
    {
        [$from, $to, $year] = $this->range($request);
        $data = Finances::forOrganizer($from, $to);
        $d = fn (int $c) => number_format($c / 100, 2, '.', '');

        return response()->streamDownload(function () use ($data, $d) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Event', 'Date', 'Status', 'Vendors paid', 'Table fees online', 'Platform fee', 'Refunded', 'Tickets sold', 'Tickets online',
                'Paid out', 'Table fees by hand', 'Door cash', 'Door card', 'Door other', 'Collected in person', 'Total to you']);
            foreach ($data['rows'] as $r) {
                $e = $r['event'];
                fputcsv($out, [$e->title, $e->starts_on?->format('Y-m-d'), $e->status, $r['vendors_paid'], $d($r['tables_online']), $d($r['tables_fee']),
                    $d($r['tables_refunded']), $r['tickets_sold'], $d($r['tickets_online']), $d($r['payout']), $d($r['tables_cash']),
                    $d($r['door_cash']), $d($r['door_card']), $d($r['door_other']), $d($r['collected_in_person']), $d($r['total'])]);
            }
            fclose($out);
        }, 'financials'.($year ? '-'.$year : '').'.csv', ['Content-Type' => 'text/csv']);
    }
}
