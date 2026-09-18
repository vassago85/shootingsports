<?php

namespace App\Http\Controllers;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\PrelaunchContributorRole;
use App\Http\Requests\StoreComingSoonInterestRequest;
use App\Mail\EnquiryReceivedMail;
use App\Mail\PrelaunchContributorConfirmMail;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class ComingSoonInterestController extends Controller
{
    public function store(StoreComingSoonInterestRequest $request): RedirectResponse
    {
        $key = 'coming-soon-interest:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()
                ->withInput()
                ->withErrors(['body' => 'Too many submissions from this network. Please try again later.']);
        }

        RateLimiter::hit($key, 60 * 15);

        $role = PrelaunchContributorRole::from($request->validated('role'));
        $email = strtolower($request->validated('email'));

        $activeSameRole = Enquiry::query()
            ->where('type', EnquiryType::PrelaunchContributor)
            ->where('email', $email)
            ->whereIn('status', [EnquiryStatus::New, EnquiryStatus::Read])
            ->get()
            ->contains(fn (Enquiry $enquiry): bool => data_get($enquiry->context, 'role') === $role->value);

        if ($activeSameRole) {
            return back()
                ->withInput()
                ->withErrors(['email' => 'We already have a confirmed interest from this email for that role. We will be in touch.']);
        }

        $pending = Enquiry::query()
            ->where('type', EnquiryType::PrelaunchContributor)
            ->where('email', $email)
            ->where('status', EnquiryStatus::PendingConfirmation)
            ->first();

        if ($pending !== null) {
            $pending->fill([
                'name' => $request->validated('name'),
                'body' => $request->validated('body'),
                'subject' => $role->subjectLine(),
                'context' => ['role' => $role->value],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);
            $pending->save();
            $token = $pending->issueConfirmationToken();

            Mail::to($pending->email)->queue(
                (new PrelaunchContributorConfirmMail($pending->fresh(), $token))->afterCommit()
            );

            return redirect()
                ->route('coming-soon')
                ->with('interest_status', 'check_email');
        }

        $enquiry = Enquiry::query()->create([
            'type' => EnquiryType::PrelaunchContributor,
            'name' => $request->validated('name'),
            'email' => $email,
            'subject' => $role->subjectLine(),
            'body' => $request->validated('body'),
            'context' => ['role' => $role->value],
            'status' => EnquiryStatus::PendingConfirmation,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        $token = $enquiry->issueConfirmationToken();

        Mail::to($enquiry->email)->queue(
            (new PrelaunchContributorConfirmMail($enquiry->fresh(), $token))->afterCommit()
        );

        return redirect()
            ->route('coming-soon')
            ->with('interest_status', 'check_email');
    }

    public function confirm(string $token): View
    {
        $enquiry = Enquiry::query()
            ->where('confirmation_token', $token)
            ->where('type', EnquiryType::PrelaunchContributor)
            ->first();

        if ($enquiry === null) {
            return view('public.coming-soon-confirm', [
                'ok' => false,
                'title' => 'Link not valid',
                'message' => 'This confirmation link is invalid or has already been used. You can submit again from the coming soon page.',
            ]);
        }

        if ($enquiry->status !== EnquiryStatus::PendingConfirmation || $enquiry->confirmationIsExpired()) {
            return view('public.coming-soon-confirm', [
                'ok' => false,
                'title' => 'Link expired',
                'message' => 'This confirmation link has expired. Please submit again from the coming soon page and confirm within 48 hours.',
            ]);
        }

        $enquiry->markConfirmed();

        $staffEmails = User::query()
            ->where('is_staff', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->all();

        foreach ($staffEmails as $email) {
            Mail::to($email)->queue(
                (new EnquiryReceivedMail($enquiry->fresh()))->afterCommit()
            );
        }

        return view('public.coming-soon-confirm', [
            'ok' => true,
            'title' => 'Interest confirmed',
            'message' => 'Thanks — your email is confirmed. Our team will be in touch as we load clubs, ranges, matches and listings.',
        ]);
    }
}
