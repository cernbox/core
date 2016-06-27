/*
 * Copyright (c) 2015
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

(function() {
	/**
	 * @memberof OCA.Versions
	 */
	var VersionModel = OC.Backbone.Model.extend({

		/**
		 * Restores the original file to this revision
		 */
		revert: function(options) {
			options = options ? _.clone(options) : {};
			var model = this;
			var file = this.getFullPath();
			/** CERNBOX FILE VERSION PLUGIN PATCH */
			//var revision = this.get('timestamp');
			var revision = this.get('id');
			/** PATCH END */

			$.ajax({
				type: 'GET',
				url: OC.generateUrl('/apps/files_versions/ajax/rollbackVersion.php'),
				dataType: 'json',
				data: {
					file: file,
					revision: revision
				},
				success: function(response) {
					if (response.status === 'error') {
						if (options.error) {
							options.error.call(options.context, model, response, options);
						}
						model.trigger('error', model, response, options);
					} else {
						if (options.success) {
							options.success.call(options.context, model, response, options);
						}
						model.trigger('revert', model, response, options);
					}
				}
			});
		},

		getFullPath: function() {
			return this.get('fullPath');
		},

		getPreviewUrl: function() {
			var url = OC.generateUrl('/apps/files_versions/preview');
			var params = {
				file: this.get('fullPath'),
				version: this.get('id')//this.get('timestamp') /** CERNBOX FILE VERSION PLUGIN PATCH */
			};
			return url + '?' + OC.buildQueryString(params);
		},

		getDownloadUrl: function() {
			var url = OC.generateUrl('/apps/files_versions/download.php');
			var params = {
				file: this.get('fullPath'),
				revision: this.get('id') //this.get('timestamp') /* CERNBOX FILE VERSION PLUGIN PATCH */
			};
			return url + '?' + OC.buildQueryString(params);
		}
	});

	OCA.Versions = OCA.Versions || {};

	OCA.Versions.VersionModel = VersionModel;
})();

