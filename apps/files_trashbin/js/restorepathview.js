(function() {
	if (!OC.Share) {
		OC.Share = {};
	}
	
	var TEMPLATE =
        '<p><b>EOS Restoration path</b>:</p><textarea onclick="this.focus();this.select()">{{eospathText}}</textarea>';
	
	var RestorePathView = OC.Backbone.View.extend({
	/** @type {string} **/
		id: 'trashbinDialogEosRestorepath',

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
		},
		
		get$: function() {
			return this.$el;
		},
		
		setFileInfo: function(fileInfo) {
			this.model = fileInfo;
			
			if(this.model)
			{
				this.model.on('change:eospath', function() {
					view.render();
				});
			}
			
			this.render();
		},

		render: function() {
			if(!this.model)
				return null;
			var eospathT = this.model.get('restore-path');
			
			if (!eospathT || typeof eospathT == 'undefined')
			{
				this.$el.empty();
				return this;
			}
			
			this.$el.removeClass('hidden');

			var reshareTemplate = this.template();
			
			this.$el.html(reshareTemplate({
				eospathText: eospathT
			}));
			
			//this.$el.find('textarea').select();
		
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

	OCA.Trashbin.RestorePathView = RestorePathView;
})();