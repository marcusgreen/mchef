<?php

namespace App\Service;

use App\Model\AIProvider;
use App\Model\PostInstall as PostInstallModel;
use App\Model\Recipe;

class PostInstall extends AbstractService {

    use \App\Traits\ExecTrait;

    private Moodle $moodleService;
    private Docker $dockerService;

    final public static function instance(): PostInstall {
        return self::setup_singleton();
    }

    public function process(Recipe $recipe, string $moodleContainer): void {
        if (empty($recipe->postInstall)) {
            return;
        }

        $postInstall = $recipe->postInstall;

        if (!empty($postInstall->siteConfig)) {
            $this->processSiteConfig($postInstall, $moodleContainer, $recipe);
        }

        if (!empty($postInstall->aiProviders)) {
            $this->processAIProviders($postInstall, $moodleContainer, $recipe);
        }

        $this->cli->success('Post-install configuration completed.');
    }

    private function processSiteConfig(PostInstallModel $postInstall, string $moodleContainer, Recipe $recipe): void {
        $this->cli->notice('Applying site configuration...');

        $resolved = $this->resolveEnvVars($postInstall->siteConfig, 'postInstall.siteConfig');

        $this->runMoodleScript(
            $recipe,
            $moodleContainer,
            'set_config_mchef.php',
            'MCHEF_SITE_CONFIG_PATH',
            '/tmp/mchef_site_config.json',
            $resolved
        );

        $this->cli->success('Site configuration applied.');
    }

    private function processAIProviders(PostInstallModel $postInstall, string $moodleContainer, Recipe $recipe): void {
        $this->cli->notice('Configuring AI providers...');

        $providers = array_map(fn(AIProvider $provider) => [
            'name' => $provider->name,
            'type' => $provider->type,
            'enabled' => $provider->enabled,
            'config' => $provider->config ?? [],
            'actionconfig' => $provider->actionconfig ?? [],
        ], $postInstall->aiProviders);

        $resolved = $this->resolveEnvVars($providers, 'postInstall.aiProviders');

        $output = $this->runMoodleScript(
            $recipe,
            $moodleContainer,
            'configure_ai_providers_mchef.php',
            'MCHEF_AI_PROVIDERS_PATH',
            '/tmp/mchef_ai_providers.json',
            $resolved
        );

        // The script exits 0 and prints a warning when core_ai provider instances are unavailable (Moodle < 5.0).
        if (str_contains($output, 'SKIPPED:')) {
            $this->cli->warning(trim(substr($output, strpos($output, 'SKIPPED:'))));
            return;
        }

        $this->cli->success('AI providers configured.');
    }

    /**
     * Write $data to a temp JSON file, copy it and a Moodle CLI script into the container, then run
     * the script with $envVarName pointing at the copied JSON.
     *
     * @return string Script output.
     */
    private function runMoodleScript(
        Recipe $recipe,
        string $moodleContainer,
        string $scriptName,
        string $envVarName,
        string $containerPath,
        array $data
    ): string {
        $tempFile = $this->writeTempJson($data);

        try {
            $this->dockerService->copyFileToContainer($tempFile, $moodleContainer, $containerPath);

            $scriptSource = __DIR__ . '/../../scripts/moodle/admin/cli/' . $scriptName;
            $moodlePath = $this->moodleService->getDockerMoodlePath($recipe);
            $scriptDest = $moodlePath . '/admin/cli/' . $scriptName;
            $this->dockerService->copyFileToContainer($scriptSource, $moodleContainer, $scriptDest);

            [$output, $returnVar] = $this->dockerService->executeInContainerWithEnv(
                $moodleContainer,
                $envVarName,
                $containerPath,
                $scriptDest
            );

            if ($returnVar !== 0) {
                throw new \Exception("{$scriptName} failed (exit {$returnVar}): " . substr($output, 0, 2000));
            }

            return $output;
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Recursively resolve ${ENV_VAR} placeholders in any string values of an array.
     *
     * ${ENV_VAR} fails if the variable is unset. ${ENV_VAR:-default} falls back to "default"
     * (which may be empty) when the variable is unset or empty, as in bash.
     *
     * @param array $data
     * @param string $context Recipe path shown in the error when a variable is unset.
     */
    private function resolveEnvVars(array $data, string $context): array {
        array_walk_recursive($data, function (mixed &$value) use ($context): void {
            if (!is_string($value)) {
                return;
            }
            $pattern = '/\$\{([A-Za-z_][A-Za-z0-9_]*)(?::-([^}]*))?\}/';
            $value = preg_replace_callback($pattern, function (array $matches) use ($context): string {
                $varName = $matches[1];
                $resolved = getenv($varName);
                if (isset($matches[2])) {
                    return ($resolved === false || $resolved === '') ? $matches[2] : $resolved;
                }
                if ($resolved === false) {
                    throw new \Exception("Environment variable '\${" . $varName . "}' referenced in {$context} is not set.");
                }
                return $resolved;
            }, $value);
        });
        return $data;
    }

    private function writeTempJson(array $data): string {
        $tempFile = tempnam(sys_get_temp_dir(), 'mchef_postinstall_');
        if ($tempFile === false) {
            throw new \Exception('Failed to create temp file for post-install config.');
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            unlink($tempFile);
            throw new \Exception('Failed to encode post-install config as JSON: ' . json_last_error_msg());
        }
        if (file_put_contents($tempFile, $json) === false) {
            unlink($tempFile);
            throw new \Exception('Failed to write post-install config temp file.');
        }
        return $tempFile;
    }
}
