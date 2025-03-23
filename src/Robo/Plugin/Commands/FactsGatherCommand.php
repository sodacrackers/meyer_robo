<?php

namespace IfRobo\Robo\Plugin\Commands;

use Robo\Tasks;

/**
 * Robo commands to collect env information.
 *
 * Gathers drupal and composer facts and writes to a json file. The file is
 * pushed to Amazee Graph API via lagoo-facts-gather.
 *
 * @see .lagoon.yml
 * @see https://github.com/uselagoon/lagoon-facts-app
 * @see https://github.com/consolidation/Robo/blob/4.x/examples/src/Robo/Plugin/Commands/ExampleCommands.php
 * @see http://robo.li/
 */
class FactsGatherCommand extends Tasks {
  /**
   * Results file.
   *
   * @var string
   */
  protected ?string $outputFile = NULL;

  /**
   * Print debugging.
   *
   * @var bool
   */
  protected ?bool $debug = FALSE;

  /**
   * Collected facts.
   *
   * @var array
   */
  protected ?array $collection;

  /**
   * Class constructior.
   */
  public function __construct() {
    $this->stopOnFail(FALSE);
  }

  /**
   * Collect project facts.
   *
   * Run drush, database and composer routines to gather project information,
   * and optionally save results to a file.
   *
   * @param array $options
   *   A collection of command options.
   *
   * @option debug Print task information.
   * @option composer Gather composer information.
   * @option drush Gather drush information.
   * @option content Gather content information.
   * @option result-file The file to save results to.
   *
   * @example vendor/bin/robo ifsight:gather-facts --composer
   * @example vendor/bin/robo ifsight:gather-facts --composer --drush --content --debug
   */
  public function ifsightGatherFacts(array $options = [
    "debug" => FALSE,
    "composer" => FALSE,
    "drush" => FALSE,
    "content" => FALSE,
    "result-file" => "",
  ]) {
    $this->debug = $options["debug"];
    $this->outputFile = $options["result-file"];
    $this->emptyCollection();

    if ($options["composer"]) {
      $this->runComposerCommands();
    }

    if ($options["drush"]) {
      $this->runDrushCommands();
    }

    if ($options["content"]) {
      $this->runDatabaseCommands();
    }

    $this->saveCollection();
  }

  /**
   * Run a Robo task.
   */
  private function tryCommand(string $task = "", string $args = "--format=json"): array {
    $result = $this
      ->taskExec($task . " " . $args)
      ->printOutput(FALSE)
      ->run();

    $message = $result->getMessage();

    if ($result->wasSuccessful()) {
      $data = json_decode($message, TRUE);
      if (is_array($data)) {
        return $data;
      }
      return [$message];
    }

    $this->print("Failed to execute: $task\n" . $message . "\n");
    return [];
  }

  /**
   * Run a vendor command.
   */
  private function tryVendorCommand(string $command = ""): array {
    return $this->tryCommand("vendor/bin/$command");
  }

  /**
   * Gather composer information.
   */
  public function runComposerCommands() {
    // Gather installs.
    $result = $this->tryCommand("composer show --direct");
    foreach ($result["installed"] as $value) {
      $this->appendCollection($value["name"], $value["version"], "Installed version", "Composer package");
    }

    // Gather updates.
    $result = $this->tryCommand("composer outdated --direct");
    foreach ($result["installed"] as $value) {
      $this->appendCollection($value["name"], $value["latest"], "Latest version", "Composer package");
    }

    // Gather minor updates.
    $result = $this->tryCommand("composer outdated --direct --minor-only");
    foreach ($result["installed"] as $value) {
      $this->appendCollection($value["name"], $value["latest"], "Latest minor version", "Composer package");
    }
  }

