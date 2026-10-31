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
// keyboard, focus moves to its first link. Up and Down move between categories. Without this script every
// category's links show, one list under the other.
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
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                tabs[(index + (event.key === 'ArrowDown' ? 1 : tabs.length - 1)) % tabs.length].focus();
            }
        });
    });
});
