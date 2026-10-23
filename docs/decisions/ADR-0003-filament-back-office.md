# ADR-0003 — Filament 5 back office organised by business job

* **Status:** PROPOSED
* **Date:** 2026-10-24

## Context
Filament 5.10.3 is installed, but no panel is configured yet. The brief requires a bespoke, non-technical, business-oriented interface, not generic CRUD screens.

## Decision
* One staff panel, with a path to be confirmed (default `/admin`).
* A custom theme (`make:filament-theme`) sharing design tokens with the public site.
* Navigation by **clusters** named after jobs: Website, Workspaces, Sales, Marketing, Insights, Settings.
* Role-specific dashboards (Today's tours, New leads, Spaces needing attention, Awaiting approval).
* Guided wizards for multi-step tasks (Create location → Floors → Spaces → Media → Preview → Submit).
* Required MFA.

## Consequences
* (+) Fast delivery on proven components; MFA, authorisation re-checks on Livewire requests and Builder blocks come built in.
* (−) Custom pages and wizards take more effort than default resources. That effort is intentional.
