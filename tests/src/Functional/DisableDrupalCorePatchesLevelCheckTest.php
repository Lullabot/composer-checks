<?php

namespace Lullabot\ComposerChecks\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Tests for the Disable Drupal Core Patches Level Check functionality.
 */
class DisableDrupalCorePatchesLevelCheckTest extends TestCase {

  /**
   * Directory for test 1.
   *
   * @var string
   */
  private $test1Dir;

  /**
   * Directory for test 2.
   *
   * @var string
   */
  private $test2Dir;

  /**
   * Directory for test 3.
   *
   * @var string
   */
  private $test3Dir;

  /**
   * Directory for test 4.
   *
   * @var string
   */
  private $test4Dir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create test directories.
    $this->test1Dir = sys_get_temp_dir() . '/composer-checks-test1-' . uniqid();
    $this->test2Dir = sys_get_temp_dir() . '/composer-checks-test2-' . uniqid();
    $this->test3Dir = sys_get_temp_dir() . '/composer-checks-test3-' . uniqid();
    $this->test4Dir = sys_get_temp_dir() . '/composer-checks-test4-' . uniqid();

    mkdir($this->test1Dir, 0777, TRUE);
    mkdir($this->test2Dir, 0777, TRUE);
    mkdir($this->test3Dir, 0777, TRUE);
    mkdir($this->test4Dir, 0777, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Clean up test directories.
    $this->runProcess(['rm', '-rf', $this->test1Dir]);
    $this->runProcess(['rm', '-rf', $this->test2Dir]);
    $this->runProcess(['rm', '-rf', $this->test3Dir]);
    $this->runProcess(['rm', '-rf', $this->test4Dir]);

    parent::tearDown();
  }

  /**
   * Test 1: Check should FAIL when patchLevel is not configured (no disable flag).
   */
  public function testFailWithoutPatchLevelAndDisableFlag(): void {
    // Build composer.json without patchLevel and without disable flag.
    $this->setupComposerJson($this->test1Dir, [
      'break-composer-installation-if-patches-fail',
      'disable-exit-on-patch-failure-check',
    ]);

    // Run composer install and expect it to fail.
    $process = $this->runComposerInstall($this->test1Dir, FALSE);

    // Check if the output contains the expected warning message.
    $this->assertStringContainsString(
      'Configure patches for Composer\'s packages to use',
      $process->getOutput() . $process->getErrorOutput()
    );
  }

  /**
   * Test 2: Check should PASS (show warning) when disable flag is present.
   */
  public function testPassWithDisableFlag(): void {
    // Build composer.json with disable flag.
    $this->setupComposerJson($this->test2Dir, [
      'disable-drupal-core-patches-level-check',
      'break-composer-installation-if-patches-fail',
      'disable-exit-on-patch-failure-check',
    ]);

    // Run composer install and expect it to succeed with warnings.
    $process = $this->runComposerInstall($this->test2Dir, TRUE);

    // Check if the output contains the expected warning message.
    $this->assertStringContainsString(
      'Configure patches for Composer\'s packages to use',
      $process->getOutput() . $process->getErrorOutput()
    );
  }

  /**
   * Test 3: Check should PASS silently when patchLevel is correctly configured.
   */
  public function testPassWithCorrectPatchLevel(): void {
    // Build composer.json with correct patchLevel configuration.
    $this->setupComposerJson($this->test3Dir, [
      'break-composer-installation-if-patches-fail',
      'disable-exit-on-patch-failure-check',
    ], TRUE);

    // Run composer install and expect it to succeed without warnings.
    $process = $this->runComposerInstall($this->test3Dir, TRUE);

    // Check if the output doesn't contain the expected warning message.
    $this->assertStringNotContainsString(
      'Configure patches for Composer\'s packages to use',
      $process->getOutput() . $process->getErrorOutput()
    );
  }

  /**
   * Test 4: Check should FAIL when patchLevel is configured but incorrect value.
   */
  public function testFailWithIncorrectPatchLevel(): void {
    // Build composer.json with incorrect patchLevel value.
    $this->setupComposerJson($this->test4Dir, [
      'break-composer-installation-if-patches-fail',
      'disable-exit-on-patch-failure-check',
    ], FALSE, TRUE);

    // Run composer install and expect it to fail.
    $process = $this->runComposerInstall($this->test4Dir, FALSE);

    // Check if the output contains the expected warning message.
    $this->assertStringContainsString(
      'Configure patches for Composer\'s packages to use',
      $process->getOutput() . $process->getErrorOutput()
    );
  }

