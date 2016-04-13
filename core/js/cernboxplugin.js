(function() 
{
	OC.Cernbox = OC.Cernbox || {};

	/**
	 * @namespace
	 */
	OC.Cernbox.Util = 
	{
		
		attach: function(fileList) 
		{
			if (fileList.id === 'files.public') 
			{
				return;
			}

			var view = new OC.Cernbox.CernboxDialogView('cernboxTabView', {order: -5});
			
			fileList.registerTabView(view);
			
			if (fileList.id === 'trashbin')
			{
				view.registerSubView('restorepathView', new OCA.Trashbin.RestorePathView());
			}
		}
	};
})();

OC.Plugins.register('OCA.Files.FileList', OC.Cernbox.Util);
