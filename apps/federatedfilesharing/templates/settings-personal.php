<?php
/** @var \OCP\IL10N $l */
/** @var array $_ */
script('federatedfilesharing', 'settings-personal');
style('federatedfilesharing', 'settings-personal');
if ($_['showShareIT']) {
	script('federatedfilesharing', '3rdparty/gs-share/gs-share');
	style('federatedfilesharing', '3rdparty/gs-share/style');
}
?>

