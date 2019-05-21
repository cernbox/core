<div class="section">
	<h2 class="app-name"><?php p($l->t('Version'));?></h2>
	<p>
		<a href="<?php print_unescaped($theme->getBaseUrl()); ?>" target="_blank">
			<?php p($theme->getTitle()); ?>
		</a> <?php p(OC_Util::getHumanVersion()) ?>
	</p>
	<p><?php include('settings.development.notice.php'); ?></p>
	<br>
	<br>

	<h2 class="app-name"><?php p($l->t('Canary Testing'));?></h2>
	<p>
	<p>Enable bleding edge UI and applications?
	<input type="radio" name="canary-adopter" value="yes">Yes
	<input type="radio" name="canary-adopter" value="no">No<br>
	</p>
	<script type="text/javascript">
	$().ready(function() {
		$('input:radio[name=canary-adopter]').on('change', function(){
			var val = document.querySelector('input[name="canary-adopter"]:checked').value;
			console.log("changing to " + val);
			var data = JSON.stringify({ "is_adopter": val === "yes"? true : false });
			var arr = { City: 'Moscow', Age: 25 };
			$.ajax({
			    url: '/index.php/apps/canary',
			    type: 'POST',
			    data: data,
			    contentType: 'application/json; charset=utf-8',
			    dataType: 'json',
			    async: false,
			    success: function(msg) {
				    location.reload(true);
			    }
			});

		});
		$.getJSON("/index.php/apps/canary", function(data) {
			if (data["is_adopter"]) {
				$('input:radio[name=canary-adopter][value=yes]').prop('checked', true);	
				$('input:radio[name=canary-adopter][value=no]').prop('checked', false);	
			} else {
				$('input:radio[name=canary-adopter][value=yes]').prop('checked', false);	
				$('input:radio[name=canary-adopter][value=no]').prop('checked', true);	
			}
		});
	});
	</script>

</div>
