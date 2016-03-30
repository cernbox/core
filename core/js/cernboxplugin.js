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

			fileList.registerTabView(new OC.Cernbox.CernboxDialogView('cernboxTabView', {order: -5}));
		}
	};
})();

OC.Plugins.register('OCA.Files.FileList', OC.Cernbox.Util);
