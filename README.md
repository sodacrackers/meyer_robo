# Meyer Robo

Global Robo commands for Drupal projects.

This utility provides Robo commands to simplify Drupal development tasks, such as enabling debugging configurations for Drupal sites. The primary command provided by this utility is `robo meyer:enable-drupal-debugging`, which generates a `settings.local.php` and `services.local.yml` with standard debugging configurations, making it easier to service Drupal projects.


For more about Robo, see:

- [Robo Documentation](https://robo.li/)

## ⚙️ Setup for Global Use (Global Composer)

To use this utility globally across projects, follow these steps:

1. Edit your global `composer.json` file to include this project. You can find the global Composer configuration path by running:

   ```bash
   composer global config home
   ```

   Add the following configuration to your `composer.json`:

   ```json
   {
     "minimum-stability": "dev",
     "prefer-stable": true,
     "repositories": [
       {
         "type": "path",
         "url": "/Users/meyer/www/GitHub/meyer_robo",
         "options": {
           "symlink": true
         }
       }
     ]
   }
   ```

2. Update your global Composer installation:

   ```bash
   composer global update && composer global require "meyer/meyer-robo"
   ```

3. Confirm that the `robo` command is available:

   ```bash
   which robo
   # Expected output: /Users/.../.composer/vendor/bin/robo
   ```

4. Verify that the `meyer:...` commands are listed:

   ```bash
   robo list
   ```

## Commands Overview

### `meyer:enable-drupal-debugging`

This command enables debugging for a specified Drupal site directory or all detected site directories. It creates or updates the following files:

- `settings.local.php`: Configures debugging settings for the site.
- `services.local.yml`: Configures service overrides for debugging.

### `meyer:find-drupal-sites`

This command scans the current directory for Drupal site directories containing `settings.php` files. It excludes common directories like `core`, `modules`, `vendor`, and `node_modules`.

For more details on available commands, run:

```bash
robo list
```
