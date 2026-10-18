<?php

namespace App\Http\Controllers;

use App\Filament\Desk\Resources\ClubMembers\ClubMemberResource;
use App\Models\PlatformFeeStatement;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a platform fee statement's lines as CSV for the club's
 * accountant. Staff or the club's managers only.
 */
class FeeStatementDownloadController extends Controller
{
    public function __invoke(Request $request, PlatformFeeStatement $statement): StreamedResponse
    {
        abort_unless(
            $request->user()->is_staff
                || in_array($statement->organisation_id, ClubMemberResource::managedClubIds(), true),
            403,
        );

        $lines = $statement->lines()
            ->with('membershipInvoice.clubMember')
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();

        return response()->streamDownload(function () use ($lines): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Invoice ID', 'Member', 'Paid at', 'Gross (ZAR)', 'Fee (ZAR)', 'VAT (ZAR)']);

            foreach ($lines as $line) {
                fputcsv($out, [
                    $line->membership_invoice_id,
                    $line->membershipInvoice?->clubMember?->fullName(),
                    $line->paid_at?->toDateTimeString(),
                    number_format($line->gross_cents / 100, 2, '.', ''),
                    number_format($line->fee_cents / 100, 2, '.', ''),
                    number_format($line->fee_vat_cents / 100, 2, '.', ''),
                ]);
            }

            fclose($out);
        }, "fee-statement-{$statement->period_start->format('Y-m')}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
