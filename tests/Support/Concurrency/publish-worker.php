<?php

/*
 * Test-only worker for Tests\Support\Concurrency\PublishingHarness.
 *
 * Boots the application, waits for the shared release instant, then publishes a revision by hand, runs
 * the scheduled publisher or takes a testimonial off the website. After every read of a revision it
 * pauses briefly, which widens the race window so a missing lock would show up as a double
 * publication. Prints one JSON line.
 */

use App\Actions\Content\PublishRevision;
use App\Actions\Content\PublishScheduledRevisions;
use App\Actions\Content\UnpublishContent;
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
    if (preg_match('/^\s*select\b.*\bfrom `revisions`/is', $query->sql)) {
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
        app(UnpublishContent::class)->handle(Testimonial::query()->findOrFail((int) getenv('PUBLISH_TESTIMONIAL_ID')), User::query()->findOrFail((int) getenv('PUBLISH_USER_ID')), 'Consent withdrawn');
        $outcome = 'taken down';
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
