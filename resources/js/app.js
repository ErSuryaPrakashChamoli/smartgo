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
