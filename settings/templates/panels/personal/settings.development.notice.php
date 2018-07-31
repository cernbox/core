<?php if (OC_Util::getEditionString() === OC_Util::EDITION_COMMUNITY): ?>
	<p>
		<?php print_unescaped(\str_replace(
			[
				'{communityopen}',
				'{githubopen}',
				'{licenseopen}',
				'{linkclose}',
			],
			[
				'<a href="http://information-technology.web.cern.ch/about/organisation/storage" target="_blank" rel="noreferrer">',
				'<a href="https://github.com/cernbox/core" target="_blank" rel="noreferrer">',
				'<a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank" rel="noreferrer">',
				'</a>',
			],
			$l->t('Developed by the {communityopen}CERN IT Storage Group{linkclose}, the {githubopen}source code{linkclose} is licensed under the {licenseopen}<abbr title="Affero General Public License">AGPL</abbr>{linkclose}.')
		)); ?>
	</p>
<?php endif; ?>
