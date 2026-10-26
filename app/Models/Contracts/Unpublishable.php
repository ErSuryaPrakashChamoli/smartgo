<?php

namespace App\Models\Contracts;

/**
 * Revisioned content that anyone allowed to publish it may take off the website at once, with a
 * reason (OD-98, BR-CMS-7a): testimonials and customer stories, whose consent can be withdrawn.
 */
interface Unpublishable extends Revisionable
{
    /**
     * What the content is called in messages and notes ("testimonial", "customer story").
     */
    public function contentLabel(): string;
}
