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
      $io->say("Hello, world");
    }
  }

  /**
   * Write out local Drupal debugging files. To call.
   *
   * @example vendor/bin/robo drupal:enable-debugging
   */
  public function enableDrupalDebugging() {

    $this->say("Enabling local debugging for Drupal.");
    $this->say("This will write out the following files:");
    $this->say("  - web/sites/default/services.local.yml");
    $this->say("  - web/sites/default/settings.local.php");
    $this->writeSettingsFile();
    $this->updateServicesFile();

    // Clear caches.
    $this->taskExec("vendor/bin/drush cr")->run();
  }

  /**
   *
   */
  private function writeSettingsFile() {
    // Prepare Drupal settings file.
    $this->_touch('web/sites/default/settings.local.php');
    $existing = file_get_contents("web/sites/default/settings.local.php");

    // Our debug settings.
    $lines = [
      "<?php",
      "\$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/default/services.local.yml';",
      "\$settings['cache']['bins']['render'] = 'cache.backend.null';",
      "\$settings['cache']['bins']['page'] = 'cache.backend.null';",
      "\$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';",
      "\$config['system.performance']['css']['preprocess'] = FALSE;",
      "\$config['system.performance']['js']['preprocess'] = FALSE;",
      "\$config['simplesamlphp_auth.settings']['activate'] = FALSE;",
      "\$settings['config_exclude_modules'] = ['devel', 'stage_file_proxy'];",
    ];

    // Append missing settings.
    foreach ($lines as $line) {
      if (stripos($existing, $line) === FALSE) {
        $this->taskWriteToFile("web/sites/default/settings.local.php")
          ->append(TRUE)
          ->line($line)
          ->run();
      }
    }
  }

  /**
   *
   */
  private function updateServicesFile() {
    // Prepare debug service file.
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

    // Merge settings.
    $existing = Yaml::parseFile("web/sites/default/services.local.yml") ?? [];
    $settings = Yaml::dump($lines + $existing);

    // Write out merged settings.
    $this->taskWriteToFile("web/sites/default/services.local.yml")
      ->line($settings)
      ->run();
  }

}
