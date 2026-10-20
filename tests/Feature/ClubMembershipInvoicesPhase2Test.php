<?php

use App\Enums\ClubMemberCategory;
use App\Enums\ClubMemberStatus;
use App\Enums\MembershipInvoicePaymentMethod;
use App\Enums\MembershipInvoiceStatus;
use App\Models\ClubMember;
use App\Models\MembershipInvoice;
use App\Models\Organisation;
use App\Models\OrganisationMembershipFee;
use App\Models\PaystackEvent;
use App\Services\Memberships\IssueAnnualMembershipInvoices;
use App\Services\Memberships\IssueMembershipInvoice;
use App\Services\Memberships\RecordMembershipPayment;
use Carbon\CarbonImmutable;

it('issues a single invoice snapshotting the organisation fee for the member category', function () {
    $organisation = Organisation::factory()->create(['membership_enabled' => true]);
    OrganisationMembershipFee::factory()->senior(60000)->create(['organisation_id' => $organisation->id]);

    $member = ClubMember::factory()->active()->create([
        'organisation_id' => $organisation->id,
        'category' => ClubMemberCategory::Senior,
    ]);

    $start = CarbonImmutable::create(2026, 1, 1);
    $end = CarbonImmutable::create(2026, 12, 31);

    $invoice = app(IssueMembershipInvoice::class)($member, $start, $end);

    expect($invoice->amount_cents)->toBe(60000)
        ->and($invoice->status)->toBe(MembershipInvoiceStatus::Issued)
        ->and($invoice->period_start->toDateString())->toBe('2026-01-01')
        ->and($invoice->period_end->toDateString())->toBe('2026-12-31');
});

it('is idempotent per member and period', function () {
    $organisation = Organisation::factory()->create();
    OrganisationMembershipFee::factory()->senior(50000)->create(['organisation_id' => $organisation->id]);

    $member = ClubMember::factory()->active()->create([
        'organisation_id' => $organisation->id,
        'category' => ClubMemberCategory::Senior,
    ]);

    $start = CarbonImmutable::create(2026, 1, 1);
    $end = CarbonImmutable::create(2026, 12, 31);

    $first = app(IssueMembershipInvoice::class)($member, $start, $end);
    $second = app(IssueMembershipInvoice::class)($member, $start, $end);

    expect($first->id)->toBe($second->id)
        ->and(MembershipInvoice::where('club_member_id', $member->id)->count())->toBe(1);
});

it('throws when no fee exists for the member category', function () {
    $organisation = Organisation::factory()->create();
    $member = ClubMember::factory()->active()->create([
        'organisation_id' => $organisation->id,
        'category' => ClubMemberCategory::Senior,
    ]);

    $start = CarbonImmutable::create(2026, 1, 1);
    $end = CarbonImmutable::create(2026, 12, 31);

    expect(fn () => app(IssueMembershipInvoice::class)($member, $start, $end))
        ->toThrow(RuntimeException::class, 'No active fee');
});

it('bulk-issues one invoice per billable member and skips existing invoices on a second run', function () {
    $organisation = Organisation::factory()->create();
    OrganisationMembershipFee::factory()->senior(45000)->create(['organisation_id' => $organisation->id]);
    OrganisationMembershipFee::factory()->junior(20000)->create(['organisation_id' => $organisation->id]);

    ClubMember::factory()->active()->create([
        'organisation_id' => $organisation->id,
        'category' => ClubMemberCategory::Senior,
    ]);
    ClubMember::factory()->active()->create([
        'organisation_id' => $organisation->id,
        'category' => ClubMemberCategory::Senior,
    ]);
    ClubMember::factory()->junior()->create([
        'organisation_id' => $organisation->id,
    ]);
    // Resigned members do not get billed.
    ClubMember::factory()->create([
        'organisation_id' => $organisation->id,
        'category' => ClubMemberCategory::Senior,
        'status' => ClubMemberStatus::Resigned,
    ]);

    $start = CarbonImmutable::create(2026, 1, 1);
    $end = CarbonImmutable::create(2026, 12, 31);

    $run1 = app(IssueAnnualMembershipInvoices::class)($organisation, $start, $end);
    expect($run1['created'])->toBe(3)
        ->and($run1['skipped'])->toBe(0)
        ->and(MembershipInvoice::count())->toBe(3);

    $run2 = app(IssueAnnualMembershipInvoices::class)($organisation, $start, $end);
    expect($run2['created'])->toBe(0)
        ->and($run2['skipped'])->toBe(3);
});

