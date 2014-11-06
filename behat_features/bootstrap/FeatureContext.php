<?php



use Behat\Behat\Context\ClosuredContextInterface,
  Behat\Behat\Context\TranslatedContextInterface,
  Behat\Behat\Context\BehatContext,
  Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
  Behat\Gherkin\Node\TableNode;

use Drupal\Component\Utility\Random;
use Drupal\DrupalExtension\Context\DrupalContext;
use WK\Behat\EmailService,
  WK\Behat\MailCatcherEmailService;

//
// Require 3rd-party libraries here:
//
//   require_once 'PHPUnit/Autoload.php';
//   require_once 'PHPUnit/Framework/Assert/Functions.php';
//

/**
 * Some of our features need to run their scenarios sequentially
 * and we need a way to pass relevant data (like generated node id)
 * from one scenario to the next.  This class provides a simple
 * registry to pass data. This should be used only when absolutely
 * necessary as scenarios should be independent as often as possible.
 */
abstract class HackyDataRegistry {
  public static $data = array();
  public static function set($name, $value) {
    self::$data[$name] = $value;
  }
  public static function get($name) {
    $value = "";
    if (isset(self::$data[$name])) {
      $value = self::$data[$name];
    }
    if ($value === "") {
      $backtrace = debug_backtrace(FALSE, 2);
      $calling = $backtrace[1];
      if (array_key_exists('line', $calling) && array_key_exists('file', $calling)) {
        throw new PendingException(sprintf("Fix HackyDataRegistry accessing with unset key at %s:%d in %s.", $calling['file'], $calling['line'], $calling['function']));
      } else {
        // Disabled primarily for calls from AfterScenario for now due to too many errors.
        //throw new PendingException(sprintf("Fix HackyDataRegistry accessing with unset key in %s.", $calling['function']));
      }
    }
    return $value;
  }
  public static function keyExists($name) {
    if (isset(self::$data[$name])) {
      return TRUE;
    }
    return FALSE;
  }
}

class LocalDataRegistry {
  public $data = array();

  public function set($name, $value) {
    $this->data[$name] = $value;
  }

  public function get($name) {
    $value = "";
    if (isset($this->data[$name])) {
      $value = $this->data[$name];
    }
    return $value;
  }
}

/**
 * Features context.
 */
class FeatureContext extends DrupalContext {
  /**
   *Store rss feed xml content
   */
  private $xmlContent = "";

  /**
   * Store project value
   */
  private $project_value = '';

  /**
   * Store the md5 hash of a downloaded file.
   */
  private $md5Hash = '';

  /**
   * Store a post title value
   */
  private $postTitle = '';

  /**
   * Store the file name of a downloaded file
   */
  private $downloadedFileName = '';

  private $vars = array();

  /**
   * Create a context specific data storage container.
   */

  private $dataRegistry = '';

  /**
   * Initializes context.
   *
   * Every scenario gets its own context object.
   *
   * @param array $parameters
   *   context parameters (set them up through behat.yml)
   */
  public function __construct(array $parameters) {
    $this->dataRegistry = new LocalDataRegistry();
    $this->default_browser = $parameters['default_browser'];
    if (isset($parameters['drupal_users'])) {
      $this->drupal_users = $parameters['drupal_users'];
    }
    if (isset($parameters['drupal_strings'])) {
      $this->drupal_strings = $parameters['drupal_strings'];
    }
    if (isset($parameters['emailTestServerUrl'])) {
      $this->emailTestServerUrl = $parameters['emailTestServerUrl'];
    }
    if (isset($parameters['activationUrlBase'])) {
      $this->activationUrlBase = $parameters['activationUrlBase'];
    }
  }

