<?php

namespace App\Tests;

use App\Model\AIProvider;
use App\Model\PostInstall as PostInstallModel;
use App\Model\Recipe;
use App\Service\Docker;
use App\Service\ModelJSONDeserializer;
use App\Service\Moodle;
use App\Service\PostInstall;
use App\Traits\CallRestrictedMethodTrait;
use PHPUnit\Framework\MockObject\MockObject;

class PostInstallTest extends MchefTestCase {
    use CallRestrictedMethodTrait;

    private PostInstall $postInstallService;
    private MockObject $dockerService;
    private MockObject $moodleService;

    protected function setUp(): void {
        parent::setUp();

        $this->dockerService = $this->createMock(Docker::class);
        $this->moodleService = $this->createMock(Moodle::class);
        $this->moodleService->method('getDockerMoodlePath')->willReturn('/var/www/html/moodle');

        $this->postInstallService = PostInstall::instance();
        $this->applyMockedServices([
            'dockerService' => $this->dockerService,
            'moodleService' => $this->moodleService,
        ], $this->postInstallService);
    }

    protected function tearDown(): void {
        putenv('MCHEF_TEST_SMTP_PASS');
        putenv('MCHEF_TEST_OPENAI_KEY');
        parent::tearDown();
    }

    private function createTestRecipe(?PostInstallModel $postInstall = null): Recipe {
        $recipe = new Recipe(
            moodleTag: '4.5.0',
            phpVersion: '8.2',
            name: 'test-recipe'
        );
        $recipe->setRecipePath('/tmp/test-recipe.json');
        $recipe->postInstall = $postInstall;
        return $recipe;
    }

    public function testDeserializeRecipeWithPostInstall(): void {
        $json = '{
            "moodleTag": "v4.5.0",
            "phpVersion": "8.2",
            "postInstall": {
                "siteConfig": {
                    "core": { "noemailever": "1" },
                    "tool_dataprivacy": { "contactdataprotectionofficer": "1" }
                }
            }
        }';

        $recipe = ModelJSONDeserializer::instance()->deserialize($json, Recipe::class);

