<?php

namespace Database\Seeders;

use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Enums\Content\RevisionState;
use App\Models\CustomerSegment;
use App\Models\Faq;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostTopic;
use App\Models\Product;
use App\Models\Property;
use App\Models\User;
use App\Services\Website\WorkspaceCatalogue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * SmartGro sample content (OD-143, OD-149, OD-150, OD-165): example pages, articles, FAQs and menus for the
 * business team to replace from the back office, written through the real workflow (an author, a different
 * approver, then a publisher), plus example text for each location's website profile as a draft.
 *
 *   php artisan db:seed --class=SampleContentSeeder
 *
 * writes as the dummy Marketing Managers (DummyStaffSeeder, run first when they are missing); the browser
 * kit's `seed-sample-content.php` calls seedWith() with its fixture staff. Pages use the sample layout and
 * articles the sample-content topic: both show a "Sample content" notice, are never indexed and stay out of
 * the sitemap, and production refuses to publish them (OD-149); this seeder refuses production altogether.
 * Segment names and workspace descriptions come from the records (OD-04, WorkspaceCatalogue); the Solutions
 * menu's offerings use the owner's sample copy (OD-149); everything else is clearly example text. No price,
 * availability, capacity, amenity, opening hour, date, contact detail, testimonial, customer or other
 * business fact is written. Safe to rerun: unchanged content gets no new version, and content someone else
 * published or is working on is left alone (listed under "left_alone").
 */
class SampleContentSeeder extends Seeder
{
    public function run(): void
    {
        $marketing = fn (string $key): ?User => User::query()->where('email', DummyStaffSeeder::email($key))->first();

        if ($marketing('marketing') === null || $marketing('marketing2') === null) {
            $this->call(DummyStaffSeeder::class);
        }

        [$author, $approver] = [$marketing('marketing'), $marketing('marketing2')];

        if (! $author->is_active || ! $approver->is_active) {
            throw new RuntimeException('The dummy Marketing Managers write and approve the sample content: activate both first.');
        }

        $this->command?->info('Sample content: '.json_encode($this->seedWith($author, $approver, $author), JSON_UNESCAPED_SLASHES));
    }

