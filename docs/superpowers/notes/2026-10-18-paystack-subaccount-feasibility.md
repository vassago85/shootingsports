# Paystack SA Subaccount Feasibility — 2026-10-18

**Status:** Documentation stub — awaiting live dev-account probe by the project owner.

**Why a stub:** The feasibility probe requires live Paystack dev-account access and a test transaction. The AI controller has no credentials. The questions, the exact probe commands, and the decision slots are recorded here so the project owner can execute the probe in one sitting.

## Questions

1. **Subaccounts for SA merchants in 2026?** Does Paystack SA support subaccount creation for South African merchants in the current API?
2. **Transaction charge + subaccount compatible?** Does `POST /transaction/initialize` accept `transaction_charge` **alongside** `subaccount` so our 2% + VAT platform fee is deducted from the gross before settlement reaches the subaccount? (If `transaction_charge` is not supported, we fall back to Multi-split and set the club's `share` percentage so our fee is deducted the same way.)
3. **Subaccount KYC burden?** What KYC fields does subaccount creation require (business name, settlement bank, settlement account, FICA docs, directors)? Is it workable for a volunteer club secretary with no business registration (i.e. an unincorporated association)?

The answer to #3 determines whether `PRIMARY=subaccounts` is actually usable in practice. If subaccount KYC requires a registered legal entity + FICA docs, most clubs won't clear it and the bank-transfer fallback becomes the *de facto* primary path — same code ships, just reorder which `payout_method` is the default.

## Dev-account probe (owner to execute)

Prerequisite: live Paystack test secret key in `sk_test_xxx` form, logged into the Paystack dashboard with "Developer" > "API Keys" visible.

### Probe step 1 — Create a subaccount

```bash
curl -X POST https://api.paystack.co/subaccount \
  -H "Authorization: Bearer sk_test_xxx" \
  -H "Content-Type: application/json" \
  -d '{
    "business_name": "ShootingSports Test Club",
    "settlement_bank": "058",
    "account_number": "0123456789",
    "percentage_charge": 0.0
  }'
```

Expected: `201 Created` with `data.subaccount_code = "ACCT_xxxxxxxxxx"`. Record it.

If a settlement bank code is rejected, consult `GET https://api.paystack.co/bank?country=south%20africa` for the live list.

### Probe step 2 — Initialise a split transaction with `transaction_charge`

```bash
curl -X POST https://api.paystack.co/transaction/initialize \
  -H "Authorization: Bearer sk_test_xxx" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@shootingsports.co.za",
    "amount": 1000,
    "subaccount": "ACCT_xxxxxxxxxx",
    "transaction_charge": 23,
    "bearer": "subaccount",
    "metadata": {
      "purpose": "probe",
      "notes": "Section 54 agency probe — 2% of R10 = R0.20 + 15% VAT = R0.23"
    }
  }'
```

Expected: `200` with `data.authorization_url`. Pay the hosted checkout in the browser with a Paystack test card (`4084 0840 8408 4081`, any future expiry, any CVV, PIN `0000`, OTP `123456`).

### Probe step 3 — Verify the split landed correctly

```bash
curl https://api.paystack.co/transaction/verify/<reference> \
  -H "Authorization: Bearer sk_test_xxx"
```

Expected shape:
- `data.amount = 1000` (gross collected)
- `data.fees` + `data.fees_split` reflects Paystack's own gateway cut
- The subaccount settles R10 - R0.23 - Paystack-gateway-cut
- Our main account receives R0.23 (the `transaction_charge`)

Then confirm the dashboard **Transactions > Splits** view shows the R0.23 landing in the main account and the R9.77 - Paystack-cut landing in the subaccount's pending settlement.

## Evidence

TODO (owner): paste the responses from the three curl commands here, with secrets redacted.

## Decision

**Record one** of these outcomes and commit:

- `PRIMARY=subaccounts` — Steps 1-3 succeed, KYC is workable for clubs, `transaction_charge` is supported.
- `PRIMARY=bank_transfer` — Any of: subaccount creation rejected for SA, `transaction_charge` + `subaccount` not jointly supported, or KYC blocks real-world clubs.

The plan ships **both paths** regardless. This decision only reorders which one is the default `payout_method` on the Paystack-Subaccount wizard (Task 10) and which one the Agency Agreement describes as the primary settlement route.

Current default (pre-probe): `PRIMARY=subaccounts`, with `payout_method = 'bank_transfer'` as the explicit opt-out. The owner can flip this by changing one `config('platform.default_payout_method')` setting at any point.
