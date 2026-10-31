# SmartGro — Report and KPI Definitions (Phase 12)

| | |
|---|---|
| Status | **For the owner's review; not approved.** Phase 12 is in progress and not accepted. |
| Screens | Back office → Reports → **Enquiries and tours** (`leads.reports.view`) and **Content freshness** (`content.view`) |
| Sources | PRD FR-AN-1, FR-AN-2; PROJECT_MASTER_PLAN Phase 12 (AC-12.1, AC-12.2); DECISION_REGISTER OD-100, OD-132 (a, b), OD-135 |

Every figure below is a count of records SmartGro already holds. None uses visitor tracking (OD-100), and none applies a target or threshold.

## 1. Implemented and checked on the test database

**Period.** Whole days in India time, from 00:00 on the first day to the end of the last. A period may be up to 366 days, and the first day can't be after the last. "Workload now" ignores the period.

| Figure | What it counts | Checked by (`tests/Feature/Services/Reports/EnquiryReportTest.php`) |
|---|---|---|
| Enquiries received | Enquiries created in the period, whatever their status now. Enquiries are never deleted, and spam is never stored. | Equal to an independent query, including at the first and last second of the period |
| Over time | Per India day for periods up to 31 days; otherwise per week, Monday to Sunday. Days and weeks with no enquiries show 0. | Totals equal "received"; correct with another time zone and language set (OD-135) |
| By status now | The status today: New, Contacted, Qualified, Lost, Disqualified | Independent query |
| By source | Website enquiry form, website tour request, city waitlist | Expected counts |
| By location | The location chosen, or "No location chosen" | Independent query, with and without the location filter |
| By campaign | `utm_source` from the address the visitor arrived on, or "No campaign recorded" | Expected counts |
| Led to a tour | Enquiries received in the period that have at least one tour, of any status (shown only to staff who can see tours) | Expected count |
| Time to first contact | From receipt to the earliest **call, email, WhatsApp or meeting** recorded on the enquiry. Shows the median and the 90th percentile (nearest rank), in minutes. | Worked example: 30, 90 and 600 minutes give a median of 90 and a 90th percentile of 600 |
| Not contacted yet | Enquiries received in the period with no such contact. A note, a follow-up or a status change on its own is not a contact. | Same example |
| Unassigned open enquiries | Status New, Contacted or Qualified, with no owner | Independent query |
| Follow-ups overdue | Open follow-ups whose due time has passed | Independent query |
| Follow-ups due later today | Open follow-ups due between now and midnight, India time | 23:59:59 counts; 00:00 the next day does not |
| Tours requested or arranged | Tours created in the period, by status now, by channel (website or staff) and by location | Independent queries, including the period's edges |
| Tours taking place | Tours starting in the period, by status | Independent queries |
| Content freshness | Everything live on the website, with when its current version was published, oldest first | Lists only published content |

**Who sees what.** Super Admin, Operations Manager and Marketing Manager see every property; a Property Manager sees their own properties. A Sales/CRM Executive sees their assigned locations, or every property if recorded as working on all of them. Finance sees no enquiry report. Tour figures are visible only to staff who can view tours. Enquiries with no location count only for staff who work on every property. No report shows a name, email, phone number or message.

**Speed.** The report runs the same number of queries for 2,000 more enquiries (`EnquiryReportTest`, `QueryGrowthTest`).

## 2. Choices made while building, for the owner to confirm

These are working defaults. None has been approved as a business definition.

1. Days are counted in India time; weeks run Monday to Sunday; periods over 31 days switch to weekly totals.
2. Only a recorded call, email, WhatsApp or meeting counts as first contact. **The enquiry list's "First response" column differs:** it is also set when someone only changes the status. Should both use one definition?
3. The 90th percentile uses the nearest-rank method.
4. "Led to a tour" includes tours later cancelled or not attended.
5. Overdue follow-ups include those on enquiries already Lost or Disqualified. Should closed enquiries be left out?
6. "Campaign" is `utm_source` only. Medium, campaign name, term and content are stored but not reported.
7. Content freshness labels nothing as stale, because no review period has been set.

## 3. Not built: needs an owner decision or other work

| FR-AN-2 figure | Status | Waiting on |
|---|---|---|
| Visits, the top of the funnel | Not built | OD-100 (no tracking), the consent approach (D-18), L-4 |
| Deals and deal values | Not built | Deals (FR-CRM-4) |
| Response target and compliance | Not built; only observed times are shown | Response targets (D-09) |
| Occupancy and availability by location | Not built | The real-inventory entry gate; public availability (A-8) |
| Consent-aware tag loading (FR-AN-1) | Not built; tracking IDs are stored but never output | OD-100, D-18, L-4 |

## 4. Readiness for review

* **Ready for the owner to review:** the definitions in sections 1 and 2, on the test database. Browser evidence for the report screens (OD-135): `../testing/evidence/phase14/2026-10-29-od135/checks-admin/` (0 axe violations).
* **Not possible here:** checking the totals against real enquiries, because the inventory entry gate is closed and no real data exists. AC-12.1 (FR-AN-1 and FR-AN-2 pass) waits on section 3.
