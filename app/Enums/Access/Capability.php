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

    /** Writing and changing drafts of website content. */
    case EditContent = 'content.edit';

    /** Approving someone else's submitted content; never one's own (BR-CMS-2, OD-73). */
    case ApproveContent = 'content.approve';

    /** Publishing, scheduling or rolling back an approved version someone else approved (BR-CMS-2a, OD-65). */
    case PublishContent = 'content.publish';
}
