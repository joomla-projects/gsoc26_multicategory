<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_categories
 *
 * @copyright   (C) 2026 Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later
 */

namespace Joomla\Component\Categories\Administrator\Field;

use Joomla\CMS\Factory;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Secondary Categories field.
 *
 * @since __DEPLOY_VERSION__
 */
class SecondaryCategoriesField extends CategoryeditField
{
    /**
     * Field type.
     *
     * @var    string
     *
     * @since  __DEPLOY_VERSION__
     */
    public $type = 'SecondaryCategories';

    /**
     * Layout to use for the field.
     *
     * @var string
     *
     * @since __DEPLOY_VERSION__
     */
    protected $layout = 'joomla.form.field.list-fancy-select';

    /**
     * Method to get the field options.
     *
     * @return  array
     *
     * @since   __DEPLOY_VERSION__
     */
    protected function getOptions()
    {
        $options   = [];
        $published = $this->element['published'] ? explode(',', (string) $this->element['published']) : [0, 1];

        $extension = $this->element['extension'] ? (string) $this->element['extension'] : 'com_content';

        // Always use the primary category as ACL reference.
        $oldCat = (int) $this->form->getValue('catid', 0);

        $db   = $this->getDatabase();
        $user = $this->getCurrentUser();

        $query = $db->createQuery()
            ->select(
                [
                    $db->quoteName('a.id', 'value'),
                    $db->quoteName('a.title', 'text'),
                    $db->quoteName('a.level'),
                    $db->quoteName('a.published'),
                    $db->quoteName('a.lft'),
                    $db->quoteName('a.language'),
                ]
            )
            ->from($db->quoteName('#__categories', 'a'))
            ->where($db->quoteName('a.extension') . ' = :extension')
            ->bind(':extension', $extension, ParameterType::STRING);

        $state = ArrayHelper::toInteger($published);
        $query->whereIn($db->quoteName('a.published'), $state);

        if (!$user->authorise('core.admin')) {
            $query->whereIn(
                $db->quoteName('a.access'),
                $user->getAuthorisedViewLevels()
            );
        }

        $query->order($db->quoteName('a.lft') . ' ASC');

        $db->setQuery($query);

        try {
            $options = $db->loadObjectList();
        } catch (\RuntimeException $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return [];
        }

        foreach ($options as $option) {
            if ($option->published == 1) {
                $option->text = str_repeat('- ', max(0, $option->level - 1)) . $option->text;
            } else {
                $option->text = str_repeat('- ', max(0, $option->level - 1)) . '[' . $option->text . ']';
            }

            if ($option->language !== '*') {
                $option->text .= ' (' . $option->language . ')';
            }
        }

        if ($oldCat === 0) {
            foreach ($options as $i => $option) {
                if ( $option->level != 0 && !$user->authorise('core.create', $extension . '.category.' . $option->value )) {
                    unset($options[$i]);
                }
            }
        } else {
            $currentAsset = $extension . '.category.' . $oldCat;

            foreach ($options as $i => $option) {

                if ($option->level != 0 && $option->value != $oldCat && !$user->authorise('core.edit.state', $currentAsset)) {
                    unset($options[$i]);
                    continue;
                }

                $targetAsset = $extension . '.category.' . $option->value;

                if ($option->level != 0 && $option->value != $oldCat && !$user->authorise('core.create', $targetAsset)) {
                    unset($options[$i]);
                }
            }
        }

        return $options;
    }
}