    /**
     * Write the sample content as these staff; the approver must be someone other than the author and the publisher.
     *
     * @return array{solutions: int, community: int, about: int, articles: int, faqs: int, profile_drafts: int, menus: list<string>, offerings: array<string, int>, left_alone: list<string>}
     */
    public function seedWith(User $author, User $approver, User $publisher): array
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Sample content is for local databases only.');
        }

        // Allowed for this run even when `.env` refuses sample content for the owner's server (OD-152).
        $sampleContent = config('backoffice.sample_content');
        config(['backoffice.sample_content' => true]);

        try {
            return $this->write(['author' => $author, 'approver' => $approver, 'publisher' => $publisher]);
        } finally {
            config(['backoffice.sample_content' => $sampleContent]);
        }
    }

    /**
     * @param  array{author: User, approver: User, publisher: User}  $staff
     * @return array{solutions: int, community: int, about: int, articles: int, faqs: int, profile_drafts: int, menus: list<string>, offerings: array<string, int>, left_alone: list<string>}
     */
    private function write(array $staff): array
    {
        // Content this seeder published and nobody has changed since gets a new version only when its text differs;
        // content someone else published, or with a version in progress, is left alone. Content last published by
        // an account that is no longer active (such as the deactivated local helpers, OD-116) has nobody to protect
        // it: it gets the sample version, and the earlier one stays in its history to restore.
        $leftAlone = [];
        $publish = function (Model $content, array $payload) use ($staff, &$leftAlone): void {
            $published = $content->publishedRevision;

            if ($content->revisions()->whereIn('state', RevisionState::open())->exists() || ($published !== null && $published->author_id !== $staff['author']->id && (bool) $published->author?->is_active)) {
                $leftAlone[] = class_basename($content).' '.($content->slug ?? $content->handle ?? $content->getKey());

                return;
            }

            if ($published !== null && $published->payload == $content->prepareRevisionPayload($payload, forSubmission: true)) {
                return;
            }

            $draft = app(CreateDraft::class)->handle($content, $staff['author'], $payload);
            app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $staff['author']), $staff['approver']), $staff['publisher']);
        };

        $section = fn (string $label, string $key): array => ['label' => $label, 'type' => 'section', 'page_id' => null, 'section' => $key, 'url' => null];
        $toPage = fn (string $label, Page $page): array => ['label' => $label, 'type' => 'page', 'page_id' => $page->id, 'section' => null, 'url' => null];

        // The page at an address (live, or only in a version), or a new one.
        $findPage = fn (string $slug): Page => Page::query()->where('slug', $slug)->first()
            ?? Page::query()->whereHas('revisions', fn ($revisions) => $revisions->where('payload->slug', $slug))->first()
            ?? Page::query()->forceCreate(['status' => 'draft']);

        // A sample page at the address, published with these sections.
        $page = function (string $slug, string $title, array $blocks, ?array $seo = null) use ($findPage, $publish): Page {
            $page = $findPage($slug);
            $publish($page, ['title' => $title, 'slug' => $slug, 'template' => 'sample', 'blocks' => $blocks, ...($seo === null ? [] : ['seo' => $seo])]);

            return $page->fresh();
        };

        $howItWorks = ['type' => 'features', 'data' => ['heading' => 'How it works', 'intro' => 'Example steps for the team to confirm.', 'items' => [
            ['title' => 'Choose a workspace type', 'text' => 'Compare private offices, desks, meeting rooms, virtual offices and managed offices.'],
            ['title' => 'See a location', 'text' => 'Request a tour time on the website. SmartGro confirms it with you.'],
            ['title' => 'Agree the details', 'text' => 'SmartGro shares prices and availability with you directly. An enquiry does not reserve anything.'],
        ]]];
        $askUs = fn (string $heading): array => ['type' => 'cta', 'data' => ['eyebrow' => null, 'heading' => $heading, 'text' => 'Tell us what your team needs and which location suits you.',
            'primary_link' => $section('Send an enquiry', 'enquire'), 'secondary_link' => $section('Book a tour', 'book_a_tour')]];

        // Solutions: one page per customer segment in the records (OD-04), each pointing to the workspace types
        // that usually suit it (example mapping for the team to confirm).
        $typesFor = [
            'startups-and-small-businesses' => ['dedicated_desk', 'private_office', 'meeting_room'],
            'freelancers-and-independent-professionals' => ['hot_desk', 'dedicated_desk', 'meeting_room'],
            'smes-and-growing-companies' => ['private_office', 'meeting_room', 'conference_room'],
            'large-enterprises-and-corporates' => ['managed_office', 'private_office', 'conference_room'],
            'remote-and-hybrid-employees' => ['hot_desk', 'dedicated_desk'],
            'consultants-and-business-travellers' => ['hot_desk', 'meeting_room'],
            'companies-seeking-managed-offices' => ['managed_office', 'conference_room'],
            'businesses-needing-virtual-offices-or-registered-addresses' => ['virtual_office', 'meeting_room'],
        ];
        $catalogue = app(WorkspaceCatalogue::class);
        $products = Product::query()->shown()->get()->keyBy('key');
        $solutionPages = [];

        foreach (CustomerSegment::query()->offered()->get() as $segment) {
            $types = collect($typesFor[$segment->slug] ?? ['private_office', 'hot_desk'])->map(fn (string $key) => $products->get($key))->filter()->values();
            $solutionPages[$segment->slug] = $page('solutions/'.$segment->slug, $segment->name, [
                ['type' => 'hero', 'data' => ['eyebrow' => 'Solutions', 'heading' => $segment->name, 'intro' => 'Example text: how SmartGro\'s flexible workspaces can work for this kind of team.', 'link' => $section('Send an enquiry', 'enquire')]],
                ['type' => 'text', 'data' => ['heading' => 'What these teams usually look for', 'body' => '<p>Example text for the team to replace: describe the needs SmartGro hears most often from this kind of team, in SmartGro\'s own words.</p><ul><li>Example need: a workspace that can change as the team changes.</li><li>Example need: a place to meet clients.</li><li>Example need: clear terms agreed directly with SmartGro.</li></ul>']],
                ['type' => 'cards', 'data' => ['heading' => 'Workspace types to consider', 'intro' => 'These suggestions are examples; SmartGro confirms what suits your team.', 'items' => [
                    ...$types->map(fn (Product $product): array => ['title' => $product->name, 'text' => $catalogue->categoryCopy($product)['summary'], 'link' => $section('Compare workspace types', 'workspace_types')])->all(),
                    ...($types->count() < 2 ? [['title' => 'Talk to SmartGro', 'text' => 'Ask which workspace suits your team.', 'link' => $section('Send an enquiry', 'enquire')]] : []),
                ]]],
                $howItWorks,
                ['type' => 'faq', 'data' => ['heading' => 'Questions', 'items' => [
                    ['question' => 'Can I see a location before deciding?', 'answer' => 'Yes. Request a tour on the website and SmartGro confirms the time with you. A tour request is not a booking.'],
                    ['question' => 'Are prices shown on the website?', 'answer' => 'Not yet. SmartGro shares prices and availability when you get in touch.'],
                ]]],
                $askUs('Talk to SmartGro about your team'),
            ]);
        }

        $solutions = $page('solutions', 'Solutions', [
            ['type' => 'hero', 'data' => ['eyebrow' => 'Solutions', 'heading' => 'Workspace solutions for every kind of team', 'intro' => 'Example text: from a freelancer\'s desk to an enterprise floor, choose the kind of team you are.', 'link' => $section('Find your workspace', 'workspace_types')]],
            ['type' => 'cards', 'data' => ['heading' => 'Who we work with', 'intro' => 'The customer groups SmartGro serves (OD-04). Each page is example content.', 'items' => collect($solutionPages)->map(fn (Page $solution): array => ['title' => $solution->title, 'text' => null, 'link' => $toPage('Read more', $solution)])->values()->all()]],
            $howItWorks,
            $askUs('Not sure which solution fits?'),
        ]);

        // Community: an overview and two example events, with no date and no registration.
        $events = [
            'founders-breakfast' => 'Example event: founders\' breakfast',
            'workspace-open-house' => 'Example event: workspace open house',
        ];
        $eventPages = [];

        foreach ($events as $slug => $title) {
            $eventPages[] = $page('community/events/'.$slug, $title, [
                ['type' => 'hero', 'data' => ['eyebrow' => 'Community', 'heading' => $title, 'intro' => 'An example event page showing how SmartGro events will look. It is not a real event.', 'link' => null]],
                ['type' => 'text', 'data' => ['heading' => 'Details', 'body' => '<p><strong>Date:</strong> not set (example event).</p><p><strong>Location:</strong> not set (example event).</p><p><strong>Registration:</strong> not open. Real events will be announced on this website.</p>']],
                ['type' => 'cta', 'data' => ['eyebrow' => null, 'heading' => 'Ask about events at SmartGro', 'text' => 'Tell us what kind of events would help your team.', 'primary_link' => $section('Send an enquiry', 'enquire'), 'secondary_link' => null]],
            ]);
        }

        $community = $page('community', 'Community', [
            ['type' => 'hero', 'data' => ['eyebrow' => 'Community', 'heading' => 'Community at SmartGro', 'intro' => 'Example text: how teams at SmartGro meet, learn and share the space.', 'link' => $section('Send an enquiry', 'enquire')]],
            ['type' => 'features', 'data' => ['heading' => 'What community could look like', 'intro' => 'Examples for the team to confirm or replace.', 'items' => [
                ['title' => 'Meet other teams', 'text' => 'Example text: informal ways for members to get to know each other.'],
                ['title' => 'Learn together', 'text' => 'Example text: short sessions run by members or by SmartGro.'],
                ['title' => 'Share the space well', 'text' => 'Example text: simple house rules that keep shared areas pleasant.'],
            ]]],
            ['type' => 'cards', 'data' => ['heading' => 'Events', 'intro' => 'Example events: no date is set and registration is not open.', 'items' => array_map(fn (Page $event): array => ['title' => $event->title, 'text' => 'Example event page.', 'link' => $toPage('See the example', $event)], $eventPages)]],
            ['type' => 'faq', 'data' => ['heading' => 'Questions', 'items' => [
                ['question' => 'Are these events real?', 'answer' => 'No. They are examples showing how event pages will look. Real events will be listed here once SmartGro publishes them.'],
            ]]],
            $askUs('Join a SmartGro workspace'),
        ]);

        // About: the brand's confirmed tagline (OD-01) and the footer's description; the rest is example text.
        $about = $page('about', 'About SmartGro', [
            ['type' => 'hero', 'data' => ['eyebrow' => 'About', 'heading' => 'Work smart. Grow together.', 'intro' => 'Coworking and managed workspaces in Noida, Delhi NCR.', 'link' => $section('See our locations', 'locations')]],
            ['type' => 'text', 'data' => ['heading' => 'Who we are', 'body' => '<p>Example text for the team to replace: SmartGro\'s story, in SmartGro\'s own words, once it is approved.</p>']],
            ['type' => 'features', 'data' => ['heading' => 'What we value', 'intro' => 'Example values for the team to confirm or replace.', 'items' => [
                ['title' => 'Clarity', 'text' => 'Example text: say plainly what each workspace includes.'],
                ['title' => 'Flexibility', 'text' => 'Example text: workspaces that change as teams change.'],
                ['title' => 'Care', 'text' => 'Example text: shared spaces kept in good order.'],
            ]]],
            ['type' => 'cards', 'data' => ['heading' => 'Who we work with', 'intro' => null, 'items' => [
                ...collect($solutionPages)->take(3)->map(fn (Page $solution): array => ['title' => $solution->title, 'text' => null, 'link' => $toPage('Read more', $solution)])->values()->all(),
                ['title' => 'All solutions', 'text' => 'Every kind of team SmartGro works with.', 'link' => $toPage('See all', $solutions)],
            ]]],
            $askUs('Talk to SmartGro'),
        ]);

        // Resources: three example articles under the sample-content topic.
        $topic = PostTopic::query()->firstOrCreate(['slug' => PostTopic::SAMPLE_SLUG], ['name' => 'Sample content']);
        // Pages most operators offer (OD-144, from the survey of coworking websites): careers, partnerships and an
        // accessibility statement, as sample pages. None lists a job, a commission or a commitment.
        $careers = $page('careers', 'Careers', [
            ['type' => 'hero', 'data' => ['eyebrow' => 'Careers', 'heading' => 'Work with SmartGro', 'intro' => 'Example text: what it is like to work at SmartGro, in the team\'s own words.', 'link' => $section('Contact SmartGro', 'enquire')]],
            ['type' => 'features', 'data' => ['heading' => 'What we look for', 'intro' => 'Examples for the team to confirm or replace.', 'items' => [
                ['title' => 'Care for people', 'text' => 'Example text: members and guests come first.'],
                ['title' => 'Ownership', 'text' => 'Example text: see a problem, fix it or flag it.'],
                ['title' => 'Curiosity', 'text' => 'Example text: always looking for a better way.'],
            ]]],
            ['type' => 'text', 'data' => ['heading' => 'Open roles', 'body' => '<p>No openings are listed yet. When SmartGro is hiring, roles will appear here.</p>']],
        ]);
        $partners = $page('partners', 'Partner with SmartGro', [
            ['type' => 'hero', 'data' => ['eyebrow' => 'Partners', 'heading' => 'Partner with SmartGro', 'intro' => 'Example text: for brokers who place clients and for property owners with space to offer.', 'link' => $section('Talk to SmartGro', 'enquire')]],
            ['type' => 'cards', 'data' => ['heading' => 'Who we work with', 'intro' => 'Example text; the terms of any partnership are agreed directly with SmartGro.', 'items' => [
                ['title' => 'Brokers and advisers', 'text' => 'Example text: introduce clients looking for flexible workspace.', 'link' => $section('Get in touch', 'enquire')],
                ['title' => 'Property owners', 'text' => 'Example text: talk to SmartGro about running flexible workspace in your building.', 'link' => $section('Get in touch', 'enquire')],
                ['title' => 'Companies and communities', 'text' => 'Example text: events, perks and partnerships for members.', 'link' => $section('Get in touch', 'enquire')],
            ]]],
            $howItWorks,
        ]);
        $accessibility = $page('accessibility', 'Accessibility', [
            ['type' => 'hero', 'data' => ['eyebrow' => 'Accessibility', 'heading' => 'Accessibility statement', 'intro' => 'Example text for SmartGro\'s team and advisers to review before it is published as SmartGro\'s statement.', 'link' => null]],
            ['type' => 'text', 'data' => ['heading' => 'This website', 'body' => '<p>Example text: the website is built to be usable with a keyboard and a screen reader, at any text size, and without motion for visitors who turn it off in their device settings.</p><p>Example text: if something does not work for you, tell SmartGro and say which page it was on.</p>']],
            ['type' => 'text', 'data' => ['heading' => 'Our locations', 'body' => '<p>Example text: step-free access, lifts and accessible toilets at each location will be listed here once SmartGro has confirmed them.</p>']],
        ]);

        // Example articles (OD-143, OD-144): general guidance written for the demo, each under a subject topic and
        // the sample topic, so it is labelled, never indexed and never presented as SmartGro advice.
        $subjects = collect([
            'workspace-guides' => 'Workspace guides',
            'flexible-work' => 'Flexible work',
            'workplace-life' => 'Workplace life',
        ])->map(fn (string $name, string $slug): PostTopic => PostTopic::query()->firstOrCreate(['slug' => $slug], ['name' => $name]));

        $note = '<p><strong>Example article.</strong> SmartGro\'s team will replace this text; it is general guidance, not advice from SmartGro.</p>';
        $articles = [
            'choosing-a-workspace-type' => ['workspace-guides', 'Choosing a workspace type', 'Questions to ask before choosing between desks, cabins and managed offices.',
                '<p>The right workspace depends less on the size of your team today than on how it works: how often people are in, how much privacy the work needs, and how quickly the team is likely to change.</p><h3>Start with how you work</h3><ul><li>Do people need the same desk every day, or any free desk?</li><li>How many calls and meetings need a closed door?</li><li>Will the team grow, shrink or move in the next year?</li></ul><h3>Then match the type</h3><p>Open desks suit individuals and small teams who come and go. A private office or cabin suits teams that need a door that locks. A managed office suits a company that wants its own floor or suite set up and run for it.</p>'],
            'hot-desk-or-dedicated-desk' => ['workspace-guides', 'Hot desk or dedicated desk?', 'The difference between taking any free desk and keeping your own.',
                '<p>A hot desk is any free desk in a shared area, taken when you arrive. A dedicated desk is yours: the same place every day, where you can leave a monitor and your things.</p><h3>A hot desk suits you if</h3><ul><li>you come in a few days a week;</li><li>you carry everything you need in a bag;</li><li>you like to change where you sit.</li></ul><h3>A dedicated desk suits you if</h3><ul><li>you come in most days;</li><li>you work with a larger screen or equipment;</li><li>you want neighbours you get to know.</li></ul>'],
            'what-is-a-virtual-office' => ['workspace-guides', 'What is a virtual office for?', 'A business address without renting a desk, and what to check before you choose one.',
                '<p>A virtual office gives a company an address for its correspondence and registration without renting a desk. It suits businesses that work remotely but need a professional address.</p><h3>Questions to ask</h3><ul><li>Which documents can the address be used for?</li><li>How is post received and passed on to you?</li><li>Can you book a meeting room when you need to meet in person?</li></ul><p>The answers depend on the provider and local rules, so ask them directly before you decide.</p>'],
            'managed-offices-explained' => ['workspace-guides', 'Managed offices, explained', 'When a company wants its own space, set up and run for it.',
                '<p>A managed office is a floor or suite used only by your company, set up to your layout and run by the operator, so your team does not handle the building, cleaning or utilities.</p><h3>Who chooses one</h3><p>Usually companies that have outgrown shared space, want their own branding and layout, and prefer one agreement over many suppliers.</p><h3>What to agree early</h3><ul><li>the layout and how many people it must seat;</li><li>what is included in the running of the space;</li><li>how the agreement can change as the team does.</li></ul>'],
            'planning-a-workspace-visit' => ['workspace-guides', 'Planning a workspace visit', 'What to look at, listen for and ask when you visit a workspace.',
                '<p>Photos show how a space looks; a visit shows how it works. Go at the time of day you would normally work.</p><h3>Look and listen</h3><ul><li>Light at your desk, and noise from calls and kitchens;</li><li>where you would take a private call;</li><li>how easy it is to book a meeting room.</li></ul><h3>Ask</h3><ul><li>how getting in works outside usual hours;</li><li>what is included and what costs extra;</li><li>how much notice you need to change your plan.</li></ul>'],
            'hybrid-teams-and-flexible-space' => ['flexible-work', 'Hybrid teams and flexible space', 'How teams that split their week between home and office can plan their space.',
                '<p>Hybrid teams rarely need a desk for everyone. They need enough room on their busiest day, and places to meet when everyone is in.</p><h3>Plan for the busiest day</h3><p>Count who comes in on your team\'s busiest day, not the average. Add meeting space for the days people come in to work together.</p><h3>Keep it flexible</h3><p>A mix of a few dedicated desks, shared desks and bookable meeting rooms often fits better than one large office that sits half empty.</p>'],
            'moving-your-team-checklist' => ['flexible-work', 'Moving your team: a checklist', 'Steps that make a move into a new workspace go smoothly.',
                '<ol><li><strong>Agree the date</strong> and tell the team early.</li><li><strong>List what moves:</strong> equipment, files, plants, signage.</li><li><strong>Check connections:</strong> internet, phones, printers and access cards.</li><li><strong>Update your address</strong> with clients, suppliers and registrations.</li><li><strong>Plan the first day:</strong> who sits where, and a short tour for everyone.</li></ol>'],
            'running-better-meetings' => ['flexible-work', 'Running better meetings', 'Small habits that make meetings in a shared workspace shorter and more useful.',
                '<ul><li>Book the room for the time you need, and end on time.</li><li>Share the agenda before, and the decisions after.</li><li>Test the screen and call link five minutes early.</li><li>Leave the room as you found it for the next team.</li></ul>'],
            'working-well-in-a-shared-space' => ['workplace-life', 'Working well in a shared space', 'Simple courtesies that make shared workspaces better for everyone.',
                '<p>Shared workspaces work when everyone treats them as their own and their neighbours\' at the same time.</p><ul><li>Take calls in a phone booth or meeting room.</li><li>Use headphones for audio.</li><li>Clear a shared desk when you leave.</li><li>Say hello: the people next to you may become clients, partners or friends.</li></ul>'],
            'staying-focused-at-work' => ['workplace-life', 'Staying focused in an open workspace', 'Ways to protect deep work when the room around you is busy.',
                '<ul><li>Block time for focused work in your calendar.</li><li>Choose a quieter zone for deep work and a social one for collaboration.</li><li>Turn off notifications you do not need.</li><li>Take short breaks away from your desk.</li></ul>'],
        ];

        $published = now();
        foreach (array_values(array_keys($articles)) as $position => $slug) {
            [$subject, $title, $excerpt, $body] = $articles[$slug];
            $post = Post::query()->where('slug', $slug)->first() ?? Post::query()->forceCreate(['status' => 'draft']);
            $publish($post, ['title' => $title, 'slug' => $slug, 'excerpt' => $excerpt, 'topic_ids' => [$subjects[$subject]->id, $topic->id], 'blocks' => [
                ['type' => 'text', 'data' => ['heading' => null, 'body' => $note.$body]],
            ]]);
            $post->forceFill(['published_at' => $published->copy()->subDays($position * 3)])->save();
        }

        // The Solutions menu (OD-145, OD-149, OD-150): two categories with the owner's offerings, labels, short
        // descriptions and sample introductions (pasted briefs, 2026-10-31; Virtual Office restored by OD-150 with
        // copy in the same style). Each offering has a sample landing page under its category's address: what it is,
        // at a glance, possible uses, what SmartGro must confirm, related offerings, questions and a way to ask.
        // A Workspaces offering whose workspace type has its page on the website (a published offering) leads there
        // from the menu and from other pages, and its sample page links to it; otherwise the sample page is the
        // destination (OD-150 decision 2). Nothing here is a SmartGro fact: no price, capacity, facility, service
        // scope or availability.
        $categories = [
            'Workspaces' => ['prefix' => 'workspace-solutions', 'icon' => 'building-office', 'description' => 'Desks and offices for individuals and teams.', 'recordsFirst' => true, 'offerings' => [
                'dedicated-desks' => [
                    'name' => 'Dedicated Desks', 'icon' => 'computer-desktop', 'visitable' => true, 'product' => 'dedicated_desk',
                    'short' => 'A dedicated workspace for professionals who want a consistent place to focus and work.',
                    'intro' => 'Explore a workspace concept designed around a dedicated desk, a regular working routine, and a professional environment. Replace this sample description with SmartGro\'s confirmed offering details before publication.',
                    'about' => [
                        'A dedicated desk is a workspace kept for one person within a shared environment. Instead of choosing a different seat each day, you return to the same desk, which can make a regular working routine easier to keep.',
                        'This sample page describes the general idea only. Desk configurations, what is included, the terms and the availability at each SmartGro location are still to be confirmed by SmartGro.',
                    ],
                    'suits' => 'Professionals who work from the same place most days and want a desk that is kept for them.',
                    'uses' => [
                        ['A regular routine', 'Working from the same desk each day, with your set-up where you left it.'],
                        ['Focused individual work', 'Long stretches of concentrated work in a professional setting.'],
                        ['A base between meetings', 'A fixed place to return to around client visits and calls.'],
                    ],
                    'confirm' => ['Desk sizes and how desks are arranged', 'Storage, such as lockers or pedestals', 'Access hours and how you get in', 'Shared facilities that come with the desk', 'Terms, prices and availability at each location'],
                    'faqs' => [
                        ['How is a dedicated desk different from a flexible desk?', 'A dedicated desk is kept for one person; a flexible desk is a free desk you use when you come in. Which arrangements SmartGro offers, and on what terms, is still to be confirmed.'],
                        ['Can I see the space before deciding?', 'You can request a tour on this website. SmartGro confirms a time with you; a tour request is not a booking.'],
                        ['Are prices and terms shown here?', 'No. SmartGro shares prices, terms and availability directly when you get in touch.'],
                    ],
                    'related' => ['flexible-desks', 'private-offices', 'day-pass'],
                ],
                'flexible-desks' => [
                    'name' => 'Flexible Desks', 'icon' => 'arrows-right-left', 'visitable' => true, 'product' => 'hot_desk',
                    'short' => 'A flexible workspace concept for people whose working needs change from day to day.',
                    'intro' => 'Discover a flexible-desk concept intended for individuals and teams who need adaptable workspace arrangements. Update this sample content with the actual access rules, facilities, pricing, and availability approved by SmartGro.',
                    'about' => [
                        'A flexible desk is a shared-seating arrangement: rather than keeping one desk, you use an available desk when you come in. It can suit people whose working pattern changes from week to week.',
                        'This sample page explains the concept only. It does not promise walk-in access, a particular booking rule or a free desk at any time. SmartGro will confirm how flexible desks work at each location.',
                    ],
                    'suits' => 'People who come in on some days, carry what they need and are happy to use any free desk.',
                    'uses' => [
                        ['Part-time office days', 'Coming in on the days you need a desk and working elsewhere on the others.'],
                        ['Hybrid working', 'A place to work near home or near clients between office and remote days.'],
                        ['Getting to know coworking', 'A way to try a shared workspace before choosing a longer arrangement.'],
                    ],
                    'confirm' => ['How access works, and whether a desk can be reserved', 'Opening hours, and any limit on days or hours', 'Shared facilities that are included', 'Prices and availability at each location'],
                    'faqs' => [
                        ['Is a desk guaranteed when I arrive?', 'This sample page makes no such promise. How desks are shared, and whether one can be reserved, is for SmartGro to confirm.'],
                        ['Can I move to a dedicated desk later?', 'Ask SmartGro which arrangements are available at your preferred location and how a change would work.'],
                        ['Do I need to visit first?', 'No, but you can request a tour on this website. SmartGro confirms a time with you; a tour request is not a booking.'],
                    ],
                    'related' => ['dedicated-desks', 'day-pass', 'workspace-packages'],
                ],
                'private-offices' => [
                    'name' => 'Private Offices', 'icon' => 'lock-closed', 'visitable' => true, 'product' => 'private_office',
                    'short' => 'A private workspace concept for individuals and teams seeking a more enclosed working environment.',
                    'intro' => 'Explore a private-office concept with a focus on privacy, concentration, and team working. Replace this placeholder with verified SmartGro office configurations, capacity, facilities, terms, and availability.',
                    'about' => [
                        'A private office is an enclosed workspace for an individual or a team: a room with a door that closes, inside a shared building. It can help with calls, confidential conversations and focused team work.',
                        'This sample page does not describe any particular office. Office configurations, capacity, furniture, facilities, terms and availability are still to be confirmed by SmartGro.',
                    ],
                    'suits' => 'Individuals and teams who need privacy for calls, focused work and confidential conversations.',
                    'uses' => [
                        ['A team room', 'A shared room where a team works together every day.'],
                        ['Confidential work', 'Conversations and papers that should stay behind a closed door.'],
                        ['Calls without the noise', 'Calls and video meetings away from an open area.'],
                    ],
                    'confirm' => ['Office sizes and layouts', 'How many people each office suits', 'Furniture and facilities included', 'Terms, prices and availability at each location'],
                    'faqs' => [
                        ['How many people can a private office hold?', 'That depends on the office. SmartGro will confirm sizes and capacity; this sample page does not state any.'],
                        ['Can a team move to a larger office later?', 'Ask SmartGro what options exist at your preferred location. Nothing on this page promises a particular move or availability.'],
                        ['Can I visit before deciding?', 'Yes, you can request a tour on this website. SmartGro confirms a time with you; a tour request is not a booking.'],
                    ],
                    'related' => ['managed-workspaces', 'dedicated-desks', 'meeting-rooms'],
                ],
                'managed-workspaces' => [
                    'name' => 'Managed Workspaces', 'icon' => 'building-office-2', 'visitable' => true, 'product' => 'managed_office',
                    'short' => 'A managed workspace concept for businesses seeking a more tailored office arrangement.',
                    'intro' => 'Review a sample managed-workspace offering designed around business requirements. Replace this content with SmartGro\'s confirmed service scope, included facilities, commercial terms, and operational responsibilities.',
                    'about' => [
                        'A managed workspace is an office arrangement shaped around one organisation\'s requirements, where the space is set up and looked after for the business rather than by it.',
                        'This sample page explains the concept only. It does not claim that any operational, facility-management or support service is included. The service scope, responsibilities and commercial terms are still to be confirmed by SmartGro.',
                    ],
                    'suits' => 'Organisations that want a space arranged around their own requirements, without running it themselves.',
                    'uses' => [
                        ['A company office', 'Space laid out for one organisation\'s teams and ways of working.'],
                        ['A new location', 'Setting up in a new area without building an office from scratch.'],
                        ['A growing team', 'An arrangement that can be reviewed as the team changes.'],
                    ],
                    'confirm' => ['Which services are included, and which are not', 'Who is responsible for what, day to day', 'How far the space can be arranged for the business', 'Commercial terms, any minimum period and availability'],
                    'faqs' => [
                        ['What does a managed workspace include?', 'That is still to be confirmed. SmartGro will set out the exact scope and responsibilities before offering this as a service.'],
                        ['How is it different from a private office?', 'In these samples, a private office is a room within a shared workspace, while a managed workspace is arranged around one organisation\'s requirements. SmartGro will confirm what it offers.'],
                        ['How do we start a conversation?', 'Send an enquiry describing your team and requirements. SmartGro replies directly; an enquiry is not an agreement.'],
                    ],
                    'related' => ['private-offices', 'workspace-packages', 'training-rooms'],
                ],
            ]],
            'Business Solutions' => ['prefix' => 'business-solutions', 'icon' => 'briefcase', 'description' => 'Rooms, passes and services for your business.', 'recordsFirst' => false, 'offerings' => [
                'meeting-rooms' => [
                    'name' => 'Meeting Rooms', 'icon' => 'user-group', 'visitable' => true, 'product' => 'meeting_room',
                    'short' => 'A professional space concept for meetings, discussions, and collaborative sessions.',
                    'intro' => 'Explore the meeting-room offering concept, including potential room configurations and use cases. Add actual room capacities, equipment, booking terms, pricing, and availability only after SmartGro confirms them.',
                    'about' => [
                        'A meeting room is a space set aside for discussions, presentations and working sessions, away from desks and open areas.',
                        'This sample page covers the general idea. Room capacities, layouts, equipment, booking rules, prices and availability are still to be confirmed by SmartGro.',
                    ],
                    'suits' => 'Teams and professionals who need a private room for a meeting, an interview or a working session.',
                    'uses' => [
                        ['Client meetings', 'Meeting clients or partners in a professional setting.'],
                        ['Interviews', 'A quiet room for interviews and one-to-one conversations.'],
                        ['Team sessions', 'Planning, reviews and short workshops for a small group.'],
                    ],
                    'confirm' => ['Room sizes, and how many people each suits', 'Equipment, such as screens or video calling', 'How rooms are booked, and for how long', 'Prices and availability at each location'],
                    'faqs' => [
                        ['Can someone without a desk at SmartGro use a meeting room?', 'That is for SmartGro to confirm. Send an enquiry with what you need, and SmartGro will reply.'],
                        ['What equipment do the rooms have?', 'This sample page does not list any. SmartGro will confirm what each room offers.'],
                        ['Does an enquiry reserve a room?', 'No. An enquiry is not a booking. SmartGro replies to discuss dates, times and availability.'],
                    ],
                    'related' => ['training-rooms', 'day-pass', 'cabin-day-pass'],
                ],
                'day-pass' => [
                    'name' => 'Day Pass', 'icon' => 'ticket', 'visitable' => true, 'product' => null,
                    'short' => 'A sample short-term workspace access offering for flexible working needs.',
                    'intro' => 'Review a sample day-access workspace concept. Replace this content with SmartGro\'s actual access periods, included facilities, booking process, pricing, and applicable conditions.',
                    'about' => [
                        'A day pass is a way to use a workspace for a short time without a longer arrangement: when you are travelling, between offices, or simply need somewhere to work for the day.',
                        'This sample page does not promise any access period, hours or facilities. What a pass includes, how it is booked and its conditions are still to be confirmed by SmartGro.',
                    ],
                    'suits' => 'Visitors, travellers and remote workers who need somewhere to work for a short time.',
                    'uses' => [
                        ['Working while travelling', 'A place to work during a short visit to Noida.'],
                        ['A change from home', 'An occasional day in a professional setting.'],
                        ['Trying a location', 'A way to experience a workspace before choosing a longer arrangement.'],
                    ],
                    'confirm' => ['The access period a pass covers', 'Which facilities are included', 'How a pass is booked and paid for', 'Prices, conditions and availability'],
                    'faqs' => [
                        ['How long does a day pass last?', 'That is still to be confirmed. This sample page does not set any access period or hours.'],
                        ['Do I need to book in advance?', 'SmartGro will confirm how passes are booked. Until then, send an enquiry and SmartGro will reply.'],
                        ['Can I use a meeting room with a day pass?', 'This sample page does not say. Ask SmartGro what a pass would include.'],
                    ],
                    'related' => ['flexible-desks', 'cabin-day-pass', 'meeting-rooms'],
                ],
                'business-address' => [
                    'name' => 'Business Address', 'icon' => 'envelope', 'visitable' => false, 'product' => null,
                    'short' => 'A sample business-address service concept for organisations exploring professional address options.',
                    'intro' => 'Review the proposed business-address service and the information customers may need before enquiring. Do not imply that a registered office address, mail handling, or statutory-use facility is available unless SmartGro confirms the precise service and applicable terms.',
                    'about' => [
                        'A business-address service gives an organisation an address it can use for its business, separate from where its people work every day.',
                        'This sample page does not offer an address. It does not state that SmartGro provides a registered office, any statutory use, mail handling or any other facility. The exact service, how the address may be used, the documents needed and the terms are still to be confirmed by SmartGro.',
                    ],
                    'suits' => 'Organisations exploring a professional address option.',
                    'uses' => [
                        ['A professional address', 'An address to show on business materials, where the service and its terms allow it.'],
                        ['A presence in Noida', 'An address in a business area, separate from where the team works.'],
                        ['Alongside other services', 'An address combined with occasional workspace, if that is offered.'],
                    ],
                    'confirm' => ['Exactly what the service includes', 'How the address may and may not be used', 'The documents and checks required', 'Terms and prices'],
                    'faqs' => [
                        ['Can the address be used as a registered office?', 'This sample page does not say so. Whether any registered or statutory use is allowed is for SmartGro to confirm, with the terms.'],
                        ['Does the service include handling post?', 'That is not confirmed. SmartGro will set out exactly what the service includes.'],
                        ['What do I need to apply?', 'SmartGro will confirm the documents and checks required. Send an enquiry to start the conversation.'],
                    ],
                    'related' => ['virtual-office', 'company-registration-support', 'gst-registration-support'],
                ],
                'gst-registration-support' => [
                    'name' => 'GST Registration Support', 'icon' => 'document-check', 'visitable' => false, 'product' => null,
                    'short' => 'A sample information page for customers exploring GST registration assistance.',
                    'intro' => 'Use this sample page to explain the exact assistance SmartGro has approved. Do not promise registration approval, imply government affiliation, or publish legal or tax advice as a verified SmartGro service without owner approval.',
                    'about' => [
                        'GST registration is how an eligible business registers under India\'s Goods and Services Tax. Some businesses look for help with preparing and organising what the process asks for.',
                        'This sample page does not offer that help. It promises no registration outcome, implies no government affiliation and is not tax or legal advice. Whether SmartGro offers any assistance, its exact scope and who would provide it are still to be confirmed, after legal and tax review.',
                    ],
                    'suits' => 'Businesses exploring help with GST registration.',
                    'uses' => [
                        ['A new business', 'Understanding what registration involves when starting out.'],
                        ['Organising documents', 'Gathering what the process asks for in one place.'],
                        ['Questions before applying', 'Knowing which questions to put to a qualified adviser.'],
                    ],
                    'confirm' => ['Whether this assistance is offered at all', 'Its exact scope, and what it does not cover', 'Who provides it, and their qualifications', 'Terms and fees'],
                    'faqs' => [
                        ['Is SmartGro connected with the GST authorities?', 'No such connection is claimed. Registration is made with the authorities; this sample page does not describe any SmartGro service.'],
                        ['Can registration be guaranteed?', 'No. Nothing on this page promises approval or a timeline.'],
                        ['Is this tax advice?', 'No. This sample page is general information only. For advice, speak to a qualified professional.'],
                    ],
                    'related' => ['company-registration-support', 'business-address', 'virtual-office'],
                ],
                'company-registration-support' => [
                    'name' => 'Company Registration Support', 'icon' => 'building-library', 'visitable' => false, 'product' => null,
                    'short' => 'A sample information page for customers exploring company formation assistance.',
                    'intro' => 'Use this sample page to describe the approved scope of any company-registration assistance. Do not guarantee registration, imply government affiliation, or make unverified claims about professional, legal, or statutory services.',
                    'about' => [
                        'Forming a company involves choosing a structure, preparing documents and completing the registration process. Founders sometimes look for help to organise these steps.',
                        'This sample page does not offer that help. It guarantees no incorporation, implies no government affiliation and does not claim that SmartGro provides professional, legal or statutory services. Any assistance, its scope and who would provide it are still to be confirmed, after legal review.',
                    ],
                    'suits' => 'Founders exploring help with forming a company.',
                    'uses' => [
                        ['Starting a company', 'Understanding the steps before forming a company.'],
                        ['Preparing documents', 'Organising what the registration process asks for.'],
                        ['Planning a first workspace', 'Thinking about a workspace alongside the company\'s first steps.'],
                    ],
                    'confirm' => ['Whether this assistance is offered at all', 'Its exact scope, and what it does not cover', 'Who provides it, and their qualifications', 'Terms and fees'],
                    'faqs' => [
                        ['Does SmartGro register companies?', 'This sample page does not say so. Whether SmartGro offers any assistance is still to be confirmed.'],
                        ['Can incorporation be guaranteed?', 'No. Nothing on this page promises an outcome or a timeline.'],
                        ['Is this legal advice?', 'No. This sample page is general information only. For advice, speak to a qualified professional.'],
                    ],
                    'related' => ['gst-registration-support', 'business-address', 'workspace-packages'],
                ],
                'workspace-packages' => [
                    'name' => 'Workspace Packages', 'icon' => 'rectangle-stack', 'visitable' => true, 'product' => null,
                    'short' => 'A sample overview of workspace options that may be combined around business needs.',
                    'intro' => 'Review a sample workspace-package concept. Add only confirmed SmartGro package combinations, included services, pricing, contract terms, and eligibility rules after verification.',
                    'about' => [
                        'A workspace package brings more than one kind of workspace or service together in a single arrangement, for example desks with time in a meeting room.',
                        'This sample page does not describe any real package. Package names, combinations, inclusions, prices, discounts and contract terms are still to be confirmed by SmartGro.',
                    ],
                    'suits' => 'Businesses that need more than one kind of workspace or service.',
                    'uses' => [
                        ['Desks and meetings', 'Everyday desks with occasional room for meetings.'],
                        ['A team and an address', 'Space for a core team alongside a business address, if that is offered.'],
                        ['One conversation', 'Discussing several needs together instead of one at a time.'],
                    ],
                    'confirm' => ['Which combinations are offered', 'What each package includes', 'Prices, any discounts and who is eligible', 'Contract terms'],
                    'faqs' => [
                        ['Are there ready-made packages?', 'None is confirmed. SmartGro will set out any packages, what they include and their terms.'],
                        ['Can a package be changed later?', 'That depends on terms SmartGro has not confirmed yet. Ask when you get in touch.'],
                        ['How do we find out what would suit us?', 'Send an enquiry describing your team and needs. SmartGro replies directly; an enquiry is not an agreement.'],
                    ],
                    'related' => ['managed-workspaces', 'dedicated-desks', 'meeting-rooms'],
                ],
                'cabin-day-pass' => [
                    'name' => 'Cabin Day Pass', 'icon' => 'key', 'visitable' => true, 'product' => null,
                    'short' => 'A sample short-term access concept for a private cabin or enclosed workspace.',
                    'intro' => 'Review a sample private-cabin access offering. Confirm the actual cabin type, access duration, capacity, booking rules, pricing, and availability before publishing as a real service.',
                    'about' => [
                        'A cabin day pass would give short-term use of a private cabin or enclosed room, for times when an open area is not quiet or private enough.',
                        'This sample page does not say that cabins are available. The cabin type, access period, capacity, booking rules, prices and availability are still to be confirmed by SmartGro.',
                    ],
                    'suits' => 'People who need a private, enclosed room for a short time.',
                    'uses' => [
                        ['Confidential calls', 'Calls and conversations that need privacy.'],
                        ['A focused day', 'A day of deep work without interruptions.'],
                        ['A small working session', 'A short session with one or two colleagues.'],
                    ],
                    'confirm' => ['Which cabins could be used, and their sizes', 'The access period a pass covers', 'How passes are booked', 'Prices and availability'],
                    'faqs' => [
                        ['How is a cabin day pass different from a day pass?', 'In these samples, a day pass is for a shared area and a cabin day pass for an enclosed room. SmartGro will confirm what each would include.'],
                        ['How many people can use a cabin?', 'That is not stated. Cabin sizes and capacity are still to be confirmed.'],
                        ['Can I see a cabin first?', 'You can request a tour on this website. SmartGro confirms a time with you; a tour request is not a booking.'],
                    ],
                    'related' => ['day-pass', 'private-offices', 'meeting-rooms'],
                ],
                'training-rooms' => [
                    'name' => 'Training Rooms', 'icon' => 'academic-cap', 'visitable' => true, 'product' => 'conference_room',
                    'short' => 'A sample space concept for training, workshops, and group learning.',
                    'intro' => 'Explore a training-room concept for organised group sessions. Add verified capacity, layouts, equipment, facilities, rates, and booking conditions only after confirmation.',
                    'about' => [
                        'A training room is a larger space for organised group sessions: training, workshops, seminars and group learning.',
                        'This sample page does not describe a real room. Layouts, capacity, equipment, facilities, rates and booking conditions are still to be confirmed by SmartGro.',
                    ],
                    'suits' => 'Organisations running training, workshops or group learning.',
                    'uses' => [
                        ['Staff training', 'Sessions for new joiners, or for teams learning a new skill.'],
                        ['Workshops', 'Hands-on group work with room to move around.'],
                        ['Seminars and talks', 'Presentations to a seated group.'],
                    ],
                    'confirm' => ['Room layouts, and how many people they suit', 'Equipment, such as screens and sound', 'Facilities available during a session', 'Rates, booking conditions and availability'],
                    'faqs' => [
                        ['How many people can a training room hold?', 'That is not stated. Capacity and layouts are still to be confirmed by SmartGro.'],
                        ['Can the room be arranged for a workshop?', 'That depends on rooms SmartGro has not described yet. Ask about what your session needs.'],
                        ['Does an enquiry reserve a date?', 'No. An enquiry is not a booking. SmartGro replies to discuss dates and availability.'],
                    ],
                    'related' => ['meeting-rooms', 'managed-workspaces', 'workspace-packages'],
                ],
                'virtual-office' => [
                    'name' => 'Virtual Office', 'icon' => 'globe-alt', 'visitable' => false, 'product' => 'virtual_office',
                    'short' => 'A sample virtual-office concept for businesses that work remotely but want a professional presence.',
                    'intro' => 'Review a sample virtual-office concept and the questions a business may want answered before enquiring. Do not state that SmartGro provides a registered office, mail handling, call answering or other virtual-office facilities until SmartGro confirms the exact service and its terms.',
                    'about' => [
                        'A virtual office usually describes services that give a business a professional presence without renting a desk, for teams that work remotely or from several places.',
                        'This sample page does not offer any such service. It does not state that SmartGro provides a registered office address, mail handling, call answering, meeting-room access or any other facility. What a SmartGro virtual office would include, if one is offered, is still to be confirmed with its terms.',
                    ],
                    'suits' => 'Businesses that work remotely or from several places and want a professional presence.',
                    'uses' => [
                        ['Remote-first teams', 'A professional presence for a team without a shared office.'],
                        ['A new market', 'Exploring a presence in Noida before taking space.'],
                        ['Occasional space', 'A presence combined with occasional workspace, if that is offered.'],
                    ],
                    'confirm' => ['Exactly what the service includes', 'Whether any address may be used for registration', 'How post, calls or visitors would be handled, if at all', 'Terms, prices and the documents required'],
                    'faqs' => [
                        ['How is a virtual office different from a business address?', 'In these samples, a business address covers the address alone, and a virtual office a wider set of possible services. SmartGro will confirm what, if anything, each includes.'],
                        ['Does it include a registered office or post handling?', 'Neither is confirmed. This sample page makes no such claim; SmartGro will set out the exact service and its terms.'],
                        ['What do I need to get started?', 'SmartGro will confirm the documents and checks required. Send an enquiry to start the conversation.'],
                    ],
                    'related' => ['business-address', 'workspace-packages', 'meeting-rooms'],
                ],
            ]],
        ];

        // Every offering's page exists before any is published, so each can link to the others, across categories.
        // Offerings are keyed by their last address part, unique across both categories.
        $offerings = collect($categories)->flatMap(fn (array $category, string $categoryName): array => collect($category['offerings'])
            ->map(fn (array $offering, string $slug): array => [...$offering, 'slug' => $category['prefix'].'/'.$slug, 'category' => $categoryName, 'recordsFirst' => $category['recordsFirst']])
            ->all());
        $records = $offerings->map(fn (array $offering): Page => $findPage($offering['slug']));
        $types = Product::query()->pluck('id', 'key');
        $typePage = fn (?string $key): ?int => $key !== null && isset($types[$key]) && $catalogue->categoryPath($types[$key]) !== null ? $types[$key] : null;
        $toType = fn (string $label, int $productId): array => ['label' => $label, 'type' => 'category', 'page_id' => null, 'section' => null, 'product_id' => $productId, 'url' => null];
        // Where the menu and other pages send a visitor for an offering: its workspace type's page, for a Workspaces
        // offering whose type has one; otherwise its sample page.
        $destination = fn (array $offering): ?int => $offering['recordsFirst'] ? $typePage($offering['product']) : null;

        foreach ($offerings as $key => $offering) {
            $typeId = $typePage($offering['product']);

            $publish($records[$key], ['title' => $offering['name'], 'slug' => $offering['slug'], 'template' => 'sample',
                'seo' => ['title' => $offering['name'].' (sample) · SmartGro', 'description' => $offering['short'], 'indexable' => true, 'image_asset_id' => null],
                'blocks' => [
                    ['type' => 'hero', 'data' => ['eyebrow' => $offering['category'], 'heading' => $offering['name'], 'intro' => $offering['intro'],
                        'link' => $typeId === null ? $section('Ask SmartGro', 'enquire') : $toType('See locations', $typeId)]],
                    ['type' => 'text', 'data' => ['heading' => 'About '.$offering['name'], 'body' => implode('', array_map(fn (string $paragraph): string => '<p>'.e($paragraph).'</p>', $offering['about']))]],
                    ['type' => 'features', 'data' => ['heading' => 'At a glance', 'intro' => 'Sample points for SmartGro to confirm or replace.', 'items' => [
                        ['title' => 'Who it may suit', 'text' => $offering['suits']],
                        ['title' => 'How to ask', 'text' => $offering['visitable']
                            ? 'Send an enquiry or request a tour. SmartGro replies with the details; a request is not a booking.'
                            : 'Send an enquiry. SmartGro replies directly; an enquiry is not a booking or an agreement.'],
                        ['title' => 'What is confirmed', 'text' => 'Nothing yet. This page is sample content, not confirmed SmartGro information.'],
                    ]]],
                    ['type' => 'features', 'data' => ['heading' => 'Possible uses', 'intro' => 'Examples of what people might use it for.', 'items' => array_map(fn (array $use): array => ['title' => $use[0], 'text' => $use[1]], $offering['uses'])]],
                    ['type' => 'text', 'data' => ['heading' => 'Before this becomes a SmartGro service', 'body' => '<p>SmartGro still has to confirm:</p><ul>'
                        .implode('', array_map(fn (string $item): string => '<li>'.e($item).'</li>', $offering['confirm']))
                        .'</ul><p>Until then, nothing on this page is an offer, a price or a promise of availability.</p>']],
                    ['type' => 'cards', 'data' => ['heading' => 'Related offerings', 'intro' => 'Options people often compare with this one.', 'items' => array_map(fn (string $related): array => [
                        'title' => $offerings[$related]['name'], 'text' => $offerings[$related]['short'],
                        'link' => $destination($offerings[$related]) === null ? $toPage('See the sample page', $records[$related]) : $toType('See locations', $destination($offerings[$related])),
                    ], $offering['related'])]],
                    ['type' => 'faq', 'data' => ['heading' => 'Questions about '.$offering['name'], 'items' => array_map(fn (array $faq): array => ['question' => $faq[0], 'answer' => $faq[1]], $offering['faqs'])]],
                    ['type' => 'cta', 'data' => ['eyebrow' => null, 'heading' => 'Ask SmartGro about '.$offering['name'], 'text' => 'Tell SmartGro what you need, and the team will reply. An enquiry is not a booking.',
                        'primary_link' => $section('Send an enquiry', 'enquire'), 'secondary_link' => $offering['visitable'] ? $section('Book a tour', 'book_a_tour') : null]],
                ],
            ]);
        }

        // The main menu (OD-140, OD-145, OD-168): every item but Solutions is a drop-down of links to what already
        // exists, each with a short description and an icon that the menu editor can change. A link shows only while
        // its destination has something to show (BR-CMS-9), so a location appears once its profile is published.
        // Workspace types are described by the catalogue, locations by their sector and city in the records.
        $link = fn (array $target, string $label, string $description, ?string $icon = null): array => ['label' => $label, ...$target, 'description' => $description, 'icon' => $icon];
        $toSection = fn (string $key): array => ['type' => 'section', 'section' => $key];
        $onPage = fn (Page $page): array => ['type' => 'page', 'page_id' => $page->id];

        $publish(NavigationMenu::query()->where('handle', 'main')->sole(), ['items' => [
            ['label' => 'Spaces', 'type' => 'group', 'children' => [
                $link($toSection('workspace_types'), 'All workspace types', 'Compare every kind of workspace SmartGro offers.', 'squares-2x2'),
                ...$products->sortBy('sort_order')->map(fn (Product $product): array => $link(['type' => 'category', 'product_id' => $product->id], $product->name, (string) $catalogue->categoryCopy($product)['summary']))->values()->all(),
            ]],
            ['label' => 'Locations', 'type' => 'group', 'children' => [
                $link($toSection('locations'), 'All locations', 'Every SmartGro location on one page.', 'map-pin'),
                ...Property::query()->with(['city', 'microMarket'])->orderBy('code')->get()
                    ->map(fn (Property $property): array => $link(['type' => 'property', 'property_id' => $property->id], 'SmartGro '.$property->name, collect([$property->microMarket?->name, $property->city?->name])->filter()->join(', ')))->all(),
            ]],
            ['label' => 'Solutions', 'type' => 'group', 'children' => collect($categories)->map(fn (array $category, string $categoryName): array => [
                'label' => $categoryName, 'type' => 'group', 'description' => $category['description'], 'icon' => $category['icon'],
                'children' => $offerings->where('category', $categoryName)->map(fn (array $offering, string $key): array => [
                    'label' => $offering['name'], 'description' => $offering['short'], 'icon' => $offering['icon'],
                    ...($destination($offering) === null
                        ? ['type' => 'page', 'page_id' => $records[$key]->id]
                        : ['type' => 'category', 'product_id' => $destination($offering)]),
                ])->values()->all(),
            ])->values()->all()],
            ['label' => 'Community', 'type' => 'group', 'children' => [
                $link($onPage($community), 'Community at SmartGro', 'How teams at SmartGro meet, learn and share the space.', 'user-group'),
                ...array_map(fn (Page $event): array => $link($onPage($event), str($event->title)->after('Example event: ')->ucfirst()->toString(), 'Example event: no date is set and registration is not open.', 'calendar-days'), $eventPages),
                $link($onPage($partners), 'Partner with SmartGro', 'For brokers, property owners and companies.', 'briefcase'),
            ]],
            ['label' => 'About', 'type' => 'group', 'children' => [
                $link($onPage($about), 'About SmartGro', 'Who we are and what we value.', 'building-office-2'),
                $link($onPage($careers), 'Careers', 'Working at SmartGro, and open roles when there are any.', 'identification'),
                $link($toSection('contact'), 'Contact', 'Phone, email and address.', 'chat-bubble-left-right'),
                $link($toSection('book_a_tour'), 'Book a tour', 'Visit a location before you decide.', 'calendar-days'),
                $link($onPage($accessibility), 'Accessibility', 'How this website works for everyone.', 'document-check'),
            ]],
            ['label' => 'Resources', 'type' => 'group', 'children' => [
                $link($toSection('insights'), 'Insights', 'Articles and guides on flexible workspace.', 'rectangle-stack'),
                $link($toSection('faqs'), 'FAQs', 'Answers to common questions.', 'chat-bubble-left-right'),
                $link($toSection('customers'), 'Customer stories', 'How teams work at SmartGro.', 'sparkles'),
                $link($toSection('search'), 'Search', 'Find a page, location or article.', 'globe-alt'),
            ]],
        ]]);

        // Footer columns and legal links over what now exists.
        $publish(NavigationMenu::query()->where('handle', 'footer')->sole(), ['items' => [
            ['label' => 'Explore', 'type' => 'group', 'children' => [
                ['label' => 'Spaces', 'type' => 'section', 'section' => 'workspace_types'],
                ['label' => 'Locations', 'type' => 'section', 'section' => 'locations'],
                ['label' => 'Resources', 'type' => 'section', 'section' => 'insights'],
                ['label' => 'FAQs', 'type' => 'section', 'section' => 'faqs'],
            ]],
            ['label' => 'Company', 'type' => 'group', 'children' => [
                ['label' => 'About', 'type' => 'page', 'page_id' => $about->id],
                ['label' => 'Solutions', 'type' => 'page', 'page_id' => $solutions->id],
                ['label' => 'Community', 'type' => 'page', 'page_id' => $community->id],
                ['label' => 'Careers', 'type' => 'page', 'page_id' => $careers->id],
                ['label' => 'Partner with us', 'type' => 'page', 'page_id' => $partners->id],
            ]],
            ['label' => 'Help', 'type' => 'group', 'children' => [
                ['label' => 'Contact', 'type' => 'section', 'section' => 'contact'],
                ['label' => 'Book a tour', 'type' => 'section', 'section' => 'book_a_tour'],
                ['label' => 'Search', 'type' => 'section', 'section' => 'search'],
                ['label' => 'Accessibility', 'type' => 'page', 'page_id' => $accessibility->id],
            ]],
        ]]);

        // Legal links, once the legal pages exist (they are SmartGro's to write; a fresh database has none).
        $legal = Page::query()->whereIn('slug', ['legal/privacy', 'legal/terms'])->orderBy('slug')->get();
        if ($legal->isNotEmpty()) {
            $publish(NavigationMenu::query()->where('handle', 'legal')->sole(), ['items' => $legal->map(fn (Page $legalPage): array => ['label' => $legalPage->slug === 'legal/privacy' ? 'Privacy' : 'Terms', 'type' => 'page', 'page_id' => $legalPage->id])->all()]);
        }

        // Example questions for the FAQ page (OD-165): how the website itself works, never a business fact, and each
        // answer says it is an example. A question is found again by its text, live or in a version.
        $workspaceTypes = $products->map(fn (Product $product): string => lcfirst($product->name))->values()->join(', ', ' and ');
        $faqs = [
            ['How do I visit a location?', 'Example answer: choose "Book a tour", pick a location and a time that suits you, and send the request. SmartGro confirms the time with you; a tour request is not a booking.'],
            ['Are prices shown on the website?', 'Example answer: not yet. SmartGro shares prices and availability directly when you send an enquiry.'],
            ['Which kinds of workspace can I ask about?', "Example answer: {$workspaceTypes}. SmartGro confirms what each location offers."],
            ['Does an enquiry reserve a workspace?', 'Example answer: no. An enquiry starts a conversation with SmartGro; nothing is reserved until you agree the details directly.'],
            ['Where are SmartGro\'s locations?', 'Example answer: in Noida. Each location has its own page once SmartGro publishes it.'],
        ];
        foreach ($faqs as $position => [$question, $answer]) {
            $faq = Faq::query()->where('question', $question)->first()
                ?? Faq::query()->whereHas('revisions', fn ($revisions) => $revisions->where('payload->question', $question))->first()
                ?? Faq::query()->forceCreate(['status' => 'draft']);
            $publish($faq, ['question' => $question, 'answer' => $answer, 'sort_order' => ($position + 1) * 10]);
        }

        // Example text for each location's website profile (OD-165), as a draft only: a profile can be published once
        // operations staff record a workspace category the property offers (BR-PROP-3), a SmartGro fact this seeder
        // never invents. A profile someone else has worked on is left alone; one already given its draft is skipped.
        $profileDrafts = 0;
        foreach (Property::query()->with('profile')->orderBy('id')->get() as $property) {
            $profile = $property->profile;

            if ($profile === null || $profile->revisions()->where('author_id', $staff['author']->id)->exists()) {
                continue;
            }

            if ($profile->revisions()->exists()) {
                $leftAlone[] = 'PropertyProfile '.$property->code;

                continue;
            }

            app(CreateDraft::class)->handle($profile, $staff['author'], [
                'headline' => "Example headline for {$property->code}: replace before publishing",
                'summary' => "Example text for SmartGro to replace: describe {$property->addressLine()} in SmartGro's own words, what it is like to work there and who it suits. Nothing here is confirmed SmartGro information.",
            ]);
            $profileDrafts++;
        }

        return [
            'solutions' => count($solutionPages) + 1,
            'community' => count($eventPages) + 1,
            'about' => $about->id,
            'articles' => count($articles),
            'faqs' => count($faqs),
            'profile_drafts' => $profileDrafts,
            'menus' => $legal->isEmpty() ? ['main', 'footer'] : ['main', 'footer', 'legal'],
            'offerings' => $offerings->countBy('category')->all(),
            'left_alone' => $leftAlone,
        ];
    }
}
