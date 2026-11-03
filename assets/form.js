(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        // Find all Simple Dynamic Forms on the page.
        const forms = document.querySelectorAll(
            '.sdf-form-container form'
        );

        forms.forEach(function (form) {

            // Get the field name from its input name attribute.
            function getFieldKey(field) {
                const match = field.name.match(
                    /sdf_submission\[([^\]]+)\]/
                );

                return match ? match[1] : null;
            }

            // Remove an existing JavaScript validation error.
            function removeError(field) {
                const group = field.closest('.sdf-form-group');

                if (!group) {
                    return;
                }

                const error = group.querySelector(
                    '.sdf-js-error-message'
                );

                if (error) {
                    error.remove();
                }

                field.removeAttribute('aria-invalid');
            }

            // Display an error below a field.
            function showError(field, message) {
                const group = field.closest('.sdf-form-group');

                if (!group) {
                    return;
                }

                removeError(field);

                const error = document.createElement('p');

                error.className =
                    'sdf-error-message sdf-js-error-message';

                error.setAttribute('role', 'alert');
                error.textContent = message;

                group.appendChild(error);

                field.setAttribute('aria-invalid', 'true');
            }

            // Validate individual fields.
            function validateField(field) {

                removeError(field);

                const key = getFieldKey(field);

                if (!key) {
                    return true;
                }

                const value = field.value.trim();
                const isRequired = field.required;

                // Required field validation.
                if (isRequired && value === '') {

                    showError(
                        field,
                        'This field is required.'
                    );

                    return false;
                }

                // Optional empty fields do not need format validation.
                if (value === '') {
                    return true;
                }

                // Field-specific validation.
                switch (key) {

                    case 'full_name':

                        if (!/^[a-zA-Z ]+$/.test(value)) {

                            showError(
                                field,
                                'Name can contain only letters and spaces.'
                            );

                            return false;
                        }

                        if (value.length > 100) {

                            showError(
                                field,
                                'Name cannot exceed 100 characters.'
                            );

                            return false;
                        }

                        break;

                    case 'email':

                        const emailPattern =
                            /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                        if (!emailPattern.test(value)) {

                            showError(
                                field,
                                'Please enter a valid email address.'
                            );

                            return false;
                        }

                        break;

                    case 'phone':

                        if (!/^[0-9]{10}$/.test(value)) {

                            showError(
                                field,
                                'Phone number must contain exactly 10 digits.'
                            );

                            return false;
                        }

                        break;

                    case 'age':

                        if (!/^[0-9]+$/.test(value)) {

                            showError(
                                field,
                                'Age must be a whole number.'
                            );

                            return false;
                        }

                        const age = Number(value);

                        if (age < 1 || age > 120) {

                            showError(
                                field,
                                'Age must be between 1 and 120.'
                            );

                            return false;
                        }

                        break;

                    case 'website':

                        try {
                            const url = new URL(value);

                            if (
                                url.protocol !== 'http:' &&
                                url.protocol !== 'https:'
                            ) {
                                throw new Error('Invalid protocol');
                            }

                        } catch (error) {

                            showError(
                                field,
                                'Please enter a valid website URL.'
                            );

                            return false;
                        }

                        break;

                    case 'city':

                        if (value.length > 100) {

                            showError(
                                field,
                                'City cannot exceed 100 characters.'
                            );

                            return false;
                        }

                        break;

                    case 'message':

                        if (value.length > 1000) {

                            showError(
                                field,
                                'Message cannot exceed 1000 characters.'
                            );

                            return false;
                        }

                        break;
                }

                return true;
            }

            // Validate all fields when the user submits the form.
            form.addEventListener('submit', function (event) {

                let isFormValid = true;
                let firstInvalidField = null;

                const fields = form.querySelectorAll(
                    '[name^="sdf_submission["]'
                );

                fields.forEach(function (field) {

                    // Ignore hidden or disabled fields.
                    if (
                        field.disabled ||
                        field.closest('[hidden]') ||
                        field.offsetParent === null
                    ) {
                        return;
                    }

                    const isValid = validateField(field);

                    if (!isValid) {

                        isFormValid = false;

                        if (!firstInvalidField) {
                            firstInvalidField = field;
                        }
                    }
                });

                // Stop submission if any field is invalid.
                if (!isFormValid) {

                    event.preventDefault();

                    firstInvalidField.focus();
                }

            });

            // Remove validation errors while the user edits a field.
            form.addEventListener('input', function (event) {

                const field = event.target;

                if (
                    field.matches(
                        '[name^="sdf_submission["]'
                    )
                ) {
                    removeError(field);
                }

            });

        });

    });

})();