  /**
   * Sets up a composer.json file in the given directory.
   *
   * @param string $directory
   *   The directory to create the composer.json file in.
   * @param array $composerChecks
   *   The composer-checks configuration.
   * @param bool $correctPatchLevel
   *   Whether to add the correct patch level.
   * @param bool $incorrectPatchLevel
   *   Whether to add an incorrect patch level.
   */
  private function setupComposerJson(string $directory, array $composerChecks, bool $correctPatchLevel = FALSE, bool $incorrectPatchLevel = FALSE): void {
    // Initialize composer.json.
    $this->runProcess(['composer', 'init', '--name=test/project', '--no-interaction'], $directory);

    // Configure repositories and plugins.
    $this->runProcess(['composer', 'config', 'repositories.lullabot/composer-checks', '{"type": "path", "url": "' . getcwd() . '"}'], $directory);
    $this->runProcess(['composer', 'config', 'allow-plugins.lullabot/composer-checks', 'true'], $directory);
    $this->runProcess(['composer', 'config', 'allow-plugins.cweagans/composer-patches', 'true'], $directory);

    // Require dependencies.
    $this->runProcess(['composer', 'require', 'cweagans/composer-patches:^1.7', '--no-update'], $directory);
    $this->runProcess(['composer', 'require', 'lullabot/composer-checks:*', '--dev', '--no-update'], $directory);

    // Set stability.
    $this->runProcess(['composer', 'config', 'minimum-stability', 'dev'], $directory);
    $this->runProcess(['composer', 'config', 'prefer-stable', 'true'], $directory);

    // Add composer-checks configuration and patch level using PHP's json functions.
    $composerJsonPath = $directory . '/composer.json';
    $composerJson = json_decode(file_get_contents($composerJsonPath), TRUE);

    // Add composer-checks configuration.
    if (!isset($composerJson['extra']['composer-checks'])) {
      $composerJson['extra']['composer-checks'] = [];
    }

    foreach ($composerChecks as $index => $check) {
      $composerJson['extra']['composer-checks'][$index] = $check;
    }

    // Add patch level if needed.
    if ($correctPatchLevel) {
      if (!isset($composerJson['extra']['patchLevel'])) {
        $composerJson['extra']['patchLevel'] = [];
      }
      $composerJson['extra']['patchLevel']['drupal/core'] = '-p2';
    }
    elseif ($incorrectPatchLevel) {
      if (!isset($composerJson['extra']['patchLevel'])) {
        $composerJson['extra']['patchLevel'] = [];
      }
      $composerJson['extra']['patchLevel']['drupal/core'] = '-p1';
    }

    // Write the updated composer.json file.
    file_put_contents($composerJsonPath, json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

  /**
   * Runs composer install in the given directory.
   *
   * @param string $directory
   *   The directory to run composer install in.
   * @param bool $expectSuccess
   *   Whether to expect the command to succeed.
   *
   * @return \Symfony\Component\Process\Process
   *   The process object.
   */
  private function runComposerInstall(string $directory, bool $expectSuccess): Process {
    $process = new Process(['composer', 'install', '--no-progress', '--ignore-platform-reqs'], $directory);
    $process->setTimeout(300);
    $process->run();

    if ($expectSuccess) {
      $this->assertTrue($process->isSuccessful(), 'Composer install should succeed but failed with: ' . $process->getErrorOutput());
    }
    else {
      $this->assertFalse($process->isSuccessful(), 'Composer install should fail but succeeded');
    }

    return $process;
  }

  /**
   * Runs a process with the given command.
   *
   * @param array $command
   *   The command to run.
   * @param string|null $cwd
   *   The working directory or NULL to use the current one.
   *
   * @return \Symfony\Component\Process\Process
   *   The process object.
   */
  private function runProcess(array $command, ?string $cwd = NULL): Process {
    $process = new Process($command, $cwd);
    $process->setTimeout(60);
    $process->run();

    if (!$process->isSuccessful()) {
      throw new ProcessFailedException($process);
    }

    return $process;
  }

}