  /**
   * Gather drupal information.
   */
  public function runDrushCommands() {
    // Gather module list.
    $result = $this->tryVendorCommand("drush pm:list");
    foreach ($result as $key => $value) {
      $this->appendCollection($value["display_name"], $value["version"], $value["status"], "Drupal extension");
    }

    // Gather status.
    $result = $this->tryVendorCommand("drush status");
    $result = $this->prefixAndFlatten($result);
    foreach ($result as $key => $value) {
      $this->appendCollection($key, $value, "Drush status", "Drupal configuration");
    }

    // Gather performance settings.
    $result = $this->tryVendorCommand("drush config:get system.performance");
    $result = $this->prefixAndFlatten($result);
    foreach ($result as $key => $value) {
      $this->appendCollection($key, $value, "Performance config", "Drupal configuration");
    }

    // Gather smtp settings.
    $result = $this->tryVendorCommand("drush config:get smtp.settings");
    $result = $this->prefixAndFlatten($result);
    foreach ($result as $key => $value) {
      $this->appendCollection($key, $value, "SMTP config", "Drupal configuration");
    }

    // Gather config status.
    $result = $this->tryVendorCommand("drush config:status");
    $result = $this->groupByKey($result, "state");
    foreach ($result as $key => $value) {
      $this->appendCollection($key, $value, "Config status", "Drupal configuration");
    }

    // Gather core requirements.
    $result = $this->tryVendorCommand("drush core:requirements");
    $result = $this->groupByKey($result, "severity");
    foreach ($result as $key => $value) {
      $this->appendCollection($key, $value, "Core requirements", "Drupal configuration");
    }

    // Gather config ignore list.
    $result = $this->tryVendorCommand("drush config:get config_ignore.settings");
    $result = implode("|", $result["ignored_config_entities"] ?? []);
    $this->appendCollection("ignored_config_entities", $result, "Config ignore entities", "Drupal configuration");

  }

  /**
   * Gather database information.
   */
  private function runDatabaseCommands() {
    // Files count.
    $result = $this->tryDrushQuery("SELECT COUNT(*) FROM file_managed");
    $this->appendCollection("Files count", reset($result), "Content query", "Content");

    // Files size.
    $result = $this->tryDrushQuery("SELECT SUM(filesize) FROM file_managed");
    $this->appendCollection("Files size", reset($result), "Content query", "Content");

    // Fields count.
    $result = $this->tryDrushQuery("SELECT COUNT(*) FROM information_schema.tables
      WHERE table_name LIKE \"%field%\" AND table_name NOT REGEXP \"(deleted|old|revision)\"");
    $this->appendCollection("Fields count", reset($result), "Content query", "Content");

    // Node types.
    $result = $this->tryDrushQuery("SELECT COUNT(*) FROM node_type");
    $this->appendCollection("Node types count", reset($result), "Content query", "Content");

    // Node types2.
    $result = $this->tryDrushQuery("SELECT COUNT(DISTINCT type) FROM node");
    $this->appendCollection("Node types count2", reset($result), "Content query", "Content");

    // Nodes count.
    $result = $this->tryDrushQuery("SELECT COUNT(*) FROM node");
    $this->appendCollection("Nodes count", reset($result), "Content query", "Content");

    // Users count.
    $result = $this->tryDrushQuery("SELECT COUNT(*) FROM users");
    $this->appendCollection("Users count", reset($result), "Content query", "Content");

    // Paragraph types count.
    $result = $this->tryDrushQuery("SELECT COUNT(DISTINCT type) FROM paragraph_type");
    $this->appendCollection("Paragraph types count", reset($result), "Content query", "Content");

    // Paragraph types count2.
    $result = $this->tryDrushQuery("SELECT COUNT(DISTINCT type) FROM paragraphs_item");
    $this->appendCollection("Paragraph types count v2", reset($result), "Content query", "Content");

    // Paragraph types count3.
    $result = $this->tryDrushQuery("SELECT COUNT(DISTINCT type) FROM paragraphs_item_field_data");
    $this->appendCollection("Paragraph types count v3", reset($result), "Content query", "Content");

    // Paragraphs count.
    $result = $this->tryDrushQuery("SELECT COUNT(*) FROM paragraph");
    $this->appendCollection("Paragraphs count", reset($result), "Content query", "Content");

    // Paragraphs count2.
    $result = $this->tryDrushQuery("SELECT COUNT(DISTINCT id) FROM paragraphs_item");
    $this->appendCollection("Paragraphs count v2", reset($result), "Content query", "Content");

    // Block types count.
    $result = $this->tryDrushQuery("SELECT COUNT(DISTINCT type) FROM block_content_type");
    $this->appendCollection("Block types count", reset($result), "Content query", "Content");

    // Block types count2.
    $result = $this->tryDrushQuery("SELECT COUNT(DISTINCT type) FROM block_content");
    $this->appendCollection("Block types count v2", reset($result), "Content query", "Content");

    // Blocks count.
    $result = $this->tryDrushQuery("SELECT COUNT(DISTINCT id) FROM block_content");
    $this->appendCollection("Blocks count", reset($result), "Content query", "Content");
  }

