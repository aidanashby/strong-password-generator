# Strong Password Generator

A WordPress plugin that generates strong, memorable passwords via a shortcode. Supports two modes: word-based passphrases and random character strings.

## Features

- **Passphrase mode** — combines words from the [EFF short wordlist](https://www.eff.org/dice) with a digit and special character. Passwords are memorable and human-readable.
- **Super strong mode** — generates a random character string from configurable character sets, with ambiguous characters (`0`, `O`, `I`, `l`, `1`, `|`) always excluded.
- Cryptographically secure random generation (`random_int()`).
- Admin settings page under **Settings > Strong Password Generator**.
- One-click copy to clipboard.
- Updates via the standard WordPress updater.

## Usage

Add the shortcode to any page or post:

```
[password_generator]
```

A button will appear that generates passwords on click.

## Settings

Go to **Settings > Strong Password Generator** in the WordPress admin to configure:

| Setting | Options |
|---|---|
| Passwords shown | 1–10 |
| Mode | Passphrase / Super strong |
| **Passphrase:** Word count | 2–6 (default 4) |
| **Passphrase:** Separator | Hyphen, dot, underscore, space, none |
| **Passphrase:** Title case | On/off |
| **Passphrase:** Include digit | On/off |
| **Passphrase:** Special character | Random, !, ?, #, () |
| **Super strong:** Character count | 8–64 (default 16) |
| **Super strong:** Character types | Uppercase, lowercase, digits, special characters |

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Installation

1. Download the latest release zip from the [Releases](https://github.com/aidanashby/strong-password-generator/releases) page.
2. In WordPress admin, go to **Plugins > Add New > Upload Plugin**.
3. Upload the zip and activate.

Future updates will appear automatically in **Dashboard > Updates**.

## Licence

[MIT](LICENSE)
