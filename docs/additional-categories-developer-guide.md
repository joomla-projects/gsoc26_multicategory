# Additional Categories for Developers

## Introduction

Additional Categories allow an item to belong to more than one category. The existing `catid` column remains the primary category. Additional category assignments are stored separately.

This feature supports the following contexts:

| Component | Context |
| --- | --- |
| Articles | `com_content.article` |
| Contacts | `com_contact.contact` |
| Banners | `com_banners.banner` |
| News Feeds | `com_newsfeeds.newsfeed` |

## Data Storage

Additional category assignments are stored in `#__category_item_map`.

| Column | Description |
| --- | --- |
| `context` | The item context, for example `com_content.article`. |
| `item_id` | The ID of the item. |
| `category_id` | The ID of the Additional Category. |
| `ordering` | The order of the Additional Categories for the item. |

The unique key on `context`, `item_id`, and `category_id` prevents duplicate assignments.

Do not add the primary category to `#__category_item_map`. The primary category remains in the item `catid` column.

> One article has `catid = 4` as its primary category. The same article has category IDs `7` and `9` in `#__category_item_map` as Additional Categories.

![alt text](image.png)

## Forms

The user interface label is **Additional Categories**. The internal form name remains `secondary_categories` for compatibility.

Each supported form uses the `categorymultiple` field and sets `primarycategoryfield="catid"`. The field prevents the primary category from being selected again.

When saving, the model must:

1. Convert values to integer category IDs.
2. Remove empty and duplicate values.
3. Remove the primary category ID.
4. Keep only categories the current user can manage.
5. Save the remaining values in `#__category_item_map`.

## Access and Site Lists

Before an item is included through an Additional Category, Joomla checks that the category is published, matches the current language, and can be viewed by the current user.

The primary category and item access checks still apply. An Additional Category must not give a visitor access to an item they cannot normally view.

The `include_secondary_categories` parameter is used by Content and Contact category views:

| Value | Result |
| --- | --- |
| `1` | Include main-category and Additional Category assignments. |
| `0` | Include only main-category assignments. |

![alt text](image-2.png)

The Articles module loads accessible Additional Categories before it creates category display data.

## Frontend Support

Article support includes Category Blog, Category List, Featured Articles, Archived Articles, Smart Search results, article information, and the page navigation plugin.

Contact support includes category views and the contact information page.

The article and contact layouts render the main category first. They append accessible Additional Categories only when `include_secondary_categories` is enabled.

The page navigation plugin receives the same category context. This keeps previous and next links inside the same category result set.

Smart Search indexing and result display include Additional Category assignments for articles. Do not expose an Additional Category title to a user who cannot view that category.

## Administrator Lists and Counts

The administrator lists for Articles, Contacts, Banners, and News Feeds support the `category_match` state.

| Value | Meaning |
| --- | --- |
| Empty | Default category filter behaviour. |
| `1` | Match the selected category as the main category. |
| `2` | Match the selected category as an Additional Category. |

Category item counters add values such as `count_secondary_published`. The Category Manager renders the result as `main (+additional)`, for example `10 (+5)`.

![alt text](image-4.png)


When joining `#__category_item_map`, avoid duplicate rows. Use an `EXISTS` query or group results when needed.

## Custom Fields

Articles and Contacts collect the primary category and Additional Category IDs in `fieldscatid` before custom fields are loaded.

```php
$fieldscatid = array_values(array_unique(array_merge([$catid], $secondaryCategories)));
```

This lets Joomla load fields assigned to the primary category and fields assigned to accessible Additional Categories.

## Web Services API

The JSON:API property remains `secondary_categories` for compatibility. It is an array of category IDs.

The Articles, Contacts, Banners, and News Feeds endpoints support this property.

```json
{
  "secondary_categories": [12, 18]
}
```

| Request | Result |
| --- | --- |
| `POST` with `secondary_categories` | Creates the assignments. |
| `PATCH` with `secondary_categories` | Replaces the assignments. |
| `PATCH` without `secondary_categories` | Keeps the existing assignments. |
| `PATCH` with `secondary_categories: []` | Removes all Additional Category assignments. |

The Articles API also supports `filter[category_match]`.

## Guided Tours

The Article, Contact, Banner, and News Feed guided tours include an informational step for `#jform_secondary_categories` after the main Category step.

Guided tour changes require:

1. Language keys in the relevant `guidedtours.*_steps.ini` file.
2. Installation SQL for MySQL and PostgreSQL.
3. Update SQL for MySQL and PostgreSQL.
4. A PostgreSQL sequence value higher than the last inserted guided-tour step ID.

## Routing Status (out of scope)

Routing support for Additional Categories is not part of this documentation because it is not merged. Do not document it as a released feature.

The unmerged experiment is commit [`e879fc827e`](https://github.com/joomla-projects/gsoc26_multicategory/commit/e879fc827e). It should be reviewed separately for canonical URL, menu context, breadcrumb, duplicate menu item, access-control, and SEF side effects before it is proposed upstream.


## Tests

Add or update tests for every changed behaviour:

- Administrator edit tests for all four supported components.
- Administrator list tests for Category Match values.
- API tests for create, replace, omitted `PATCH`, clear, and returned assignments.
- Article and Contact API tests for custom fields assigned to Additional Categories.
- Site tests for the Include Additional Categories option, access filtering, category display, and Articles module display.

Run the focused Cypress tests first. Run the related system-test group before opening a pull request.
