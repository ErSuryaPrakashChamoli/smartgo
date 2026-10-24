# SmartGro — Existing Website Audit (smartgro.space)

| | |
|---|---|
| Date | 2026-10-24 |
| Source | Owner-supplied URL `https://www.smartgro.space/coworking-space/#` (D-19) |
| Method | Read-only HTTP requests: `robots.txt`, `sitemap.xml`, `sitemap_index.xml`, `wp-sitemap.xml`, the supplied page, and two sample URLs fetched as a normal browser and as Googlebot. No login was used and nothing on the site was changed. |
| Use | URL inventory for redirects at launch (Phase 11) and a security warning. **Content on the existing site is not treated as confirmed business fact.** |

---

## 1. Platform (observed)

* WordPress 6.8.11 (generator meta), theme `hello-elementor`, Elementor 4.3.5 + Elementor Pro, ElementsKit Lite, MetForm, a WhatsApp click-to-chat plugin, and Site Kit by Google 1.187.0.
* The supplied page `/coworking-space/` returns 200 with title "Best Coworking Space in Noida | Flexible Offices- smartgro.space" and a self-referencing canonical URL.

---

## 2. 🔴 Security finding: the site shows signs of compromise (SEO-spam cloaking)

| Evidence | Observation |
|---|---|
| `sitemap.xml` | 57 URLs, consistent with a small WordPress site |
| `sitemap_index.xml` and `wp-sitemap.xml` | each a `urlset` (not a sitemap index) of **1,039 URLs**: the home page plus **1,038 `/pin/<random code>` URLs**, every `lastmod` in the **+09:00** time zone |
| `/pin/2e94471046`, `/pin/5352838313` as a normal browser | **404** "Page not found" |
| `/pin/2e94471046` as **Googlebot** | **200**, 197 KB, with a **Japanese-language title advertising car parts** |

Serving different content to search engines than to visitors ("cloaking"), together with injected spam sitemaps, is the signature of a known WordPress compromise pattern often called the "Japanese keyword hack". It usually means an attacker can run code or write files on the hosting account.

**Recommended immediate actions (owner / whoever manages the WordPress hosting). These are outside this project's scope and have not been done:**
1. Treat the hosting account as compromised. Take a backup for forensics, then clean or restore from a known-good backup.
2. Run a malware scan. Review all WordPress admin users, plugins, themes, `wp-config.php`, `.htaccess`, must-use plugins and scheduled tasks. Update WordPress and all plugins.
3. Rotate all credentials: hosting, FTP/SFTP, database, WordPress admins and API keys.
4. In Google Search Console:
   * check **Security issues** and **Manual actions**
   * remove the spam sitemaps
   * request removal of `/pin/` URLs
   * resubmit the clean sitemap
5. Monitor for reinfection.

**Implications for the new platform**
* **Never copy files, plugins, themes, databases or uploads from the existing WordPress install** into the new platform. Content (text, approved photos) is re-entered or re-uploaded through the CMS after review.
* At cut-over, `/pin/*` must return **410 Gone** (not redirect) so search engines drop the spam. This needs prefix matching in the redirect manager (ERD change C-12).
* The new platform launches on clean infrastructure with its own credentials (D-14).
* Tracked as risk **R-16** in `../decisions/DECISION_REGISTER.md`.

---

## 3. Legitimate URL inventory (from `sitemap.xml`, 57 URLs)

| Type | Count | URLs |
|---|---|---|
| Pages | 14 | `/`, `/coworking-space/`, `/managed-office-space/`, `/meeting-rooms/`, `/virtual-office/`, `/business-registration-services/`, `/pricing/`, `/our-space/`, `/gallery/`, `/blog/`, `/help-center/`, `/contact-us/`, `/privacy-policy/`, `/terms-conditions/` |
| Blog posts (at site root) | 6 | `/affordable-coworking-space-noida-a-practical-guide-for-startups-and-freelancers/` · `/coworking-space-in-noida-private-cabin-dedicated-desk-or-hot-desk-which-should-you-choose/` · `/coworking-space-in-noida-sector-2-what-to-check-before-choosing-your-workspace-2/` · `/flexible-office-space-in-noida-how-startups-can-scale-without-long-term-lease-commitments/` · `/managed-office-space-in-noida-vs-traditional-office-which-is-more-cost-effective-for-growing-teams/` · `/when-should-a-business-move-to-a-managed-office-space-in-noida-7-signs-youre-ready-2/` |
| Tag archives | 13 | `/tag/…/` (e.g. `coworking-space-in-noida`, `managed-office-space-in-noida`; two misspelled `coworing-…` tags) |
| Feeds | 24 | `/feed/`, `/comments/feed/` and per-post, per-page and per-tag `…/feed/` |

---

## 4. Draft redirect plan (decided in Phase 11; listed now so the IA stays compatible)

| Existing | Proposed new destination | Code |
|---|---|---|
| `/` | `/` | — |
| `/coworking-space/` | `/workspaces/hot-desks` or a coworking hub page (owner choice, Q-OW-6) | 301 |
| `/managed-office-space/` | `/workspaces/managed-offices` | 301 |
| `/meeting-rooms/` | `/workspaces/meeting-rooms` | 301 |
| `/virtual-office/` | `/workspaces/virtual-office` | 301 |
| `/business-registration-services/` | `/workspaces/virtual-office` (if the service is the same product; owner to confirm) | 301 |
| `/pricing/` | `/pricing` (if public pricing is approved, D-04) or `/workspaces` | 301 |
| `/our-space/`, `/gallery/` | the Noida property page(s) | 301 |
| `/blog/` and the 6 posts | `/insights` and `/insights/{same-slug}` | 301 |
| `/contact-us/`, `/help-center/` | `/contact`, `/faqs` | 301 |
| `/privacy-policy/`, `/terms-conditions/` | `/legal/privacy`, `/legal/terms` | 301 |
| `/tag/*` | the closest topic page, or 410 if low value | 301/410 |
| `*/feed/` | `/insights/feed` (if a feed is provided) or 410 | 301/410 |
| `/pin/*` | — | **410** |

Before cut-over, a fresh crawl plus Search Console data (top landing pages) must confirm the final map. Any page with traffic or backlinks gets a 301 to its closest equivalent.

---

## 5. Observations not treated as fact

The existing site's copy (products, pricing, addresses, claims) was **not** reviewed for accuracy and is **not** used as a source of business facts. The owner should confirm which existing content, if any, may be reused (Q-OW-6).
