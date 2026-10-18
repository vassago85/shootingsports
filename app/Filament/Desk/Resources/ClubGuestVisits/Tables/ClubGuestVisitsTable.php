<?php

namespace App\Filament\Desk\Resources\ClubGuestVisits\Tables;

use App\Enums\ClubMemberCategory;
use App\Enums\ClubMemberStatus;
use App\Filament\Desk\Resources\ClubMembers\ClubMemberResource;
use App\Models\ClubGuestVisit;
use App\Models\ClubMember;
use App\Models\Organisation;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ClubGuestVisitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('visit_date', 'desc')
            ->columns([
                TextColumn::make('visit_date')
                    ->date('j M Y')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('hostMember.first_name')
                    ->label('Host')
                    ->getStateUsing(fn (ClubGuestVisit $record): string => $record->hostMember?->fullName() ?? '—'),
                TextColumn::make('organisation.name')
                    ->label('Club')
                    ->visible(fn (): bool => self::managesMultipleClubs())
                    ->toggleable(),
                IconColumn::make('converted')
                    ->label('Converted')
                    ->boolean()
                    ->getStateUsing(fn (ClubGuestVisit $record): bool => $record->hasBeenConverted()),
            ])
            ->recordActions([
                Action::make('convertToApplication')
                    ->label('Convert to application')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('success')
                    ->visible(fn (ClubGuestVisit $record): bool => ! $record->hasBeenConverted())
                    ->schema([
                        Select::make('category')
                            ->options(ClubMemberCategory::class)
                            ->default(ClubMemberCategory::Senior->value)
                            ->required(),
                    ])
                    ->action(function (ClubGuestVisit $record, array $data): void {
                        $raw = $data['category'] ?? ClubMemberCategory::Senior;
                        $category = $raw instanceof ClubMemberCategory
                            ? $raw
                            : ClubMemberCategory::from((string) $raw);

                        [$first, $last] = self::splitName($record->name);

                        $member = ClubMember::create([
                            'organisation_id' => $record->organisation_id,
                            'user_id' => null,
                            'first_name' => $first,
                            'last_name' => $last,
                            'email' => $record->email,
                            'phone' => $record->phone,
                            'category' => $category,
                            'status' => ClubMemberStatus::Applied,
                            'notes' => trim('Converted from guest visit '.$record->visit_date?->format('j M Y')."\n\n".(string) $record->notes),
                        ]);

                        $member->statusChanges()->create([
                            'from_status' => null,
                            'to_status' => ClubMemberStatus::Applied->value,
                            'changed_by' => Auth::id(),
                            'reason' => 'Guest visit convert',
                        ]);

                        $record->forceFill(['converted_club_member_id' => $member->id])->save();

                        Notification::make()
                            ->title('Guest converted to applied member.')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ]);
    }

    private static function managesMultipleClubs(): bool
    {
        $user = Auth::user();

        if ($user?->is_staff) {
            return true;
        }

        return count(ClubMemberResource::managedClubIds()) > 1;
    }

    /** @return array{0: string, 1: string} */
    private static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        if ($parts === []) {
            return ['Guest', 'Unknown'];
        }

        $first = array_shift($parts);
        $last = $parts === [] ? 'Unknown' : implode(' ', $parts);

        return [$first, $last];
    }

    /** @return list<int> */
    private static function managedClubIds(): array
    {
        $user = Auth::user();

        if ($user?->is_staff) {
            return Organisation::query()->clubs()->pluck('id')->all();
        }

        return ClubMemberResource::managedClubIds();
    }
}
