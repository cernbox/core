/**
 * author: Nadir Roman Guerrero
 */

(function() {
	if (!OC.Share) {
		OC.Share = {};
	}
	
	var TEMPLATE =
		'<p><b>E-Group Permissions:</b></p><ul><li>cernbox-project-{{projectnameText}}-readers</li><li>cernbox-project-{{projectnameText}}-writers</li></ul>';
	
	var ShareDialogProjectname = OC.Backbone.View.extend({
	/** @type {string} **/
		id: 'shareDialogProjectName',

		/** @type {string} **/
		tagName: 'div',

		/** @type {string} **/
		className: 'projectname',

		/** @type {OC.Share.ShareConfigModel} **/
		configModel: undefined,

		/** @type {Function} **/
		_template: undefined,

		initialize: function(options) {
			var view = this;

			/*this.model.on('change:projectname', function() {
				view.render();
			});*/

			if(!_.isUndefined(options.configModel)) {
				this.configModel = options.configModel;
			} else {
				//throw 'missing OC.Share.ShareConfigModel';
			}
		},

		render: function() {
			if(!this.model)
			{
				this.$el.empty();
				return;
			}
			
			var projectnameT = this.model.get('projectname');
			if (!projectnameT || typeof projectnameT == 'undefined')
			{
				this.$el.empty();
				return this;
			}
			
			this.$el.removeClass('hidden');

			var reshareTemplate = this.template();
			var projectnameT = 'Test';
			
			this.$el.html(reshareTemplate({
				projectnameText: projectnameT
			}));

			return this;
		},

		/**
		 * @returns {Function} from Handlebars
		 * @private
		 */
		template: function () {
			if (!this._template) {
				this._template = Handlebars.compile(TEMPLATE);
			}
			return this._template;
		}

	});

	OC.Share.ShareDialogProjectname = ShareDialogProjectname;

})();