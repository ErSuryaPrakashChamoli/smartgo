// Marketing pages need no framework JavaScript: menus use the native Popover API and FAQs use
// <details>. Livewire (which bundles Alpine) loads only on pages that render a Livewire component.

// The phone menu works without this script. With it, the Menu button reports whether the menu is open,
// focus moves into the menu when it opens, Tab stays inside it while it covers the page (WCAG 2.4.11),
// and focus goes back to the Menu button when it closes (OD-140).
const menu = document.getElementById('mobile-menu');

if (menu) {
    const opener = document.querySelector('[popovertarget="mobile-menu"]:not([popovertargetaction="hide"])');
    const focusable = () => [...menu.querySelectorAll('a[href], button, summary')].filter((element) => element.getClientRects().length > 0);

    opener?.setAttribute('aria-expanded', 'false');

    menu.addEventListener('toggle', (event) => {
        const isOpen = event.newState === 'open';
        opener?.setAttribute('aria-expanded', String(isOpen));

        if (isOpen) {
            focusable()[0]?.focus();
        } else if (menu.contains(document.activeElement) || document.activeElement === document.body) {
            opener?.focus();
        }
    });

    menu.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab') {
            return;
        }

        const items = focusable();

        if (event.shiftKey && document.activeElement === items[0]) {
            event.preventDefault();
            items.at(-1).focus();
        } else if (!event.shiftKey && document.activeElement === items.at(-1)) {
            event.preventDefault();
            items[0].focus();
        }
    });
}

// The header's drop-down groups work without this script; with it, each group's button reports whether
// its list is open (OD-143).
document.querySelectorAll('[popover][id^="nav-group-"]').forEach((panel) => {
    const button = document.querySelector(`[popovertarget="${panel.id}"]`);

    button?.setAttribute('aria-expanded', 'false');
    panel.addEventListener('toggle', (event) => button?.setAttribute('aria-expanded', String(event.newState === 'open')));
});

// Numbers taken from records count up once as they come into view (OD-144). The page already shows the
// real number, so without this script or under reduced motion nothing changes.
const counters = document.querySelectorAll('[data-count-to]');

if (counters.length > 0 && 'IntersectionObserver' in window && window.matchMedia('(prefers-reduced-motion: no-preference)').matches) {
    const observer = new IntersectionObserver((entries) => {
        entries.filter((entry) => entry.isIntersecting).forEach(({ target }) => {
            observer.unobserve(target);
            const total = Number(target.dataset.countTo);
            const started = performance.now();
            const step = (now) => {
                const progress = Math.min(1, (now - started) / 1200);
                target.textContent = String(Math.round(total * (1 - (1 - progress) ** 3)));

                if (progress < 1) {
                    requestAnimationFrame(step);
                }
            };

            requestAnimationFrame(step);
        });
    });

    counters.forEach((counter) => observer.observe(counter));
}

// Header drop-downs with categories (OD-145): each category is a disclosure button that shows its links on the
// right, one category at a time, the first by default and again each time the menu reopens. It opens on click, Enter or Space, or after the pointer
// rests on it briefly (so crossing another category on the way to the links does not switch); opened from the
// keyboard, focus moves to its first link. Up and Down, or Left and Right (the Spaces drop-down's tabs sit in a
// row, OD-177), move between categories. Without this script every category's links show, one list under the other.
document.querySelectorAll('[data-flyout]').forEach((flyout) => {
    const tabs = [...flyout.querySelectorAll('[data-flyout-tab]')];
    const panes = tabs.map((tab) => document.getElementById(tab.getAttribute('aria-controls')));
    let hoverTimer = null;

    const activate = (index) => {
        tabs.forEach((tab, position) => {
            tab.setAttribute('aria-expanded', String(position === index));
            panes[position].hidden = position !== index;
        });
    };

    activate(0);
    flyout.closest('[popover]')?.addEventListener('toggle', (event) => {
        if (event.newState === 'closed') {
            activate(0);
        }
    });

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', (event) => {
            activate(index);

            if (event.detail === 0) {
                panes[index].querySelector('a')?.focus();
            }
        });
        tab.addEventListener('mouseenter', () => {
            clearTimeout(hoverTimer);
            hoverTimer = setTimeout(() => activate(index), 120);
        });
        tab.addEventListener('mouseleave', () => clearTimeout(hoverTimer));
        tab.addEventListener('keydown', (event) => {
            const next = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[event.key];

            if (next !== undefined) {
                event.preventDefault();
                tabs[(index + next + tabs.length) % tabs.length].focus();
            }
        });
    });
});

