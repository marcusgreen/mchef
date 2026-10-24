<?php
// Applies site configuration from a JSON file produced by mchef.
// Reads component => [name => value] pairs and calls set_config() for each.
// "core" component maps to mdl_config (no component arg); all others map to mdl_config_plugins.
//
// Usage:
//   MCHEF_SITE_CONFIG_PATH=/tmp/config.json sudo -u www-data php admin/cli/set_config_mchef.php

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(['help' => false], ['h' => 'help']);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)));
}

if ($options['help']) {
    echo <<<EOL
Apply site configuration from mchef recipe postInstall.siteConfig block.

JSON format:
  {
    "core": { "smtphosts": "smtp.example.org" },
    "tool_dataprivacy": { "contactdataprotectionofficer": "1" }
  }

Options:
  -h, --help    Print this help

Example:
  \$MCHEF_SITE_CONFIG_PATH=/tmp/config.json sudo -u www-data /usr/bin/php admin/cli/set_config_mchef.php

EOL;
    exit(0);
}

$configPath = getenv('MCHEF_SITE_CONFIG_PATH');
if (empty($configPath)) {
    cli_error('MCHEF_SITE_CONFIG_PATH environment variable is not set.');
}

if (!file_exists($configPath)) {
    cli_error("Config file not found: {$configPath}");
}

$raw = file_get_contents($configPath);
// The file may hold secrets (e.g. smtppass) - remove it as soon as it has been read.
@unlink($configPath);
if ($raw === false) {
    cli_error("Failed to read config file: {$configPath}");
}

$config = json_decode($raw, true);
if ($config === null) {
    cli_error("Failed to parse config JSON: " . json_last_error_msg());
}

if (!is_array($config)) {
    cli_error("Config JSON must be an object (component => settings map).");
}

$total = 0;

foreach ($config as $component => $settings) {
    if (!is_array($settings)) {
        cli_error("Settings for component '{$component}' must be an object.");
    }

    foreach ($settings as $name => $value) {
        if ($component === 'core') {
            set_config($name, $value);
            mtrace("set_config({$name}) = " . redact_if_sensitive($name, $value));
        } else {
            set_config($name, $value, $component);
            mtrace("set_config({$name}, component={$component}) = " . redact_if_sensitive($name, $value));
        }
        $total++;
    }
}

mtrace("Applied {$total} configuration setting(s).");
exit(0);

function redact_if_sensitive(string $name, mixed $value): string {
    if (preg_match('/key|secret|token|password/i', $name)) {
        return '***';
    }
    return (string) $value;
}
