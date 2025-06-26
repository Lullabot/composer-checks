## Composer configuration checks

To promote best practices within Composer projects, we run a few checks after running Composer commands.

It adds the following checks when running `composer install` or `composer update`.

### More information
* https://architecture.lullabot.com/adr/20220429-composer-patchlevel/
* https://architecture.lullabot.com/adr/20220429-composer-patches-inline/
* https://architecture.lullabot.com/adr/20220429-composer-patch-files/
* https://architecture.lullabot.com/adr/20220429-composer-exit-failure/


## Installation

Add to your `composer.json` file

```
    "repositories": [
        {
            "type": "git",
            "url": "https://github.com/Lullabot/composer-checks.git"
        }
    ]
```

```
    "config": {
        "allow-plugins": {
            "lullabot/composer-checks": true
        }
    }
```
Run the command:
```
composer require lullabot/composer-checks
```

### Check: Configure Composer Patches to Use `-p2` as `patchLevel` for Drupal core

> Drupal's git repository has a different directory structure than projects built on Drupal. Default Composer Patches settings can cause Drupal patches to be silently misapplied.
See https://architecture.lullabot.com/adr/20220429-composer-patchlevel/

To make this check a warning instead of an error, make sure the following value exists in `extra.composer-checks` path: "disable-drupal-core-patches-level-check"

### Check: Break composer install if patches don't apply

> Drupal's git repository has a different directory structure than projects built on Drupal. Default Composer Patches settings can cause Drupal patches to be silently misapplied.
See https://architecture.lullabot.com/adr/20220429-composer-exit-failure/


To make this check a warning instead of an error, make sure the following value exists in `extra.composer-checks` path: "disable-exit-on-patch-failure-check"

### Check: Store Composer Patches configuration in composer.json

> Validating a complete Composer configuration is important to ensuring build issues are caught early.
See https://architecture.lullabot.com/adr/20220429-composer-patches-inline/

To make this check a warning instead of an error, make sure the following value exists in `extra.composer-checks` path: "disable-patches-file-check"

### Check: Use local copies of patch files

> When using [cweagans/composer-patches](https://github.com/cweagans/composer-patches), it is important that patch sources are consistent and do not change between builds.
See https://architecture.lullabot.com/adr/20220429-composer-patch-files/

To make this check a warning instead of an error, make sure the following value exists in `extra.composer-checks` path: "disable-local-patches-check"
