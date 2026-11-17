import { useState, useEffect } from '@wordpress/element';
import { CheckboxControl, Button } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

function App() {

    // Store all available form fields received from WordPress.
    const [fields, setFields] = useState({});

    // Store the current Show and Required settings.
    const [settings, setSettings] = useState({});

    // Track whether settings are being fetched.
    const [loading, setLoading] = useState(true);

    // Store any error that occurs while fetching settings.
    const [error, setError] = useState('');

    // Track whether settings are currently being saved.
    const [saving, setSaving] = useState(false);

    // Store the success message after saving settings.
    const [success, setSuccess] = useState('');

    // Store errors that occur while saving settings.
    const [saveError, setSaveError] = useState('');

    // Fetch the existing form fields and settings from WordPress.
    useEffect(() => {

        apiFetch({
            path: '/sdf/v1/settings',
            method: 'GET',
        })

        .then((response) => {

            // Save the available field definitions.
            setFields(response.fields);

            // Save the existing field configurations.
            setSettings(response.settings);

        })

        .catch((error) => {

            console.error('Error fetching settings:', error);

            setError('Unable to load form settings.');

        })

        .finally(() => {

            // Stop displaying the loading message.
            setLoading(false);

        });

    }, []);

    // Handle changes to the Show checkbox.
    const handleShowChange = (fieldKey, checked) => {

        // Clear previous messages when settings change.
        setSuccess('');
        setSaveError('');

        setSettings((previousSettings) => ({

            ...previousSettings,

            [fieldKey]: {

                ...previousSettings[fieldKey],

                // Update the Show value.
                show: checked ? 1 : 0,

                // If the field is hidden, make it not required.
                required: checked
                    ? previousSettings[fieldKey]?.required
                    : 0,

            },

        }));

    };

    // Handle changes to the Required checkbox.
    const handleRequiredChange = (fieldKey, checked) => {

        // Clear previous messages when settings change.
        setSuccess('');
        setSaveError('');

        setSettings((previousSettings) => ({

            ...previousSettings,

            [fieldKey]: {

                ...previousSettings[fieldKey],

                // Update the Required value.
                required: checked ? 1 : 0,

            },

        }));

    };

    // Handle saving settings to the WordPress database.
    const handleSave = async () => {

        // Start the saving process.
        setSaving(true);

        // Clear previous messages.
        setSuccess('');
        setSaveError('');

        try {

            // Send updated settings to the WordPress REST API.
            const response = await apiFetch({

                path: '/sdf/v1/settings',

                method: 'POST',

                data: {
                    settings: settings,
                },

            });

            // Update React state with the saved settings.
            setSettings(response.settings);

            // Display the success message.
            setSuccess(
                response.message || 'Settings saved successfully!'
            );

        }

        catch (error) {

            // Display the error in the browser console.
            console.error('Error saving settings:', error);

            // Display the error message in the interface.
            setSaveError(
                error.message || 'Unable to save form settings.'
            );

        }

        finally {

            // Stop the saving process.
            setSaving(false);

        }

    };

    // Display loading message while fetching settings.
    if (loading) {
        return <p>Loading form settings...</p>;
    }

    // Display an error if the initial API request fails.
    if (error) {
        return <p>{error}</p>;
    }

    return (

        <div>

            <h2>Simple Dynamic Form Settings</h2>

            <p>
                Configure which fields should appear on your form
                and which fields should be mandatory.
            </p>

            {Object.keys(fields).map((fieldKey) => {

                // Get the settings for the current field.
                const fieldSettings = settings[fieldKey] || {
                    show: 1,
                    required: 0,
                };

                // Determine whether the field is visible.
                const isVisible = Boolean(fieldSettings.show);

                return (

                    <div
                        key={fieldKey}
                        style={{
                            border: '1px solid #ddd',
                            padding: '15px',
                            marginBottom: '15px',
                            backgroundColor: '#fff',
                        }}
                    >

                        <h3>{fields[fieldKey].label}</h3>

                        <CheckboxControl
                            label="Show this field"
                            checked={isVisible}
                            onChange={(checked) =>
                                handleShowChange(fieldKey, checked)
                            }
                        />

                        <CheckboxControl
                            label="Make this field required"
                            checked={Boolean(fieldSettings.required)}
                            disabled={!isVisible}
                            onChange={(checked) =>
                                handleRequiredChange(fieldKey, checked)
                            }
                        />

                    </div>

                );

            })}

            {/* Display success message after saving. */}
            {success && (
                <p style={{ color: 'green' }} role="status">
                    {success}
                </p>
            )}

            {/* Display error message if saving fails. */}
            {saveError && (
                <p style={{ color: 'red' }} role="alert">
                    {saveError}
                </p>
            )}

            {/* Save Settings button. */}
            <Button
                variant="primary"
                onClick={handleSave}
                disabled={saving}
                isBusy={saving}
            >
                {saving ? 'Saving...' : 'Save Settings'}
            </Button>

            <p>
                <strong>Note:</strong> Changes are stored in React state
                until you click Save Settings.
            </p>

        </div>

    );

}

export default App;
