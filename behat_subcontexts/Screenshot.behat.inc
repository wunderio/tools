<?php
/**
 * @file: 
 *  Screenshot.behat.inc
 * Date: 29.10.14
 * Time: 08:39
 */
use Behat\Behat\Context\BehatContext;
use Drupal\DrupalExtension\Context\DrupalSubContextInterface;

/**
 * Screenshot
 */
class ScreenshotContext extends BehatContext implements DrupalSubContextInterface {

  /**
   * Return a unique alias for this sub-context.
   *
   * @return string
   */
  public static function getAlias() {
    return 'Screenshot';
  }

  /**
   * This works for Selenium and other real browsers that support screenshots.
   *
   * @Then /^show me a screenshot$/
   */
  public function showMeAScreenshot() {
    $mainContext = $this->getMainContext();
    $image_data = $mainContext->getSession()->getDriver()->getScreenshot();
    $file_and_path = getcwd() . '/screenshots/behat_screenshot_' . date("Y-m-d_H-i-s") . '.jpg';
    file_put_contents($file_and_path, $image_data);

  }

}
