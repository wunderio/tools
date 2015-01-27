<?php

namespace WK\Behat;

use FeatureContext;
use WK\Behat\EmailService;

/**
 * Implementation for mailinator.com email service.
 */
class MailinatorEmailService implements EmailService {

  const TOKEN = '09915226ad1e44db842360c21f860076';
  const API_BASE_URL = 'https://api.mailinator.com/api/';
  const EMAIL_BASE_PATH = 'email';
  const INBOX_BASE_PATH = 'inbox';

  private $featureContext;
  private $session;

  public function __construct(FeatureContext $featureContext) {
    $this->featureContext = $featureContext;
    $this->session = $this->featureContext->getSession();
  }

  public function viewTheLatestEmailForUser($user) {
    $this->session->wait(3000);
    $inboxUrl = $this->getInboxUrl($user);
    $inboxResponse = $this->get($inboxUrl);
    $newestMessage = array_pop($inboxResponse->messages);
    $emailUrl = $this->getEmailUrl($newestMessage->id);
    $emailResponse = $this->get($emailUrl);

    return $emailResponse->data->parts[0]->body;
  }

  public function viewTheLatestEmailForAddress($email) {
    $this->session->wait(3000);
    $inboxUrl = $this->getInboxUrl($email);
    $inboxResponse = $this->get($inboxUrl);
    $newestMessage = array_pop($inboxResponse->messages);
    $emailUrl = $this->getEmailUrl($newestMessage->id);
    $emailResponse = $this->get($emailUrl);

    return $emailResponse->data->parts[0]->body;
  }

  public function clickALinkInEmail($emailBody, $pattern) {
    $link = $this->getActivationLink($emailBody);
    $this->session->executeScript('window.location.href = "'.$link.'";');
  }

  private function getInboxUrl($user) {
    $parameters = $this->getParameters(array('to' => $user));

    return self::API_BASE_URL . self::INBOX_BASE_PATH . '?' . $parameters;
  }

  private function getEmailUrl($emailId) {
    $parameters = $this->getParameters(array('msgid' => $emailId));

    return self::API_BASE_URL . self::EMAIL_BASE_PATH . '?' . $parameters;
  }

  private function get($url) {
    return json_decode(file_get_contents($url));
  }

  private function getParameters(array $parameters) {
    $parameters += array('token' => self::TOKEN);

    return http_build_query($parameters);
  }

  private function getActivationLink($emailBody) {
    $linkStartPosition = strpos($emailBody, 'https://');
    $linkEndPosition = strpos($emailBody, '=fi') + strlen('=fi');
    $link = substr($emailBody, $linkStartPosition, $linkEndPosition - $linkStartPosition);

    return $link;
  }
}
