<?php

namespace App\Model;

class AIProvider extends AbstractModel {
    public function __construct(
        /**
         * @var string - Human readable instance name. Used as the lookup key, so re-running a
         * recipe updates the existing instance rather than creating a duplicate.
         */
        public string $name,

        /**
         * @var string - Provider plugin component, e.g. "aiprovider_openai".
         */
        public string $type,

        /**
         * @var bool - Whether the provider instance is enabled.
         */
        public bool $enabled = true,

        /**
         * @var array|null - Provider config, e.g. { "apikey": "${OPENAI_KEY}", "orgid": "" }.
         * Values support ${ENV_VAR} interpolation.
         */
        public ?array $config = null,

        /**
         * @var array|null - Per action config keyed by action short name, e.g.
         * { "generate_text": { "enabled": true, "model": "gpt-4o" } }.
         * "enabled" toggles the action; all other keys are action settings.
         * Actions not listed keep the provider defaults.
         */
        public ?array $actionconfig = null,
    ) {}
}