// The home page's banner carousel (OD-169). Without this script the banners are a row that scrolls sideways. With
// it, the controls appear; the next banner slides in from the right when the current dot has filled (a CSS
// animation, so the pointer or focus on the banners holds it); the pause button stops it; only the banner on screen
// can be reached by Tab, and screen readers hear the new banner when it changes while paused. Under reduced motion it
// never moves by itself and jumps instead of sliding.
document.querySelectorAll('[data-carousel]').forEach((carousel) => {
    const track = carousel.querySelector('[data-carousel-track]');
    const slides = [...carousel.querySelectorAll('[data-carousel-slide]')];
    const dots = [...carousel.querySelectorAll('[data-carousel-dot]')];
    const controls = carousel.querySelector('[data-carousel-controls]');
    const toggle = carousel.querySelector('[data-carousel-toggle]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    if (!track || slides.length < 2 || !controls) {
        return;
    }

    let current = 0;
    let playing = carousel.hasAttribute('data-carousel-autoplay') && !reducedMotion.matches;
    let settleTimer = null;

    const show = (index) => {
        current = index;
        slides.forEach((slide, position) => {
            slide.toggleAttribute('data-active', position === index);
            slide.inert = position !== index;
        });
        dots.forEach((dot, position) => (position === index ? dot.setAttribute('aria-current', 'true') : dot.removeAttribute('aria-current')));
    };

    const goTo = (index) => {
        const target = (index + slides.length) % slides.length;
        show(target);
        track.scrollTo({ left: target * track.clientWidth, behavior: reducedMotion.matches ? 'auto' : 'smooth' });
    };

    const setPlaying = (value) => {
        playing = value;
        carousel.toggleAttribute('data-carousel-playing', playing);
        track.setAttribute('aria-live', playing ? 'off' : 'polite');
        toggle?.setAttribute('aria-label', playing ? 'Pause the banners' : 'Play the banners');
        toggle?.querySelector('[data-carousel-pause-icon]')?.classList.toggle('hidden', !playing);
        toggle?.querySelector('[data-carousel-play-icon]')?.classList.toggle('hidden', playing);
    };

    controls.hidden = false;
    show(0);
    setPlaying(playing);

    carousel.querySelector('[data-carousel-previous]')?.addEventListener('click', () => goTo(current - 1));
    carousel.querySelector('[data-carousel-next]')?.addEventListener('click', () => goTo(current + 1));
    dots.forEach((dot, index) => dot.addEventListener('click', () => goTo(index)));
    toggle?.addEventListener('click', () => setPlaying(!playing));

    // The filled dot moves the carousel on.
    carousel.addEventListener('animationend', (event) => {
        if (playing && event.animationName === 'carousel-progress') {
            goTo(current + 1);
        }
    });

    // The pointer or keyboard focus on the banners holds them.
    const hold = (held) => carousel.toggleAttribute('data-carousel-held', held);
    carousel.addEventListener('pointerenter', (event) => event.pointerType === 'mouse' && hold(true));
    carousel.addEventListener('pointerleave', () => hold(carousel.contains(document.activeElement)));
    carousel.addEventListener('focusin', () => hold(true));
    carousel.addEventListener('focusout', (event) => hold(carousel.contains(event.relatedTarget) || carousel.matches(':hover')));

    // A swipe or a sideways scroll picks the banner it stops on.
    track.addEventListener('scroll', () => {
        clearTimeout(settleTimer);
        settleTimer = setTimeout(() => {
            const index = Math.round(track.scrollLeft / track.clientWidth);

            if (index !== current && slides[index]) {
                show(index);
            }
        }, 120);
    }, { passive: true });

    // Keep the banner on screen in place when the window is resized.
    window.addEventListener('resize', () => track.scrollTo({ left: current * track.clientWidth }));

    reducedMotion.addEventListener('change', () => reducedMotion.matches && setPlaying(false));
});