        $this->assertInstanceOf(PostInstallModel::class, $recipe->postInstall);
        $this->assertEquals('1', $recipe->postInstall->siteConfig['core']['noemailever']);
        $this->assertEquals('1', $recipe->postInstall->siteConfig['tool_dataprivacy']['contactdataprotectionofficer']);
    }

    public function testProcessSkipsWhenNoPostInstall(): void {
        $this->dockerService->expects($this->never())->method('copyFileToContainer');
        $this->dockerService->expects($this->never())->method('executeInContainerWithEnv');

        $this->postInstallService->process($this->createTestRecipe(), 'moodle-container');
    }

    public function testProcessSkipsWhenSiteConfigEmpty(): void {
        $this->dockerService->expects($this->never())->method('copyFileToContainer');
        $this->dockerService->expects($this->never())->method('executeInContainerWithEnv');

        $this->postInstallService->process(
            $this->createTestRecipe(new PostInstallModel(siteConfig: [])),
            'moodle-container'
        );
    }

    public function testProcessAppliesSiteConfig(): void {
        putenv('MCHEF_TEST_SMTP_PASS=s3cret');
        $recipe = $this->createTestRecipe(new PostInstallModel(siteConfig: [
            'core' => ['smtphosts' => 'smtp.example.org', 'smtppass' => '${MCHEF_TEST_SMTP_PASS}'],
        ]));

        $copied = [];
        $tempFile = null;
        $this->dockerService->expects($this->exactly(2))
            ->method('copyFileToContainer')
            ->willReturnCallback(function (string $source, string $container, string $dest) use (&$copied, &$tempFile) {
                $this->assertEquals('moodle-container', $container);
                if ($dest === '/tmp/mchef_site_config.json') {
                    $tempFile = $source;
                    $copied['config'] = json_decode(file_get_contents($source), true);
                } else {
                    $copied['script'] = [$source, $dest];
                }
            });

        $this->dockerService->expects($this->once())
            ->method('executeInContainerWithEnv')
            ->with(
                'moodle-container',
                'MCHEF_SITE_CONFIG_PATH',
                '/tmp/mchef_site_config.json',
                '/var/www/html/moodle/admin/cli/set_config_mchef.php'
            )
            ->willReturn(['Applied 2 configuration setting(s).', 0]);

        $this->postInstallService->process($recipe, 'moodle-container');

        $this->assertEquals('smtp.example.org', $copied['config']['core']['smtphosts']);
        $this->assertEquals('s3cret', $copied['config']['core']['smtppass']);
        $this->assertStringEndsWith('scripts/moodle/admin/cli/set_config_mchef.php', $copied['script'][0]);
        $this->assertEquals('/var/www/html/moodle/admin/cli/set_config_mchef.php', $copied['script'][1]);
        $this->assertFileDoesNotExist($tempFile, 'Temp config file should be removed after success');
    }

    public function testProcessFailsWhenScriptExitsNonZero(): void {
        $recipe = $this->createTestRecipe(new PostInstallModel(siteConfig: [
            'core' => ['noemailever' => '1'],
        ]));

        $tempFile = null;
        $this->dockerService->method('copyFileToContainer')
            ->willReturnCallback(function (string $source, string $container, string $dest) use (&$tempFile) {
                if ($dest === '/tmp/mchef_site_config.json') {
                    $tempFile = $source;
                }
            });
        $this->dockerService->method('executeInContainerWithEnv')->willReturn(['boom', 1]);

        try {
            $this->postInstallService->process($recipe, 'moodle-container');
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertStringContainsString('set_config_mchef.php failed (exit 1): boom', $e->getMessage());
        }
        $this->assertFileDoesNotExist($tempFile, 'Temp config file should be removed after failure');
    }

    public function testMissingEnvVarFailsBeforeTouchingContainer(): void {
        putenv('MCHEF_TEST_SMTP_PASS');
        $recipe = $this->createTestRecipe(new PostInstallModel(siteConfig: [
            'core' => ['smtppass' => '${MCHEF_TEST_SMTP_PASS}'],
        ]));

        $this->dockerService->expects($this->never())->method('copyFileToContainer');
        $this->dockerService->expects($this->never())->method('executeInContainerWithEnv');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("'\${MCHEF_TEST_SMTP_PASS}'");
        $this->postInstallService->process($recipe, 'moodle-container');
    }

    public function testResolveEnvVarsLeavesNonStringsAndPlainStrings(): void {
        putenv('MCHEF_TEST_SMTP_PASS=abc');
        $result = $this->callRestricted($this->postInstallService, 'resolveEnvVars', [[
            'core' => ['a' => 'plain', 'b' => 'pre-${MCHEF_TEST_SMTP_PASS}-post', 'c' => 5],
        ], 'postInstall.siteConfig']);

        $this->assertSame('plain', $result['core']['a']);
        $this->assertSame('pre-abc-post', $result['core']['b']);
        $this->assertSame(5, $result['core']['c']);
    }

    public function testDeserializeRecipeWithAIProviders(): void {
        $json = '{
            "moodleTag": "v5.0.0",
            "phpVersion": "8.3",
            "postInstall": {
                "aiProviders": [
                    {
                        "name": "OpenAI",
                        "type": "aiprovider_openai",
                        "config": { "apikey": "${OPENAI_KEY}" },
                        "actionconfig": {
                            "generate_text": { "enabled": true, "model": "gpt-4o" },
                            "generate_image": { "enabled": false }
                        }
                    }
                ]
            }
        }';

        $recipe = ModelJSONDeserializer::instance()->deserialize($json, Recipe::class);

        $providers = $recipe->postInstall->aiProviders;
        $this->assertCount(1, $providers);
        $this->assertInstanceOf(AIProvider::class, $providers[0]);
        $this->assertEquals('OpenAI', $providers[0]->name);
        $this->assertEquals('aiprovider_openai', $providers[0]->type);
        $this->assertTrue($providers[0]->enabled, 'enabled should default to true');
        $this->assertEquals('${OPENAI_KEY}', $providers[0]->config['apikey']);
        $this->assertEquals('gpt-4o', $providers[0]->actionconfig['generate_text']['model']);
        $this->assertFalse($providers[0]->actionconfig['generate_image']['enabled']);
        $this->assertNull($recipe->postInstall->siteConfig);
    }

    public function testProcessConfiguresAIProviders(): void {
        putenv('MCHEF_TEST_OPENAI_KEY=sk-test');
        $recipe = $this->createTestRecipe(new PostInstallModel(aiProviders: [
            new AIProvider(
                name: 'OpenAI',
                type: 'aiprovider_openai',
                config: ['apikey' => '${MCHEF_TEST_OPENAI_KEY}'],
                actionconfig: ['generate_text' => ['model' => 'gpt-4o']],
            ),
            new AIProvider(name: 'Ollama', type: 'aiprovider_ollama', enabled: false),
        ]));

        $sent = null;
        $this->dockerService->expects($this->exactly(2))
            ->method('copyFileToContainer')
            ->willReturnCallback(function (string $source, string $container, string $dest) use (&$sent) {
                if ($dest === '/tmp/mchef_ai_providers.json') {
                    $sent = json_decode(file_get_contents($source), true);
                }
            });

        $this->dockerService->expects($this->once())
            ->method('executeInContainerWithEnv')
            ->with(
                'moodle-container',
                'MCHEF_AI_PROVIDERS_PATH',
                '/tmp/mchef_ai_providers.json',
                '/var/www/html/moodle/admin/cli/configure_ai_providers_mchef.php'
            )
            ->willReturn(['Configured 2 AI provider(s).', 0]);

        $this->cli->expects($this->never())->method('warning');

        $this->postInstallService->process($recipe, 'moodle-container');

        $this->assertEquals([
            [
                'name' => 'OpenAI',
                'type' => 'aiprovider_openai',
                'enabled' => true,
                'config' => ['apikey' => 'sk-test'],
                'actionconfig' => ['generate_text' => ['model' => 'gpt-4o']],
            ],
            [
                'name' => 'Ollama',
                'type' => 'aiprovider_ollama',
                'enabled' => false,
                'config' => [],
                'actionconfig' => [],
            ],
        ], $sent);
    }

    public function testProcessRunsSiteConfigBeforeAIProviders(): void {
        $recipe = $this->createTestRecipe(new PostInstallModel(
            siteConfig: ['qtype_aitext' => ['backend' => 'core_ai_subsystem']],
            aiProviders: [new AIProvider(name: 'OpenAI', type: 'aiprovider_openai')],
        ));

        $scripts = [];
        $this->dockerService->method('executeInContainerWithEnv')
            ->willReturnCallback(function (string $container, string $envVar, string $path, string $script) use (&$scripts) {
                $scripts[] = basename($script);
                return ['ok', 0];
            });

        $this->postInstallService->process($recipe, 'moodle-container');

        $this->assertEquals(['set_config_mchef.php', 'configure_ai_providers_mchef.php'], $scripts);
    }

    public function testProcessWarnsWhenAIProvidersSkipped(): void {
        $recipe = $this->createTestRecipe(new PostInstallModel(aiProviders: [
            new AIProvider(name: 'OpenAI', type: 'aiprovider_openai'),
        ]));

        $this->dockerService->method('executeInContainerWithEnv')->willReturn([
            "SKIPPED: aiProviders requires Moodle 5.0+ (core_ai provider instances). Site version is 4.5.3.",
            0,
        ]);

        $this->cli->expects($this->once())
            ->method('warning')
            ->with($this->stringStartsWith('SKIPPED: aiProviders requires Moodle 5.0+'));

        $this->postInstallService->process($recipe, 'moodle-container');
    }

    public function testMissingEnvVarInAIProviderConfigFails(): void {
        putenv('MCHEF_TEST_OPENAI_KEY');
        $recipe = $this->createTestRecipe(new PostInstallModel(aiProviders: [
            new AIProvider(name: 'OpenAI', type: 'aiprovider_openai', config: ['apikey' => '${MCHEF_TEST_OPENAI_KEY}']),
        ]));

        $this->dockerService->expects($this->never())->method('copyFileToContainer');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("'\${MCHEF_TEST_OPENAI_KEY}' referenced in postInstall.aiProviders is not set.");
        $this->postInstallService->process($recipe, 'moodle-container');
    }

    public function testResolveEnvVarsWithDefault(): void {
        putenv('MCHEF_TEST_OPENAI_KEY');
        $data = ['config' => [
            'blank' => '${MCHEF_TEST_OPENAI_KEY:-}',
            'fallback' => '${MCHEF_TEST_OPENAI_KEY:-sk-default}',
        ]];

        // Unset: defaults are used and no exception is thrown.
        $result = $this->callRestricted($this->postInstallService, 'resolveEnvVars', [$data, 'postInstall.aiProviders']);
        $this->assertSame('', $result['config']['blank']);
        $this->assertSame('sk-default', $result['config']['fallback']);

        // Set but empty: defaults are used, as in bash.
        putenv('MCHEF_TEST_OPENAI_KEY=');
        $result = $this->callRestricted($this->postInstallService, 'resolveEnvVars', [$data, 'postInstall.aiProviders']);
        $this->assertSame('sk-default', $result['config']['fallback']);

        // Set: the env value wins.
        putenv('MCHEF_TEST_OPENAI_KEY=sk-real');
        $result = $this->callRestricted($this->postInstallService, 'resolveEnvVars', [$data, 'postInstall.aiProviders']);
        $this->assertSame('sk-real', $result['config']['blank']);
        $this->assertSame('sk-real', $result['config']['fallback']);
    }
}
