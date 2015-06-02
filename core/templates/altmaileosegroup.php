<?php
$msg = "Hey there,\n\njust letting you know that %s shared the folder %s with the e-group %s.\n\n\nTo see the share log in with your account that belongs to %s in %s and click the tab Shared with me.\n\nAlso, if you want to sync the share in your desktop sync client add a new folder with this path:\n\n%s\n\n";
$msgreal = sprintf($msg,$_['user_displayname'], $_['filename'],$_['recipient'],$_['recipient'],$_['link'], $_['path']);
print_unescaped($msgreal);
p($l->t("Cheers!"));
?>
--
<?php p($theme->getName() . ' - ' . $theme->getSlogan()); ?>
<?php print_unescaped("\n".$theme->getBaseUrl());
