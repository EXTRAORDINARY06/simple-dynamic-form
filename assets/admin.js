
document.addEventListener('DOMContentLoaded', function () {

    // Get all Show checkboxes.
    const showCheckboxes = document.querySelectorAll('.sdf-show-field');

    showCheckboxes.forEach(function (showCheckbox) {

        // Get the field key of the current Show checkbox.
        const fieldKey = showCheckbox.dataset.field;

        // Find the matching Required checkbox.
        const requiredCheckbox = document.querySelector(
            '.sdf-required-field[data-field="' + fieldKey + '"]'
        );

        // Stop if the matching checkbox does not exist.
        if (!requiredCheckbox) {
            return;
        }

        // Update Required checkbox according to Show checkbox.
        function updateRequiredState() {

            if (showCheckbox.checked) {

                // Show is checked: enable Required.
                requiredCheckbox.disabled = false;

            } else {

                // Show is unchecked: disable and uncheck Required.
                requiredCheckbox.checked = false;
                requiredCheckbox.disabled = true;

            }
        }

        // Run whenever the administrator changes Show.
        showCheckbox.addEventListener('change', updateRequiredState);

        // Set the correct state immediately when the page loads.
        updateRequiredState();

    });

});
