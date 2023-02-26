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

		<form method="POST" action="service.php">
			<div class="form-group">
				<label>Date:</label>
				<input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
			</div>

			<div class="form-group">
				<label>Order of Service:</label>
				<select name="order_of_service" class="form-control" required>
					<option value="matins">Matins</option>
					<option value="vespers">Vespers</option>
				</select>
			</div>

			<div class="form-group">
				<label>Opening Hymn:</label>
				<select name="opening_hymn" class="form-control" required>
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
				<select name="chief_hymn" class="form-control" required>
					<?php
						foreach ($hymns as $key => $hymn) {
							echo '<option value="' . $key . '">' . $hymn['title'] . '</option>';
						}
					?>
				</select>
			</div>

			<div class="form-group">
				<label>Canticle:</label>
				<select name="canticle" class="form-control" required>
					<option value="Magnificat">Magnificat</option>
					<option value="Nunc Dimittis">Nunc Dimittis</option>
					<option value="Te Deum">Te Deum</option>
					<option value="Benedictus">Benedictus</option>
				</select>
			</div>

			<div class="form-check">
				<input type="checkbox" class="form-check-input" id="replace_psalm" name="replace_psalm">
				<label class="form-check-label" for="replace_psalm">Replace Psalm with Introit</label>
			</div>

			<input type="submit" value="Submit" class="btn btn-primary">
		</form>
	</div>

	<!-- Add Bootstrap JavaScript -->
	<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
	<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
</body>
</html>
