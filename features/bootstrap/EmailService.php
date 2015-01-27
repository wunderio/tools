<?php

namespace WK\Behat;

/**
 * An interface for abstracting email service functionalities.
 */
interface EmailService {

  public function clickALinkInEmail($emailBody, $pattern);
  public function viewTheLatestEmailForUser($user);
  public function viewTheLatestEmailForAddress($email);
}