  /**
   * Restart the session after each scenario.
   *
   * @AfterScenario
   */
  public function restartSession() {
    $this->getSession()->reset();
  }
  /**
   * Take screenshot when step fails. Works only with Selenium2Driver.
   *
   * If on a Mac, runnining from the command line, with the SHOW_SNAPSHOT
   *  environment variable set to 1, the snapshot will open in Preview.
   *
   * Screenshot is saved at [Date]/[Feature]/[Scenario]/[Step].jpg .
   *
   * @AfterStep @javascript
   */
  public function takeScreenshotAfterFailedStep(Behat\Behat\Event\StepEvent $event) {
    if ($event->getResult() === Behat\Behat\Event\StepEvent::FAILED) {
      $driver = $this->getSession()->getDriver();
      if ($driver instanceof Behat\Mink\Driver\Selenium2Driver) {
        $step = $event->getStep();
        $path = array(
          'date' => date("Ymd-Hi"),
          'feature' => $step->getParent()->getFeature()->getTitle(),
          'scenario' => $step->getParent()->getTitle(),
          'step' => $step->getType() . ' ' . $step->getText(),
        );
        $path = preg_replace('/[^\-\.\w]/', '_', $path);
        $filename = getcwd() . '/screenshots/failures/' . implode('/', $path) . '.jpg';

        // Create directories if needed.
        if (!@is_dir(dirname($filename))) {
          @mkdir(dirname($filename), 0775, TRUE);
        }

        file_put_contents($filename, $driver->getScreenshot());
      }
    }
  }
  /**
   * @defgroup MinkContext overrides
   * @{
   */

  /**
   * Override MinkContext::fixStepArgument().
   */
  protected function fixStepArgument($argument) {
    $argument = str_replace('\\"', '"', $argument);

    // Initialize the replaceable arguments.
    $this->initVars();
    // Token replace the argument.
    static $random = array();
    $random_generator = new Random();
    for ($start = 0; ($start = strpos($argument, '[', $start)) !== FALSE; ) {
      $end = strpos($argument, ']', $start);
      if ($end === FALSE) {
        break;
      }
      $name = substr($argument, $start + 1, $end - $start - 1);
      if ($name == 'random') {
        $this->vars[$name] = $random_generator->name();
        $random[] = $this->vars[$name];
      }
      // In order to test previous random values stored in the form,
      // suppport random:n, where n is the number or random's ago
      // to use, i.e., random:1 is the previous random value.
      elseif (substr($name, 0, 7) == 'random:') {
        $num = substr($name, 7, 1);
        $num = (int) $num;
        if (0 < $num && $num <= count($random)) {
          $this->vars[$name] = $random[$num - 1];
        }
        else {
          $this->vars[$name] = $random_generator->name();
          $random[] = $this->vars[$name];
        }
      }
      if (isset($this->vars[$name])) {
        $argument = substr_replace($argument, $this->vars[$name], $start, $end - $start + 1);
        $start += strlen($this->vars[$name]);
      }
      else {
        $start = $end + 1;
      }
    }

    return $argument;
  }
  /**
   * @} End of "defgroup MinkContext overrides".
   *
   * @defgroup helper functions
   * @{
   */
  public function replaceTokenArgument($arg) {
    return $this->fixStepArgument($arg);
  }
  /**
   * Helper function to fetch user passwords stored in behat.yml.
   *
   * @param string $type
   *   The user type, e.g. drupal or git.
   *
   * @param string $name
   *   The username to fetch the password for.
   *
   * @return string
   *   The matching password or FALSE on error.
   */
  public function fetchPassword($type, $name) {
    $property_name = $type . '_users';
    try {
      $property = $this->$property_name;
      $password = $property[$name];
      return $password;
    } catch (Exception $e) {
      throw new Exception("Non-existant user/password for $property_name:$name please check behat.local.yml.");
    }
  }
  /**
   * Helper function to fetch Drupal strings stored in behat.yml.
   *
   * @param string $name
   *   The username to fetch the password for.
   *
   * @return string
   *   The matching password or FALSE on error.
   */
  public function fetchDrupalString($name) {
    $property_name = 'drupal_strings';
    try {
      $property = $this->$property_name;
      $string = $property[$name];
      return $string;
    } catch (Exception $e) {
      throw new Exception("Non-existant Druapl string for $property_name:$name please check behat.yml.");
    }
  }

  /**
   * Helper function to fetch previously generated random strings stored by randomString().
   *
   * @param string $name
   *   The name of the random string.
   *
   * @return string
   *   The stored string.
   */
  public function fetchRandomString($name) {
    return HackyDataRegistry::get('random:' . $name);
  }

