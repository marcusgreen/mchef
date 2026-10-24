# Post-Install Configuration

Mchef supports automated post-install configuration via the `postInstall` block in a recipe file. Steps run after DB install, sample data generation, and restore structure — in that order.

## Recipe Block

```json
{
  "postInstall": {
    "siteConfig": {
      "core": {
        "smtphosts": "smtp.example.org",
        "noreplyaddress": "noreply@example.org",
        "noemailever": "0"
      },
      "tool_dataprivacy": {
        "contactdataprotectionofficer": "1"
      },
      "mod_forum": {
        "replytouser": "0"
      }
    }
  }
}
```

All fields under `postInstall` are optional. Omitting the block entirely skips post-install.

## `siteConfig`

Sets Moodle configuration values via `set_config()`. Organised as a map of **component** → **settings**.

| Component key | Maps to | Moodle table |
|---|---|---|
| `"core"` | `set_config($name, $value)` | `mdl_config` |
| anything else | `set_config($name, $value, $component)` | `mdl_config_plugins` |

This mirrors how Moodle itself separates core config from plugin config. The component name is the Moodle plugin frankenstyle name (e.g. `tool_dataprivacy`, `mod_forum`, `auth_saml2`).

All values must be strings (Moodle stores config as strings internally).

### Idempotency

`set_config()` is a write — it overwrites whatever is already there. Running `mchef up` a second time will re-apply the same values. This is intentional: the recipe is the source of truth.

### Env Var Interpolation

Any string value can reference an environment variable:

```json
"siteConfig": {
  "core": {
    "smtppass": "${SMTP_PASSWORD}"
  }
}
```

`${VAR_NAME}` is resolved from the environment of the shell running mchef, before the config JSON is passed into the container. If the variable is not set, mchef fails loudly with a clear error — it will not silently write an empty value.

`${VAR_NAME:-default}` is the optional form: if the variable is unset or empty, `default` is used instead (as in bash). The default may be empty — `${OPENAI_KEY:-}` gives a blank value rather than an error. Defaults cannot contain `}`.

```json
"config": { "apikey": "${OPENAI_KEY:-}" }
```

This lets one recipe serve both workflows: export the key (`read -rs OPENAI_KEY && export OPENAI_KEY`), or replace the placeholder with the literal key in a local copy of the recipe.

mchef does not load `.env` files — export variables in the shell (or CI environment) before running it. Never commit real credentials in recipe files; if you paste a key into a recipe, work on a copy that is not committed.

Sensitive values (keys matching `/key|secret|token|password/i`) are automatically redacted in log output.

## `aiProviders`

Creates or updates `core_ai` provider instances. **Requires Moodle 5.0+** — on older sites the step is skipped with a warning (on 4.5, configure providers through `siteConfig` instead).

```json
"aiProviders": [
  {
    "name": "OpenAI",
    "type": "aiprovider_openai",
    "enabled": true,
    "config": { "apikey": "${OPENAI_KEY}", "orgid": "" },
    "actionconfig": {
      "generate_text": { "enabled": true, "model": "gpt-4o" },
      "generate_image": { "enabled": false }
    }
  }
]
```

| Field | Required | Notes |
|---|---|---|
| `name` | yes | Instance name. With `type`, the lookup key — re-running updates rather than duplicates. |
| `type` | yes | Provider plugin component, e.g. `aiprovider_openai`, `aiprovider_ollama`, `aiprovider_azureai`. Must be installed. |
| `enabled` | no | Default `true`. |
| `config` | no | Provider settings (keys as stored by the provider, e.g. `apikey`, `orgid`). Merged over existing config on update. |
| `actionconfig` | no | Keyed by action short name (`generate_text`, `summarise_text`, `explain_text`, `generate_image`) or full class name. `enabled` toggles the action; all other keys go into the action's settings. Unlisted actions and settings keep the provider defaults. |

Unknown action names fail the step and list the actions the provider supports. Providers on the site that are not in the recipe are left alone. An enabled provider that is missing required config (e.g. no API key) is created but flagged with a warning.

The JSON file passed into the container is deleted by the script as soon as it is read, so API keys do not linger in `/tmp`.

## Execution Flow

1. `MoodleInstall::installMoodle()` calls `PostInstall::process()` after `processRestoreStructure()`
2. `PostInstall::process()` resolves env vars, writes a temp JSON file
3. Temp file is copied into the Moodle container at `/tmp/mchef_site_config.json`
4. `set_config_mchef.php` is copied from `scripts/moodle/admin/cli/` into the container
5. Script executes inside the container with `MCHEF_SITE_CONFIG_PATH` pointing at the config file
6. Temp file is cleaned up whether or not the step succeeds

## Files

| File | Purpose |
|---|---|
| `src/Model/PostInstall.php` | Model for the `postInstall` recipe block |
| `src/Service/PostInstall.php` | Orchestrator — env var resolution, container copy, execution |
| `src/Model/AIProvider.php` | Model for an `aiProviders` entry |
| `scripts/moodle/admin/cli/set_config_mchef.php` | Moodle-side script — calls `set_config()` for each value |
| `scripts/moodle/admin/cli/configure_ai_providers_mchef.php` | Moodle-side script — creates/updates `core_ai` provider instances |

## Error Handling

- Missing env var referenced in config → fatal error before container is touched
- CLI script exits non-zero → exception with first 2000 chars of output
- PHP errors in CLI script → caught via `2>&1` redirect, surfaced in exception message

## Extending: Adding More Post-Install Steps

The `PostInstall` model (`src/Model/PostInstall.php`) is the place to add new fields. Follow the same pattern:

1. Add a nullable property to `PostInstall` (use `#[ArrayOf(ClassName::class)]` for typed arrays)
2. Add a private method to `PostInstall` service that handles the new step
3. Call it from `PostInstall::process()` after `siteConfig`
4. Add a Moodle-side CLI script in `scripts/moodle/admin/cli/` if Moodle PHP API access is needed

### Planned Extensions

- `commands` — run arbitrary Moodle CLI scripts after install (e.g. `admin/cli/purge_caches.php`)
