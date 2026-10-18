<?php

namespace App\Filament\Desk\Pages;

use App\Enums\ClubMemberStatus;
use App\Enums\MembershipInvoiceStatus;
use App\Filament\Desk\Resources\ClubMembers\ClubMemberResource;
use App\Models\ClubMember;
use App\Models\MembershipInvoice;
use App\Models\Organisation;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Secretary-facing summary of membership health and revenue for the
 * clubs the current Desk user manages.
 */
class MembershipReports extends Page
{
    protected string $view = 'filament.desk.pages.membership-reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Club';

    protected static ?string $navigationLabel = 'Reports';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'membership-reports';

    protected static ?string $title = 'Membership reports';

    public ?string $year = null;

    public function mount(): void
    {
        $this->year ??= (string) CarbonImmutable::today()->year;
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && ($user->is_staff || $user->holdsClubMemberManagingRole());
    }

    /**
     * @return array<string, int>
     */
    public function statusBreakdown(): array
    {
        $rows = $this->baseMemberQuery()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        $out = [];
        foreach (ClubMemberStatus::cases() as $status) {
            $out[$status->value] = (int) ($rows[$status->value] ?? 0);
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    public function categoryBreakdown(): array
    {
        return $this->baseMemberQuery()
            ->whereIn('status', [ClubMemberStatus::Active->value, ClubMemberStatus::Suspended->value])
            ->selectRaw('category, COUNT(*) as c')
            ->groupBy('category')
            ->pluck('c', 'category')
            ->all();
    }

    public function revenueCentsForYear(): int
    {
        [$start, $end] = $this->currentYearRange();

        return (int) $this->baseInvoiceQuery()
            ->where('status', MembershipInvoiceStatus::Paid->value)
            ->whereBetween('paid_at', [$start->startOfDay(), $end->endOfDay()])
            ->sum('amount_cents');
    }

    public function outstandingCount(): int
    {
        return $this->baseInvoiceQuery()
            ->where('status', MembershipInvoiceStatus::Issued->value)
            ->count();
    }

    public function renewalsDueCount(): int
    {
        [$start, $end] = $this->currentYearRange();

        return $this->baseMemberQuery()
            ->whereIn('status', [ClubMemberStatus::Active->value, ClubMemberStatus::Suspended->value])
            ->whereBetween('expires_at', [$start->toDateString(), $end->toDateString()])
            ->count();
    }

    public function downloadRevenueCsv(): StreamedResponse
    {
        [$start, $end] = $this->currentYearRange();

        $filename = sprintf('membership-revenue-%s.csv', $start->year);
        $rows = $this->baseInvoiceQuery()
            ->where('status', MembershipInvoiceStatus::Paid->value)
            ->whereBetween('paid_at', [$start->startOfDay(), $end->endOfDay()])
            ->with(['clubMember', 'organisation'])
            ->orderBy('paid_at')
            ->get(['id', 'organisation_id', 'club_member_id', 'period_start', 'period_end', 'amount_cents', 'paid_at', 'payment_method', 'paystack_reference']);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Invoice ID',
                'Club',
                'Member',
                'Period start',
                'Period end',
                'Amount (ZAR)',
                'Paid at',
                'Method',
                'Reference',
            ]);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->id,
                    $r->organisation?->name,
                    $r->clubMember?->fullName(),
                    $r->period_start?->toDateString(),
                    $r->period_end?->toDateString(),
                    number_format(($r->amount_cents ?? 0) / 100, 2, '.', ''),
                    $r->paid_at?->toDateTimeString(),
                    $r->payment_method?->value,
                    $r->paystack_reference,
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return list<int>
     */
    protected function managedClubIds(): array
    {
        $user = Auth::user();

        if ($user?->is_staff) {
            return Organisation::query()->clubs()->pluck('id')->all();
        }

        return ClubMemberResource::managedClubIds();
    }

    protected function baseMemberQuery(): Builder
    {
        return ClubMember::query()->whereIn('organisation_id', $this->managedClubIds());
    }

    protected function baseInvoiceQuery(): Builder
    {
        return MembershipInvoice::query()->whereIn('organisation_id', $this->managedClubIds());
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function currentYearRange(): array
    {
        $year = (int) ($this->year ?: CarbonImmutable::today()->year);

        return [
            CarbonImmutable::create($year, 1, 1),
            CarbonImmutable::create($year, 12, 31),
        ];
    }
}
