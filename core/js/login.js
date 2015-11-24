/**
 * Copyright (c) 2015
 *  Vincent Petry <pvince81@owncloud.com>
 *  Jan-Christoph Borchardt, http://jancborchardt.net
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 */

/**
 * @namespace
 * @memberOf OC
 */
OC.Login = _.extend(OC.Login || {}, {
	onLogin: function () {
		$('#submit')
			.css('background-color', 'transparent')
			.css('color', 'transparent')
			.removeClass('icon-confirm')
			.addClass('icon-loading-dark');
		return true;
	}
});
$(document).ready(function() {
	$('form[name=login]').submit(OC.Login.onLogin);
});