  /**
   * Helper function to check if the `expect` library is installed.
   */
  public function checkExpectLibraryStatus() {
    $process = new Process('which expect');
    $process->run();
    if (!$process->isSuccessful()) {
      throw new RuntimeException('This feature requires that the `expect` library be installed');
    }
  }

  /**
   * Private function for the whoami step.
   */
  private function whoami() {
    $element = $this->getSession()->getPage();
    // Go to the user page.
    $this->getSession()->visit($this->locatePath('/user'));
    if ($find = $element->find('css', '#page-title')) {
      $page_title = $find->getText();
      $username = str_replace($this->fetchDrupalString('user_profile_header_prefix'), '', $page_title);
      if ($username) {
        return $username;
      }
    }
    return FALSE;
  }


  /**
   * A step to deal with slow loading pages
   */

  public function spin ($lambda, $wait = 120) {
    for ($i = 0; $i < $wait; $i++) {
      try {
        if ($lambda($this)) {
          return true;
        }
      } catch (Exception $e) {
        // do nothing
      }
      sleep(1);
    }
    $backtrace = debug_backtrace();
    throw new Exception('Something* took too long to load at ' . $this->getSession()->getCurrentUrl());
  }
  /**
   * Private function for checking login status.
   */
  private function loggedInStatus() {
    // Go to the user page.
    $this->getSession()->visit($this->locatePath('/user'));
    $element = $this->getSession()->getPage();
    if (empty($element)) {
      throw new Exception('Page not found');
    }
    // Get the page title.
    $title_element = $element->findByID('page-title');
    if (empty($title_element)) {
      throw new Exception ('No page title found at ' . $this->getSession()->getCurrentUrl());
    }
    $page_title = $title_element->getText();
    if ($page_title == 'User account') {
      return FALSE;
    }
    return TRUE;
  }
  /**
   * Initialize the replaceable arguments.
   */
  private function initVars() {
    if (!isset($this->vars['host'])) {
      $this->vars['host'] = parse_url($this->getSession()->getCurrentUrl(), PHP_URL_HOST);
    }
  }

  /**
   * Creates an email service.
   */
  private function setEmailService() {
    require_once 'MailCatcherEmailService.php';
    $this->emailService = new MailCatcherEmailService($this);
  }
  /**
   * @} End of defgroup "helper functions".
   */

  /**
   * Authenticates a user.
   *
   * @Given /^I am logged in as the "([^"]*)" with the password "([^"]*)"$/
   */
  public function iAmLoggedInAsWithThePassword($username, $passwd) {
    $logged_in = $this->loggedInStatus();
    if ($logged_in) {
      // Logout.
      $this->getSession()->visit($this->locatePath('/user/logout'));
    }

    $page = $this->getSession()->getPage();
    if (empty($page)) {
      throw new Exception('Page not found');
    }
    $page->fillField($this->getDrupalText('username_field'), $username);
    $page->fillField($this->getDrupalText('password_field'), $passwd);
    $submit = $page->findButton($this->getDrupalText('log_in'));
    if (empty($submit)) {
      throw new Exception('No submit button at ' . $this->getSession()->getCurrentUrl());
    }
    // Log in.
    $submit->click();
    $user = $this->whoami();
    if (strtolower($user) == strtolower($username)) {
      HackyDataRegistry::set('username', $username);
      $this->dataRegistry->set('current_username', $username);
      // @todo: find a way to also save user ID
//        $link = $this->getSession()->getPage()->findLink("Your Dashboard");
//        // URL format: /user/{uid}/dashboard
//        preg_match("/\/user\/(.*)\//", $link->getAttribute('href'), $match);
//        if (!empty($match[1])) {
//          HackyDataRegistry::set('uid:' . $username, trim($match[1]));
//        }
      return;
    }

    throw new Exception('Not logged in.');
  }

