(function() 
{
	if(!OC.Cernbox) 
	{
		OC.Cernbox = {};
	}
	
	var TEMPLATE_BASE =
		'<div class="cernboxInfoView subView"></div>' +
		'<div class="eospathView subView hidden"></div>' +
		'<div class="projectnameView subView hidden"></div>';
	
	var CernboxDialogView = OCA.Files.DetailTabView.extend (
	{	
		id: 'cernboxTabView',
		className: 'tab cernboxTabView',
		order: -5,
		
		/** @type {Object} **/
		_templates: {},
		
		/** @type {string} **/
		tagName: 'div',
		
		/** @type {OC.Share.ShareConfigModel} **/
		configModel: undefined,
		
		/** @type {object} **/
		eospathView: undefined, 
		
		/** @type {object} **/
		projectnameView: undefined,
		
		subViews: 
		{
				eospathView: 'ShareDialogEospath',			// CERNBOX SHOW SHARE INFO PR PATCH
				projectnameView: 'ShareDialogProjectname'	// CERNBOX SHOW SHARE INFO PR PATCH
		},
		
		initialize: function(/*options*/)
		{	
			OCA.Files.DetailTabView.prototype.initialize.apply(this, arguments);
			var view = this;
			
			/*if(!_.isUndefined(options.configModel)) 
			{
				this.configModel = options.configModel;
			} 
			else 
			{
				//throw 'missing OC.Share.ShareConfigModel';
			}*/
			
			var subViewOptions = 
			{
					model: this.model,
					configModel: this.configModel
			};
			
			for(var name in this.subViews) 
			{
				var className = this.subViews[name];
				this[name] = new OC.Share[className](subViewOptions);/*_.isUndefined(options[name])
					? new OC.Share[className](subViewOptions)
					: options[name];*/
			}
		},
		
		getLabel: function() {
			return t('core', 'CERNBox Info');
		},
		
		render: function() 
		{
			var baseTemplate = this._getTemplate('base', TEMPLATE_BASE);

			this.$el.html(baseTemplate());
			
			this.eospathView.model = this.model;
			this.eospathView.$el = this.$el.find('.eospathView');
			this.eospathView.render();
			
			this.projectnameView.model = this.model;
			this.projectnameView.$el = this.$el.find('.projectnameView');
			this.projectnameView.render();

			return this;
		},
		
		_getTemplate: function (key, template) 
		{
			if (!this._templates[key]) 
			{
				this._templates[key] = Handlebars.compile(template);
			}
			return this._templates[key];
		},
		
		setFileInfo: function(fileInfo) 
		{
			OCA.Files.DetailTabView.prototype.setFileInfo.apply(this, arguments);
			this.model = fileInfo;
			this.render();
		}
	});
	
	OC.Cernbox.CernboxDialogView = CernboxDialogView;
})();