# CMS demonstration (OD-143), 30 October 2026

Isolated demo server 127.0.0.1:8768 on the `smartgro_demo` database (kit fixtures plus sample content), About page,
driven through the back office in Chromium at 1440 px. Author: marketing@fixture.test; approver: admin@fixture.test.
Afterwards the sample content was published again, so the demo shows no demonstration text.

Third run (edits marked "Demo edit 3"); every step passed:

| # | Step | Result |
|---|------|--------|
| 1 | Author starts a draft and changes the hero heading, the text section's body, the hero button's label and destination (All workspace types → All locations), and the first card's title, then saves | PASS — "Draft saved" |
| 2 | Reload the editor | PASS — heading, body, button label, destination and card all kept |
| 3 | Preview (signed staff link) | PASS — shows every change; button goes to /locations; not indexable |
| 4 | Public /about while the version is a draft | PASS — published version only |
| 5 | Site search for the draft text | PASS — nothing found |
| 6 | Author submits for approval | PASS — "Submitted for approval" |
| 7 | Author tries to approve their own version | PASS — Approve disabled: "You worked on this version, so someone else must review it." |
| 8 | Public /about while waiting for approval | PASS — still the published version |
| 9 | A different person (admin) approves | PASS — "Version approved" |
| 10 | Approver tries to publish what they approved | PASS — Publish now disabled: "You approved this version, so someone else must publish it." |
| 11 | Public /about while approved but unpublished | PASS — still the published version |
| 12 | The author publishes | PASS — "Page published" |
| 13 | Public /about | PASS — new heading, body, button and card shown; button goes to /locations |
| 14 | Signed preview link without a staff session, and with a tampered signature | PASS — both redirect to the staff login |

The first run stopped at step 12 because the script asked the approver to publish (refused by design, step 10); its
version was then published by the author. The second run's failures were in the script (notices read before they
appeared, a search for text that run one had already published); its state checks all passed.

Screenshots: `1-edited.png` (editor with changes), `2-preview.png` (staff preview), `3-published.png` (public page).
