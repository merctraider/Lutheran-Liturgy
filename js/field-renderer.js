/**
 * Dynamic Field Renderer
 * Renders form fields based on configuration type
 */

// Field type renderers
const FieldRenderers = {
    /**
     * Render a hymn select dropdown
     */
    hymn_select: function(fieldConfig, hymnsData) {
        if (!hymnsData) {
            return '<div class="alert alert-warning">Loading hymns...</div>';
        }

        let html = `<div class="form-group">
            <label>${fieldConfig.label}:</label>
            <select name="${fieldConfig.name}" class="form-control hymn" ${fieldConfig.required ? 'required' : ''}>`;

        // Add default option if required
        if (fieldConfig.required) {
            html += '<option value="default">Use Lectionary Hymn</option>';
        } else{
            html += '<option value="default">Skip</option>';
        }

        // Add all hymns
        for (let key in hymnsData) {
            html += `<option value="${key}">${hymnsData[key].title}</option>`;
        }

        html += '</select></div>';
        return html;
    },

    /**
     * Render a standard select dropdown
     */
    select: function(fieldConfig) {
        let html = `<div class="form-group">
            <label>${fieldConfig.label}:</label>
            <select name="${fieldConfig.name}" class="form-control" ${fieldConfig.required ? 'required' : ''}>`;

        // Add options
        fieldConfig.options.forEach(function(option) {
            let selected = option.value === fieldConfig.default ? 'selected' : '';
            html += `<option value="${option.value}" ${selected}>${option.label}</option>`;
        });

        html += '</select></div>';
        return html;
    },

    /**
     * Render a checkbox field
     */
    checkbox: function(fieldConfig) {
        let id = fieldConfig.name;
        return `<div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="${id}" name="${fieldConfig.name}" value="1">
            <label class="form-check-label" for="${id}">${fieldConfig.label}</label>
        </div>`;
    },

    /**
     * Render a text input field
     */
    text: function(fieldConfig) {
        return `<div class="form-group">
            <label>${fieldConfig.label}:</label>
            <input type="text" name="${fieldConfig.name}" class="form-control" 
                   ${fieldConfig.required ? 'required' : ''}
                   ${fieldConfig.placeholder ? `placeholder="${fieldConfig.placeholder}"` : ''}>
        </div>`;
    },

    /**
     * Render a textarea field
     */
    textarea: function(fieldConfig) {
        return `<div class="form-group">
            <label>${fieldConfig.label}:</label>
            <textarea name="${fieldConfig.name}" class="form-control" 
                      rows="${fieldConfig.rows || 3}"
                      ${fieldConfig.required ? 'required' : ''}
                      ${fieldConfig.placeholder ? `placeholder="${fieldConfig.placeholder}"` : ''}></textarea>
        </div>`;
    },

    /**
     * Render a number input field
     */
    number: function(fieldConfig) {
        return `<div class="form-group">
            <label>${fieldConfig.label}:</label>
            <input type="number" name="${fieldConfig.name}" class="form-control" 
                   ${fieldConfig.required ? 'required' : ''}
                   ${fieldConfig.min !== undefined ? `min="${fieldConfig.min}"` : ''}
                   ${fieldConfig.max !== undefined ? `max="${fieldConfig.max}"` : ''}
                   ${fieldConfig.step !== undefined ? `step="${fieldConfig.step}"` : ''}>
        </div>`;
    }
};

/**
 * Render all fields from configuration
 */
function renderFieldsFromConfig(config, hymnsData) {
    let html = '';

    config.fields.forEach(function(fieldConfig) {
        // Get the renderer for this field type
        const renderer = FieldRenderers[fieldConfig.type];

        if (renderer) {
            // Render the field
            html += renderer(fieldConfig, hymnsData);
        } else {
            console.error(`Unknown field type: ${fieldConfig.type}`);
            html += `<div class="alert alert-warning">Unknown field type: ${fieldConfig.type}</div>`;
        }
    });

    return html;
}

/**
 * Initialize select2 on hymn selects after rendering
 */
function initializeFieldWidgets() {
    // Initialize select2 on hymn selects
    $('select.hymn').select2();

    // Add any other widget initializations here
    // For example: datepickers, color pickers, etc.
}