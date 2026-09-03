# UserFlairs

Adds the ability to set flairs for user groups similar to Discourse. Flairs are small images that appear next to a user's profile picture (if a supported extension is installed) that quickly signify what group that user is a member of. This could be the site logo for administrators, or a shield icon for moderators, etc.

Read below for how to install. This extension will still work without a companion extension, but will visually do nothing.

## Installation

### Requirements

* MediaWiki 1.43
* Either [IntegratedProfiles](https://github.com/obbywiki/mediawiki-extensions-IntegratedProfiles) or [UserProfileV2](https://github.com/obbywiki/mediawiki-extensions-UserProfileV2) enabled (more support exists for IP)
*** If you do not have either one of these extensions install and are not using Citizen, then this extension will do nothing more than add a configuration page and an API.

### Setup

1. Place this extension in `extensions/UserFlairs`.
2. Add `wfLoadExtension( 'UserFlairs' );` to `LocalSettings.php`.
3. Run `maintenance/update.php`.

Administrators get the `userflairs-manage` right by default, but you'll have to assign it to any other user group manually.

## Usage

Users with the `userflairs-manage` right can use `Special:UserFlairs` to customize flairs on their wiki. This page offers three main controls:

* Assign a flair (image) to an available user group
* Modify an existing flair for a user group
* Re-order all groups in order to sort the priority of which flairs already display

If the user is in multiple groups which have configured flairs, then the highest flair will be used. This means you should not structure groups by power level. For example, you may want to put the bot group above administrator, so that a bot should inherit the bot flair even if it is an administrator.
