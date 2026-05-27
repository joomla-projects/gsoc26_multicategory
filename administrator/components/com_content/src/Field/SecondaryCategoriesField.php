<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_content
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Content\Administrator\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\ParameterType;
use Joomla\Component\Categories\Administrator\Field\CategoryeditField;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Secondary Categories field.
 *
 * @since  __DEPLOY_VERSION__
 */
class SecondaryCategoriesField extends CategoryeditField
{
    /**
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    public $type = 'SecondaryCategories';

    /**
     * Method to get a list of categories that respects access controls and
     * adapted for multiple secondary category selection.
     *
     * @return  object[]
     * @since   __DEPLOY_VERSION__
     */
    protected function getOptions(): array
    {
        $jinput    = Factory::getApplication()->getInput();
        $extension = $this->element['extension']
            ? (string) $this->element['extension']
            : (string) $jinput->get('option', 'com_content');

        $published = $this->element['published']
            ? array_map('intval', explode(',', (string) $this->element['published']))
            : [0, 1, 2]; // published, unpublished, archived — exclude trashed (-2)

        $db   = $this->getDatabase();
        $user = $this->getCurrentUser();

        $query = $db->createQuery()
            ->select([
                $db->quoteName('a.id',        'value'),
                $db->quoteName('a.title',     'text'),
                $db->quoteName('a.level',     'level'),
                $db->quoteName('a.published', 'published'),
                $db->quoteName('a.lft',       'lft'),
                $db->quoteName('a.language',  'language'),
            ])
            ->from($db->quoteName('#__categories', 'a'))
            ->where($db->quoteName('a.extension') . ' = :extension')
            ->bind(':extension', $extension, ParameterType::STRING)
            ->whereIn($db->quoteName('a.published'), $published)
            ->order($db->quoteName('a.lft') . ' ASC');

        // Filter by user access levels
        if (!$user->authorise('core.admin')) {
            $groups = $user->getAuthorisedViewLevels();
            $query->whereIn($db->quoteName('a.access'), $groups);
        }

        try {
            $options = $db->setQuery($query)->loadObjectList();
        } catch (\RuntimeException $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return [];
        }

        // Get the ID of the "Uncategorised" category for this extension — used for special handling in the UI.
        $alias           = 'uncategorised';
        $uncatQuery      = $db->createQuery()
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = :extension')
            ->where($db->quoteName('alias')     . ' = :alias')
            ->where($db->quoteName('parent_id') . ' = 1')
            ->where($db->quoteName('level')     . ' = 1')
            ->bind(':extension', $extension, ParameterType::STRING)
            ->bind(':alias',     $alias,     ParameterType::STRING);

        $uncategorisedId = (int) $db->setQuery($uncatQuery)->loadResult();

        // Resolve existing secondary categories for this article, for ACL checks and UI labelling.
        $articleId = (int) $this->form->getValue('id', 0);

        $existingSecondaries = [];

        if ($articleId) {
            $context  = $extension . '.article';
            $mapQuery = $db->createQuery()
                ->select($db->quoteName('category_id'))
                ->from($db->quoteName('#__category_item_map'))
                ->where($db->quoteName('context') . ' = :context')
                ->where($db->quoteName('item_id') . ' = :itemId')
                ->bind(':context', $context,   ParameterType::STRING)
                ->bind(':itemId',  $articleId, ParameterType::INTEGER);

            $existingSecondaries = array_map(
                'intval',
                $db->setQuery($mapQuery)->loadColumn()
            );
        }

        // Build a new options array, filtering out categories the user shouldn't see.
        $filteredOptions = [];

        foreach ($options as $option) {
            // Never show root level
            if ($option->level == 0) {
                continue;
            }

            // Never show Uncategorised
            if ($uncategorisedId && (int) $option->value === $uncategorisedId) {
                continue;
            }

            // ACL check
            $alreadySaved = \in_array((int) $option->value, $existingSecondaries, true);
            $canCreate    = $user->authorise(
                'core.create',
                $extension . '.category.' . $option->value
            );

            if (!$alreadySaved && !$canCreate) {
                continue;
            }

            // Format label
            $indent        = str_repeat('- ', $option->level - 1);
            $label         = $option->published == 1
                ? $indent . $option->text
                : $indent . '[' . $option->text . ']';

            if ($option->language !== '*') {
                $label .= ' (' . $option->language . ')';
            }

            $filteredOptions[] = HTMLHelper::_(
                'select.option',
                $option->value,
                $label
            );
        }

        return $filteredOptions;
    }
}
