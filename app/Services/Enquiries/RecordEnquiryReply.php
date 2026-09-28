<?php

namespace App\Services\Enquiries;

use App\Enums\EnquiryStatus;
use App\Mail\EnquiryFollowUpMail;
use App\Mail\EnquiryReplyMail;
use App\Models\Enquiry;
use App\Models\EnquiryReply;
use App\Models\User;
use App\Support\EnquiryListingContact;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RecordEnquiryReply
{
    public function fromStaff(Enquiry $enquiry, User $staff, string $body): EnquiryReply
    {
        $reply = $enquiry->replies()->create([
            'user_id' => $staff->id,
            'from_enquirer' => false,
            'body' => $body,
        ]);

        $enquiry->forceFill([
            'reply_token' => $enquiry->reply_token ?? Str::random(48),
            'status' => EnquiryStatus::Replied,
        ])->save();

        Mail::to($enquiry->email)->queue(new EnquiryReplyMail($enquiry, $reply));

        return $reply;
    }

    public function fromEnquirer(Enquiry $enquiry, string $body): EnquiryReply
    {
        $reply = $enquiry->replies()->create([
            'from_enquirer' => true,
            'body' => $body,
        ]);

        $enquiry->forceFill([
            'status' => EnquiryStatus::New,
            'read_at' => null,
        ])->save();

        EnquiryListingContact::deliver(
            $enquiry,
            new EnquiryFollowUpMail($enquiry, $reply),
            new EnquiryFollowUpMail($enquiry, $reply, forStaff: true),
        );

        return $reply;
    }
}
