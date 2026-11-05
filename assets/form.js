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

            // Display success or server-side error messages.
            function showFormMessage(message, isSuccess) {

                let messageBox = form.querySelector(
                    '.sdf-response-message'
                );

                if (!messageBox) {

                    messageBox = document.createElement('p');

                    messageBox.className =
                        'sdf-response-message';

                    messageBox.setAttribute('role', 'status');

                    form.parentNode.insertBefore(
                        messageBox,
                        form
                    );
                }

                messageBox.textContent = message;

                messageBox.classList.toggle(
                    'sdf-success-message',
                    isSuccess
                );

                messageBox.classList.toggle(
                    'sdf-error-message',
                    !isSuccess
                );
            }

            // Validate fields and submit the form using AJAX.
            form.addEventListener('submit', function (event) {

                // Stop the browser's normal form submission.
                event.preventDefault();

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

                // Stop here if validation fails.
                if (!isFormValid) {

                    firstInvalidField.focus();
                    return;
                }

                // Find the submit button.
                const submitButton = form.querySelector(
                    '[type="submit"]'
                );

                // Prevent multiple submissions.
                if (submitButton) {
                    submitButton.disabled = true;
                }

                // Display a temporary submission message.
                showFormMessage(
                    'Submitting your form...',
                    true
                );

                // Send form data to WordPress using jQuery AJAX.
                jQuery.ajax({

                    // WordPress AJAX endpoint.
                    url: sdf_ajax.ajax_url,

                    // Use POST to send form data.
                    method: 'POST',

                    // Collect form data and add WordPress AJAX parameters.
                    data: jQuery(form).serialize() +
                        '&action=sdf_submit&nonce=' +
                        encodeURIComponent(sdf_ajax.nonce),

                    // Expect a JSON response from PHP.
                    dataType: 'json',

                    // Runs when the AJAX request succeeds at HTTP level.
                    success: function (response) {

                        if (response.success) {

                            showFormMessage(
                                response.data.message,
                                true
                            );

                            // Clear the form after successful submission.
                            form.reset();

                            // Remove previous field validation errors.
                            fields.forEach(function (field) {
                                removeError(field);
                            });

                        } else {

                            // Display the error returned by PHP.
                            showFormMessage(
                                response.data.message,
                                false
                            );
                        }
                    },

                    // Runs when the HTTP request fails.
                    error: function (xhr) {

                        let message =
                            'Something went wrong. Please try again.';

                        if (
                            xhr.responseJSON &&
                            xhr.responseJSON.data &&
                            xhr.responseJSON.data.message
                        ) {
                            message = xhr.responseJSON.data.message;
                        }

                        showFormMessage(message, false);
                    },

                    // Runs after success or error.
                    complete: function () {

                        // Allow the user to submit again.
                        if (submitButton) {
                            submitButton.disabled = false;
                        }
                    }

                });

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
