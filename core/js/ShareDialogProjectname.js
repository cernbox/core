/**
 * author: Nadir Roman Guerrero
 */

(function() {
	if (!OC.Share) {
		OC.Share = {};
	}
	
	var TEMPLATE =
		'<span class="eosinfo">' +
		'    {{projectnameText}}' +
		'</span><br/>'
		;
	
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

			this.model.on('change:projectname', function() {
				view.render();
			});

			if(!_.isUndefined(options.configModel)) {
				this.configModel = options.configModel;
			} else {
				//throw 'missing OC.Share.ShareConfigModel';
			}
		},

		render: function() {
			var projectnameT = this.model.getProjectName();
			if (!projectnameT || projectnameT == 'undefined')
			{
				this.$el.empty();
				return this;
			}

			var reshareTemplate = this.template();
			
			this.$el.html(reshareTemplate({
				projectnameText: '<p>EGroup Permissions</p><p>cernbox-project-'+projectnameT+'-readers</p><p>cernbox-project-'+projectnameT+'-writers</p>'
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