  /**
   * Authenticates a user with password from configuration.
   *
   * @Given /^I am logged in as the "([^"]*)"$/
   */
  public function iAmLoggedInAs($username) {
    $password = $this->fetchPassword('drupal', $username);
    $this->iAmLoggedInAsWithThePassword($username, $password);
    $this->dataRegistry->set('current_username', $username);
  }

  /**
   * @Then /^I should see the page title containing "([^"]*)"$/
   */
  public function iShouldSeeThePageTitleContaining($string_partial) {
    $page = $this->getSession()->getPage();
    $current_url = $this->getSession()->getCurrentUrl();
    $title_tag = $page->find('css', 'h1');
    if (!$title_tag) {
      throw new \Exception(sprintf("A page title (H1) was not found on the page %s", $current_url));
    }
    $title_text = $title_tag->getText();
    if (!$title_text) {
      throw new \Exception(sprintf("The page title was empty on the page %s", $current_url));
    }
    if (strpos($title_text, $string_partial) !== FALSE) {
      return;
    }
    throw new \Exception(sprintf("The text '%s' was not found in the page title on the page %s", $string_partial, $current_url));
  }

  /**
   * @Then /^I should see a panel pane with the headline "([^"]*)" in the "([^"]*)" region$/
   */
  public function iShouldSeeAPanelPaneWithTheHeadlineInTheRegion($headline_text, $region) {
    $region_obj = $this->getRegion($region);
    if (!$region_obj->findAll('css', '.panel-pane')) {
      throw new \Exception('No panel pane found.');
    }
    foreach (array(
               '.panel-pane h1',
               '.panel-pane h2',
               '.panel-pane h3',
               '.panel-pane h4',
               '.panel-pane h5',
               '.panel-pane h6',
             ) as $tag) {
      $results = $region_obj->findAll('css', $tag);
      foreach ($results as $result) {
        if ($result->getText() == $headline_text) {
          return;
        }
      }
    }
    throw new \Exception(sprintf("The text '%s' was not found in any pane headline", $headline_text));
  }

  /**
   * @Given /^I should see a table containing the text "([^"]*)" in the "([^"]*)" region$/
   */
  public function iShouldSeeATableContainingTheTextInTheRegion($table_cell_text, $region) {
    $region_obj = $this->getRegion($region);
    $tables = $region_obj->findAll('css', 'table');
    if (!count($tables)) {
      throw new \Exception(sprintf("No table found in the region '%s'", $region));
    }
    foreach ($tables as $table) {

      if (strpos($table->getText(), $table_cell_text) !== FALSE) {
        return;
      }

    }
    throw new \Exception(sprintf("The text '%s' was not found in any table", $table_cell_text));
  }

  /**
   * @Given /^I should see the link "([^"]*)" pointing to "([^"]*)"$/
   */
  public function iShouldSeeTheLinkPointingTo($link, $url) {
    $page = $this->getSession()->getPage();
    $link = $page->findLink($link);
    $path = $link->getAttribute('href');
    if ($path == $url) {
      return;
    }
    throw new \Exception(sprintf("The link href(%s) did not match the given url(%s)", $path, $url));
  }

  /**
   * @Given I click the activation link in my email inbox
   */
  public function iClickTheActivationLinkInMyEmailInbox() {
    $this->setEmailService();
    $email_body = $this->emailService->viewTheLatestEmailForUser($this->dataRegistry->get('current_username'));
    $this->emailService->clickALinkInEmail($email_body);
  }

  /**
   * @When /^I click the activation link that was sent to "([^"]*)"$/
   */
  public function iClickTheActivationLinkThatWasSentTo($email) {
    $email = $this->fixStepArgument($email);
    $this->setEmailService();
    $email_body = $this->emailService->viewTheLatestEmailForAddress($email);
    $this->emailService->clickALinkInEmail($email_body, $this->activationUrlBase);
  }

  /**
   * @When /^I click the "([^"]*)" link that was sent to "([^"]*)"$/
   */
  public function iClickTheLinkThatWasSentTo($pattern, $email) {
    $email = $this->fixStepArgument($email);
    $this->setEmailService();
    $email_body = $this->emailService->viewTheLatestEmailForAddress($email);
    $this->emailService->clickALinkInEmail($email_body, $pattern);
  }
}
