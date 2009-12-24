<?php
/**
 * Whups mail processing library.
 *
 * Copyright 2004-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author  Jason M. Felice <jason.m.felice@gmail.com>
 * @author  Jan Schneider <jan@horde.org>
 * @package Whups
 */
class Whups_Mail {

    /**
     * Parse a MIME message and create a new ticket.
     *
     * @param string $text       This is the full text of the MIME message.
     * @param array $info        An array of information for the new ticket.
     *                           This should include:
     *                           - 'queue'    => queue id
     *                           - 'type'     => type id
     *                           - 'state'    => state id
     *                           - 'priority' => priority id
     *                           - 'ticket'   => ticket id (prevents creation
     *                                           of new tickets)
     * @param string $auth_user  This will be the Horde user that creates the
     *                           ticket. If null, we will try to deduce from
     *                           the message's From: header. We do NOT default
     *                           to Horde_Auth::getAuth().
     *
     * @return Whups_Ticket | PEAR_Error  Ticket or Error object.
     */
    static public function processMail($text, $info, $auth_user = null)
    {
        global $conf;

        $message = Horde_Mime_Part::parseMessage($text);

        if (preg_match("/^(.*?)\r?\n\r?\n/s", $text, $matches)) {
            $hdrText = $matches[1];
        } else {
            $hdrText = $text;
        }
        $headers = Horde_Mime_Headers::parseHeaders($hdrText);

        // If this message was generated by Whups, don't process it.
        if ($headers->getValue('X-Whups-Generated')) {
            return true;
        }

        // Use the message subject as the ticket summary.
        $info['summary'] = $headers->getValue('subject');
        $from = $headers->getValue('from');

        // Format the message into a comment.
        $comment = _("Received message:") . "\n\n";
        if (!empty($GLOBALS['conf']['mail']['include_headers'])) {
            foreach ($headers->toArray(array('nowrap' => true)) as $name => $vals) {
                if (!in_array(strtolower($name), array('subject', 'from', 'to', 'cc', 'date'))) {
                    if (is_array($vals)) {
                        foreach ($vals as $val) {
                            $comment .= $name . ': ' . $val . "\n";
                        }
                    } else {
                        $comment .= $name . ': ' . $vals . "\n";
                    }
                }
            }

            $comment .= "\n";
        }

        // Look for the body part.
        $body_id = $message->findBody();
        if ($body_id) {
            $part = $message->getPart($body_id);
            $comment .= Horde_String::convertCharset($part->transferDecode(), $part->getCharset());
        } else {
            $comment .= _("[ Could not render body of message. ]");
        }

        $info['comment'] = $comment . "\n";

        // Try to determine the Horde user for creating the ticket.
        if (empty($auth_user)) {
            $auth_user = self::_findAuthUser(Horde_Mime_Address::bareAddress($from));
        }
        $author = $auth_user;

        if (empty($auth_user) && !empty($info['default_auth'])) {
            $auth_user = $info['default_auth'];
            if (!empty($from)) {
                $info['user_email'] = $from;
            }
        }

        if (empty($auth_user) && !empty($conf['mail']['username'])) {
            $auth_user = $conf['mail']['username'];
            if (!empty($from)) {
                $info['user_email'] = $from;
            }
        }

        // Authenticate as the correct Horde user.
        if (!empty($auth_user) && $auth_user != Horde_Auth::getAuth()) {
            Horde_Auth::setAuth($auth_user, array());
        }

        // See if we can match this message to an existing ticket.
        if ($ticket = self::_findTicket($info)) {
            $ticket->change('comment', $info['comment']);
            $ticket->change('comment-email', $from);
            $result = $ticket->commit($author);
            if (is_a($result, 'PEAR_Error')) {
                $result->addUserInfo(_("current user:") . ' ' . $auth_user);
                return $result;
            }
        } elseif (!empty($info['ticket'])) {
            // Didn't match an existing ticket though a ticket number had been
            // specified.
            return PEAR::raiseError(sprintf(_("Could not find ticket \"%s\"."),
                                            $info['ticket']));
        } else {
            if (!empty($info['guess-queue'])) {
                // Try to guess the queue name for the new ticket from the
                // message subject.
                $queues = $GLOBALS['whups_driver']->getQueues();
                foreach ($queues as $queueId => $queueName) {
                    if (preg_match('/\b' . preg_quote($queueName, '/') . '\b/i',
                                   $info['summary'])) {
                        $info['queue'] = $queueId;
                        break;
                    }
                }
            }
            // Create a new ticket.
            $ticket = Whups_Ticket::newTicket($info, $author);
            if (is_a($ticket, 'PEAR_Error')) {
                $ticket->addUserInfo(_("current user:") . ' ' . $auth_user);
                return $ticket;
            }
        }

        // Extract attachments.
        $dl_list = array_slice(array_keys($message->contentTypeMap()), 1);
        foreach ($dl_list as $key) {
            if (strpos($key, '.', 1) === false) {
                $part = $message->getPart($key);
                $part->transferDecodeContents();
                $tmp_name = Horde::getTempFile('whups');
                $fp = @fopen($tmp_name, 'wb');
                if (!$fp) {
                    Horde::logMessage(sprintf('Cannot open file %s for writing.',
                                              $tmp_name),
                                      __FILE__, __LINE__, PEAR_LOG_ERR);
                    return $ticket;
                }
                fwrite($fp, $part->getContents());
                fclose($fp);
                $part_name = $part->getName(true);
                $ticket->change('attachment', array('name' => $part_name,
                                                    'tmp_name' => $tmp_name));
                $result = $ticket->commit();
                if (is_a($result, 'PEAR_Error')) {
                    $result->addUserInfo(_("current user:") . ' ' . $auth_user);
                    return $result;
                }
            }
        }
    }

    /**
     * Returns the ticket number matching the provided information.
     *
     * @param array $info  A hash with ticket information.
     *
     * @return integer  The ticket number if has been passed in the subject,
     *                  false otherwise.
     */
    static protected function _findTicket($info)
    {
        if (!empty($info['ticket'])) {
            $ticketnum = $info['ticket'];
        } elseif (preg_match('/\[[\w\s]*#(\d+)\]/', $info['summary'], $matches)) {
            $ticketnum = $matches[1];
        } else {
            return false;
        }

        $ticket = Whups_Ticket::makeTicket($ticketnum);
        if (is_a($ticket, 'PEAR_Error')) {
            return false;
        }

        return $ticket;
    }

    /**
     * Searches the From: header for an email address contained in one
     * of our users' identities.
     *
     * @param string $from  The From address.
     *
     * @return string  The Horde user name that matches the headers' From:
     *                 address or null if the users can't be listed or no
     *                 match has been found.
     */
    static protected function _findAuthUser($from)
    {
        global $conf;

        require_once 'Horde/Identity.php';

        $params = Horde::getDriverConfig('auth', $conf['auth']['driver']);
        $auth = Horde_Auth::singleton($conf['auth']['driver'], $params);
        if ($auth->hasCapability('list')) {
            foreach ($auth->listUsers() as $user) {
                $identity = &Identity::singleton('none', $user);
                $addrs = $identity->getAll('from_addr');
                foreach ($addrs as $addr) {
                    if (strcasecmp($from, $addr) == 0) {
                        return $user;
                    }
                }
            }
        }

        return false;
    }

}