it('marks an invoice paid and renews the underlying member to period_end', function () {
    $organisation = Organisation::factory()->create();
    OrganisationMembershipFee::factory()->senior(60000)->create(['organisation_id' => $organisation->id]);

    $member = ClubMember::factory()->active()->create([
        'organisation_id' => $organisation->id,
        'category' => ClubMemberCategory::Senior,
        'expires_at' => '2026-01-15',
    ]);

    $invoice = MembershipInvoice::factory()->create([
        'organisation_id' => $organisation->id,
        'club_member_id' => $member->id,
        'period_start' => '2026-01-01',
        'period_end' => '2026-12-31',
        'status' => MembershipInvoiceStatus::Issued,
    ]);

    app(RecordMembershipPayment::class)($invoice, MembershipInvoicePaymentMethod::Eft, 'EFT-123');

    $invoice->refresh();
    $member->refresh();

    expect($invoice->status)->toBe(MembershipInvoiceStatus::Paid)
        ->and($invoice->payment_method)->toBe(MembershipInvoicePaymentMethod::Eft)
        ->and($invoice->paystack_reference)->toBe('EFT-123')
        ->and($invoice->paid_at)->not->toBeNull()
        ->and($member->status)->toBe(ClubMemberStatus::Active)
        ->and($member->expires_at->toDateString())->toBe('2026-12-31');
});

it('leaves a paid invoice alone on a second mark-paid call', function () {
    $organisation = Organisation::factory()->create();
    $member = ClubMember::factory()->active()->create(['organisation_id' => $organisation->id]);

    $invoice = MembershipInvoice::factory()->paid()->create([
        'organisation_id' => $organisation->id,
        'club_member_id' => $member->id,
        'payment_method' => MembershipInvoicePaymentMethod::Cash,
    ]);

    $before = $invoice->paid_at?->getTimestamp();

    app(RecordMembershipPayment::class)($invoice, MembershipInvoicePaymentMethod::Eft, 'ignored');

    $invoice->refresh();
    expect($invoice->payment_method)->toBe(MembershipInvoicePaymentMethod::Cash)
        ->and($invoice->paid_at?->getTimestamp())->toBe($before);
});

it('routes a Paystack club_membership webhook to the invoice recorder', function () {
    $this->withoutExceptionHandling();

    // Pin the webhook secret before anything else — the controller
    // reads config('services.paystack.secret_key') per request. The
    // default .env ships with no PAYSTACK_SECRET_KEY, so we have to
    // give it one or signature verification short-circuits with
    // {"status":"invalid"} and the invoice never gets touched.
    config()->set('services.paystack.secret_key', 'sk_test_dummy');
    config()->set('services.paystack.webhook_secret', null);

    $organisation = Organisation::factory()->create();
    OrganisationMembershipFee::factory()->senior(60000)->create(['organisation_id' => $organisation->id]);

    $member = ClubMember::factory()->active()->create([
        'organisation_id' => $organisation->id,
        'category' => ClubMemberCategory::Senior,
    ]);

    $invoice = MembershipInvoice::factory()->create([
        'organisation_id' => $organisation->id,
        'club_member_id' => $member->id,
        'period_start' => '2026-01-01',
        'period_end' => '2026-12-31',
        'status' => MembershipInvoiceStatus::Issued,
    ]);

    $payload = [
        'id' => 'evt-'.uniqid(),
        'event' => 'charge.success',
        'data' => [
            'reference' => 'PSK-TEST-123',
            'customer' => ['email' => $member->email],
            'metadata' => [
                'purpose' => 'club_membership',
                'invoice_id' => $invoice->id,
            ],
        ],
    ];

    $rawBody = json_encode($payload);
    $signature = hash_hmac('sha512', $rawBody, 'sk_test_dummy');

    // Build the request manually so getContent() receives the exact
    // bytes we signed and HTTP_X_PAYSTACK_SIGNATURE arrives as a real
    // request header (mirrors the pattern in PaystackWebhookTest).
    $response = $this->call(
        method: 'POST',
        uri: '/paystack/webhook',
        parameters: [],
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
        ],
        content: $rawBody,
    );

    // Signature verification uses the raw body — if the request body
    // Laravel's test client sends does not match the bytes we signed,
    // the controller returns {"status":"invalid"} with 200 and the
    // invoice never gets touched. Verify we got the real "ok".
    $response->assertOk();
    expect($response->json('status'))->toBe('ok');

    $event = PaystackEvent::query()->first();
    expect($event)->not->toBeNull()
        ->and($event->event_type)->toBe('charge.success')
        ->and($event->processed_at)->not->toBeNull()
        ->and($event->error)->toBeNull();

    $invoice->refresh();
    expect($invoice->status)->toBe(MembershipInvoiceStatus::Paid)
        ->and($invoice->payment_method)->toBe(MembershipInvoicePaymentMethod::Paystack)
        ->and($invoice->paystack_reference)->toBe('PSK-TEST-123');

    // Idempotent — a replay of the same event is a no-op.
    $replay = $this->call(
        method: 'POST',
        uri: '/paystack/webhook',
        parameters: [],
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
        ],
        content: $rawBody,
    );
    $replay->assertOk();
    expect($replay->json('status'))->toBe('already_processed');

    expect(PaystackEvent::count())->toBe(1);
});
