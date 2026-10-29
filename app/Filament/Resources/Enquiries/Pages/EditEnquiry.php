<?php

namespace App\Filament\Resources\Enquiries\Pages;

use App\Enums\EnquiryStatus;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Mail\EnquiryForwardedMail;
use App\Models\Enquiry;
use App\Models\User;
use App\Services\Enquiries\RecordEnquiryReply;
use App\Support\EnquiryListingContact;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Mail;

class EditEnquiry extends EditRecord
{
    protected static string $resource = EnquiryResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->getRecord()->markRead();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reply')
                ->label('Reply')
                ->schema([
                    Textarea::make('body')
                        ->label('Message')
                        ->required()
                        ->minLength(2)
                        ->maxLength(5000)
                        ->rows(6),
                ])
                ->action(function (array $data, RecordEnquiryReply $replies): void {
                    /** @var Enquiry $enquiry */
                    $enquiry = $this->getRecord();
                    $staff = auth()->user();

                    if (! $staff instanceof User) {
                        return;
                    }

                    $replies->fromStaff($enquiry, $staff, $data['body']);
                    $enquiry->unsetRelation('replies');

                    Notification::make()->title('Reply sent.')->success()->send();
                })
                ->visible(fn (): bool => $this->getRecord()->status !== EnquiryStatus::PendingConfirmation),
            Action::make('forward_to_organiser')
                ->label('Forward to organiser')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Forward enquiry to organiser')
                ->modalDescription(function (): string {
                    /** @var Enquiry $enquiry */
                    $enquiry = $this->getRecord();
                    $organiser = EnquiryListingContact::address($enquiry) ?? '—';
                    $enquirer = trim((string) $enquiry->email);

                    return $enquirer !== '' && strcasecmp($enquirer, $organiser) !== 0
                        ? "Send this enquiry to {$organiser} and copy {$enquirer}."
                        : "Send this enquiry to {$organiser}.";
                })
                ->modalSubmitActionLabel('Forward')
                ->action(function (): void {
                    /** @var Enquiry $enquiry */
                    $enquiry = $this->getRecord();
                    $organiser = EnquiryListingContact::address($enquiry);

                    if ($organiser === null) {
                        Notification::make()
                            ->title('No organiser email on file.')
                            ->danger()
                            ->send();

                        return;
                    }

                    Mail::to($organiser)->queue(new EnquiryForwardedMail($enquiry, copyEnquirer: true));

                    Notification::make()->title('Enquiry forwarded.')->success()->send();
                })
                ->visible(function (): bool {
                    /** @var Enquiry $enquiry */
                    $enquiry = $this->getRecord();

                    return $enquiry->status !== EnquiryStatus::PendingConfirmation
                        && EnquiryListingContact::address($enquiry) !== null
                        && trim((string) $enquiry->email) !== '';
                }),
            DeleteAction::make(),
        ];
    }
}
