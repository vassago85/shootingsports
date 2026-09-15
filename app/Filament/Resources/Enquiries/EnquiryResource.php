<?php

namespace App\Filament\Resources\Enquiries;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Filament\Resources\Enquiries\Pages\EditEnquiry;
use App\Filament\Resources\Enquiries\Pages\ListEnquiries;
use App\Models\Enquiry;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class EnquiryResource extends Resource
{
    protected static ?string $model = Enquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Enquiry')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->disabled(),
                        TextInput::make('email')->disabled(),
                        TextInput::make('phone')->disabled(),
                        Select::make('type')->options(EnquiryType::class)->disabled(),
                        TextInput::make('subject')->disabled()->columnSpanFull(),
                        Textarea::make('body')->disabled()->rows(8)->columnSpanFull(),
                        // Context is polymorphic per enquiry type:
                        //   pro_waitlist: { trigger, answer? }
                        //   advertise:    { product }
                        // Rendered as JSON — enough for staff triage
                        // without an editor UI for what is an audit trail.
                        Textarea::make('context')
                            ->disabled()
                            ->rows(4)
                            ->columnSpanFull()
                            ->formatStateUsing(fn ($state): string => $state
                                ? (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                                : ''
                            ),
                        Select::make('status')
                            ->options(EnquiryStatus::class)
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime('j M H:i')->sortable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('subject')->limit(40)->toggleable(),
                // Waitlist trigger (pulled from JSON context) — makes
                // "which cap is driving demand" a scannable column,
                // not a click-in-to-see-JSON hunt.
                TextColumn::make('context_trigger')
                    ->label('Trigger')
                    ->getStateUsing(fn (Enquiry $record): ?string => data_get($record->context, 'trigger'))
                    ->badge()
                    ->toggleable(),
                // Truncated free-text answer, same intent.
                TextColumn::make('context_answer')
                    ->label('Answer')
                    ->getStateUsing(fn (Enquiry $record): ?string => data_get($record->context, 'answer'))
                    ->limit(60)
                    ->toggleable(),
                // Advertise product key (pro_waitlist rows leave this
                // empty, and vice versa).
                TextColumn::make('context_product')
                    ->label('Product')
                    ->getStateUsing(fn (Enquiry $record): ?string => data_get($record->context, 'product'))
                    ->badge()
                    ->toggleable(),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options(EnquiryStatus::class),
                SelectFilter::make('type')->options(EnquiryType::class),
            ])
            ->recordActions([
                EditAction::make()->label('Open'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEnquiries::route('/'),
            'edit' => EditEnquiry::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
