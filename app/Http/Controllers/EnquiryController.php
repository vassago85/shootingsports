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
        $products = config('advertising.products', []);
        $commitments = config('advertising.commitments', []);

        // Product/Offer JSON-LD, one Offer per product. Search engines
        // can surface pricing snippets and treat each product as a
        // distinct service. Runs through the site() base graph so the
        // WebSite + Organization identity ships too.
        $offers = array_values(array_map(static function (array $product): array {
            return [
                '@type' => 'Offer',
                'name' => $product['name'],
                'description' => $product['summary'],
                'price' => number_format(($product['price_per_month_cents'] ?? 0) / 100, 2, '.', ''),
                'priceCurrency' => 'ZAR',
                'availability' => 'https://schema.org/InStock',
                'url' => route('advertise').'#prod-'.$product['key'],
            ];
        }, $products));

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => 'Shooting Sports advertising',
            'provider' => [
                '@type' => 'SportsOrganization',
                'name' => 'Shooting Sports',
                'url' => route('home'),
            ],
            'areaServed' => [
                '@type' => 'Country',
                'name' => 'South Africa',
            ],
            'offers' => $offers,
        ];

        return view('public.advertise', [
            'products' => $products,
            'commitments' => $commitments,
            'jsonLd' => $jsonLd,
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

        $type = $request->validated('type');
        $product = $request->validated('product');

        // Merge whatever context the form supplied. Advertise carries a
        // product key (from the rate card select); other paths get an
        // empty context, kept as null so the column is nullable-clean.
        $context = null;
        if ($type === 'advertise' && filled($product)) {
            $context = ['product' => $product];
        }

        $enquiry = Enquiry::query()->create([
            'type' => $type,
            'user_id' => auth()->id(),
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'subject' => $request->validated('subject'),
            'body' => $request->validated('body'),
            'about_type' => $aboutType,
            'about_id' => $aboutId,
            'context' => $context,
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
