# 15 — UI/UX DESIGN SYSTEM (tokens summary)

Palette (trust + premium): primary indigo-600 #4F46E5, accent teal-500 #14B8A6, success emerald-600, warning amber-500, danger rose-600, neutrals slate scale. Typography: Inter (latin) + system fallback; display 700/1.1, body 400/1.6. Radius: 8/12/16/999. Shadow: sm/md/lg soft. Spacing 4pt grid. Breakpoints: sm 640 / md 768 / lg 1024 / xl 1280. Motion: 150–250ms ease-out; respects prefers-reduced-motion. Z-scale: dropdown 30, drawer 40, modal 50, toast 60.

Components (Blade + Tailwind, `resources/views/components/ui/*`): button (primary/secondary/ghost/danger, loading), input/textarea/select/autocomplete/search, card/service-card/vendor-card, badge/chip, table/data-grid, tabs, drawer, bottom-sheet (mobile), modal, stepper, timeline, toast, alert, skeleton, empty-state, error-state, pagination, date/time picker, upload, rating, stat-card, chart-container, chat-bubble, transaction-card.

Accessibility WCAG 2.2 AA target: semantic landmarks, focus-visible rings, contrast ≥4.5, touch ≥44px, form errors tied to inputs (aria-describedby), full keyboard nav.
