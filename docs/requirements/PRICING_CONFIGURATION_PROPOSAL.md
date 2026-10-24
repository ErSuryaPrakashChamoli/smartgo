# SmartGro — Pricing Configuration Proposal (option P-2)

| | |
|---|---|
| Status | **PROPOSAL — for the owner to edit and approve.** Drafted 2026-10-24 under option P-2, because section 9 of the owner's brief ("Pricing configuration") was not received. |
| What this is | **How** prices, charges, taxes, offers, payment and cancellation rules are configured in the back office |
| What this is not | It sets **no prices, rates, deposits, fees, discounts or terms.** Every value is either "Owner to set" or a clearly marked proposal. |
| Builds on | BR-PR-1…6 (`BUSINESS_RULES.md`), ERD §4 and §4.1 (`../architecture/DATABASE_ERD.md`), ADR-0013 |
| Needed by | Phase 5 (pricing build). Phase 8 for payment and cancellation behaviour. |

If section 9 of the brief still exists, it replaces anything here that it contradicts.

---

## 1. Principles (already approved in the architecture)

1. **Prices live only in price plans.** Pages and copy show live prices through price tokens, never typed numbers (BR-PR-1).
2. **Property-specific pricing** (OD-08): every product category is priced per property (offering), with optional overrides for individual spaces.
3. Money is stored as integer paise in INR. Prices are stored **tax-exclusive** (BR-PR-4 default).
4. **Four-eyes:** Finance creates or changes a price plan, and a different authorised person activates it (BR-PR-2). Every change is audited.
5. A confirmed booking or agreement keeps an immutable **price snapshot**; later price changes never alter it (BR-PR-6).
6. No price, fee or percentage is hard-coded. All are back-office data.

---

## 2. Price units per product category (owner chooses which apply)

| Category (OD-07) | Pricing unit options | Proposed default |
|---|---|---|
| Private cabins / private offices | per cabin per month · per seat per month | per cabin per month |
| Dedicated desks | per seat per month | per seat per month |
| Hot desks / flexible seating | per day (day pass) · per month (membership) · optional day-pass bundle (N days) | per day + per month |
| Meeting rooms | per hour · per half day · per full day | per hour, with half/full-day rates optional |
| Conference and training rooms | per hour · per half day · per full day | per half day + per full day |
| Virtual office / business address | per month, by plan (the owner defines any plans and what each includes) | per month |
| Managed offices for enterprises | **quotation** (custom proposal); no public price plan | quotation only |

## 3. Price levels

| Level | Use | Who |
|---|---|---|
| Offering price (category × property) | the standard price at a property | Finance (four-eyes) |
| Space override | a specific cabin or room priced differently (size, view, equipment) | Finance (four-eyes) |
| Quotation | managed offices and large custom deals; created in the CRM deal, not public | Sales Manager + Finance approval above a threshold (Owner to set) |

## 4. Additional charges (owner decides which exist)

| Charge | Configurable as | Value |
|---|---|---|
| Security deposit | number of months of fee, or a fixed amount, per category/offering | Owner to set |
| One-time setup / onboarding fee | fixed amount per category/offering, or none | Owner to set |
| Add-ons (e.g. parking, storage, extra services) | named add-on items with their own unit and price, per property | Owner to list; none assumed |
| Minimum term / lock-in | months, per category/offering | Owner to set |
| Notice period | months, per category | Owner to set |

## 5. Taxes (GST)

* The GST rate is **configurable per category**, with the applicable SAC code. **The rates and codes must be confirmed by SmartGro's finance/tax adviser.** None is assumed here.
* Display rule (proposed): prices shown with "+ GST". Totals at checkout show GST separately.
* GSTIN capture for business customers is an optional field on bookings, agreements and virtual-office orders.
* Producing tax invoices is not in scope until D-08 (payments and invoicing) is decided.

## 6. Public price display (per offering)

Options per BR-PR-3: **hidden** · **"from" price** (lowest active plan for that unit at that property) · **exact**.

| Category | Proposed display (not approved) |
|---|---|
| Hot desks, meeting rooms, conference/training rooms | "from" price |
| Dedicated desks, private cabins | "from" price, if the owner wants prices public; otherwise hidden with "request a quote" |
| Virtual office | exact plan price |
| Managed offices | hidden; quotation only |

Changing the display setting requires pricing permission (Finance or Super Admin).

## 7. Discounts and offers

| Type | Configuration | Control |
|---|---|---|
| Promotional offer | percent, fixed amount, or free period; valid dates; eligible offerings; optional code; maximum redemptions | approval before activation; expires automatically |
| Term-commitment discount | discount by agreement length (e.g. longer term → lower monthly price) | values Owner to set; Finance approval |
| Price floor | minimum price per offering below which no offer or discount can go | Owner to set (optional) |
| Sales discretion | maximum discount a sales executive may give without approval | Owner to set |

## 8. Payment rules per category (depends on D-07/D-08)

| Option | Typical use (proposal) |
|---|---|
| No online payment: enquiry or booking request confirmed by staff | all categories until online payments are approved |
| Full prepayment online | meeting rooms, conference/training rooms, day passes, once a gateway is chosen |
| Deposit online, balance by invoice | optional for larger bookings |
| Invoice (term agreements) | private cabins, dedicated desks, memberships, virtual office, managed offices |

The gateway (D-08) and whether instant online booking is offered at launch (D-07) remain open. Until decided, no category is set to online payment (BR-PRODCFG-5).

## 9. Cancellation policies

Named policies, each with a customer-facing summary, a free-cancellation window and the refund percentage after it, assigned per category or offering. **All windows and percentages: Owner to set.** Policies are shown at the point of booking and in confirmations.

## 10. Who can do what

| Action | Roles |
|---|---|
| Create or edit price plans, charges, add-ons | Finance (Super Admin) |
| Activate a price plan or offer | a second authorised person: Finance or Super Admin, not the author |
| Change public price display | Finance, Super Admin |
| Create quotations | Sales Manager, Sales Executive (scoped). Approval above the discretion threshold. |
| View prices | all back-office roles that see offerings |

## 11. Decisions the owner needs to make

| # | Decision | Options |
|---|---|---|
| PR-1 | Pricing unit(s) per category (§2) | as listed |
| PR-2 | Public price display per category (§6) | hidden / from / exact |
| PR-3 | Security deposit rule per category | months / fixed / none |
| PR-4 | Setup fee per category | amount / none |
| PR-5 | Minimum term and notice period per category | months |
| PR-6 | Add-ons offered, if any | list |
| PR-7 | GST rates and SAC codes per category | finance to confirm |
| PR-8 | Discount controls: sales discretion limit, price floors, term discounts | values |
| PR-9 | Payment rule per category | §8 |
| PR-10 | Cancellation policy per category | window + refund % |
| PR-11 | Virtual-office plans and what each includes | owner defines |

## 12. Data-model additions (proposed for Phase 5; ERD §4)

* `price_plans.unit`: add `per_cabin_month` and `bundle_days` (with `bundle_size`). Add `deposit_type` (`months`, `fixed`, `none`) with a value, and `setup_fee_minor`.
* `offering_addons`: `offering_id`, `name`, `unit`, `amount_minor`, `is_active` (only if PR-6 lists any)
* `products.gst_rate_bps`, `products.sac_code`: per category (values from PR-7)
* `pricing_controls`: `sales_discount_limit_bps`, `price_floor_minor` per offering (from PR-8)
