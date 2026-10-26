<?php

namespace App\Enums\Access;

/**
 * Business capabilities checked by policies (ADR-0011).
 *
 * The catalogue grows module by module, following the approved matrix in
 * docs/security/SECURITY_MODEL.md §3.1; only capabilities with an implemented
 * screen or action are listed here.
 */
enum Capability: string
{
    /** Entering the back office at all: every protected page, route and Livewire action (OD-74). */
    case AccessBackoffice = 'backoffice.access';

    case ManageUsers = 'users.manage';
    case ViewAuditLog = 'audit_log.view';

    /** Reading website content in the back office: drafts, their history and previews. */
    case ViewContent = 'content.view';

    /** Writing and changing drafts of website content, including restoring an earlier version as a new draft. */
    case EditContent = 'content.edit';

    /** Approving someone else's submitted content; never one's own (BR-CMS-2, OD-73). */
    case ApproveContent = 'content.approve';

    /** Publishing or scheduling an approved version someone else approved (BR-CMS-2a, OD-65). */
    case PublishContent = 'content.publish';

    /** Publishing testimonials and case studies, only with consent on file and after another's approval (BR-CMS-7). */
    case PublishTestimonials = 'testimonials.publish';

    /** Uploading media with its origin and licence, and editing its descriptions (FR-CMS-5, BR-MED-1). */
    case ManageMedia = 'media.manage';
}
