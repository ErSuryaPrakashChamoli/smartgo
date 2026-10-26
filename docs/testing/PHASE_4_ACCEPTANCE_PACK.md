# SmartGro — Phase 4 Moderated Acceptance Pack

| | |
|---|---|
| Status | **Ready for the owner to schedule (2026-10-26).** Not yet run. |
| Purpose | Evidence for Phase 4 acceptance: AC-4.1 (FR-CMS-1 to FR-CMS-8), AC-4.2 (UJ-7 and UJ-8 completed unaided in a moderated test) and AC-4.3 (no routine CMS task needs code, JSON or SQL). `PROJECT_MASTER_PLAN.md` §2, Phase 4. |
| Who runs it | The owner (or someone the owner names) moderates. Participants are the real people who will use the back office. |
| Where | The owner's local environment or a demo environment the owner chooses. Staging is deferred (OD-79), so nothing here is public. |
| Related | `../phases/PHASE_STATUS.md` (Phase 4), `../decisions/DECISION_REGISTER.md` (OD-94 to OD-100), `../security/SECURITY_MODEL.md` §11.6, `../requirements/INFORMATION_ARCHITECTURE.md` (UJ-7, UJ-8) |

---

## 1. What this session tests, and what it does not

**Tests:** whether the people who will run the website can create, review, approve, publish and correct website content on their own, and whether the rules on who may do what hold in practice.

**Does not test** (not built in Phase 4; listed so they are not logged as defects):

| Not available | Why | Decision |
|---|---|---|
| Property and offering profiles, and the photo gallery of a centre | They attach to the property and offering records that Phase 5 builds (step 4.7). UJ-7 is therefore run on a **page with image sections** here, and repeated on a real centre profile in Phase 5. | Dependency recorded in PHASE_STATUS, step 4.7 |
| Location list and enquiry form sections | Need Phase 5 data and the Phase 6 enquiry forms. | FR-CMS-1 (partial) |
| Taking published content down (unpublishing) | Not built; the owner decides who may do it and how (needed before real testimonials go live). | OD-98 (open) |
| Analytics and advertising code on the website | Tracking IDs can be approved, but nothing is added to the website until cookie consent is decided. | OD-100 (open) |
| Editing the logo and design colours | Needs SVG sanitising and contrast checks. | OD-99 |
| A side-by-side comparison of two versions | The approver reviews the preview and the editor's fields. Note in the defect log whether this was enough. | UJ-8 |
| 404 monitor for missing addresses | Not built. | FR-SEO-4 (partial) |

---

## 2. Before the session

### 2.1 People and accounts

Each participant uses **their own account**. Accounts are never shared: the rules compare people by account (SECURITY_MODEL §11.2).

| Code | Role | Used for |
|---|---|---|
| P1 | Marketing Manager | Author (UJ-7) |
| P2 | Marketing Manager (a different person) | Approver (UJ-8) |
| P3 | Super Admin | Publisher and second checker |
| P4 | Finance | Legal entity details |
| P5 | Sales/CRM Executive (or any role without website access) | Access refusals |

A Super Admin creates the accounts in **Settings → Staff users**. Each person signs in once beforehand to set up two-step login.

### 2.2 Environment (whoever sets it up)

1. `php artisan migrate`
2. `php artisan access:sync` (gives each role its permissions; audited)
3. `npm run build`
4. Keep `php artisan schedule:work` running in a terminal, so scheduled publication happens (scenario S4).
5. Check that no page, menu or setting from earlier tests is live, so participants start from a clean website.

### 2.3 Material

* Two or three photographs that SmartGro owns or has a licence for, with the licence details to hand. If none are ready, use images clearly labelled "TEST" and choose the matching origin.
* Text the participants write during the session. Mark it "TEST" if it is not meant for the real website. **Do not use invented prices, availability, customer quotes or legal wording.** A testimonial or customer story needs a real person's consent; for the session use a statement given by a colleague who agrees, and record that.

### 2.4 Moderator rules

