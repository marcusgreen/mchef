# Post-Install Configuration Feature

Automated post-install configuration for Moodle via recipe file. Runs after DB install, sample data, and restore structure.

## Recipe Shape

```json
{
  "postInstall": {
    "siteConfig": {
      "core": {
        "smtphosts": "smtp.example.org",
        "noemailever": "1"
      },
      "tool_dataprivacy": {
        "contactdataprotectionofficer": "1"
      }
    },
    "aiProviders": [
      {
        "name": "OpenAI Editorial",
        "type": "aiprovider_openai",
        "enabled": true,
        "config": {
          "apikey": "${OPENAI_KEY}",
          "endpoint": "https://api.openai.com/v1"
        },
        "actionconfig": {
          "generate_text": { "model": "gpt-4o", "enabled": true },
          "generate_image": { "enabled": false }
        }
      }
    ]
  }
}
```

### Env Var Interpolation

`${VAR_NAME}` in any string value is replaced with the env var at runtime. Fails loud if var is unset, unless written `${VAR_NAME:-default}` (default used when unset or empty; may be blank). mchef does not load `.env` files — export vars in the shell or CI. Never commit keys in recipes.

## Architecture

Follows the existing trio pattern (Model / Service / Moodle CLI script):

| File | Role |
|---|---|
| `src/Model/PostInstall.php` | Umbrella model — holds `siteConfig`, `aiProviders` |
| `src/Model/AIProvider.php` | AI provider instance model |
| `src/Service/PostInstall.php` | Orchestrator — resolves env vars, copies JSON + script into container, runs each step |
| `scripts/moodle/admin/cli/set_config_mchef.php` | Moodle-side `set_config()` wrapper |
| `scripts/moodle/admin/cli/configure_ai_providers_mchef.php` | Moodle-side `core_ai` upsert |

Hook point: `MoodleInstall::installMoodle()` — call `$this->postInstallService->process()` after `processRestoreStructure`.

## Ordering

1. `siteConfig` — site settings first (may affect auth/email for subsequent steps)
2. `aiProviders` — needs installed DB and site config
3. (future) `commands` — arbitrary CLI scripts, run last

## Idempotency

- `siteConfig`: `set_config()` is inherently idempotent
- `aiProviders`: lookup by provider component + `name`, update if exists, create if not. Providers absent from the recipe are never touched (`pruneMissing` not implemented yet)

## Moodle Version Gate

Provider *instances* (`mdl_ai_providers`, `core_ai\manager::create_provider_instance()`) arrived in Moodle 5.0 (MDL-82977). Moodle 4.5 has `core_ai` but providers are plugin-level config — use `siteConfig` there (e.g. `aiprovider_openai` → `apikey`).

The CLI script checks for `create_provider_instance()` rather than a version number. If missing, it prints `SKIPPED: ...` and exits 0; mchef shows that as a warning and the install continues.

## Secrets

- `${ENV_VAR}` → resolved in PHP before passing config JSON to container
- Redact `/key|secret|token|password/i` keys in any log output
- Fail loudly at parse time if referenced env var is unset (unless `${VAR:-default}` form is used)

## Implementation PRs

### PR 1 (done): `siteConfig`
- `src/Model/PostInstall.php` — umbrella with `?array $siteConfig`
- `src/Service/PostInstall.php` — env var resolution + CLI script execution
- `scripts/moodle/admin/cli/set_config_mchef.php` — `set_config()` wrapper
- `Recipe.php` — add `?PostInstall $postInstall`
- `MoodleInstall.php` — wire up `postInstallService`

### PR 2 (done, not yet tested against a live container): `aiProviders`
- `src/Model/AIProvider.php`
- Add `#[ArrayOf(AIProvider::class)] ?array $aiProviders` to `PostInstall`
- Step added to `src/Service/PostInstall.php` (shares `runMoodleScript()` with `siteConfig`, so no separate `AIProviderService`)
- `scripts/moodle/admin/cli/configure_ai_providers_mchef.php`

### PR 3: `commands` (generic CLI runner)
- Add `?array $commands` to `PostInstall` — `[{name, script, args?, continueOnError?}]`
- `src/Service/PostInstallCommands.php`
