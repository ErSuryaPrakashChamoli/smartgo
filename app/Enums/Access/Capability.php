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

    /** Drafting changes to the public contact details (OD-53, OD-64). */
    case ManageContactSettings = 'settings.contact.manage';

    /** Approving someone else's contact-detail changes (OD-64, OD-69). */
    case ApproveContactSettings = 'settings.contact.approve';

    /** Putting contact details live after another's approval (OD-64, OD-65). */
    case PublishContactSettings = 'settings.contact.publish';

    /** Seeing the legal entity and GST details (OD-54, OD-55). */
    case ViewLegalSettings = 'settings.legal.view';

    /** Editing the legal entity and GST details, and putting a verified version in use (OD-54, OD-59). */
    case ManageLegalSettings = 'settings.legal.manage';

    /** Verifying legal details against the registration documents; never details one edited (OD-55, OD-62). */
    case VerifyLegalSettings = 'settings.legal.verify';

    /** Drafting changes to the redirects made by hand (FR-SEO-4, FR-SEO-6). */
    case ManageRedirects = 'redirects.manage';

    /** Approving someone else's redirect changes (OD-66). */
    case ApproveRedirects = 'redirects.approve';

    /** Putting approved redirects live after another's approval (OD-66, OD-65). */
    case PublishRedirects = 'redirects.publish';

    /** Drafting changes to public brand settings: social links now; logo and design settings later (OD-67). */
    case ManageBrand = 'brand.manage';

    /** Approving someone else's brand changes (OD-67, OD-70). */
    case ApproveBrand = 'brand.approve';

    /** Putting approved brand changes live after another's approval (OD-67, OD-70, OD-65). */
    case PublishBrand = 'brand.publish';

    /** Drafting changes to tracking IDs, analytics identifiers and advertising pixels (OD-71). */
    case ManageTracking = 'settings.tracking.manage';

    /** Approving someone else's tracking changes (OD-71). */
    case ApproveTracking = 'settings.tracking.approve';

    /** Making approved tracking changes active after another's approval (OD-71, OD-65). */
    case PublishTracking = 'settings.tracking.publish';

    /** Seeing cities, properties, buildings and workspace categories in the back office (SECURITY_MODEL §3.1). */
    case ViewProperties = 'properties.view';

    /** Adding and editing cities, properties and buildings, including address, status and operating model (§3.1). */
    case ManageProperties = 'properties.manage';

    /** Adding and editing workspace categories and which properties offer them (§3.1, ADR-0013). */
    case ManageProducts = 'products.manage';

    /** Reading property profiles in the back office: drafts, their history and previews (FR-CMS-6; the "Profiles" cells of `content.view`). */
    case ViewProfiles = 'profiles.view';

    /** Writing drafts of property profiles; Property Managers only for their assigned properties (FR-CMS-6, §3.1). */
    case EditProfiles = 'profiles.edit';

    /** Seeing enquiries and their notes; limited to assigned properties for Property Managers and restricted Sales/CRM Executives (BR-CRM-6). */
    case ViewLeads = 'leads.view';

    /** Changing an enquiry's status and recording notes and follow-ups (§11 `leads.manage`). */
    case ManageLeads = 'leads.manage';

    /** Assigning or reassigning an enquiry's owner by hand, audited (BR-CRM-3a, OD-51). */
    case AssignLeads = 'leads.assign';

    /** Exporting enquiries, audited; Super Admin only (FR-CRM-6, OD-36). */
    case ExportLeads = 'leads.export';

    /** Managing the lists used on enquiries, such as the reasons for closing one (BR-CRM-5); Super Admin only, like assignment rules and pipeline stages (SECURITY_MODEL §3.1). */
    case ManageLeadSettings = 'leads.settings.manage';

    /** Seeing uploaded 3D models, their checks and anchors, and previewing them (§3.1 "3D model upload and anchors"). */
    case ViewTwinModels = 'twin_models.view';

    /** Uploading 3D models prepared by the 3D-modelling supplier, and removing them (§3.1, OD-37). */
    case ManageTwinModels = 'twin_models.manage';
}
