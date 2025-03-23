# Meyer Robo

Global Robo commands for Drupal projects.

This provides utility commands to simplify development workflows. The primary
command is `meyer drupal:enable-debugging [site-directory]`. The command writes
out `settings.local.php` and `services.local.yml` files with standard debugging
configurations, to ease servicing projects.

## ⚙️ Setup for Use Across Projects (Global Composer)

Edit your global `~/.composer/composer.json` and ensure it includes this
project:

```json
{
  "minimum-stability": "dev",
  "prefer-stable": true,
  "require": {
    "meyer/meyer-robo": "*"
  },
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

Then update your Composer global:

```bash
composer global update
```

Confirm the `meyer` command is available:

```bash
which meyer
# Should return: /Users/meyer/.composer/vendor/bin/meyer
```

Ensure the CLI launcher is executable:

In the root of this project, ensure the `meyer` file is marked as executable:

```bash
chmod +x /Users/meyer/www/GitHub/meyer_robo/meyer && composer global update
```

## 🧪 Development Notes

Changes made in this project will reflect immediately in your global environment (because of the `symlink: true` option).

This approach keeps the command globally available while allowing for active local development.

Requires Robo (`consolidation/robo`) and Symfony YAML (`symfony/yaml`), already included in `composer.json`.