* Read each task aloud as written. Do not show where to click.
* Ask participants to think aloud.
* Help only when a participant is stuck for more than three minutes or asks to stop. Record every assist; a task with an assist is **not** "unaided".
* Record for each task: completed (yes / with assist / no), time taken, errors, and comments.
* Log every problem in the defect log (§5) straight away, with the scenario and step.

---

## 3. Scenarios

Each scenario lists the participant, the task as read aloud, and what should happen. "Pass" means every expected outcome was seen.

### S1. Prepare images (P1) — UJ-7, FR-CMS-5

**Task:** "Add two photos to the media library with their descriptions and licence details."

| Step | Expected outcome |
|---|---|
| Website → Media library → **Upload image** | Upload form opens. |
| Try to save a non-decorative image without alt text | Refused, with a message on the alt text field. |
| Choose origin "Licensed stock" and leave the licence holder empty | Refused until the licence holder and reference are filled. |
| Choose origin "AI concept" for a test image | Saved; the image will carry a "Concept visual" label wherever it appears. |
| Save both images | Both appear in the list. The origin cannot be changed afterwards. |

### S2. Build and submit a page (P1) — UJ-7, FR-CMS-1, FR-CMS-3

**Task:** "Create a page called 'TEST About our spaces' with an introduction, a text section, one of the photos, a figures section and a call to action. Preview it, then send it for approval."

| Step | Expected outcome |
|---|---|
| Website → Pages → **New page** | Editor opens; nothing is live. |
| Add sections and reorder them by dragging | Order changes; no code, JSON or HTML needed (AC-4.3). |
| Add an image section and pick a photo without alt text | Cannot be submitted until alt text is given or "decorative" is ticked. |
| Add a figures section | "Where these figures come from" must be filled before submission; it is not shown on the website. |
| **Save draft**, then **Preview** | Preview opens in a new tab, marked as a preview, not indexed by search engines. |
| **Submit for approval** | Status "Submitted for approval"; the fields can no longer be changed. |

### S3. Review, reject, revise and approve (P2, then P1) — UJ-8, FR-CMS-2

**Task for P2:** "Find the page waiting for your approval, check it and reject it with a note asking for one change."

| Step | Expected outcome |
|---|---|
| P2 opens the **Dashboard** | "Waiting for approval" lists the page with its author. |
| Opens it, previews it | Preview matches what P1 built. |
| **Reject** without a note | Refused: a note is required. |
| **Reject** with a note | Status "Rejected"; the note is shown. |

**Task for P1:** "Make the change asked for and send it again." Expected: **Revise the rejected version** starts a new draft with the note visible; resubmitting works.

**Task for P2:** "Approve it." Expected: approved. **P2 sees "Publish now" and "Schedule" disabled, with the reason** (the approver never publishes, OD-65).

**Check:** P1 cannot approve their own version (button disabled with the reason).

### S4. Publish and schedule (P1 or P3) — UJ-8, FR-CMS-2

**Task:** "Put the approved page live now." Then, on a second change: "Schedule the next version for five minutes from now, then cancel the schedule and schedule it again."

| Step | Expected outcome |
|---|---|
| **Publish now** | The page is live at its address within 60 seconds (UJ-8). |
| Version history | Shows who wrote, approved and published each version, and when. |
| **Schedule**, **Cancel schedule**, **Schedule** again | Times shown in India Standard Time; the version goes live at the scheduled minute without anyone acting. |

### S5. Change an address and restore an old version (P1, P2, P3) — BR-CMS-6, BR-CMS-3

**Task:** "Change the page's address and publish the change. Then bring back the previous wording."

| Step | Expected outcome |
|---|---|
| New address published | Visiting the old address takes you to the new one (301). Website → Site setup → Redirects lists it as "Page address change". |
| Version history → **Restore** on an earlier version | A new draft is created from it; it still needs approval and publication. The old version is unchanged. |

### S6. Menus (P1, P2, P3) — FR-CMS-4

**Task:** "Add the new page to the main menu and to a footer column."

