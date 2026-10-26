# SmartGro — Phase 4 Moderated Acceptance Session Plan

| | |
|---|---|
| Status | **Ready to schedule once the preconditions in §2 are met (updated 2026-10-26, round 33).** Not yet run. No scenario has a result yet. Phase 4 is **not** accepted. |
| Purpose | Human evidence for Phase 4 acceptance: AC-4.1 (FR-CMS-1 to FR-CMS-8), AC-4.2 (UJ-7 and UJ-8 completed unaided in a moderated test) and AC-4.3 (no routine CMS task needs code, JSON or SQL). `PROJECT_MASTER_PLAN.md` §2, Phase 4. |
| Who runs it | The owner, or someone the owner names, moderates. Participants are the real people who will use the back office. A second person takes notes if possible. |
| Where | The owner's local environment or a demo environment the owner chooses. Staging is deferred (OD-79), so nothing in this session is public. |
| Related | `../phases/PHASE_STATUS.md` (Phase 4), `../decisions/DECISION_REGISTER.md` (OD-94 to OD-101, §0.31, §0.32), `../security/SECURITY_MODEL.md` §11.6 and §11.7, `../requirements/INFORMATION_ARCHITECTURE.md` (UJ-7, UJ-8) |

**Automated tests do not replace this session.** A passing test suite shows that the rules are coded; only the session shows that people can use them unaided. No scenario may be marked as passed unless it was observed in the session.

---

## 1. What the session covers

| Covered | Scenarios |
|---|---|
| UJ-7 (author prepares content), interim version on a page | S1, S2 |
| UJ-8 (second person approves; someone else publishes) | S3, S4 |
| Address changes, restoring, menus | S5, S6 |
| Articles, FAQs, testimonials, customer stories, legal pages | S7, S8 |
| Contact details, social links, legal entity details, manual redirects | S9, S10, S11 |
| Access refusals | S12 |
| Taking a testimonial or customer story off the website (OD-98) | S13 (needs a commit that includes OD-98, precondition 2.10) |
| Tracking stays disabled (OD-100) | S14 |

**Not covered** (not built, or built only in part; these are not defects):

| Not available | Why | Reference |
|---|---|---|
| Property and offering profiles, and a centre's photo gallery | They attach to the property and offering records Phase 5 builds (step 4.7). No stand-in records are created. UJ-7 is run on a **page with image sections** here and **must be repeated against a real centre profile in Phase 5**. | PHASE_STATUS, step 4.7 |
| Location list and enquiry form sections | Need Phase 5 data and Phase 6 forms. | FR-CMS-1 |
| Analytics and advertising code | Disabled until the consent approach is agreed with counsel. | OD-100, SECURITY_MODEL §11.7 |
| Recording that consent was withdrawn, and forcing new consent before republishing | The system cannot yet tell withdrawn consent from valid consent; reviewers must check. | OD-101 (open) |
| Taking down pages, articles, FAQs or other content | Not in the approved scope. | OD-98 |
| Editing the logo and design colours | Needs SVG sanitising and contrast checks. | OD-99 |
| Side-by-side comparison of two versions | The approver uses the preview and the editor. Record in the notes whether this was enough. | UJ-8 |
| 404 monitor | Not built. | FR-SEO-4 |

---

## 2. Preconditions and environment

Complete this checklist on the day, before participants arrive. If an item fails, fix it or reschedule.

