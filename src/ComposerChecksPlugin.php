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
use RuntimeException;

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

    $message_type = $this->getMessageType('disable-local-patches-check');
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

    $this->say("
      Use local copies of patch files for Composer's packages.
      \nSee https://architecture.lullabot.com/adr/20220429-composer-patch-files/
      \n$patchesInfo
      ",
      $message_type
    );

  }

  /**
   * Composer configuration advice: "composer-exit-on-patch-failure": true
   *
   * @return void
   */
  private function checkComposerBreaksIfPatchesDoNotApply()
  {

    $message_type = $this->getMessageType('disable-exit-on-patch-failure-check');

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

    $this->say("
      It's recommended to break Composer's installation if patches don't apply.
      \nSee https://architecture.lullabot.com/adr/20220429-composer-exit-failure/
      ",
      $message_type
    );

  }

  /**
   *  Composer configuration advice: "patchLevel": {"drupal/core": "-p2"}
   *
   * @return void
   */
  private function checkDrupalCoreComposerPatchesLevel()
  {

    $message_type = $this->getMessageType('disable-drupal-core-patches-level-check');

    $patchLevel = $this->extra['patchLevel']['drupal/core'] ?? false;
    $patchLevelIsString = is_string($patchLevel);
    $should_warn = (!$patchLevel || !$patchLevelIsString)
      || ($patchLevelIsString && $patchLevel != '-p2');

    if (!$should_warn) {
      return;
    }

    $this->say("
      Configure patches for Composer's packages to use `-p2` as `patchLevel` for Drupal core.
      \nSee https://architecture.lullabot.com/adr/20220429-composer-patchlevel/
      ",
      $message_type
    );
  }

  /**
   *  Composer configuration advice: "patches-file" is not set/used.
   *
   * @return void
   */
  private function checkPatchesStoredInComposerJson()
  {

    $message_type = $this->getMessageType('disable-patches-file-check');

    if (!isset($this->extra['patches-file'])) {
      return;
    }

    $this->say("
      Store patches for Composer's packages in `composer.json`, not in a separate file.
      \nSee https://architecture.lullabot.com/adr/20220429-composer-patches-inline/
      ",
      $message_type
    );
  }

  /**
   * Handle post install command events.
   *
   * @param Event $event the event to handle
   */
  public function onPostInstallCmd(Event $event)
  {
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
    // Composer checks.
    $this->checkDrupalCoreComposerPatchesLevel();
    $this->checkComposerBreaksIfPatchesDoNotApply();
    $this->checkPatchesStoredInComposerJson();
    $this->checkComposerPatchesAreLocal();
  }

  /**
   * Say a message and throw exception if the message type is error.
   */
  private function say(string $message, string $message_type)
  {
    $this->io->log($message, $message_type);

    if ($message_type === 'error') {
      throw new RuntimeException(trim($message));
    }
  }


  /**
   * Get the message type based on the setting.
   *
   * @param string $setting
   * @return string
   */
  private function getMessageType(string $setting) {
    if (!isset($this->extra['composer-checks'])) {
      return 'error';
    }

    $just_a_warning = in_array($setting, $this->extra['composer-checks']) ?? false;
    $message_type = $just_a_warning ? 'warning' : 'error';
    return $message_type;
  }

}
