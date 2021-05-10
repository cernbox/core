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
	<p>Enable bleeding edge UI and applications?
	<input type="radio" name="canary-adopter" value="canary">Yes
	<input type="radio" name="canary-adopter" value="production">No
	<span id="ocis-button" style="display:none"><input type="radio" name="canary-adopter" value="ocis">OCIS</span><br>
	</p>
	<script type="text/javascript">
	$().ready(function() {

		// check if user is in cernbox-ocis-adopters e-group.
 		// the available e-groups are displayed in the same page under
		// <div id="groups"> and inside the second <p> tag 
		var groups = $("#groups p:nth-child(3)");
		var adopter = groups.text().trim().indexOf("cernbox-ocis-adopters") !== -1;
		console.log("OCIS adopter: " + adopter);
		
		if (adopter) {
			$('#ocis-button').show();
		}

		$('input:radio[name=canary-adopter]').on('change', function(){
			var val = document.querySelector('input[name="canary-adopter"]:checked').value;
			console.log("changing to " + val);
			var data = JSON.stringify({ "version": val });
			$.ajax({
			    url: '/index.php/apps/canary',
			    type: 'POST',
			    data: data,
			    contentType: 'application/json; charset=utf-8',
			    dataType: 'json',
			    async: false,
			    success: function(msg) {
				    window.location.href= "/";
			    }
			});

		});
		$.getJSON("/index.php/apps/canary", function(data) {
			$('input:radio[name=canary-adopter]').prop('checked', false);
			$('input:radio[name=canary-adopter][value='+data["version"]+']').prop('checked', true);
		});
	});
	</script>

</div>
