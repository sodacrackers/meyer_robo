<?php

namespace Meyer\Robo\Plugin\Commands;

use Robo\Tasks;
use Symfony\Component\Yaml\Yaml;

/**
 * Robo Drupal utility commands.
 *
 * @see .lagoon.yml
 * @see https://github.com/uselagoon/lagoon-facts-app
 * @see https://github.com/consolidation/Robo/blob/4.x/examples/src/Robo/Plugin/Commands/ExampleCommands.php
 * @see http://robo.li/
 *
 * Robo can execute commands from a different RoboFile, eg. located in different
 * directory or with a different filename. You can specify the path to another
 * RoboFile by including the --load-from option:
 * robo run --load-from /path/to/my/other/robofile
 */
class DrupalDebugCommands extends Tasks {

  /**
   * Constructs the Robo Tasks for Drupal projects.
   */
  public function __construct() {
    $this->stopOnFail(FALSE);
  }

  /**
   * @command drupal:find-sites
   */
  public function findSites() {
    $cwd = getenv('PWD');
    $this->say("Finding settings.php files...");
    $this->say("Current directory: $cwd");

    $res = $this->taskExec('find')
      ->args([
        $cwd,
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
      ])
      ->printOutput(FALSE)
      ->run();

    if ($res->getExitCode() !== 0) {
      $this->say("Error running find command.");
      return;
    }

    // This will contain the output from find.
    $output = trim($res->getMessage());
    if (empty($output)) {
      $this->say("No settings.php files found.");
      return;
    }

    // Convert to array.
    $directories = [];
    $files = array_filter(explode("\n", $output));

    $this->say("Found " . count($files) . " settings.php files:");
    foreach ($files as $file) {
      $this->say(" - $file");
      $directories[] = dirname($file);
    }

    return $directories;
  }

  /**
   * Write out local Drupal debugging files.
   *
   * @param string $siteDir
   *   Relative site path (e.g. "web/sites/default").
   *
   * @command drupal:enable-debugging
   */
  public function enableDrupalDebugging($siteDir = 'web/sites/default') {
    $this->say("Enabling debugging in $siteDir");

    $this->writeSettingsFile($siteDir);
    $this->updateServicesFile($siteDir);

  }

  /**
   *
   */
  private function writeSettingsFile($siteDir) {
    $file = "$siteDir/settings.local.php";
    // $this->taskFilesystemStack()
    //   ->touch($file)
    //   ->run();
    // Check if the file exists.
    if (!file_exists($file)) {
      $this->_touch($file);
      file_put_contents($file, "<?php\n");
    }

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
   *
   */
  private function updateServicesFile($siteDir) {
    $file = "$siteDir/services.local.yml";

    $lines = Yaml::parse(<<<YML
parameters:
  http.response.debug_cacheability_headers: true
  twig.config:
    cache: false
    debug: true
    auto_reload: true
services:
  cache.backend.null:
    class: Drupal\Core\Cache\NullBackendFactory
YML);

    $existing = file_exists($file) ? Yaml::parseFile($file) : [];
    $settings = Yaml::dump($lines + $existing);

    $this->taskWriteToFile($file)
      ->line($settings)
      ->run();
  }

}
