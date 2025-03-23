<?php
$settings['container_yamls'][] = DRUPAL_ROOT . '/docroot/sites/default/services.local.yml';
$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['page'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;
$config['simplesamlphp_auth.settings']['activate'] = FALSE;
$settings['config_exclude_modules'] = ['devel', 'stage_file_proxy'];
