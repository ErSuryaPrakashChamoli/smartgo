# ADR-0008 — Use the Alpine.js bundled with Livewire

* **Status:** PROPOSED
* **Date:** 2026-10-24

## Context
Livewire 4.4.8 (a Filament dependency) ships Alpine inside its JS bundle (`window.Alpine` in `vendor/livewire/livewire/dist/livewire.js`). Loading a second Alpine copy causes double initialisation.

## Decision
The public site loads Livewire's scripts and uses its Alpine. No separate `alpinejs` npm package. Alpine plugins (e.g. focus) are registered on Livewire's Alpine instance if needed.

## Consequences
* (+) One Alpine instance, consistent between the public site and the back office.
* (−) Pages wanting Alpine also load Livewire's runtime. This is acceptable; Livewire is needed on interactive pages anyway.
