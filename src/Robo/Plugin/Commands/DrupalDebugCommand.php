<?php

namespace Meyer\Robo\Plugin\Commands;

use Robo\Tasks;
use Robo\Symfony\ConsoleIO;
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
class DrupalDebugCommand extends Tasks {

  /**
   * Constructs the Robo Tasks for Drupal projects.
   */
  public function __construct() {
    $this->stopOnFail(FALSE);
  }

  /**
   *
   */
  public function hello(ConsoleIO $io, $opts = ['silent|s' => FALSE]) {
    if (!$opts['silent']) {
      $io->say("Hello, YOU!");
    }
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

    // Check if the file exists
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
