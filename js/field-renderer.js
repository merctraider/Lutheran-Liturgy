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
        } else {
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

        let countryCode = localStorage.getItem('userCountry');
        if(!countryCode){
            autoDetectCountry(); 
        }
        

        // Add options
        fieldConfig.options.forEach(function(option) {
            let selected = option.value === fieldConfig.default ? 'selected' : '';
            if(fieldConfig.name == 'country' && countryCode){
                selected = countryCode; 
            }

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
    },

    /**
     * Render a select2 multi-select dropdown for collects
     */
    select2: function(fieldConfig, collectsData) {
        if (!collectsData) {
            return '<div class="alert alert-warning">Loading collects...</div>';
        }

        // Helper function to get first N words from text
        const getFirstWords = (text, n) => {
            const words = text.split(/\s+/);
            return words.slice(0, n).join(' ') + (words.length > n ? '...' : '');
        };

        // Group collects by theme
        const groupedCollects = {};
        collectsData.collects.forEach(collect => {
            if (!groupedCollects[collect.theme]) {
                groupedCollects[collect.theme] = [];
            }
            groupedCollects[collect.theme].push(collect);
        });

        let html = `<div class="form-group">
            <label>${fieldConfig.label}:</label>
            <select name="${fieldConfig.name}" class="form-control collect-select2"
                    ${fieldConfig.multiple ? 'multiple' : ''}
                    ${fieldConfig.required ? 'required' : ''}>`;

        // Add optgroups for each theme
        for (let theme in groupedCollects) {
            html += `<optgroup label="${theme}">`;
            groupedCollects[theme].forEach(collect => {
                const label = `${collect.id}. ${getFirstWords(collect.text, 5)}`;
                html += `<option value="${collect.id}">${label}</option>`;
            });
            html += '</optgroup>';
        }

        html += '</select></div>';
        return html;
    }
};

/**
 * Render all fields from configuration, separating hymns into a subsection
 */
function renderFieldsFromConfig(config, hymnsData, collectsData) {
    let html = '';
    let hymnFields = [];
    let otherFields = [];

    // Separate hymn fields from other fields
    config.fields.forEach(function(fieldConfig) {
        if (fieldConfig.type === 'hymn_select') {
            hymnFields.push(fieldConfig);
        } else {
            otherFields.push(fieldConfig);
        }
    });

    // Render non-hymn fields first
    if (otherFields.length > 0) {
        otherFields.forEach(function(fieldConfig) {
            const renderer = FieldRenderers[fieldConfig.type];

            if (renderer) {
                // Pass appropriate data based on field type
                if (fieldConfig.type === 'select2') {
                    html += renderer(fieldConfig, collectsData);
                } else {
                    html += renderer(fieldConfig, hymnsData);
                }
            } else {
                console.error(`Unknown field type: ${fieldConfig.type}`);
                html += `<div class="alert alert-warning">Unknown field type: ${fieldConfig.type}</div>`;
            }
        });
    }

    // Render hymn fields in a separate subsection
    if (hymnFields.length > 0) {
        html += '<div class="hymn-subsection mt-4">';
        html += '<h4>Hymns</h4>';

        hymnFields.forEach(function(fieldConfig) {
            const renderer = FieldRenderers[fieldConfig.type];

            if (renderer) {
                html += renderer(fieldConfig, hymnsData);
            } else {
                console.error(`Unknown field type: ${fieldConfig.type}`);
                html += `<div class="alert alert-warning">Unknown field type: ${fieldConfig.type}</div>`;
            }
        });

        html += '</div>';
    }

    return html;
}

/**
 * Initialize select2 on hymn selects after rendering
 */
function initializeFieldWidgets() {
    // Initialize select2 on hymn selects
    $('select.hymn').select2();

    // Initialize select2 on collect multi-selects
    $('select.collect-select2').select2({
        placeholder: 'Select collects...',
        allowClear: true
    });

    autoDetectCountry();

    // Add any other widget initializations here
    // For example: datepickers, color pickers, etc.
}

/**
 * Auto-detect user's country and set it as default for country select
 */
async function autoDetectCountry() {


    try {
        // Check cache first
        let countryCode = localStorage.getItem('userCountry');

        if (!countryCode) {
            // Fetch from Cloudflare
            const response = await fetch('https://www.cloudflare.com/cdn-cgi/trace');
            const text = await response.text();

            const countryLine = text.split('\n').find(line => line.startsWith('loc='));
            countryCode = countryLine ? countryLine.split('=')[1] : null;



            // Map country codes to select values
            const countryMap = {
                'PH': 'philippines',
                'SG': 'singapore',
                'US': 'usa',
                // Commonwealth Realms (all 15 countries)
                'GB': 'commonwealth',  // United Kingdom
                'AU': 'commonwealth',  // Australia
                'CA': 'commonwealth',  // Canada
                'NZ': 'commonwealth',  // New Zealand
                'JM': 'commonwealth',  // Jamaica
                'BS': 'commonwealth',  // The Bahamas
                'GD': 'commonwealth',  // Grenada
                'PG': 'commonwealth',  // Papua New Guinea
                'SB': 'commonwealth',  // Solomon Islands
                'TV': 'commonwealth',  // Tuvalu
                'LC': 'commonwealth',  // Saint Lucia
                'VC': 'commonwealth',  // Saint Vincent and the Grenadines
                'BZ': 'commonwealth',  // Belize
                'AG': 'commonwealth',  // Antigua and Barbuda
                'KN': 'commonwealth'   // Saint Kitts and Nevis
            };
            let mapped = 'usa'; 
            if (countryCode) {
                mapped = countryMap[countryCode];
            }


            // Cache it
            if (mapped) {
                localStorage.setItem('userCountry',mapped);
            }
        }


    } catch (error) {
        console.log('Could not auto-detect country, using default');
    }
}