  /**
   * Prepare object for GraphQL.
   *
   * @param string $name
   *   The value to save.
   * @param ?string $value
   *   The value to save.
   * @param string $source
   *   The value to save.
   * @param string $category
   *   The value to save.
   *
   * @see https://github.com/uselagoon/lagoon/blob/main/services/api/database/migrations/20220908065119_initial_db.js#L116C30-L116C30
   */
  private function appendCollection(string $name = "", ?string $value = NULL, string $source = "", string $category = "Custom") {
    $item = [
      "name" => $this->formatValue($name, 300),
      "value" => $this->formatValue($value, 300),
      "source" => $this->formatValue($source, 300),
      "category" => $this->formatValue($category),
      "environment" => getenv("LAGOON_ENVIRONMENT_TYPE") ?? "",
      "keyFact" => "false",
      "description" => "IFRobo gathered " . date("r"),
    ];

    // Ensure non-duplicate keys.
    $uniqueKey = $item["environment"] . $item["source"] . $item["name"];
    $this->collection[$uniqueKey] = $item;

    $this->print($item, TRUE);
  }

  /**
   * Run a database query.
   */
  private function tryDrushQuery(string $query = ""): array {
    return $this->tryVendorCommand("drush php:eval \"return
      \Drupal::service('database')
      ->query('$query')
      ->fetchField()
      \"");
  }

  /**
   * Print a message.
   */
  private function print(mixed $message = "") {
    if ($this->debug) {
      $message = is_string($message) ? $message : print_r($message, TRUE);
      $this->writeln("\n\n" . $message . "\n\n");
    }
  }

  /**
   * Empty current data.
   */
  private function emptyCollection() {
    $this->collection = [];
    if ($this->outputFile) {
      file_put_contents($this->outputFile, "");
    }
  }

  /**
   * Save current data.
   */
  private function saveCollection() {
    $this->print("Saving to " . $this->outputFile);
    if ($this->outputFile) {
      $string = json_encode(array_values($this->collection), JSON_PRETTY_PRINT) ?? "";
      file_put_contents($this->outputFile, $string);
    }
  }

  /**
   * Flatten array.
   *
   * @param array $data
   *   The array to flatten.
   * @param string $prefix
   *   The prefix to add to each key.
   *
   * @return array
   *   The flattened array.
   */
  private function prefixAndFlatten(array $data = [], $prefix = ""): array {
    $result = [];
    foreach ($data as $key => $value) {
      $newKey = trim($prefix . "." . $key, ".");
      if (is_array($value)) {
        $result = $result + self::prefixAndFlatten($value, $newKey);
      }
      else {
        $result[$newKey] = $value;
      }
    }
    return $result;
  }

  /**
   * Collect all values by a matching property.
   *
   * @param array $data
   *   The array of objects.
   * @param string $property
   *   The field to group results by.
   *
   * @return array
   *   The new array of grouped objects.
   */
  private function groupByKey(array $data, string $property = ""): array {
    $groups = [];

    foreach ($data as $key => $value) {
      $groupName = $value[$property];

      if (!isset($groups[$groupName])) {
        $groups[$groupName] = "";
      }

      $groups[$groupName] .= "$key|";
    }

    return $groups;
  }

  /**
   * Clean string.
   *
   * @param mixed $value
   *   The value to be tidied.
   * @param ?int $length
   *   The maximum string length.
   *
   * @return string
   *   The cleaned string.
   */
  public function formatValue(mixed $value, ?int $length = NULL): string {
    if (is_string($value)) {
      $string = strip_tags($value);
    }
    else {
      $string = json_encode($value);
    }
    if ($length && $string) {
      $string = substr($string, 0, $length);
    }
    return $string ? $string : "";
  }

}
