<?php
// Creates or updates core_ai provider instances from a JSON file produced by mchef.
// Each entry is matched on provider component + name: existing instances are updated, missing
// ones are created. Instances not listed in the file are left untouched.
// Requires Moodle 5.0+ (core_ai provider instances, MDL-82977). On older versions prints "SKIPPED:"
// and exits 0 - on 4.5 configure providers via siteConfig (e.g. aiprovider_openai/apikey) instead.
//
// Usage:
//   MCHEF_AI_PROVIDERS_PATH=/tmp/providers.json sudo -u www-data php admin/cli/configure_ai_providers_mchef.php

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(['help' => false], ['h' => 'help']);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)));
}

if ($options['help']) {
    echo <<<EOL
Create or update core_ai provider instances from mchef recipe postInstall.aiProviders block.

JSON format:
  [
    {
      "name": "OpenAI",
      "type": "aiprovider_openai",
      "enabled": true,
      "config": { "apikey": "sk-...", "orgid": "" },
      "actionconfig": {
        "generate_text": { "enabled": true, "model": "gpt-4o" },
        "generate_image": { "enabled": false }
      }
    }
  ]

Options:
  -h, --help    Print this help

Example:
  \$MCHEF_AI_PROVIDERS_PATH=/tmp/providers.json sudo -u www-data /usr/bin/php admin/cli/configure_ai_providers_mchef.php

EOL;
    exit(0);
}

$configPath = getenv('MCHEF_AI_PROVIDERS_PATH');
if (empty($configPath)) {
    cli_error('MCHEF_AI_PROVIDERS_PATH environment variable is not set.');
}

if (!file_exists($configPath)) {
    cli_error("Config file not found: {$configPath}");
}

$raw = file_get_contents($configPath);
// The file holds API keys - remove it as soon as it has been read.
@unlink($configPath);
if ($raw === false) {
    cli_error("Failed to read config file: {$configPath}");
}

$providers = json_decode($raw, true);
if (!is_array($providers) || !array_is_list($providers)) {
    cli_error('Config JSON must be a list of provider objects.');
}

// Provider instances arrived in Moodle 5.0. On 4.5 core_ai exists but providers are plugin-level config.
if (!class_exists(\core_ai\manager::class) || !method_exists(\core_ai\manager::class, 'create_provider_instance')) {
    mtrace('SKIPPED: aiProviders requires Moodle 5.0+ (core_ai provider instances). Site version is ' . $CFG->release . '.');
    exit(0);
}

// Provider action setting defaults are built from moodleforms, which expect a page context.
$PAGE->set_context(context_system::instance());

$manager = \core\di::get(\core_ai\manager::class);

foreach ($providers as $index => $entry) {
    foreach (['name', 'type'] as $required) {
        if (empty($entry[$required]) || !is_string($entry[$required])) {
            cli_error("Provider #{$index}: '{$required}' is required and must be a string.");
        }
    }

    $component = $entry['type'];
    $classname = '\\' . $component . '\\provider';
    if (!class_exists($classname) || !is_a($classname, \core_ai\provider::class, true)) {
        cli_error("Provider '{$entry['name']}': {$component} is not an installed AI provider plugin.");
    }

    $enabled = (bool) ($entry['enabled'] ?? true);
    $config = $entry['config'] ?? [];
    $record = $manager->get_provider_record(['provider' => $component, 'name' => $entry['name']]);

    if ($record) {
        $existing = new $classname(
            enabled: (bool) $record->enabled,
            name: $record->name,
            config: $record->config,
            actionconfig: $record->actionconfig ?? '',
            id: $record->id,
        );
        $actionconfig = mchef_merge_action_config($existing->actionconfig, $entry['actionconfig'] ?? [], $entry['name']);
        $provider = $manager->update_provider_instance(
            provider: $existing,
            config: array_merge($existing->config ?? [], $config),
            actionconfig: $actionconfig,
        );
        $provider = $enabled
            ? $manager->enable_provider_instance($provider)
            : $manager->disable_provider_instance($provider);
        $action = 'Updated';
    } else {
        $actionconfig = mchef_merge_action_config(
            $classname::initialise_action_settings(),
            $entry['actionconfig'] ?? [],
            $entry['name']
        );
        $provider = $manager->create_provider_instance(
            classname: $classname,
            name: $entry['name'],
            enabled: $enabled,
            config: $config,
            actionconfig: $actionconfig,
        );
        $action = 'Created';
    }

    $state = $provider->enabled ? 'enabled' : 'disabled';
    mtrace("{$action} AI provider '{$provider->name}' ({$component}, id {$provider->id}, {$state}).");
    if ($provider->enabled && !$provider->is_provider_configured()) {
        mtrace("  Warning: '{$provider->name}' is enabled but not fully configured (e.g. missing API key).");
    }
}

mtrace('Configured ' . count($providers) . ' AI provider(s).');
exit(0);

/**
 * Overlay recipe action config onto a provider's existing (or default) action config.
 *
 * Recipe keys are action short names ("generate_text") or fully qualified action classes.
 * "enabled" toggles the action; every other key is merged into the action's settings.
 *
 * @param array $base Existing action config, keyed by action class.
 * @param array $overrides Recipe action config.
 * @param string $providername For error messages.
 * @return array
 */
function mchef_merge_action_config(array $base, array $overrides, string $providername): array {
    foreach ($overrides as $action => $values) {
        $actionclass = str_contains($action, '\\') ? ltrim($action, '\\') : 'core_ai\\aiactions\\' . $action;
        if (!array_key_exists($actionclass, $base)) {
            $supported = implode(', ', array_map(fn($class) => substr($class, strrpos($class, '\\') + 1), array_keys($base)));
            cli_error("Provider '{$providername}': action '{$action}' is not supported. Supported actions: {$supported}.");
        }
        if (!is_array($values)) {
            cli_error("Provider '{$providername}': action '{$action}' must be an object.");
        }
        if (array_key_exists('enabled', $values)) {
            $base[$actionclass]['enabled'] = (bool) $values['enabled'];
            unset($values['enabled']);
        }
        $base[$actionclass]['settings'] = array_merge($base[$actionclass]['settings'] ?? [], $values);
    }
    return $base;
}
