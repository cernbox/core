<table cellspacing="0" cellpadding="0" border="0" width="100%">
<tr><td>
<table cellspacing="0" cellpadding="0" border="0" width="600px">
<tr>
<td bgcolor="<?php p($theme->getMailHeaderColor());?>" width="20px">&nbsp;</td>
<td bgcolor="<?php p($theme->getMailHeaderColor());?>">
<img src="<?php p(OC_Helper::makeURLAbsolute(image_path('', 'logo-mail.gif'))); ?>" alt="<?php p($theme->getName()); ?>"/>
</td>
</tr>
<tr><td colspan="2">&nbsp;</td></tr>
<tr>
<td width="20px">&nbsp;</td>
<td style="font-weight:normal; font-size:0.8em; line-height:1.2em; font-family:verdana,'arial',sans;">
<?php
$msg = 'Hey there,<br><br>just letting you know that <b><font color="#58ACFA">%s</font></b> (%s) shared the folder <strong>%s</strong> with the e-group %s.<br><br>To see the share log in with your account that belongs to %s in  <a href="%s">CERNBox</a> and click the tab <strong>Shared with you</strong>.<br><br>Also, if you want to sync the share in your desktop sync client add a new folder with this path <b><br><br><b>%s</b><br><br>';
$msgreal = sprintf($msg,$_['user_displayname'], $_['user_id'], $_['filename'],$_['recipient'],$_['recipient'],$_['link'], $_['path']);
print_unescaped($msgreal);
p($l->t('Cheers!'));
?>
</td>
</tr>
<tr><td colspan="2">&nbsp;</td></tr>
<tr>
<td width="20px">&nbsp;</td>
<td style="font-weight:normal; font-size:0.8em; line-height:1.2em; font-family:verdana,'arial',sans;">--<br>
<?php p($theme->getName()); ?> -
<a href="<?php p($theme->getDocBaseUrl()); ?>"><?php p($theme->getSlogan()); ?></a>
<br><a href="<?php p($theme->getBaseUrl()); ?>"><?php p($theme->getBaseUrl());?></a>
</td>
</tr>
<tr>
<td colspan="2">&nbsp;</td>
</tr>
</table>
</td></tr>
</table>
