<?php

namespace Meyer\Robo\Plugin\Commands;

use Robo\Tasks;
use Symfony\Component\Yaml\Yaml;

/**
 * Robo Drupal utilities.
 *
 * @see http://robo.li/
 * @see https://symfony.com/doc/current/components/yaml.html
 * @see https://github.com/consolidation/Robo/blob/4.x/examples/src/Robo/Plugin/Commands/ExampleCommands.php
 */
class DrupalDebugCommands extends Tasks {

  /**
   * Construct a Robo Task runner.
   */
  public function __construct() {
    $this->stopOnFail(FALSE);
  }

  /**
   * Find Drupal site directories.
   *
   * @command meyer:find-drupal-sites
   */
  public function findSites() {
    $this->say('Finding settings.php files...');

    $res = $this->taskExec('find')
      ->args(
              [
                getenv('PWD'),
                '-name',
                'settings.php',
                '-type',
                'f',
                '-path',
                '*/sites/*',
                '-not',
                '-path',
                '*/core/*',
                '-not',
                '-path',
                '*/modules/*',
                '-not',
                '-path',
                '*/vendor/*',
                '-not',
                '-path',
                '*/node_modules/*',
              ]
          )
      ->printOutput(FALSE)
      ->run();

    // This will contain the output from find.
    $output = trim($res->getMessage());

    // Convert to array.
    $directories = [];
    $files = array_filter(explode("\n", $output));
    foreach ($files as $file) {
      $directories[] = dirname($file);
    }

    return $directories;
  }

  /**
   * Create debug settings files.
   *
   * @command meyer:enable-drupal-debugging
   */
  public function enableDrupalDebugging() {
    $this->io()->title("\nAdding Drupal development settings.");
    if (empty($siteDir)) {
      $directories = $this->findSites() ?: [];

      $this->say('Found ' . count($directories) . ' settings.php files:');
      foreach ($directories as $i => $dir) {
        $this->say("  [$i] $dir");
      }
      $this->say('  [all] Apply to all directories');

      $choice = $this->ask('Please select a site directory (0-' . (count($directories) - 1) . ' or "all"):');

      if ($choice === 'all') {
        foreach ($directories as $dir) {
          $this->io()->title("\nEnabling debugging in $dir");
          $this->writeSettingsFile($dir);
          $this->updateServicesFile($dir);
        }
        return;
      }

      $siteDir = $directories[$choice] ?? NULL;
    }

    if (empty($siteDir)) {
      $this->yell('Invalid selection.');
      return;
    }

    $this->say('Enabling debugging in ' . $siteDir);
    $this->writeSettingsFile($siteDir);
    $this->updateServicesFile($siteDir);
  }

  /**
   * Write out settings.local.php.
   *
   * @param string $siteDir
   *   The site directory to write the settings file to.
   *
   * @command meyer:write-settings
   */
  private function writeSettingsFile($siteDir) {
    $file = $siteDir . '/settings.local.php';
    $this->taskExec('touch')
      ->args($file)
      ->printOutput(FALSE)
      ->run();

    $existing = file_get_contents($file);

    $lines = [
      "<?php",
      "\$settings['container_yamls'][] = DRUPAL_ROOT . '/$siteDir/services.local.yml';",
      "\$settings['cache']['bins']['render'] = 'cache.backend.null';",
      "\$settings['cache']['bins']['page'] = 'cache.backend.null';",
      "\$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';",
      "\$config['system.performance']['css']['preprocess'] = FALSE;",
      "\$config['system.performance']['js']['preprocess'] = FALSE;",
      "\$config['simplesamlphp_auth.settings']['activate'] = FALSE;",
      "\$settings['config_exclude_modules'] = ['devel', 'stage_file_proxy'];",
    ];

    foreach ($lines as $line) {
      if (stripos($existing, $line) === FALSE) {
        $this->taskWriteToFile($file)
          ->append(TRUE)
          ->line($line)
          ->run();
      }
    }
  }

  /**
   * Write out services.local.yml.
   *
   * @param string $siteDir
   *   The site directory to write the settings file to.
   *
   * @command meyer:write-services
   */
  private function updateServicesFile($siteDir) {
    $file = $siteDir . '/services.local.yml';
    $this->taskExec('touch')
      ->args($file)
      ->printOutput(FALSE)
      ->run();

    $lines = Yaml::parse(
        <<<YML
        parameters:
          http.response.debug_cacheability_headers: true
          twig.config:
            cache: false
            debug: true
            auto_reload: true
        services:
          cache.backend.null:
            class: Drupal\Core\Cache\NullBackendFactory
        YML
      );

    $existing = Yaml::parseFile($file);
    $existing = $existing ?? [];
    $settings = Yaml::dump($lines + $existing);

    $this->taskWriteToFile($file)
      ->line($settings)
      ->run();
  }

}
