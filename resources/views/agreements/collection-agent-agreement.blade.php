{{-- TBD: finalise this wording with the appointed accountant/lawyer before launch. --}}
<article class="agency-agreement">
  <h1>Collection Agent Agreement (version {{ $version }})</h1>

  <p>This agreement is entered into between <strong>shootingsports.co.za</strong> (“the Agent”, VAT registration pending confirmation) and the undersigned organisation (“the Principal”).</p>

  <h2>1. Appointment</h2>
  <p>The Principal appoints the Agent as its collection agent for the purpose of receiving payment from members, event entrants, and the general public on the Principal’s behalf.</p>

  <h2>2. Nature of the money collected</h2>
  <p>All amounts collected by the Agent on behalf of the Principal remain the property of the Principal from the moment of collection. The Agent holds such funds in trust and does not take ownership of them. This arrangement is intended to qualify under <strong>Section 54 of the Value-Added Tax Act, 1991</strong> such that the Agent does not account for VAT on amounts collected on the Principal’s behalf.</p>

  <h2>3. Platform fee</h2>
  <p>The Agent charges a platform fee of <strong>2% of the gross collected amount plus 15% VAT</strong> (the fee rate and VAT rate are configurable by the Agent; the rates applicable to a given transaction are those shown to the member at checkout). The Agent is entitled to deduct this fee (together with gateway charges levied by the payment processor) from the collected amount before remitting the balance to the Principal.</p>

  <h2>4. Payment processing</h2>
  <p>The Agent uses Paystack as its payment service provider. Where supported, settlement is routed directly to a Paystack subaccount nominated by the Principal. Where subaccounts are unavailable or the Principal elects not to use them, the Agent collects to its own account and remits by bank transfer per clause 6.</p>

  <h2>5. Documents</h2>
  <p>Every member payment produces a tax invoice from the Principal (where the Principal is VAT-registered) and a separate tax invoice from the Agent for the platform fee. The Agent furnishes the Principal with monthly reconciliations.</p>

  <h2>6. Settlement</h2>
  <p>Where the Agent collects to its own account on the Principal’s behalf, the Agent will remit the net amount (gross less platform fee, VAT on the fee, and gateway charges) to the Principal’s nominated bank account within 7 calendar days of collection, less an administrative fee of R50 (ex-VAT) for the second and subsequent payouts in each calendar month.</p>

  <h2>7. Versioning</h2>
  <p>This agreement is version <strong>{{ $version }}</strong>. The Agent may update this agreement from time to time; the Principal will be prompted to re-accept any new version before continuing to use the service.</p>

  <h2>8. Termination</h2>
  <p>Either party may terminate this agreement on 30 days’ written notice. Collection of amounts already in-flight at the time of termination will be completed and remitted in the ordinary course.</p>

  <p class="footer-note"><em>TBD-pending-accountant: confirm wording of clauses 2 and 3 against the current SARS guidance on Section 54 before launch.</em></p>
</article>
