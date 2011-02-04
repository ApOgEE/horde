<?php
/**
 * The Horde_Kolab_Cli_Module_Folder:: class handles single folders.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Kolab_Cli
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link     http://pear.horde.org/index.php?package=Kolab_Cli
 */

/**
 * The Horde_Kolab_Cli_Module_Folder:: class handles single folders.
 *
 * Copyright 2010-2011 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @category Horde
 * @package  Kolab_Cli
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link     http://pear.horde.org/index.php?package=Kolab_Cli
 */
class Horde_Kolab_Cli_Module_Folder
implements Horde_Kolab_Cli_Module
{
    /**
     * Get the usage description for this module.
     *
     * @return string The description.
     */
    public function getUsage()
    {
        return Horde_Kolab_Cli_Translation::t("  folder - Handle a single folder (the default action is \"show\")

  - show   PATH        : Display information about the folder at PATH.
  - create PATH [TYPE] : Create the folder PATH (with the optional type TYPE).
  - delete PATH        : Delete the folder PATH.
  - rename OLD NEW     : Rename the folder from OLD to NEW.


");
    }

    /**
     * Get a set of base options that this module adds to the CLI argument
     * parser.
     *
     * @return array The options.
     */
    public function getBaseOptions()
    {
        return array();
    }

    /**
     * Indicate if the module provides an option group.
     *
     * @return boolean True if an option group should be added.
     */
    public function hasOptionGroup()
    {
        return false;
    }

    /**
     * Return the title for the option group representing this module.
     *
     * @return string The group title.
     */
    public function getOptionGroupTitle()
    {
        return '';
    }

    /**
     * Return the description for the option group representing this module.
     *
     * @return string The group description.
     */
    public function getOptionGroupDescription()
    {
        return '';
    }

    /**
     * Return the options for this module.
     *
     * @return array The group options.
     */
    public function getOptionGroupOptions()
    {
        return array();
    }

    /**
     * Handle the options and arguments.
     *
     * @param mixed &$options   An array of options.
     * @param mixed &$arguments An array of arguments.
     * @param array &$world     A list of initialized dependencies.
     *
     * @return NULL
     */
    public function handleArguments(&$options, &$arguments, &$world)
    {
    }

    /**
     * Run the module.
     *
     * @param Horde_Cli $cli       The CLI handler.
     * @param mixed     $options   An array of options.
     * @param mixed     $arguments An array of arguments.
     * @param array     &$world    A list of initialized dependencies.
     *
     * @return NULL
     */
    public function run($cli, $options, $arguments, &$world)
    {
        if (!isset($arguments[1])) {
            $action = 'show';
        } else {
            $action = $arguments[1];
        }
        if (!isset($arguments[2])) {
            $folder_name = 'INBOX';
        } else {
            $folder_name = $arguments[2];
        }
        switch ($action) {
        case 'create':
            if (!isset($arguments[3])) {
                $folder = $world['storage']->getList()
                    ->createFolder($folder_name);
            } else {
                $folder = $world['storage']->getList()
                    ->createFolder($folder_name, $arguments[3]);
            }
            $this->_showFolder($folder_name, $world, $cli);
            break;
        case 'rename':
            $folder = $world['storage']->getList()
                ->renameFolder($folder_name, $arguments[3]);
            $this->_showFolder($arguments[3], $world, $cli);
            break;
        case 'delete':
            $folder = $world['storage']->getList()
                ->deleteFolder($folder_name);
            break;
        case 'getacl':
            $acl = $world['storage']->getList()
                ->getQuery(Horde_Kolab_Storage_List::QUERY_ACL)
                ->getAcl($folder_name);
            $cli->writeln($folder_name);
            $cli->writeln(str_repeat('=', strlen($folder_name)));
            $pad = max(array_map('strlen', array_keys($acl))) + 2;
            foreach ($acl as $user => $rights) {
                $cli->writeln(Horde_String::pad($user . ':', $pad) . $rights);
            }
            break;
        case 'getmyacl':
            $acl = $world['storage']->getList()
                ->getQuery(Horde_Kolab_Storage_List::QUERY_ACL)
                ->getMyAcl($folder_name);
            $cli->writeln('Your rights on ' . $folder_name . ': ' . $acl);
            break;
        case 'show':
            $this->_showFolder($folder_name, $world, $cli);
            break;
        default:
            $cli->message(
                sprintf(
                    Horde_Kolab_Cli_Translation::t('Action %s not supported!'),
                    $action
                ),
                'cli.error'
            );
            break;
        }
    }

    private function _showFolder($folder_name, $world, $cli)
    {
        $folder = $world['storage']->getList()->getFolder($folder_name);
        $cli->writeln('Path:      ' . $folder->getPath());
        $cli->writeln('Title:     ' . $folder->getTitle());
        $cli->writeln('Owner:     ' . $folder->getOwner());
        $cli->writeln('Type:      ' . $folder->getType());
        $cli->writeln('Namespace: ' . $folder->getNamespace());
    }
}