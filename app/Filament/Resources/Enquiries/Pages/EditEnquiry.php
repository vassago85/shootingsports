<?php

namespace App\Filament\Resources\Enquiries\Pages;

use App\Enums\EnquiryStatus;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Models\Enquiry;
use App\Models\User;
use App\Services\Enquiries\RecordEnquiryReply;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

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
            DeleteAction::make(),
        ];
    }
}
