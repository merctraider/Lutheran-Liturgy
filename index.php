<!DOCTYPE html>
<html>

<head>
	<title>Service Builder</title>
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.7/css/select2.min.css">
	<link rel="stylesheet" href="missal-common.css">
	<script src="js/field-renderer.js"></script>
</head>

<body>
	<div class="container mt-5">
		<h1>Service Builder</h1>

		<form id="service-form" method="GET" action="service.php">

			<!-- Section 1: Date Selection -->
			<div class="form-section">
				<h4>1. Select Date</h4>
				<div class="form-group">
					<label>Date:</label>
					<input type="date" name="date" id="date-input" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
				</div>
			</div>

			<!-- Section 2: Day Type Selection (hidden until date selected) -->
			<div id="day-type-section" class="form-section hidden-section">
				<h4>2. Select Day Type</h4>
				<div id="day-options-container">
					<div class="text-center">
						<span class="loading-spinner"></span>
					</div>
				</div>
			</div>

			<!-- Section 3: Order Selection (hidden until day type selected) -->
			<div id="order-section" class="form-section hidden-section">
				<h4>3. Select Order of Service</h4>
				<div class="form-group">
					<label>Order of Service:</label>
					<select name="order_of_service" id="order-select" class="form-control" required>
						<option value="">-- Select Order --</option>
						<option value="matins">Matins</option>
						<option value="vespers">Vespers</option>
						<option value="chief_service">Chief Service</option>
					</select>
				</div>
			</div>

			<!-- Section 4: Dynamic Settings Fields (hidden until order selected) -->
			<div id="settings-section" class="form-section hidden-section">
				<h4>4. Service Settings</h4>
				<div id="settings-fields-container">
					<div class="text-center">
						<span class="loading-spinner"></span>
					</div>
				</div>
			</div>

			<!-- Submit Button (only shown when all fields are ready) -->
			<div id="submit-section" class="hidden-section">
				<input type="submit" value="Generate Service" class="btn btn-success btn-lg btn-block">
			</div>

		</form>
	</div>

	<!-- Scripts -->
	<script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
	<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.7/js/select2.min.js"></script>

	<script>
		$(document).ready(function() {
			let selectedDate = $('#date-input').val();
			let selectedDayType = null;
			let selectedOrdo = null;
			let hymnsData = null;

			// Load hymns data
			$.getJSON('tlh.json', function(data) {
				hymnsData = data;
			});

			// Helper function to show section with animation
			function showSection(sectionId) {
				$(`#${sectionId}`).removeClass('hidden-section').addClass('fade-in');
			}

			function hideSection(sectionId) {
				$(`#${sectionId}`).addClass('hidden-section').removeClass('fade-in');
			}

			// Initialize: Load day options for today's date
			loadDayOptions(selectedDate);

			// Date change handler
			$('#date-input').change(function() {
				selectedDate = $(this).val();

				if (!selectedDate) {
					hideSection('day-type-section');
					hideSection('order-section');
					hideSection('settings-section');
					hideSection('submit-section');
					return;
				}

				// Reset downstream selections
				selectedDayType = null;
				selectedOrdo = null;
				hideSection('order-section');
				hideSection('settings-section');
				hideSection('submit-section');

				// Load new day options
				loadDayOptions(selectedDate);
			});

			$('#service-form').submit(function(e) {
				e.preventDefault(); // Prevent normal form submission

				// Start with basic required fields
				const formData = {
					date: selectedDate,
					day_type: selectedDayType,
					order_of_service: selectedOrdo
				};

				// Dynamically collect all form inputs
				// This handles any field without hardcoding field names
				$(this).find('input, select, textarea').each(function() {
					const $field = $(this);
					const name = $field.attr('name');

					// Skip if no name attribute
					if (!name) return;

					// Skip already-collected fields
					if (name === 'date' || name === 'day_type' || name === 'order_of_service') {
						return;
					}

					// Handle different input types
					if ($field.attr('type') === 'checkbox') {
						// Only include if checked
						if ($field.is(':checked')) {
							formData[name] = true;
						}
					} else if ($field.attr('type') === 'radio') {
						// Only include if checked
						if ($field.is(':checked')) {
							formData[name] = $field.val();
						}
					} else {
						// Text, select, textarea, etc.
						const value = $field.val();
						if (value !== null && value !== '') {
							formData[name] = value;
						}
					}
				});

				// Normalize field names for backward compatibility
				// Handle legacy 'override_prayers' → 'prayers'
				if (formData.override_prayers && !formData.prayers) {
					formData.prayers = formData.override_prayers;
					delete formData.override_prayers;
				}

				// Debug: log what we're sending
				console.log('Form data:', formData);

				// ENCODING STEP: Convert to JSON then Base64 (URL-safe)
				const jsonStr = JSON.stringify(formData);
				const base64 = btoa(jsonStr)
					.replace(/\+/g, '-') // Make URL-safe
					.replace(/\//g, '_') // Make URL-safe
					.replace(/=/g, ''); // Remove padding

				// Redirect to service with encoded parameter
				window.location.href = 'service.php?s=' + base64;
			});

			// Load day options from API
			function loadDayOptions(date) {
				$('#day-options-container').html('<div class="text-center"><span class="loading-spinner"></span></div>');
				showSection('day-type-section');

				$.get('api.php', {
					action: 'check_date',
					date: date
				}, function(response) {
					if (response.success) {
						renderDayOptions(response.data);
					} else {
						$('#day-options-container').html(`<div class="alert alert-danger">${response.error}</div>`);
					}
				}).fail(function() {
					$('#day-options-container').html('<div class="alert alert-danger">Failed to load day options.</div>');
				});
			}

			// Render day type options
			function renderDayOptions(options) {
				let html = '';

				options.forEach(function(option, index) {
					html += `
						<div class="day-option" data-day-type="${option.value}">
							<label class="mb-0">
								<input type="radio" name="day_type" value="${option.value}" ${index === 0 ? 'checked' : ''}>
								<strong>${option.display}</strong>
							</label>
						</div>
					`;
				});

				$('#day-options-container').html(html);

				// Select first option by default
				if (options.length > 0) {
					selectedDayType = options[0].value;
					$(`.day-option[data-day-type="${selectedDayType}"]`).addClass('selected');
					showSection('order-section');
				}

				// Handle day type selection
				$('.day-option').click(function() {
					$('.day-option').removeClass('selected');
					$(this).addClass('selected');
					$(this).find('input[type="radio"]').prop('checked', true);

					selectedDayType = $(this).data('day-type');

					// Reset downstream selections
					selectedOrdo = null;
					$('#order-select').val('');
					hideSection('settings-section');
					hideSection('submit-section');

					// Show order section
					showSection('order-section');
				});
			}

			// Order selection handler
			$('#order-select').change(function() {
				selectedOrdo = $(this).val();

				if (!selectedOrdo) {
					hideSection('settings-section');
					hideSection('submit-section');
					return;
				}

				// Load settings fields
				loadSettingsFields(selectedOrdo);
			});

			// Load settings fields from API
			function loadSettingsFields(ordo) {
				$('#settings-fields-container').html('<div class="text-center"><span class="loading-spinner"></span></div>');
				showSection('settings-section');

				$.get('api.php', {
					action: 'get_ordo_fields',
					date: selectedDate,
					ordo: ordo
				}, function(response) {
					if (response.success) {
						renderSettingsFields(response.data);
						showSection('submit-section');
					} else {
						$('#settings-fields-container').html(`<div class="alert alert-danger">${response.error}</div>`);
						hideSection('submit-section');
					}
				}).fail(function() {
					$('#settings-fields-container').html('<div class="alert alert-danger">Failed to load settings.</div>');
					hideSection('submit-section');
				});
			}

			// Render dynamic settings fields
			function renderSettingsFields(config) {
				// Use the new renderFieldsFromConfig function from field-renderer.js
				const html = renderFieldsFromConfig(config, hymnsData);

				$('#settings-fields-container').html(html);

				// Initialize any widgets (select2, etc.)
				initializeFieldWidgets();
			}

			function renderHymnField(name, label, required) {
				if (!hymnsData) return '<div class="alert alert-warning">Loading hymns...</div>';

				let html = `<div class="form-group">
					<label>${label}:</label>
					<select name="${name}" class="form-control hymn" ${required ? 'required' : ''}>`;

				if (required) {
					html += '<option value="default">Use Lectionary Hymn</option>';
				}

				for (let key in hymnsData) {
					html += `<option value="${key}">${hymnsData[key].title}</option>`;
				}

				html += '</select></div>';
				return html;
			}

			function renderCanticleField(options, defaultValue) {
				let html = `<div class="form-group">
					<label>Canticle:</label>
					<select name="canticle" class="form-control" required>`;

				options.forEach(function(option) {
					let selected = option.value === defaultValue ? 'selected' : '';
					html += `<option value="${option.value}" ${selected}>${option.label}</option>`;
				});

				html += '</select></div>';
				return html;
			}

			function renderReplacePsalmField() {
				return `<div class="form-check mb-3">
					<input type="checkbox" class="form-check-input" id="replace_psalm" name="replace_psalm" value="1">
					<label class="form-check-label" for="replace_psalm">Replace Psalm with Introit</label>
				</div>`;
			}

			function renderPrayersField(options) {
				let html = `<div class="form-group">
					<label>Prayers:</label>
					<select name="override_prayers" class="form-control" required>`;

				options.forEach(function(option) {
					html += `<option value="${option.value}">${option.label}</option>`;
				});

				html += '</select></div>';
				return html;
			}
		});
	</script>
</body>

</html>