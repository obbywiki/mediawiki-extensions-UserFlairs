# UserFlairs

Adds the ability to set flairs for user groups similar to Discourse. Flairs are small images that appear next to a user's profile picture (if a supported extension is installed) that quickly signify what group that user is a member of. This could be the site logo for administrators, or a shield icon for moderators, etc.

Read below for how to install. This extension will still work without a companion extension, but will visually do nothing.

## Installation

### Requirements

* MediaWiki 1.43
* Either [IntegratedProfiles](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles) or [UserProfileV2](https://github.com/obbywiki/mediawiki-extensions-UserProfileV2) enabled (more support exists for IP)

### Setup

1. Place this extension in `extensions/UserFlairs`.
2. Add `wfLoadExtension( 'UserFlairs' );` to `LocalSettings.php`.
3. Run `maintenance/update.php`.

Administrators get the `userflairs-manage` right by default, but you'll have to assign any other role manually.

## Usage

TODO