// The highlights strip (OD-169) moves by CSS; this shows its pause button and makes it work (WCAG 2.2.2). Under
// reduced motion the strip stands still, so the button stays hidden.
document.querySelectorAll('[data-marquee]').forEach((marquee) => {
    const toggle = marquee.querySelector('[data-marquee-toggle]');

    if (!toggle || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    toggle.hidden = false;
    toggle.addEventListener('click', () => {
        const paused = !marquee.hasAttribute('data-paused');
        marquee.toggleAttribute('data-paused', paused);
        toggle.setAttribute('aria-label', paused ? 'Play the highlights' : 'Pause the highlights');
        toggle.querySelector('[data-marquee-pause-icon]')?.classList.toggle('hidden', paused);
        toggle.querySelector('[data-marquee-play-icon]')?.classList.toggle('hidden', !paused);
    });
});

// The footer's Google map (OD-180) loads only when the visitor asks for it, so Google is not contacted and sets
// no cookies before then. Without this script its placeholder offers only the "Open in Google Maps" link. With it,
// the placeholder also offers "Show map"; pressing it puts the map in the placeholder's place and moves focus to it.
// The choice is remembered on this device (browser storage, not a cookie), so later pages show the map without
// asking, once it nears the screen. Where storage is blocked, the visitor is simply asked again.
const MAP_CHOICE_KEY = 'smartgro.showGoogleMap';

const remembersMapChoice = () => {
    try {
        return window.localStorage.getItem(MAP_CHOICE_KEY) === 'yes';
    } catch {
        return false;
    }
};

document.querySelectorAll('[data-map]').forEach((map) => {
    const frame = map.querySelector('[data-map-frame]');
    const load = map.querySelector('[data-map-load]');

    if (!frame || !load || !map.dataset.mapSrc) {
        return;
    }

    const showMap = (moveFocus) => {
        const iframe = document.createElement('iframe');
        iframe.src = map.dataset.mapSrc;
        iframe.title = map.dataset.mapTitle ?? 'Map';
        iframe.loading = 'lazy';
        iframe.referrerPolicy = 'strict-origin-when-cross-origin';
        iframe.className = 'absolute inset-0 block h-full w-full';
        frame.replaceWith(iframe);

        if (moveFocus) {
            iframe.focus();
        }
    };

    if (remembersMapChoice()) {
        showMap(false);

        return;
    }

    map.querySelectorAll('[data-map-load], [data-map-note]').forEach((element) => {
        element.hidden = false;
    });
    load.addEventListener('click', () => {
        try {
            window.localStorage.setItem(MAP_CHOICE_KEY, 'yes');
        } catch {
            // Storage blocked: the map still shows now, and the visitor is asked again next time.
        }

        showMap(true);
    }, { once: true });
});

// A location page's 3D walkthrough (OD-181, OD-184, BR-3D-6). It loads by itself once it comes near the screen, as
// BR-3D-6 allows ("or the viewer scrolls into view"); nothing 3D is fetched before that. A browser set to save data
// waits for "Explore in 3D" instead, and the button comes back to start again after "Stop loading" or a failure. The
// model, the viewer module (twin-viewer.js, its own Vite entry) and the decoders this model needs download together,
// with the download's progress and a way to stop it. The viewer does not trap the page's scroll (pageScroll): the
// wheel zooms only with Ctrl or ⌘ held. It draws only when the view changes, and with reduced motion a guided view
// is a cut, not a flight. Without this script nothing loads; the views are listed and the floor plans show the same
// floor.
document.querySelectorAll('[data-twin-walkthrough]').forEach((root) => {
    const status = root.querySelector('[data-twin-status]');
    const stage = root.querySelector('[data-twin-stage]');
    const poster = root.querySelector('[data-twin-poster]');
    const controls = root.querySelector('[data-twin-controls]');
    const start = root.querySelector('[data-twin-start]');
    const load = root.querySelector('[data-twin-load]');
    const stop = root.querySelector('[data-twin-stop]');
    const progress = root.querySelector('[data-twin-progress]');
    const views = [...controls.querySelectorAll('[data-twin-camera]')];
    let viewer = null;
    let loading = null;

    const failure = (name) => Object.assign(new Error(name), { name });

    // The model's bytes, showing the download's progress.
    const download = async (url, signal) => {
        let response;

        try {
            response = await fetch(url, { signal });
        } catch (error) {
            throw signal.aborted ? error : failure('DownloadFailed');
        }

        if (!response.ok || !response.body) {
            throw failure('DownloadFailed');
        }

        const total = Number(response.headers.get('Content-Length')) || 0;
        const reader = response.body.getReader();
        const parts = [];
        let received = 0;

        if (total) {
            progress.max = total;
            progress.value = 0;
        } else {
            progress.removeAttribute('value');
        }

        for (let part = await reader.read(); !part.done; part = await reader.read()) {
            parts.push(part.value);
            received += part.value.length;

            if (total) {
                progress.value = received;
            }
        }

        const bytes = new Uint8Array(received);
        let offset = 0;

        for (const part of parts) {
            bytes.set(part, offset);
            offset += part.length;
        }

        return bytes.buffer;
    };

    const reset = (message) => {
        loading?.abort();
        viewer?.dispose();
        viewer = null;
        loading = null;
        stage.hidden = true;
        stage.replaceChildren();
        poster.hidden = false;
        progress.hidden = true;
        stop.hidden = true;
        controls.hidden = true;
        load.hidden = false;
        status.textContent = message;
    };

    start.hidden = false;

    stop.addEventListener('click', () => {
        reset('Loading stopped. Press "Explore in 3D" to start again.');
        load.focus();
    });

    controls.querySelector('[data-twin-whole]').addEventListener('click', () => {
        viewer?.showWhole();
        views.forEach((view) => view.setAttribute('aria-pressed', 'false'));
        status.textContent = 'The whole floor is shown.';
    });

    views.forEach((button) => {
        button.setAttribute('aria-pressed', 'false');
        button.addEventListener('click', () => {
            const shown = viewer?.showCamera(button.dataset.twinCamera) ?? false;
            views.forEach((view) => view.setAttribute('aria-pressed', String(shown && view === button)));
            status.textContent = shown ? `${button.textContent.trim()} is shown.` : 'This view is not in the model.';
        });
    });

    const begin = async () => {
        const abort = new AbortController();
        const pressed = document.activeElement === load;
        loading = abort;
        load.hidden = true;
        stop.hidden = false;
        progress.hidden = false;
        status.textContent = 'Loading the 3D view…';

        if (pressed) {
            stop.focus();
        }

        try {
            // The model, the viewer module, the modules it imports and the decoders this model needs all start now, so
            // their downloads overlap; the viewer waits for the model.
            const model = download(root.dataset.model, abort.signal);
            model.catch(() => {});

            for (const href of root.dataset.moduleImports.split(' ').filter(Boolean)) {
                document.head.append(Object.assign(document.createElement('link'), { rel: 'modulepreload', href }));
            }

            for (const href of root.dataset.decoderFiles.split(' ').filter(Boolean)) {
                document.head.append(Object.assign(document.createElement('link'), { rel: 'preload', as: 'fetch', crossOrigin: 'anonymous', href }));
            }

            const { mountTwinViewer } = await import(/* @vite-ignore */ root.dataset.module);
            const shown = await mountTwinViewer(stage, {
                model,
                label: root.dataset.label,
                decoders: root.dataset.decoders.split(' ').filter(Boolean),
                onContextLost: () => reset('The 3D view stopped: the device ran out of graphics memory. Press "Explore in 3D" to try again.'),
                signal: abort.signal,
                pageScroll: true,
            });

            if (abort.signal.aborted) {
                shown.dispose();

                return;
            }

            viewer = shown;
            loading = null;
            poster.hidden = true;
            controls.hidden = false;
            status.textContent = 'Drag to turn the floor; pinch, or hold Ctrl (⌘ on a Mac) and scroll, to zoom; or choose a view. With the view focused, arrow keys move it.';
        } catch (error) {
            if (abort.signal.aborted) {
                return;
            }

            reset({
                WebGLUnavailable: 'This browser or device cannot show 3D. The views are listed below.',
                DownloadFailed: 'The 3D model could not be downloaded. Check the connection and press "Explore in 3D" to try again.',
            }[error?.name] ?? 'The 3D view could not be shown. Press "Explore in 3D" to try again.');
        }
    };

    load.addEventListener('click', begin);

    // Starts once the walkthrough is within 200 px of the screen, so the model is usually drawn by the time it is in
    // view; with the browser set to save data, only the button starts it.
    if (!navigator.connection?.saveData) {
        load.hidden = true;
        const nearby = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                nearby.disconnect();
                begin();
            }
        }, { rootMargin: '200px 0px' });
        nearby.observe(stage.parentElement);
    }
});

