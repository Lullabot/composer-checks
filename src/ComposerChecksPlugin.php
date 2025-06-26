<?php

declare(strict_types=1);

namespace Lullabot\ComposerChecks;

use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

class ComposerChecksPlugin implements PluginInterface, EventSubscriberInterface {

  /**
   * @var IOInterface
   */
  protected $io;

  /**
   * Composer instance configuration.
   *
   * @var Config
   */
  protected $config;

  /**
   * Composer extra field configuration.
   *
   * @var array
   */
  protected $extra;

  /**
   * {@inheritDoc}
   */
  public function activate(Composer $composer, IOInterface $io)
  {
    $this->io = $io;
    $this->config = $composer->getConfig();
    $this->extra = $composer->getPackage()->getExtra();
  }

  /**
   * {@inheritDoc}
   */
  public function deactivate(Composer $composer, IOInterface $io)
  {
  }

  /**
   * {@inheritDoc}
   */
  public function uninstall(Composer $composer, IOInterface $io)
  {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents()
  {
    return [
      ScriptEvents::POST_INSTALL_CMD => 'onPostInstallCmd',
      ScriptEvents::POST_UPDATE_CMD => 'onPostUpdateCmd',
    ];
  }

  /**
   * Composer configuration advice: Use local copies of patch files.
   *
   * @return void
   */
  private function checkComposerPatchesAreLocal()
  {

    $value = $this->extra['composer-checks']['disable-local-patches-check'] ?? false;
    $message_type = $value ? 'warning' : 'error';
    $patchesInComposer = $this->extra['patches'] ?? false;
    $patchesInExtraFile = $this->extra['patches-file'] ?? false;

    // Patches defined on a separate file.
    if ($patchesInExtraFile) {
      if (!file_exists($patchesInExtraFile)) {
        $this->io->$message_type("The patches file `$patchesInExtraFile` can't be read.");
        return;
      }

      $patchesJsonEncoded = file_get_contents($patchesInExtraFile);
      $patchesContent = json_decode($patchesJsonEncoded, true)['patches'] ?? [];

      if (json_last_error()) {
        $this->io->$message_type(
          "The patches file `$patchesInExtraFile` can't be parsed. Message \""
          . json_last_error_msg(). '"'
        );
        return;
      }

    } else if ($patchesInComposer) {
      $patchesContent = $patchesInComposer;
    }

    if (empty($patchesContent)) {
      return;
    }

    // Patches content is not a string.
    if (!is_array($patchesContent)) {
      $this->io->$message_type("The patches content can't be validated. Check your patches defined in Composer.");
      return;
    }

    // Collecting remote patches (if any).
    $remotePatches = [];
    foreach ($patchesContent as $projectName => $patches) {
      foreach ($patches as $patchName => $patchUri) {
        if (str_starts_with($patchUri, 'http')) {
          $remotePatches[$projectName] = "$patchName | $patchUri";
        }
      }
    }

    if (!$remotePatches) {
      return;
    }

    // Collect the remote patches info.
    $patchesInfo = PHP_EOL;
    $count = 1;
    foreach ($remotePatches as $projectName => $remote_patch) {
      $patchesInfo.= "[$count] $projectName: $remote_patch " . PHP_EOL;
      $count++;
    }
    $patchesInfo = rtrim($patchesInfo, PHP_EOL);

    // Communicate the user.
    $msg = "Use local copies of patch files for Composer's packages. \nSee";
    $link = 'https://architecture.lullabot.com/adr/20220429-composer-patch-files/';
    $this->io->$message_type("$msg $link $patchesInfo");

    if ($message_type === 'error') {
      exit(1);
    }
  }

  /**
   * Composer configuration advice: "composer-exit-on-patch-failure": true
   *
   * @return void
   */
  private function checkComposerBreaksIfPatchesDoNotApply()
  {

    $value = $this->extra['composer-checks']['disable-exit-on-patch-failure-check'] ?? false;
    $message_type = $value ? 'warning' : 'error';

    $composerExitsOnPatchFailure = $this->extra['composer-exit-on-patch-failure']
      ?? false;
    $composerExitsOnPatchFailureBool = is_bool($composerExitsOnPatchFailure);
    $isNotConfiguredOrNotBool = !$composerExitsOnPatchFailure ||
      !$composerExitsOnPatchFailureBool;
    $isBoolButNotTrue = $composerExitsOnPatchFailureBool
      && $composerExitsOnPatchFailure !== true;
    $should_warn = $isNotConfiguredOrNotBool && $isBoolButNotTrue;

    if (!$should_warn) {
      return;
    }

    $msg = "It's recommended to break Composer's installation if patches don't apply. \nSee";
    $link = 'https://architecture.lullabot.com/adr/20220429-composer-exit-failure/';
    $this->io->$message_type("$msg $link");

    if ($message_type === 'error') {
      exit(1);
    }

  }

  /**
   *  Composer configuration advice: "patchLevel": {"drupal/core": "-p2"}
   *
   * @return void
   */
  private function checkDrupalCoreComposerPatchesLevel()
  {

    $value = $this->extra['composer-checks']['disable-drupal-core-patches-level-check'] ?? false;
    $message_type = $value ? 'warning' : 'error';

    $patchLevel = $this->extra['patchLevel']['drupal/core'] ?? false;
    $patchLevelIsString = is_string($patchLevel);
    $should_warn = (!$patchLevel || !$patchLevelIsString)
      || ($patchLevelIsString && $patchLevel != '-p2');

    if (!$should_warn) {
      return;
    }

    $msg = "Configure patches for Composer's packages to use `-p2` as `patchLevel` for Drupal core. \nSee";
    $link = 'https://architecture.lullabot.com/adr/20220429-composer-patchlevel/';
    $this->io->$message_type("$msg $link");

    if ($message_type === 'error') {
      exit(1);
    }
  }

  /**
   *  Composer configuration advice: "patches-file" is not set/used.
   *
   * @return void
   */
  private function checkPatchesStoredInComposerJson()
  {

    $value = $this->extra['composer-checks']['disable-patches-file-check'] ?? false;
    $message_type = $value ? 'warning' : 'error';

    if (!isset($this->extra['patches-file'])) {
      return;
    }

    $msg = 'Store patches for Composer\'s packages in `composer.json`, not in a separate file. See';
    $link = 'https://architecture.lullabot.com/adr/20220429-composer-patches-inline/';
    $this->io->$message_type("$msg $link");

    if ($message_type === 'error') {
      exit(1);
    }
  }

  /**
   * Handle post install command events.
   *
   * @param Event $event the event to handle
   */
  public function onPostInstallCmd(Event $event)
  {

    $this->extra['composer-checks'] = $this->extra['composer-checks'] ?? [];

    // Composer checks.
    $this->checkDrupalCoreComposerPatchesLevel();
    $this->checkComposerBreaksIfPatchesDoNotApply();
    $this->checkPatchesStoredInComposerJson();
    $this->checkComposerPatchesAreLocal();
  }

  /**
   * Handle post update command events.
   *
   * @param event $event The event to handle
   */
  public function onPostUpdateCmd(Event $event)
  {
    $this->extra['composer-checks'] = $this->extra['composer-checks'] ?? [];

    // Composer checks.
    $this->checkDrupalCoreComposerPatchesLevel();
    $this->checkComposerBreaksIfPatchesDoNotApply();
    $this->checkPatchesStoredInComposerJson();
    $this->checkComposerPatchesAreLocal();
  }

}