<?php

namespace App\Model;

use App\Attributes\ArrayOf;

class PostInstall extends AbstractModel {
    public function __construct(
        /**
         * @var array|null - Map of component => [name => value] pairs to set via set_config().
         * Use "core" as the component key for mdl_config (no component); all other keys
         * map to mdl_config_plugins component values.
         * Values support ${ENV_VAR} interpolation.
         *
         * Example:
         * {
         *   "core": { "smtphosts": "smtp.example.org" },
         *   "tool_dataprivacy": { "contactdataprotectionofficer": "1" }
         * }
         */
        public ?array $siteConfig = null,

        /**
         * @var AIProvider[]|null - core_ai provider instances to create or update (Moodle 5.0+).
         * Matched on type + name; providers not listed are left untouched.
         */
        #[ArrayOf(AIProvider::class)]
        public ?array $aiProviders = null,
    ) {}
}
