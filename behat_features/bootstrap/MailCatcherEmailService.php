<?php
namespace WK\Behat;

use FeatureContext;
use WK\Behat\EmailService;

require_once 'EmailService.php';

/**
 * Implementation for MailCatcher email service.
 */
class MailCatcherEmailService implements EmailService {

  const RETRY_COUNT_MAX = 15;
  const WAIT_TIME_MILLISECONDS = 200;

  private $baseUrl;
  private $featureContext;
  private $session;

  public function __construct(FeatureContext $featureContext) {
    $this->featureContext = $featureContext;
    $this->session = $this->featureContext->getSession();
    $this->baseUrl = $featureContext->emailTestServerUrl;
    $this->activationUrlBase = $featureContext->activationUrlBase;
  }

  public function viewTheLatestEmailForUser($user) {
    for ($retryCount = 0; $retryCount < self::RETRY_COUNT_MAX; $retryCount++) {
      $message = $this->getLatestMessageForUser($user);

      if (empty($message)) {
        $this->wait();
      } else {
        break;
      }
    }

    return empty($message) ? '' : $message;
  }

  public function viewTheLatestEmailForAddress($email) {
    for ($retryCount = 0; $retryCount < self::RETRY_COUNT_MAX; $retryCount++) {
      $message = $this->getLatestMessageForAddress($email);

      if (empty($message)) {
        $this->wait();
      } else {
        break;
      }
    }

    return empty($message) ? '' : $message;
  }

  public function clickALinkInEmail($emailBody) {
    $link = $this->getActivationLink($emailBody);
    $this->session->visit($link);
  }

  private function wait() {
    $this->session->wait(self::WAIT_TIME_MILLISECONDS);
  }

  private function getLatestMessageForUser($user) {
    $messages = $this->getAllMessages();

    if (!empty($messages)) {
      foreach ($messages as $message) {
        if ($this->hasRecipient($message, $user)) {
          $latestMessage = $message;
          break;
        }
      }
    }

    return isset($latestMessage) ? $this->getMessagePlainBodyById($latestMessage->id) : NULL;
  }

  private function getLatestMessageForAddress($user) {
    $messages = $this->getAllMessages();

    if (!empty($messages)) {
      foreach ($messages as $message) {
        if ($this->hasRecipientAddress($message, $user)) {
          $latestMessage = $message;
          break;
        }
      }
    }

    return isset($latestMessage) ? $this->getMessagePlainBodyById($latestMessage->id) : NULL;
  }

  private function getAllMessages() {
    return $this->get("$this->baseUrl/messages");
  }

  private function get($url) {
    return json_decode(file_get_contents($url));
  }

  private function hasRecipient($message, $user) {
    return !empty($message->recipients) && in_array("<$user>", $message->recipients);
  }

  private function hasRecipientAddress($message, $user) {
    return !empty($message->recipients) && in_array("<$user>", $message->recipients);
  }

  private function getMessagePlainBodyById($id) {
    return file_get_contents("$this->baseUrl/messages/$id.plain");
  }

  private function getActivationLink($emailBody) {
    $matches = array();
    $activationUrlBase = $this->activationUrlBase;
    if(preg_match_all('/https?:[\S]+/', $emailBody, $matches)) {
      return $matches[0][0];
    }
    throw new \Exception('No link in email found');
  }
}
