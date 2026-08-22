# UserFlairs

Discourse-inspired flairs for MediaWiki user groups. Admins assign a wiki image to a group; the highest-priority flair is shown on the user's profile picture.

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