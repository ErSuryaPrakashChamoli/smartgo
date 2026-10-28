<?php

/*
 * Test-only worker for Tests\Support\Concurrency\PublishingHarness.
 *
 * Boots the application, waits for the shared release instant, then publishes, submits, approves or
 * restores a revision, runs the scheduled publisher, takes a testimonial off the website, assigns an
 * enquiry, sends an enquiry or closes a property (Phase 5). After every read of a revision, enquiry,
 * contact or property it pauses briefly, which widens the race window so a missing lock would show up
 * as a double publication, assignment or enquiry. Prints one JSON line.
 */

use App\Actions\Content\ApproveRevision;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\PublishScheduledRevisions;
use App\Actions\Content\RestoreRevision;
use App\Actions\Content\SubmitRevision;
use App\Actions\Content\UnpublishContent;
use App\Actions\Crm\AssignLead;
use App\Actions\Crm\SubmitEnquiry;
use App\Actions\Properties\SaveProperty;
use App\Models\Lead;
use App\Models\Property;
use App\Models\Revision;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! str_ends_with((string) DB::connection()->getDatabaseName(), '_testing')) {
    fwrite(STDERR, 'Refusing to run the harness outside a *_testing database.'.PHP_EOL);
    exit(1);
}

DB::connection()->getPdo();

DB::listen(function (QueryExecuted $query): void {
    if (preg_match('/^\s*select\b.*\bfrom `(revisions|leads|contacts|properties)`/is', $query->sql)) {
        usleep(150_000);
    }
});

$waitMicroseconds = (int) round(((float) getenv('PUBLISH_RELEASE_AT') - microtime(true)) * 1_000_000);

if ($waitMicroseconds > 0) {
    usleep($waitMicroseconds);
}

usleep((int) getenv('PUBLISH_START_DELAY_MS') * 1000);

try {
    if (getenv('PUBLISH_MODE') === 'scheduled') {
        $outcome = app(PublishScheduledRevisions::class)->handle() > 0 ? 'published' : 'skipped';
    } elseif (getenv('PUBLISH_MODE') === 'takedown') {
        app(UnpublishContent::class)->handle(Testimonial::query()->findOrFail((int) getenv('PUBLISH_TESTIMONIAL_ID')), User::query()->findOrFail((int) getenv('PUBLISH_USER_ID')), 'Consent withdrawn', consentWithdrawn: getenv('PUBLISH_CONSENT_WITHDRAWN') === '1');
        $outcome = 'taken down';
    } elseif (getenv('PUBLISH_MODE') === 'assign_lead') {
        app(AssignLead::class)->handle(Lead::query()->findOrFail((int) getenv('PUBLISH_LEAD_ID')), User::query()->findOrFail((int) getenv('PUBLISH_USER_ID')), User::query()->findOrFail((int) getenv('PUBLISH_OWNER_ID')), expectedOwnerId: null);
        $outcome = 'assigned';
    } elseif (getenv('PUBLISH_MODE') === 'submit_enquiry') {
        $outcome = app(SubmitEnquiry::class)->handle(json_decode((string) getenv('PUBLISH_ENQUIRY'), true, flags: JSON_THROW_ON_ERROR), '127.0.0.1')?->reference ?? 'spam';
    } elseif (getenv('PUBLISH_MODE') === 'close_property') {
        $property = Property::query()->with('primaryBuilding')->findOrFail((int) getenv('PUBLISH_PROPERTY_ID'));
        app(SaveProperty::class)->handle(User::query()->findOrFail((int) getenv('PUBLISH_USER_ID')), $property, [
            ...$property->only(['name', 'code', 'slug', 'city_id', 'micro_market_id']),
            'operating_model' => $property->operating_model->value,
            'operational_status' => 'closed',
            'building' => $property->primaryBuilding->only(['address_line_1', 'locality']),
        ]);
        $outcome = 'closed';
    } elseif (getenv('PUBLISH_MODE') === 'approve') {
        app(ApproveRevision::class)->handle(Revision::query()->findOrFail((int) getenv('PUBLISH_REVISION_ID')), User::query()->findOrFail((int) getenv('PUBLISH_USER_ID')));
        $outcome = 'approved';
    } elseif (getenv('PUBLISH_MODE') === 'submit') {
        app(SubmitRevision::class)->handle(Revision::query()->findOrFail((int) getenv('PUBLISH_REVISION_ID')), User::query()->findOrFail((int) getenv('PUBLISH_USER_ID')));
        $outcome = 'submitted';
    } elseif (getenv('PUBLISH_MODE') === 'restore') {
        app(RestoreRevision::class)->handle(Revision::query()->findOrFail((int) getenv('PUBLISH_REVISION_ID')), User::query()->findOrFail((int) getenv('PUBLISH_USER_ID')));
        $outcome = 'restored';
    } else {
        app(PublishRevision::class)->handle(Revision::query()->findOrFail((int) getenv('PUBLISH_REVISION_ID')), User::query()->findOrFail((int) getenv('PUBLISH_USER_ID')));
        $outcome = 'published';
    }

    $message = null;
} catch (AuthorizationException|ValidationException $exception) {
    $outcome = 'refused';
    $message = $exception->getMessage();
}

echo json_encode(['outcome' => $outcome, 'message' => $message]).PHP_EOL;
