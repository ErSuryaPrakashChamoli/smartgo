<?php

namespace App\Events\Content;

use App\Models\Revision;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A version went live (ADR-0005). Dispatched after the transaction commits, so listeners (cache purges,
 * sitemaps) never see a rolled-back publication.
 */
class ContentPublished implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Revision $revision) {}
}
