/**
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
(function () {
  'use strict';

  customElements.whenDefined('joomla-field-fancy-select').then(() => {

    const primaryEl = document.getElementById('jform_catid');
    const secondaryEl = document.getElementById('jform_secondary_categories');

    if (!primaryEl || !secondaryEl) {
      return;
    }

    const primaryWrapper = primaryEl.closest('joomla-field-fancy-select');
    const secondaryWrapper = secondaryEl.closest('joomla-field-fancy-select');

    if (!primaryWrapper || !secondaryWrapper) {
      return;
    }

    const primaryChoices = primaryWrapper.choicesInstance;
    const secondaryChoices = secondaryWrapper.choicesInstance;

    if (!primaryChoices || !secondaryChoices) {
      return;
    }

    // Get config from joomla options.
    const config = Joomla.getOptions('com_content.secondary-categories', {});
    const uncategorisedId = config.uncategorisedId
      ? String(config.uncategorisedId)
      : null;

    // Cache all options from the secondary select, as an array of { value, label } objects, Before it change.
    const allOptions = Object.freeze(
      Array.from(secondaryEl.options)
        .filter(opt => opt.value !== '')
        .map(opt => ({ value: String(opt.value), label: opt.text }))
    );

    // Get initial selected secondaries from DOM, as a Set for easy add/remove operations.
    let selectedSecondaries = new Set(
      Array.from(secondaryEl.options)
        .filter(opt => opt.selected && opt.value !== '')
        .map(opt => String(opt.value))
    );

    let isRebuilding = false;

    // Helper to get current primary value as string (or empty string if none).
    const getPrimaryValue = () => String(primaryEl.value || '');

    // Helper to determine if secondary should be disabled based on primary value.
    const shouldDisable = (primaryVal) =>
      !primaryVal || primaryVal === uncategorisedId;

    const rebuildSecondary = (primaryVal) => {
      isRebuilding = true;

      try {
        // Clear all existing chips
        secondaryChoices.removeActiveItems();

        // Rebuild dropdown — all options except primary, all unselected
        const choices = allOptions
          .filter(opt => opt.value !== primaryVal)
          .map(opt => ({
            value: opt.value,
            label: opt.label,
            selected: false,
            disabled: false,
          }));

        secondaryChoices.setChoices(choices, 'value', 'label', true);

        selectedSecondaries.forEach(val => {
          if (val !== primaryVal) {
            secondaryChoices.setChoiceByValue(val);
          }
        });

      } finally {
        isRebuilding = false;
      }
    };

    // Disable secondary field, clearing all selections and chips.
    const disableSecondary = () => {
      isRebuilding = true;

      try {
        secondaryChoices.removeActiveItems();

        secondaryChoices.setChoices(
          allOptions.map(opt => ({
            value:    opt.value,
            label:    opt.label,
            selected: false,
            disabled: false,
          })),
          'value',
          'label',
          true
        );

        secondaryChoices.disable();
      } finally {
        isRebuilding = false;
      }
    };

    // Sync function to call after any change to primary or secondary.
    const sync = () => {
      const primaryVal = getPrimaryValue();

      // If primary is in selected secondaries, remove it.
      selectedSecondaries.delete(primaryVal);

      if (shouldDisable(primaryVal)) {
        selectedSecondaries.clear();
        disableSecondary();
        return;
      }

      secondaryChoices.enable();
      rebuildSecondary(primaryVal);
    };

    primaryEl.addEventListener('change', () => {
      sync();
    });

    secondaryEl.addEventListener('change', () => {
      if (isRebuilding) {
        return;
      }

      selectedSecondaries = new Set(
        Array.from(secondaryEl.options)
          .filter(opt => opt.selected && opt.value !== '')
          .map(opt => String(opt.value))
      );

      rebuildSecondary(getPrimaryValue());
    });

    // Initialize state on page load
    sync();

  });
}());
