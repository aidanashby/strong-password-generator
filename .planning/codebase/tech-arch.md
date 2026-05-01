# Strong Password Generator — Tech + Architecture Scan

_Generated: 2026-05-01_

---

## Overview

Single-file WordPress plugin. Adds a `[password_generator]` shortcode that renders a button; clicking it fires an AJAX request to generate passwords server-side and display them with a one-click clipboard copy. Two generation modes: word-based passphrases and random-character "super strong" strings. Fully configurable via a Settings page.

---

## File Structure

```
strong-password-generator/
├── strong-password-generator.php   # All plugin logic (single class)
├── js/password-generator.js        # jQuery AJAX + DOM rendering
├── css/password-generator.css      # Scoped styles for the shortcode widget
├── words.txt                       # Word list (EFF short wordlist)
├── includes/
│   └── plugin-update-checker/      # Third-party: YahnisElsts PUC v5.6
├── .github/workflows/release.yml   # GitHub Actions release workflow
└── README.md
```

---

## Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP 7.4+ (typed properties, named array destructuring) |
| WordPress | 6.0+ |
| Randomness | PHP `random_int()` — CSPRNG, no `rand()`/`mt_rand()` |
| Front-end | jQuery (WP bundled), vanilla `navigator.clipboard` API |
| Update mechanism | YahnisElsts Plugin Update Checker v5.6 via GitHub Releases |
| CI | GitHub Actions (release workflow) |

---

## Architecture

### Class design

Everything lives in a single class `Strong_Password_Generator` instantiated once at plugin load. No namespacing. No autoloader beyond the bundled PUC library.

```
Strong_Password_Generator
 ├── __construct()             — registers all hooks
 ├── get_settings()            — merges DB option with defaults
 ├── load_words()              — lazy-loads words.txt with static cache
 ├── generate_passphrase()     — word-mode password generation
 ├── generate_superstrong()    — character-mode password generation
 ├── generate_passwords_callback() — AJAX handler (public + logged-in)
 ├── password_generator_shortcode() — enqueues assets, returns HTML
 ├── admin_menu() / admin_init() / admin_page() — settings UI
 ├── sanitise_settings()       — settings sanitisation on save
 └── plugin_action_links()     — adds Settings link in plugin list
```

### Request flow

```
User clicks button
  → JS POST to admin-ajax.php (action=generate_passwords, nonce)
    → check_ajax_referer() validates nonce
      → get_settings() reads spg_settings option
        → generate_passphrase() or generate_superstrong()
          → wp_send_json_success( $passwords )
    → JS renders <ul> of password items with copy buttons
```

### Settings storage

Single `spg_settings` option (serialised array). Merged with `$defaults` on read so new settings keys are always available without migration. Sanitised via `sanitise_settings()` on save — all values are allowlisted or range-clamped.

---

## Security

| Concern | Implementation |
|---|---|
| AJAX nonce | `check_ajax_referer('password_generator_nonce','nonce')` on every request |
| Admin capability check | `current_user_can('manage_options')` gate in `admin_page()` |
| Output escaping | `esc_html__()` / `esc_attr()` throughout admin page |
| Settings sanitisation | All inputs allowlisted or int-clamped in `sanitise_settings()` |
| Randomness | `random_int()` only — CSPRNG throughout |
| ABSPATH guard | Present at top of main file |
| No user input in generation | AJAX handler takes no user-supplied parameters — all config comes from stored settings |

No XSS vectors observed. JS output is DOM-constructed (`.text()` for passwords, not `.html()`), so generated passwords cannot inject markup.

---

## Code Quality

**Strengths:**
- Clean separation of generation logic from WP plumbing
- CSPRNG used correctly throughout (no fallback to weak RNG)
- Fisher-Yates shuffle implemented correctly for superstrong mode
- Without-replacement sampling for passphrase word selection
- Ambiguous character exclusion is explicit and documented in the UI
- Settings merging pattern is defensive (new keys always have defaults)
- All admin HTML uses WP escaping functions consistently

**Weaknesses / Concerns:**

1. **`@file()` suppression** (`strong-password-generator.php:71`) — the `@` operator silences warnings from `file()`. The fallback is correct (returns a `wp_send_json_error`), but silent suppression makes debugging harder. Should use `file_exists()` before `file()` and drop the `@`.

2. **`random_int()` rejection sampling** (`strong-password-generator.php:92-96`) — the without-replacement loop is theoretically correct but has no upper bound guard. With a word list of thousands of entries and `word_count` max of 6, the probability of an infinite loop is negligible in practice, but a `$attempts` counter would make it provably safe.

3. **No `.planning/` or test infrastructure** — no unit tests, no PHP linting config (e.g. PHPCS ruleset). Password generation logic is pure and side-effect-free, making it ideal for unit testing.

4. **Single-file class, no namespace** — acceptable at this scale, but collides with WordPress global namespace. A namespace would be low-effort improvement.

5. **Inline `<script>` in admin page** (`strong-password-generator.php:474-488`) — small JS block for mode toggle is inlined in the admin page template rather than enqueued as a separate file. Functional, but inconsistent with how the front-end JS is handled.

6. **Hard-coded brand colours in CSS** (`.generate-password-btn` uses `#0a3d2e`, hover uses `#00ab52`) — NBSG Foodbank primary colour `#00ab52` matches. Fine for its intended deployment, but the plugin presents as generic. No CSS custom property usage.

7. **jQuery dependency** — `password-generator.js` only needs jQuery for `$()` wrapper and `.ajax()`. Both are easily replaceable with `document.querySelector` and `fetch()`, which would remove the jQuery dependency and work better on themes that defer jQuery.

---

## Update Mechanism

Plugin Update Checker v5.6 (YahnisElsts) is bundled in `includes/`. Configured to pull from `https://github.com/aidanashby/strong-password-generator/` release assets. The "Check for updates" manual link is suppressed via filter so updates surface only through standard WP update UI. The PUC library adds significant file weight (~50 PHP files) relative to the plugin's own code.

---

## Key Observations

- **Cryptographic correctness**: The generation logic is sound. `random_int()` throughout, Fisher-Yates shuffle correct, without-replacement word selection correct.
- **Security posture**: Good for a public-facing tool. Nonce validation, capability checks, and strict sanitisation are all present.
- **Scale fit**: Single-class single-file is appropriate. No over-engineering.
- **Main actionable issues**: Drop `@file()` suppression; consider jQuery removal; add PHPCS config; add namespace.