| Step | Expected outcome |
|---|---|
| Website → Site setup → Navigation | Pages are picked from a list; no address is typed for pages on this website. |
| Approve (P2) and publish (P1 or P3) | The header and footer show the links. Renaming the page's address later does not break them. |

### S7. Article, FAQ, testimonial and customer story (P1, P2, P3) — FR-CMS-7

**Task:** "Write an article, an FAQ and a testimonial, and put them on the website."

| Step | Expected outcome |
|---|---|
| Website → Insights → **New article**, then approve and publish | Appears at /insights and at its own address. |
| Website → FAQs → **New FAQ**, approve, publish | Appears at /faqs in its set position. |
| Website → Testimonials → **New testimonial** without ticking consent | Draft saves, but **Submit** is refused until consent, its reference and "statement is real" are given (BR-CMS-7). |
| Complete, approve, publish, then add a testimonial section to a page | The quote appears on that page; it cannot be typed into the page directly. |
| Customer stories (optional) | Same consent rule; appears at /customers. |

### S8. Legal page (P1, P2, P3) — FR-CMS-7

**Task:** "Create a legal page using the legal layout." Use placeholder text marked TEST; real legal wording comes from counsel (D-15).

| Step | Expected outcome |
|---|---|
| Layout "Legal page" | Only text sections can be added. |
| Submit without a legal review reference | Refused. |
| Publish with a reference | The page shows "Last updated" with the publication date. A later version starts without the reference. |

### S9. Contact details and social links (P1, P2, P3) — FR-CMS-8, BR-CMS-9

**Task:** "Add SmartGro's phone number, email and LinkedIn page, and put them on the website."

| Step | Expected outcome |
|---|---|
| Website → Site setup → Settings → Contact details | Wrong formats are refused with a clear message (for example a WhatsApp number without the country code). |
| Social links: a link to another website, or an http link | Refused. |
| Approve (P2), publish (P1 or P3) | The footer shows the details and links. Until then it shows none. |
| Tracking IDs (optional) | Can be approved and published, but nothing appears in the website's code (OD-100). |

### S10. Legal entity details (P4, P3) — OD-54, OD-55

**Task for P4:** "Enter SmartGro's legal name, registered address and GSTIN and send them for verification." **Task for P3:** "Verify them."

| Step | Expected outcome |
|---|---|
| P4 opens Website → Site setup → Settings | Only "Legal entity details" is listed. |
| Invalid GSTIN | Refused with the expected format. |
| P4 tries to verify their own entry | "Verify" is disabled with the reason. |
| P3 verifies; P3 tries to put it in use | Disabled: the verifier cannot. P4 puts it in use. |

### S11. Manual redirects (P1, P2, P3) — FR-SEO-4, FR-SEO-6, OD-66

**Task:** "Redirect the old address /test-old-page to the page from S2, and answer 'gone' for every address starting with /test-pin/."

| Step | Expected outcome |
|---|---|
| Website → Site setup → Redirects → **Manual redirects** | Editor with one row per redirect. |
| New address `https://example.com` | Refused: only addresses on this website. |
| Old address that a page already uses, or a redirect that points at another redirect | Refused with an explanation (no dead rules, chains or loops). |
| Approve (P2), publish (P1 or P3), visit the addresses | /test-old-page redirects in one step; /test-pin/anything answers 410. |

### S12. Access refusals (P5, P4) — SECURITY_MODEL §3.1

| Step | Expected outcome |
|---|---|
| P5 signs in and tries Website → Pages | Not available or refused. |
| P4 tries Website → Pages | Not available or refused; only legal details are visible. |

---

## 4. Measures

| Measure | Target |
|---|---|
| UJ-7 (S1 and S2) by P1 | Completed **unaided** (AC-4.2) |
| UJ-8 (S3 and S4) by P2 with P1 or P3 publishing | Completed **unaided** (AC-4.2) |
| Every scenario | No step needed code, JSON or SQL (AC-4.3) |
| Every expected refusal | Shown with a reason the participant understood |
| Time | Recorded for information; no target set |

---

## 5. Defect log

