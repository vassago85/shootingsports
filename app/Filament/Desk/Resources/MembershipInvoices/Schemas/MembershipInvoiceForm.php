<?php

namespace App\Filament\Desk\Resources\MembershipInvoices\Schemas;

use App\Enums\MembershipInvoicePaymentMethod;
use App\Enums\MembershipInvoiceStatus;
use App\Filament\Desk\Resources\ClubMembers\ClubMemberResource;
use App\Models\ClubMember;
use App\Models\Organisation;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MembershipInvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice')
                    ->columns(2)
                    ->schema([
                        Select::make('organisation_id')
                            ->label('Club')
                            ->relationship(
                                name: 'organisation',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query): Builder {
                                    $user = Auth::user();

                                    if ($user?->is_staff) {
                                        return $query->clubs()->orderBy('name');
                                    }

                                    return $query->whereIn('id', ClubMemberResource::managedClubIds())
                                        ->orderBy('name');
                                },
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->default(fn (): ?int => Auth::user()?->is_staff
                                ? null
                                : (ClubMemberResource::managedClubIds()[0] ?? null)),
                        Select::make('club_member_id')
                            ->label('Member')
                            ->options(function (callable $get): array {
                                $organisationId = (int) ($get('organisation_id') ?? 0);
                                if ($organisationId <= 0) {
                                    return [];
                                }

                                return ClubMember::query()
                                    ->where('organisation_id', $organisationId)
                                    ->orderBy('last_name')
                                    ->orderBy('first_name')
                                    ->get(['id', 'first_name', 'last_name', 'member_number'])
                                    ->mapWithKeys(fn (ClubMember $member): array => [
                                        $member->id => trim($member->fullName().' '.($member->member_number ? '('.$member->member_number.')' : '')),
                                    ])
                                    ->all();
                            })
                            ->searchable()
                            ->required(),
                        DatePicker::make('period_start')
                            ->native(false)
                            ->default(fn (): string => CarbonImmutable::create(CarbonImmutable::today()->year, 1, 1)->toDateString())
                            ->required(),
                        DatePicker::make('period_end')
                            ->native(false)
                            ->default(fn (): string => CarbonImmutable::create(CarbonImmutable::today()->year, 12, 31)->toDateString())
                            ->required(),
                        TextInput::make('amount_cents')
                            ->label('Amount (cents)')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->helperText('Snapshot of the fee at issue. Stored in cents.'),
                        Select::make('status')
                            ->options(MembershipInvoiceStatus::class)
                            ->default(MembershipInvoiceStatus::Issued->value)
                            ->required(),
                    ]),

                Section::make('Payment')
                    ->columns(2)
                    ->schema([
                        Select::make('payment_method')
                            ->options(MembershipInvoicePaymentMethod::class)
                            ->placeholder('Unpaid'),
                        TextInput::make('paystack_reference')
                            ->label('Paystack reference')
                            ->maxLength(120),
                        DatePicker::make('due_at')->native(false),
                        DatePicker::make('paid_at')->native(false),
                    ]),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->rows(3)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function pickOrganisation(?int $id): ?Organisation
    {
        return $id === null ? null : Organisation::query()->find($id);
    }
}