// A location page's floor plans (OD-181): without this script every plan shows, one under the other, each with its
// floor's name. With it, the floors become tabs (WAI-ARIA tabs: Left, Right, Home and End move between them) and one
// plan shows at a time.
document.querySelectorAll('[data-floor-plans]').forEach((plans) => {
    const tablist = plans.querySelector('[data-floor-tabs]');

    if (!tablist) {
        return;
    }

    const tabs = [...tablist.querySelectorAll('[role="tab"]')];
    const panels = tabs.map((tab) => document.getElementById(tab.getAttribute('aria-controls')));

    if (panels.some((panel) => !panel)) {
        return;
    }

    const select = (index, moveFocus) => {
        tabs.forEach((tab, position) => {
            const selected = position === index;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
            panels[position].hidden = !selected;
        });

        if (moveFocus) {
            tabs[index].focus();
        }
    };

    panels.forEach((panel, position) => {
        panel.setAttribute('role', 'tabpanel');
        panel.setAttribute('aria-labelledby', tabs[position].id);
        panel.querySelector('[data-floor-plan-heading]')?.setAttribute('hidden', '');
    });

    tabs.forEach((tab, position) => {
        tab.addEventListener('click', () => select(position, false));
        tab.addEventListener('keydown', (event) => {
            const target = { ArrowRight: position + 1, ArrowLeft: position - 1, Home: 0, End: tabs.length - 1 }[event.key];

            if (target === undefined) {
                return;
            }

            event.preventDefault();
            select((target + tabs.length) % tabs.length, true);
        });
    });

    tablist.hidden = false;
    select(0, false);
});