Severity: **Blocker** (stops a task or breaks a rule), **Major** (task completed only with help, or a rule is unclear), **Minor** (slows the task), **Cosmetic** (looks wrong, no effect on the task).

| ID | Scenario and step | Severity | What happened | Expected | Found by | Status |
|---|---|---|---|---|---|---|
| KD-1 | Any short public page, wide screen | Cosmetic | The page background shows below the footer when the page is shorter than the window. Found before the session. | The footer sits at the bottom of the window. | Claude (screenshot check) | Open |
| | | | | | | |
| | | | | | | |
| | | | | | | |

---

## 6. Sign-off checklist

### 6.1 AC-4.1: FR-CMS-1 to FR-CMS-8

Automated evidence: the full test suite (634 tests on 2026-10-26, all passing). The session adds the human evidence.

| Requirement | Built | Automated evidence | Session | Status before the session |
|---|---|---|---|---|
| FR-CMS-1 Block library | Introduction, text, image, feature grid, figures, comparison table, FAQs, testimonial, call to action | `PagePayloadTest`, `StatsAndComparisonBlocksTest`, `PageControllerTest` | S2 | **Partial:** location list (Phase 5) and form embed (Phase 6) not built |
| FR-CMS-2 Workflow, rejection, scheduling | Yes, for every content type | `RevisionWorkflowTest`, `EditPageTest`, `ContentPublishingConcurrencyTest` | S3, S4 | Ready |
| FR-CMS-3 Signed preview | Yes, `noindex`, expires after 30 minutes | `RevisionPreviewControllerTest` | S2, S3 | Ready |
| FR-CMS-4 Navigation, header and footer | Yes | `NavigationWorkflowTest`, `EditNavigationMenuTest`, `PublicShellTest` | S6 | Ready |
| FR-CMS-5 Media library | Yes; licence expiry hides images | `MediaLibraryTest`, `MediaLibraryScreensTest` | S1 | **Partial:** blocking concept images from centre galleries comes with the galleries (Phase 5) |
| FR-CMS-6 Location and offering profiles | No | none | none | **Not built:** depends on Phase 5 (step 4.7) |
| FR-CMS-7 Insights, FAQs, testimonials, customer stories, legal pages | Yes | `ContentTypesWorkflowTest`, `PublishedContentControllerTest`, `ContentTypeScreensTest` | S7, S8 | **Partial:** FAQ location and product scopes (Phase 5); unpublishing open (OD-98) |
| FR-CMS-8 Brand settings | Contact details, social links, tracking IDs, legal details, manual redirects | `SiteSettingsWorkflowTest`, `RedirectMapWorkflowTest`, `SettingsAndRedirectsScreensTest` | S9 to S11 | **Partial:** design tokens and logo (OD-99); tracking not output (OD-100) |

### 6.2 AC-4.2 and AC-4.3

| Item | Result | Notes |
|---|---|---|
| P1 completed UJ-7 (S1, S2) unaided | ☐ Yes ☐ No | |
| P2 completed UJ-8 (S3, S4) unaided | ☐ Yes ☐ No | |
| No task needed code, JSON or SQL | ☐ Yes ☐ No | |
| Every refusal in S2, S3, S7 to S12 behaved as expected | ☐ Yes ☐ No | |
| No Blocker or Major defect is open | ☐ Yes ☐ No | |

### 6.3 Owner decisions needed for full acceptance

| Decision | Needed for |
|---|---|
| OD-98: who may take content down, and how | Before the first real testimonial or customer story goes live |
| OD-100: cookie consent approach (with counsel) | Before any analytics or advertising code |
| Accept FR-CMS-1, FR-CMS-5, FR-CMS-6, FR-CMS-7 and FR-CMS-8 as partial, with the remainder moved to Phase 5, Phase 6 or a later step | Closing Phase 4 |

### 6.4 Sign-off

| Role | Name | Decision | Date |
|---|---|---|---|
| Owner | | ☐ Accept ☐ Accept with conditions ☐ Reject | |
| Moderator | | | |
