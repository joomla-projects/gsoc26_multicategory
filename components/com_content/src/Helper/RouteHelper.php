<?php

/**
 * @package     Joomla.Site
 * @subpackage  com_content
 *
 * @copyright   (C) 2007 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Content\Site\Helper;

use Joomla\CMS\Categories\CategoryNode;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Content Component Route Helper.
 *
 * @since  1.5
 */
abstract class RouteHelper
{
    /**
     * Get the article route.
     *
     * @param   integer  $id        The route of the content item.
     * @param   integer  $catid     The category ID.
     * @param   string   $language  The language code.
     * @param   string   $layout    The layout value.
     * @param   boolean  $useActiveSecondaryCategory  Use active secondary category menu context.
     *
     * @return  string  The article route.
     *
     * @since   1.5
     */
    public static function getArticleRoute($id, $catid = 0, $language = null, $layout = null, $useActiveSecondaryCategory = true)
    {
        $secondaryRoute = $useActiveSecondaryCategory
            ? self::getActiveSecondaryCategoryRoute((int) $id, (int) $catid, $language)
            : null;

        if ($secondaryRoute) {
            $catid = $secondaryRoute['catid'];
        }

        // Create the link
        $link = 'index.php?option=com_content&view=article&id=' . $id;

        if ((int) $catid > 1) {
            $link .= '&catid=' . $catid;
        }

        if (!empty($language) && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        if ($layout) {
            $link .= '&layout=' . $layout;
        }

        if ($secondaryRoute) {
            $link .= '&Itemid=' . $secondaryRoute['Itemid'];
        } elseif (!$useActiveSecondaryCategory) {
            $itemId = self::getCategoryMenuItemId((int) $catid, $language);

            if ($itemId) {
                $link .= '&Itemid=' . $itemId;
            }
        }

        return $link;
    }

    /**
     * Get the category route.
     *
     * @param   integer  $catid     The category ID.
     * @param   string   $language  The language code.
     * @param   string   $layout    The layout value.
     *
     * @return  string  The article route.
     *
     * @since   1.5
     */
    public static function getCategoryRoute($catid, $language = null, $layout = null)
    {
        if ($catid instanceof CategoryNode) {
            $id = $catid->id;
        } else {
            $id = (int) $catid;
        }

        if ($id < 1) {
            return '';
        }

        $link = 'index.php?option=com_content&view=category&id=' . $id;

        if (!empty($language) && $language !== '*' && Multilanguage::isEnabled()) {
            $link .= '&lang=' . $language;
        }

        if ($layout) {
            $link .= '&layout=' . $layout;
        }

        return $link;
    }

    /**
     * Get the form route.
     *
     * @param   integer  $id  The form ID.
     *
     * @return  string  The article route.
     *
     * @since   1.5
     */
    public static function getFormRoute($id)
    {
        return 'index.php?option=com_content&task=article.edit&a_id=' . (int) $id;
    }

    /**
     * Get the active route when it points to a secondary category for an article.
     *
     * @param   integer      $id        The article ID.
     * @param   integer      $catid     The primary category ID.
     * @param   string|null  $language  The language code.
     *
     * @return  array|null
     *
     * @since   __DEPLOY_VERSION__
     */
    private static function getActiveSecondaryCategoryRoute(int $id, int $catid = 0, ?string $language = null): ?array
    {
        $app = Factory::getApplication();

        if (!$app->isClient('site')) {
            return null;
        }

        $active = $app->getMenu()->getActive();

        if (
            !$active
            || ($active->component ?? '') !== 'com_content'
            || ($active->query['view'] ?? '') !== 'category'
            || empty($active->query['id'])
        ) {
            return null;
        }

        $activeCategoryId = (int) $active->query['id'];

        if ($activeCategoryId <= 1) {
            return null;
        }

        if ($activeCategoryId === $catid) {
            return [
                'catid'  => $activeCategoryId,
                'Itemid' => (int) $active->id,
            ];
        }

        if (
            !empty($language)
            && $language !== '*'
            && Multilanguage::isEnabled()
            && !\in_array($active->language, ['*', $language], true)
        ) {
            return null;
        }
        $context = 'com_content.article';
        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $query   = $db->createQuery()
            ->select('1')
            ->from($db->quoteName('#__category_item_map'))
            ->where($db->quoteName('context') . ' = :context')
            ->where($db->quoteName('item_id') . ' = :itemId')
            ->where($db->quoteName('category_id') . ' = :categoryId')
            ->bind(':context', $context)
            ->bind(':itemId', $id, ParameterType::INTEGER)
            ->bind(':categoryId', $activeCategoryId, ParameterType::INTEGER);

        if (!$db->setQuery($query)->loadResult()) {
            return null;
        }

        return [
            'catid'  => $activeCategoryId,
            'Itemid' => (int) $active->id,
        ];
    }

    /**
     * Get a category menu item for a category route.
     *
     * @param   integer      $catid     The category ID.
     * @param   string|null  $language  The language code.
     *
     * @return  integer
     *
     * @since   __DEPLOY_VERSION__
     */
    private static function getCategoryMenuItemId(int $catid, ?string $language = null): int
    {
        if ($catid <= 1) {
            return 0;
        }

        $app = Factory::getApplication();

        if (!$app->isClient('site')) {
            return 0;
        }

        $component = ComponentHelper::getComponent('com_content');
        $items     = $app->getMenu()->getItems(
            ['component_id', 'language'],
            [(int) $component->id, [$language ?: '*', '*']]
        );

        foreach ((array) $items as $item) {
            if (
                ($item->query['view'] ?? '') === 'category'
                && (int) ($item->query['id'] ?? 0) === $catid
            ) {
                return (int) $item->id;
            }
        }

        return 0;
    }
}
