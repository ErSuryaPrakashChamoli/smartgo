<?php

namespace App\Enums\Content;

/**
 * How a redirect's `from_path` is compared with the requested path (DATABASE_ERD §7).
 */
enum RedirectMatch: string
{
    /** The requested path equals `from_path`. */
    case Exact = 'exact';

    /** The requested path starts with `from_path`, such as the `/pin/` rules of the launch map (FR-SEO-6). */
    case Prefix = 'prefix';
}
