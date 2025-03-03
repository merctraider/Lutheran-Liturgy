<!DOCTYPE html>
<html>
<head>
	<title>Service Builder</title>
	<!-- Add Bootstrap stylesheet -->
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
</head>
<body>
	<div class="container mt-5">
		<h1>Service Builder</h1>

		<form method="GET" action="service.php">
			<div class="form-group">
				<label>Date:</label>
				<input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
			</div>

			<div class="form-group">
				<label>Order of Service:</label>
				<select name="order_of_service" class="form-control" required>
					<option value="matins">Matins</option>
					<option value="vespers">Vespers</option>
					<option value="chief_service">Chief Service</option>
				</select>
			</div>

			<div class="form-group">
				<label>Opening Hymn:</label>
				<select name="opening_hymn" class="form-control hymn" required>
					<?php
						$hymns = json_decode(file_get_contents('tlh.json'), true);

						foreach ($hymns as $key => $hymn) {
							echo '<option value="' . $key . '">' . $hymn['title'] . '</option>';
						}
					?>
				</select>
			</div>

			<div class="form-group">
				<label>Chief Hymn:</label>
				<select name="chief_hymn" class="form-control hymn" required>
					<option value="default">Use Lectionary Hymn</option>
					<?php
						foreach ($hymns as $key => $hymn) {
							echo '<option value="' . $key . '">' . $hymn['title'] . '</option>';
						}
					?>
				</select>
			</div>

			<div class="form-group" id="canticle-group">
				<label>Canticle:</label>
				<select name="canticle" class="form-control" required>
					<option value="magnificat">Magnificat</option>
					<option value="nunc_dimittis">Nunc Dimittis</option>
					<option value="te_deum">Te Deum</option>
					<option value="benedictus">Benedictus</option>
				</select>
			</div>

			<div class="form-check" id="replace-psalm-group">
				<input type="checkbox" class="form-check-input" id="replace_psalm" name="replace_psalm">
				<label class="form-check-label" for="replace_psalm">Replace Psalm with Introit</label>
			</div>

			<div class="form-group">
				<label>Prayers</label>
				<select name="override_prayers" class="form-control" required>
					<option value="default">Default (According to the Order)</option>
					<option value="litany">Litany</option>
					<option value="suffrages">Suffrages</option>
					<option value="morning_suffrages">Morning Suffrages</option>
					<option value="evening_suffrages">Evening Suffrages</option>
					<option value="bidding">Bidding Prayer</option>
				</select>
			</div>

			<input type="submit" value="Submit" class="btn btn-primary">
		</form>
	</div>

	<!-- Add Bootstrap JavaScript -->
	<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
	<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>

	<!--Add Select2 and dependencies-->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.7/js/select2.min.js"></script>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.7/css/select2.min.css">
	<script>
		$(document).ready(function() {
			$('select.hymn').select2();
			
			// Show/hide canticle and replace psalm options based on service type
			$('select[name="order_of_service"]').change(function() {
				if ($(this).val() === 'chief_service') {
					$('#canticle-group').hide();
					$('#replace-psalm-group').hide();
				} else {
					$('#canticle-group').show();
					$('#replace-psalm-group').show();
				}
			});
			
			// Trigger the change event on page load
			$('select[name="order_of_service"]').trigger('change');
		});
	</script>
</body>
</html>