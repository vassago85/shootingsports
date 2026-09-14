<?php

namespace App\Http\Controllers;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Http\Requests\StoreEnquiryRequest;
use App\Mail\EnquiryReceivedMail;
use App\Models\Enquiry;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class EnquiryController extends Controller
{
    public function create(): View
    {
        return view('public.enquiries.create', [
            'type' => EnquiryType::General,
            'about' => null,
            'heading' => 'Contact Shooting Sports',
            'intro' => 'All enquiries go through the platform. We do not publish raw contact forms that dump mail onto clubs.',
        ]);
    }

    public function advertise(): View
    {
        return view('public.enquiries.create', [
            'type' => EnquiryType::Advertise,
            'about' => null,
            'heading' => 'Advertise on the register',
            'intro' => 'Tell us which pages and slots you are interested in. Staff will come back with availability and pricing.',
        ]);
    }

    public function listing(string $type, int $id): View
    {
        $about = match ($type) {
            'organisation' => Organisation::query()->findOrFail($id),
            'provider' => Provider::query()->findOrFail($id),
            'venue' => Venue::query()->findOrFail($id),
            default => abort(404),
        };

        return view('public.enquiries.create', [
            'type' => EnquiryType::Listing,
            'about' => $about,
            'aboutType' => $type,
            'heading' => 'Enquire about '.$about->name,
            'intro' => 'Your message is delivered to Shooting Sports staff. We will relay it appropriately — listing emails are not shown publicly.',
        ]);
    }

    public function store(StoreEnquiryRequest $request): RedirectResponse
    {
        $key = 'enquiry:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()
                ->withInput()
                ->withErrors(['body' => 'Too many enquiries from this network. Please try again later.']);
        }

        RateLimiter::hit($key, 60 * 15);

        $aboutType = $request->validated('about_type');
        $aboutId = $request->validated('about_id');

        $enquiry = Enquiry::query()->create([
            'type' => $request->validated('type'),
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'subject' => $request->validated('subject'),
            'body' => $request->validated('body'),
            'about_type' => $aboutType,
            'about_id' => $aboutId,
            'status' => EnquiryStatus::New,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        $staffEmails = User::query()
            ->where('is_staff', true)
            ->whereNotNull('email')
            ->pluck('email')
            ->all();

        foreach ($staffEmails as $email) {
            Mail::to($email)->queue(new EnquiryReceivedMail($enquiry));
        }

        return redirect()
            ->route('enquiries.thanks')
            ->with('status', 'Enquiry received.');
    }

    public function thanks(): View
    {
        return view('public.enquiries.thanks');
    }
}
