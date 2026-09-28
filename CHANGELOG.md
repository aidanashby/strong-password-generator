# Changelog

All notable changes to Strong Password Generator.

## [1.0.2] - 2026-09-28

### Fixed
- The release zip now includes the update library's Parsedown file. A bare `vendor/` rule in `.gitignore` kept it out of every earlier release, which caused the fatal error that 1.0.1 worked around. Release notes now show in the update details again.
- The release zip now contains a `strong-password-generator` folder, so installing it by hand puts the plugin in the right place.
- Updates download the release zip again instead of GitHub's source archive, which had the wrong folder name and counted against GitHub's API limit.

### Added
- Plugin icon on the Plugins and Updates screens.
- Uninstall cleanup: deleting the plugin removes its settings and the update checker's stored data.
- `Update URI` header, so WordPress.org can't offer an unrelated plugin with the same name as an update.

### Changed
- Bundled Plugin Update Checker updated from 5.6 to 5.7 and moved from `includes/` to the plugin root. The 1.0.1 edit to the library is no longer needed and is gone.

## [1.0.1] - 2026-05-01

### Fixed
- Fatal error on Dashboard → Updates when the update check read a release with release notes (`Class "Parsedown" not found`), worked around by skipping the notes when Parsedown is missing.
- Passphrase word selection can no longer loop indefinitely.

### Changed
- The password generator script no longer needs jQuery.

## [1.0.0] - 2026-04-27

- Full rewrite: passphrase and random-character modes, settings page, copy to clipboard.