| # | Check | Done |
|---|---|---|
| 2.1 | The code is the agreed version: branch `main`, commit recorded here: ________ | ☐ |
| 2.2 | The database is a **dedicated session database** (for example `smartgro_uat`), never production, and not the development database if its data matters. `.env` points at it. | ☐ |
| 2.3 | `php artisan migrate` has run on that database. | ☐ |
| 2.4 | `php artisan access:sync` has run (roles get their permissions; audited). | ☐ |
| 2.5 | `npm run build` has run. | ☐ |
| 2.6 | No stale caches: `bootstrap/cache` holds no `config.php`, `routes-v7.php` or `events.php` from another setup. If they exist, run `php artisan config:clear`, `php artisan route:clear` and `php artisan event:clear`. Caches have been written by an unidentified process on this machine before. | ☐ |
| 2.7 | `php artisan schedule:work` is running in a terminal for the whole session (needed for S4). | ☐ |
| 2.8 | The website starts clean: no page, menu, setting or redirect from earlier tests is live. | ☐ |
| 2.9 | The full automated suite passed on the agreed commit (record the count): ________ | ☐ |
| 2.10 | The agreed commit includes the OD-98 take-down feature (built locally on 2026-10-26, not yet committed; committing it needs the owner's authorisation) and its automated tests pass. Otherwise S13 is marked **Blocked**. | ☐ |
| 2.11 | A browser with developer tools (Chrome, Edge or Firefox) is available for S14. | ☐ |
| 2.12 | Screen recording or screenshots are allowed by every participant. | ☐ |

**Material:**
* Two or three photographs SmartGro owns or has a licence for, with the licence details. If none are ready, use images clearly labelled "TEST" with the matching origin.
* Text participants write in the session. Mark it "TEST" unless it is meant for the real website. **Do not use invented prices, availability, customer quotes or legal wording.** For the testimonial in S7 and S13, use a statement from a colleague who agrees, and record that agreement.
* Tracking IDs for S14: use made-up IDs in the right format (for example `G-TEST123456`), never live ones.

---

## 3. Accounts and permissions

Each participant uses **their own account**; accounts are never shared, because the rules compare people by account (SECURITY_MODEL §11.2).

| Code | Person | Role | Needs | Set up and checked |
|---|---|---|---|---|
| P1 | ________ | Marketing Manager | Author of UJ-7 | ☐ |
| P2 | ________ (not P1) | Marketing Manager | Approver of UJ-8 | ☐ |
| P3 | ________ | Super Admin | Publisher, verifier of legal details | ☐ |
| P4 | ________ | Finance | Legal entity details | ☐ |
| P5 | ________ | Sales/CRM Executive (or another role without website access) | Access refusals | ☐ |

Set-up steps:
1. A Super Admin creates each account in **Settings → Staff users** with the role above.
2. Each person signs in once before the session and completes two-step login.
3. Check what each person sees, before the session:
   * P1, P2: **Website** with Pages, Media library, Insights, Customer stories, Testimonials, FAQs and Site setup (Navigation, Settings without legal details, Redirects).
   * P3: everything P1 sees, plus legal details under Settings, and Settings → Staff users.
   * P4: **Website → Site setup → Settings** with only "Legal entity details".
   * P5: no Website section.
4. If anything differs, run `php artisan access:sync` again and recheck. Do not grant individual permissions by hand.

---

## 4. Running the session

* Read each task aloud as written. Do not show where to click.
* Ask participants to think aloud.
* Help only when a participant is stuck for more than three minutes or asks to stop. Record every assist; a task with an assist is **not unaided**.
* Run the scenarios in order: later ones use content from earlier ones.
* Record results as you go (§6). Log every problem in the defect log (§8) straight away.

---

## 5. Scenarios and expected outcomes

Each step has an expected outcome. Record the actual result in the results sheet (§6) using the scenario and step number, for example **S2.4**.

### S1. Prepare images (P1) — UJ-7, FR-CMS-5

**Task:** "Add two photos to the media library with their descriptions and licence details."

| Step | Action | Expected outcome |
|---|---|---|
| S1.1 | Website → Media library → **Upload image** | Upload form opens. |
| S1.2 | Try to save a non-decorative image without alt text | Refused, with a message on the alt text field. |
| S1.3 | Origin "Licensed stock", licence holder empty | Refused until the licence holder and reference are filled. |
| S1.4 | Origin "AI concept" for a test image | Saved; the image will carry a "Concept visual" label wherever it appears. |
| S1.5 | Save both images | Both appear in the list. The origin cannot be changed afterwards. |

### S2. Build and submit a page (P1) — UJ-7, FR-CMS-1, FR-CMS-3

**Task:** "Create a page called 'TEST About our spaces' with an introduction, a text section, one of the photos, a figures section and a call to action. Preview it, then send it for approval."

| Step | Action | Expected outcome |
|---|---|---|
| S2.1 | Website → Pages → **New page** | Editor opens; nothing is live. |
| S2.2 | Add sections and reorder them by dragging | Order changes; no code, JSON or HTML needed (AC-4.3). |
| S2.3 | Image section with a photo, alt text left empty | Cannot be submitted until alt text is given or "decorative" is ticked. |
| S2.4 | Figures section | "Where these figures come from" must be filled before submission; it is not shown on the website. |
| S2.5 | **Save draft**, then **Preview** | Preview opens in a new tab, marked as a preview, not indexed by search engines. |
| S2.6 | **Submit for approval** | Status "Submitted for approval"; the fields can no longer be changed. |

### S3. Review, reject, revise and approve (P2, then P1) — UJ-8, FR-CMS-2

**Task for P2:** "Find the page waiting for your approval, check it and reject it with a note asking for one change."

| Step | Action | Expected outcome |
|---|---|---|
| S3.1 | P2 opens the **Dashboard** | "Waiting for approval" lists the page with its author. |
| S3.2 | Opens it and previews it | Preview matches what P1 built. |
| S3.3 | **Reject** without a note | Refused: a note is required. |
| S3.4 | **Reject** with a note | Status "Rejected"; the note is shown. |
| S3.5 | P1: "Make the change asked for and send it again." | **Revise the rejected version** starts a new draft with the note visible; resubmitting works. |
| S3.6 | P1 tries to approve their own version | Button disabled, with the reason. |
| S3.7 | P2: "Approve it." | Approved. P2 sees **Publish now** and **Schedule** disabled, with the reason (OD-65). |

### S4. Publish and schedule (P1 or P3) — UJ-8, FR-CMS-2

**Task:** "Put the approved page live now." Then, on a second change: "Schedule the next version for five minutes from now, cancel the schedule and schedule it again."

| Step | Action | Expected outcome |
|---|---|---|
| S4.1 | **Publish now** | The page is live at its address within 60 seconds (UJ-8). |
| S4.2 | Open the version history | Shows who wrote, approved and published each version, and when. |
| S4.3 | **Schedule**, **Cancel schedule**, **Schedule** again | Times shown in India Standard Time; the version goes live at the scheduled minute without anyone acting. |

### S5. Change an address and restore an old version (P1, P2, P3) — BR-CMS-6, BR-CMS-3

**Task:** "Change the page's address and publish the change. Then bring back the previous wording."

| Step | Action | Expected outcome |
|---|---|---|
| S5.1 | Publish a new address | The old address takes you to the new one (301). Website → Site setup → Redirects lists it as "Page address change". |
| S5.2 | Version history → **Restore** an earlier version | A new draft is created from it; it still needs approval and publication. The old version is unchanged. |

### S6. Menus (P1, P2, P3) — FR-CMS-4

**Task:** "Add the new page to the main menu and to a footer column."

| Step | Action | Expected outcome |
|---|---|---|
| S6.1 | Website → Site setup → Navigation | Pages are picked from a list; no address is typed for pages on this website. |
| S6.2 | Approve (P2), publish (P1 or P3) | Header and footer show the links. Changing the page's address later does not break them. |

### S7. Article, FAQ, testimonial and customer story (P1, P2, P3) — FR-CMS-7

**Task:** "Write an article, an FAQ and a testimonial, and put them on the website."

| Step | Action | Expected outcome |
|---|---|---|
| S7.1 | Website → Insights → **New article**, approve, publish | Appears at /insights and at its own address. |
| S7.2 | Website → FAQs → **New FAQ**, approve, publish | Appears at /faqs in its set position. |
| S7.3 | Website → Testimonials → **New testimonial** without consent | Draft saves, but **Submit for approval** is refused until consent, its reference and "statement is real" are given (BR-CMS-7). |
| S7.4 | Complete, approve, publish; add a testimonial section to a page and publish the page | The quote appears on that page; it cannot be typed into the page directly. |
| S7.5 | Customer story (optional) | Same consent rule; appears at /customers. |

### S8. Legal page (P1, P2, P3) — FR-CMS-7

**Task:** "Create a legal page using the legal layout." Use placeholder text marked TEST; real legal wording comes from counsel (D-15).

| Step | Action | Expected outcome |
|---|---|---|
| S8.1 | Layout "Legal page" | Only text sections can be added. |
| S8.2 | Submit without a legal review reference | Refused. |
| S8.3 | Publish with a reference | The page shows "Last updated" with the publication date. A later version starts without the reference. |

### S9. Contact details and social links (P1, P2, P3) — FR-CMS-8, BR-CMS-9

**Task:** "Add SmartGro's phone number, email and LinkedIn page, and put them on the website."

| Step | Action | Expected outcome |
|---|---|---|
| S9.1 | Website → Site setup → Settings → Contact details, wrong formats | Refused with a clear message (for example a WhatsApp number without the country code). |
| S9.2 | Social link to another website, or an http link | Refused. |
| S9.3 | Approve (P2), publish (P1 or P3) | The footer shows the details and links. Until then it shows none. |

### S10. Legal entity details (P4, P3) — OD-54, OD-55

**Task for P4:** "Enter SmartGro's legal name, registered address and GSTIN and send them for verification." **Task for P3:** "Verify them."

| Step | Action | Expected outcome |
|---|---|---|
| S10.1 | P4 opens Website → Site setup → Settings | Only "Legal entity details" is listed. |
| S10.2 | Invalid GSTIN | Refused with the expected format. |
| S10.3 | P4 tries to verify their own entry | **Verify** is disabled, with the reason. |
| S10.4 | P3 verifies, then tries to put it in use | P3 cannot put it in use; P4 can. |

### S11. Manual redirects (P1, P2, P3) — FR-SEO-4, FR-SEO-6, OD-66

**Task:** "Redirect the old address /test-old-page to the page from S2, and answer 'gone' for every address starting with /test-pin/."

| Step | Action | Expected outcome |
|---|---|---|
| S11.1 | Website → Site setup → Redirects → **Manual redirects** | Editor with one row per redirect. |
| S11.2 | New address `https://example.com` | Refused: only addresses on this website. |
| S11.3 | Old address a page already uses, or a target that is itself redirected | Refused with an explanation (no dead rules, chains or loops). |
| S11.4 | Approve (P2), publish (P1 or P3), visit the addresses | /test-old-page redirects in one step; /test-pin/anything answers 410. |

### S12. Access refusals (P5, P4) — SECURITY_MODEL §3.1

| Step | Action | Expected outcome |
|---|---|---|
| S12.1 | P5 signs in and looks for Website → Pages | Not shown; opening its address directly is refused. |
| S12.2 | P4 looks for Website → Pages | Not shown or refused; only legal details are visible. |

### S13. Take a testimonial and a customer story off the website (P2 or P3; P1; P5) — OD-98

**Blocked unless precondition 2.10 is met.** If blocked, record "Blocked: OD-98 not in the tested commit" for every step; this is not a pass.

**Set-up by the moderator:** the testimonial from S7 is live and shown on a page; one further version of it is **approved and scheduled** for tomorrow (so S13.4 can be observed); a customer story is live.

**Task for P2 or P3:** "The person in the published testimonial has withdrawn consent. Take the testimonial off the website now." Then: "Do the same for the customer story."

| Step | Action | Expected outcome |
|---|---|---|
| S13.1 | Website → Testimonials → open the live testimonial | **Take off the website** is shown (red), next to the workflow buttons. |
| S13.2 | Choose it and confirm without a reason | Refused: a reason is required. The testimonial stays live. |
| S13.3 | Confirm with a reason, for example "Consent withdrawn by email on [date]" | Notification "Testimonial taken off the website". The editor says "Taken off the website" with the date, the person and the reason, and warns not to put it back until consent has been given again and recorded. In the list it shows **Taken down**. |
| S13.4 | Open the version history | The version that was live is "Previously published"; the approved, scheduled version is now **Rejected**, with the note "Sent back automatically: the testimonial was taken off the website. Reason: …", and is no longer scheduled. Every version's content is still there. |
| S13.5 | Visit the page that showed the quote, in a private browser window | The quote is gone at once. |
| S13.6 | Version history → **Activity** on the previously published version; Settings → Audit log (P3) | An entry "Testimonial taken off the website" with who, when and the reason; the returned version has its own entry. |
| S13.7 | Wait until after the cancelled scheduled time (or ask P3 to check the next day) | The returned version did not go live. |
| S13.8 | P1 opens a live customer story and takes it off the website with a reason | Allowed: any holder of `testimonials.publish` (every Marketing Manager and Super Admin) may take it down, including whoever approved it. /customers and the story's own address no longer show it (the address answers "not found"). |
| S13.9 | P5 tries to open Website → Testimonials | Not shown or refused, so no take-down is possible. |
| S13.10 | Open an article or FAQ editor | No **Take off the website** button (only testimonials and customer stories have it). |
| S13.11 | "Put the customer story back." | Only possible by starting a new draft (or revising or restoring a version), submitting it, having someone else approve it, and publishing it. |
| S13.12 | **Observation, not a pass criterion (OD-101 open):** in S13.11, note whether the new draft already has consent ticked with the old reference | Expected today: yes, it does. The system does not yet force new consent after a withdrawal; the approver must check. Record what the participants did. |

### S14. Tracking stays disabled (moderator, with P1, P2, P3) — OD-100

**Task for P1, P2, P3:** enter, approve and publish **made-up** IDs in Website → Site setup → Settings → Tracking IDs (for example `G-TEST123456`, `GTM-TEST123`, `123456789012`).

**Check by the moderator**, on the home page and any published page, article, customer story and FAQ page:

| Step | Action | Expected outcome |
|---|---|---|
| S14.1 | View the page source and search for each ID | Not found. |
| S14.2 | Search the page source for `googletagmanager`, `google-analytics`, `gtag`, `dataLayer`, `fbq` and `facebook.net` | Not found. |
| S14.3 | Developer tools → Network, reload | Every request goes to the site's own address. Nothing goes to Google, Meta or any other provider. |
| S14.4 | Developer tools → Application → Cookies | Only the site's own cookies: its session cookie (named after the application, for example `laravel-session`) and `XSRF-TOKEN`. No `_ga`, `_gid`, `_fbp` or other provider cookie. |

Approval of an ID is not permission to run its script (OD-100). Any provider request or script in S14 is a **Blocker**.

---

## 6. Recording results and evidence

**Results sheet** (one row per step; copy into a spreadsheet if easier):

| Step | Participant | Result (Pass / Fail / Blocked / Not run) | Unaided? (Yes / Assist ×n / No) | Time | Evidence | Notes and defect IDs |
|---|---|---|---|---|---|---|
| S1.1 | | | | | | |
| … | | | | | | |

**Evidence:**
* A screenshot for every Fail, every refusal step and every step marked with a requirement (FR-CMS-x) in the scenario heading. Name files `S<scenario>.<step>-P<n>-<short-note>.png`, for example `S3.7-P2-publish-disabled.png`.
* Screenshots or a screen recording of S14.3 (network list) and S14.4 (cookies).
* An export or screenshot of **Settings → Audit log** at the end, covering the session's time window.
* Keep the evidence in one folder named with the session date, and record its location in §9.

---

## 7. Severity and pass/fail rules

**Severity:**

| Severity | Meaning | Effect on acceptance |
|---|---|---|
| Blocker | A task cannot be completed, or a rule is broken (for example an approver can publish their own approval, an unapproved change goes live, or a tracking script runs) | Phase 4 cannot be accepted while open |
| Major | Completed only with help, or a rule's message is so unclear the participant could not act on it | Phase 4 cannot be accepted while open, unless the owner records an exception |
| Minor | Slows the task or causes a recoverable error | Can be accepted; fixed later |
| Cosmetic | Looks wrong, no effect on the task | Can be accepted; fixed later |

**A step passes** only when its expected outcome was observed in the session. It fails if the outcome differs. It is "Blocked" when a precondition is missing (for example S13 before OD-98 is built).

**A scenario passes** when every step passes and no Blocker or Major defect was raised in it.

**AC-4.2 passes** when P1 completes S1 and S2 **unaided**, and P2 completes S3 and S4 **unaided**, with P1 or P3 publishing. Any assist in these scenarios means AC-4.2 is not met in this session.

**AC-4.3 passes** when no step in any scenario needed code, JSON or SQL.

---

## 8. Defect log, retesting and regression

| ID | Step | Severity | What happened | Expected | Evidence | Found by | Fixed in (commit) | Retested by, date | Retest result | Status |
|---|---|---|---|---|---|---|---|---|---|---|
| KD-1 | Any short public page, wide screen | Cosmetic | The page background shows below the footer when the page is shorter than the window. Seen in screenshot checks on 2026-10-26 (for example a short legal page at 1440 px). Not fixed. | The footer sits at the bottom of the window. | Screenshot check, 2026-10-26 | Claude | | | | Open |
| | | | | | | | | | | |

**When a defect is fixed:**
1. The fix comes with an automated test that fails without it, and the full suite passes. Record the commit in the log.
2. The moderator retests the failing step with the participant who found it, or a person with the same role, and records the result. Only the retest can close the defect.
3. **Regression after any fix:** rerun these steps, as a minimum, and record them in the results sheet as retests:
   * S2.6 (submit), S3.6 and S3.7 (author cannot approve, approver cannot publish), S4.1 (publish), S12 (refusals), S13.2 to S13.5 (take-down, when OD-98 is in the tested commit) and S14 (tracking);
   * every scenario that touches the changed screen or rule.
4. A Blocker or Major defect closed after the session also needs the affected scenario rerun in full, unaided, before sign-off.

---

## 9. Final acceptance checklist

### 9.1 Session record

| Item | Value |
|---|---|
| Date and time | |
| Commit tested | |
| Moderator and note-taker | |
| Evidence folder | |
| Automated suite result on that commit | |

### 9.2 AC-4.1: FR-CMS-1 to FR-CMS-8

Automated evidence, 2026-10-26 (test database only, outside caches bypassed): 637 tests passed for the content of commit `aa7ac7a`; with the uncommitted OD-98 work, 658 tests passed (2,150 assertions). Automated results are not session results: the "Session result" column stays empty until the session is run.

| Requirement | Built | Automated evidence | Scenarios | Status before the session | Session result |
|---|---|---|---|---|---|
| FR-CMS-1 Block library | Introduction, text, image, feature grid, figures, comparison table, FAQs, testimonial, call to action | `PagePayloadTest`, `StatsAndComparisonBlocksTest`, `PageControllerTest` | S2 | **Partial:** location list (Phase 5) and form embed (Phase 6) not built | |
| FR-CMS-2 Workflow, rejection, scheduling | Yes, for every content type | `RevisionWorkflowTest`, `EditPageTest`, `ContentPublishingConcurrencyTest` | S3, S4 | Built | |
| FR-CMS-3 Signed preview | Yes, `noindex`, expires after 30 minutes | `RevisionPreviewControllerTest` | S2, S3 | Built | |
| FR-CMS-4 Navigation, header and footer | Yes | `NavigationWorkflowTest`, `EditNavigationMenuTest`, `PublicShellTest` | S6 | Built | |
| FR-CMS-5 Media library | Yes; licence expiry hides images | `MediaLibraryTest`, `MediaLibraryScreensTest` | S1 | **Partial:** blocking concept images from centre galleries comes with the galleries (Phase 5) | |
| FR-CMS-6 Location and offering profiles | No | none | none | **Not built:** depends on Phase 5 (step 4.7); UJ-7 to be repeated on a real centre | |
| FR-CMS-7 Insights, FAQs, testimonials, customer stories, legal pages | Yes; taking testimonials and customer stories down built locally (OD-98, not yet committed) | `ContentTypesWorkflowTest`, `PublishedContentControllerTest`, `ContentTypeScreensTest`, `UnpublishContentTest`, `TakeDownScreensTest`, `ContentPublishingConcurrencyTest` | S7, S8, S13 | **Partial:** FAQ location and product scopes (Phase 5); withdrawn consent is not recorded as such (OD-101) | |
| FR-CMS-8 Brand settings | Contact details, social links, tracking IDs (stored only), legal details, manual redirects | `SiteSettingsWorkflowTest`, `RedirectMapWorkflowTest`, `SettingsAndRedirectsScreensTest` | S9 to S11, S14 | **Partial:** design tokens and logo (OD-99); tracking disabled by decision (OD-100) | |

### 9.3 AC-4.2, AC-4.3 and the owner's decisions

| Item | Result | Notes |
|---|---|---|
| P1 completed UJ-7 (S1, S2) unaided | ☐ Yes ☐ No | |
| P2 completed UJ-8 (S3, S4) unaided | ☐ Yes ☐ No | |
| No step needed code, JSON or SQL (AC-4.3) | ☐ Yes ☐ No | |
| Every refusal in S1 to S3 and S7 to S12 behaved as expected | ☐ Yes ☐ No | |
| OD-98 committed in the tested version, and S13 passed | ☐ Yes ☐ No ☐ Blocked | |
| OD-101 decided, or the owner accepts reviewer checks of consent until it is built | ☐ Yes ☐ No | |
| S14 passed: no tracking script, request or ID on any public page (OD-100) | ☐ Yes ☐ No | |
| No Blocker or Major defect open | ☐ Yes ☐ No | |
| Retests and regression steps recorded for every fixed defect | ☐ Yes ☐ No ☐ None needed | |
| The owner accepts FR-CMS-1, 5, 6, 7 and 8 as partial, with the remainder moved to Phase 5, Phase 6 or a later step | ☐ Yes ☐ No | |
| UJ-7 on a real centre profile is planned in Phase 5 acceptance | ☐ Yes ☐ No | |

### 9.4 Sign-off

| Role | Name | Decision | Conditions | Date | Signature |
|---|---|---|---|---|---|
| Owner | | ☐ Accept ☐ Accept with conditions ☐ Reject | | | |
| Moderator | | — | | | |
