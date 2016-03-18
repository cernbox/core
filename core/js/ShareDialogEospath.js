/**
 * author: Nadir Roman Guerrero
 */

(function() {
	if (!OC.Share) {
		OC.Share = {};
	}
	
	var TEMPLATE =
		'<p><b>EOS Path</b>: {{eospathText}}</p>';
	
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

		/*initialize: function(options) {
			var view = this;

			this.model.on('change:eospath', function() {
				view.render();
			});

			if(!_.isUndefined(options.configModel)) {
				this.configModel = options.configModel;
			} else {
				//throw 'missing OC.Share.ShareConfigModel';
			}
		},*/

		render: function() {
			if(!this.model)
			{
				this.$el.empty();
				return;
			}
			
			var eospathT = this.model.get('eospath');
			
			if(eospathT == 'undefined') 
			{
				this.$el.empty();
				return;
			}
			
			eospathT = eospathT.trim();
			
			var eosPathSplit = eospathT.split(/\.sys\.v#\./g);
			if(eosPathSplit.length > 1)
			{
				eospathT = eosPathSplit[0] + eosPathSplit[1];
			}
			
			this.$el.removeClass('hidden');

			var reshareTemplate = this.template();
			
			this.$el.html(reshareTemplate({
				eospathText: eospathT
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
