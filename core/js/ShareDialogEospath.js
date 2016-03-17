/**
 * author: Nadir Roman Guerrero
 */

(function() {
	if (!OC.Share) {
		OC.Share = {};
	}
	
	var TEMPLATE =
		'<span class="eosinfo">' +
		'    {{eospathText}}' +
		'</span><br/>'
		;
	
	var ShareDialogEospath = OC.Backbone.View.extend({
	/** @type {string} **/
		id: 'shareDialogEospath',

		/** @type {string} **/
		tagName: 'div',

		/** @type {string} **/
		className: 'eospath',

		/** @type {OC.Share.ShareConfigModel} **/
		configModel: undefined,

		/** @type {Function} **/
		_template: undefined,

		initialize: function(options) {
			var view = this;

			this.model.on('change:eospath', function() {
				view.render();
			});

			if(!_.isUndefined(options.configModel)) {
				this.configModel = options.configModel;
			} else {
				//throw 'missing OC.Share.ShareConfigModel';
			}
		},

		render: function() {
			if(!this.model.getEosPath() || this.model.getEosPath() == 'undefined') {
				return;
			}
			
			var eospathT = this.model.getEosPath().trim();
			
			if (!eospathT || eospathT == 'undefined')
			{
				this.$el.empty();
				return this;
			}
			
			var eosPathSplit = eospathT.split(/\.sys\.v#\./g);
			if(eosPathSplit.length > 1)
			{
				eospathT = eosPathSplit[0] + eosPathSplit[1];
			}
			
			this.$el.removeClass('hidden');

			var reshareTemplate = this.template();
			
			this.$el.html(reshareTemplate({
				eospathText: 'EOS Path: ' + eospathT
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

	OC.Share.ShareDialogEospath = ShareDialogEospath;

})();