// A page's main photo and its small photos (location, workspace and space pages, OD-183): choosing a small photo shows
// it in the main frame, in place (never full screen), with its label and credit, and marks it as the current one. A
// photo elsewhere on the page with [data-photo-show] (a location's or workspace's "Photos") shows itself in that frame
// too, scrolls it into view and moves focus to its small photo. Without this script the main photo stays as it is.
document.querySelectorAll('[data-photo-gallery]').forEach((gallery) => {
    const mainImage = gallery.querySelector('[data-photo-main]');
    const label = gallery.querySelector('[data-photo-label]');
    const credit = gallery.querySelector('[data-photo-credit]');
    const thumbs = [...gallery.querySelectorAll('[data-photo-thumb]')];

    const select = (index) => {
        const thumb = thumbs[index];

        if (!thumb) {
            return;
        }

        mainImage.srcset = thumb.dataset.srcset;
        mainImage.src = thumb.dataset.src;
        mainImage.alt = thumb.dataset.alt;

        if (thumb.dataset.width && thumb.dataset.height) {
            mainImage.width = Number(thumb.dataset.width);
            mainImage.height = Number(thumb.dataset.height);
        }

        if (label) {
            label.hidden = !thumb.dataset.label;
            label.querySelector('[data-photo-label-text]').textContent = thumb.dataset.label;
        }

        if (credit) {
            credit.textContent = thumb.dataset.credit;
        }

        thumbs.forEach((other) => other.setAttribute('aria-pressed', String(other === thumb)));
    };

    thumbs.forEach((thumb, index) => thumb.addEventListener('click', () => select(index)));

    document.querySelectorAll('[data-photo-show]').forEach((button) => button.addEventListener('click', () => {
        const index = Number(button.dataset.photoShow);
        select(index);
        gallery.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center' });
        thumbs[index]?.focus({ preventScroll: true });
    }));
});
