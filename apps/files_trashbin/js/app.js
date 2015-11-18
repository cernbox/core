/*
 * Copyright (c) 2014
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

/**
 * @namespace OCA.Trashbin
 */
OCA.Trashbin = {};
/**
 * @namespace OCA.Trashbin.App
 */
OCA.Trashbin.App = {
	_initialized: false,
	
	dropDownShown: false, 

	initialize: function($el) {
		if (this._initialized) {
			return;
		}
		this._initialized = true;
		this.fileList = new OCA.Trashbin.FileList(
			$('#app-content-trashbin'), {
				scrollContainer: $('#app-content'),
				fileActions: this._createFileActions(),
				detailsViewEnabled: false
			}
		);
	},
	
	showInfoDropDown: function(fileId, eospath, appendTo) {
		OCA.Trashbin.App.dropDownShown = true;
		var html = '<div id="dropdown" class="drop shareDropDown" data-item-id="'+fileId+'">';
		html += '<p class="pathtext"><u>EOS Restore Path</u>: ' + eospath + '</p></div>';
		
		var dropDownEl = $(html);
		dropDownEl = dropDownEl.appendTo(appendTo);
	},
	
	hideInfoDropDown: function(callback) {
		OCA.Trashbin.App.dropDownShown = false;
		$('#dropdown').hide('blind', function() {
			$('#dropdown').remove();
			
			if(callback) {
				callback.call();
			}
		});
	},

	_createFileActions: function() {
		var fileActions = new OCA.Files.FileActions();
		fileActions.register('dir', 'Open', OC.PERMISSION_READ, '', function (filename, context) {
			var dir = context.fileList.getCurrentDirectory();
			if (dir !== '/') {
				dir = dir + '/';
			}
			context.fileList.changeDirectory(dir + filename);
		});

		fileActions.setDefault('dir', 'Open');

		fileActions.register('all', 'Restore', OC.PERMISSION_READ, OC.imagePath('core', 'actions/history'), function(filename, context) {
			var fileList = context.fileList;
			var tr = fileList.findFileEl(filename);
			var deleteAction = tr.children("td.date").children(".action.delete");
			deleteAction.removeClass('icon-delete').addClass('icon-loading-small');
			fileList.disableActions();
			$.post(OC.filePath('files_trashbin', 'ajax', 'undelete.php'), {
					files: JSON.stringify([filename]),
					dir: fileList.getCurrentDirectory()
				},
				_.bind(fileList._removeCallback, fileList)
			);
		}, t('files_trashbin', 'Restore'));
		
		
		fileActions.register('all', 'Info', OC.PERMISSION_READ, OC.imagePath('core', 'actions/info'), function(filename, context) {
			var fileList = context.fileList;
			var tr = fileList.findFileEl(filename);
			
			if(OCA.Trashbin.App.dropDownShown) {
				var curFileId = tr.attr('data-item-id');
				if($('#dropdown').data('item-id') != curFileId) {
					OCA.Trashbin.App.hideInfoDropDown(function () {
						OCA.Trashbin.App.showInfoDropDown(curFileId, tr.attr('eospath'), $(tr).find('.action.action-info'));
					});
				} else {
					OCA.Trashbin.App.hideInfoDropDown();
					OCA.Trashbin.App.dropDownShown = false;
				}
			} else {
				OCA.Trashbin.App.showInfoDropDown(tr.attr('data-item-id'), tr.attr('eospath'), $(tr).find('.action.action-info'));
				OCA.Trashbin.App.dropDownShown = true;
			}
		
		}, t('files_trashbin', 'Info'));

		/* HUGO hide delete button per file. EOS does not support per file purge
		fileActions.registerAction({
			name: 'Delete',
			displayName: t('files', 'Delete'),
			mime: 'all',
			permissions: OC.PERMISSION_READ,
			icon: function() {
				return OC.imagePath('core', 'actions/delete');
			},
			render: function(actionSpec, isDefault, context) {
				var $actionLink = fileActions._makeActionLink(actionSpec, context);
				$actionLink.attr('original-title', t('files_trashbin', 'Delete permanently'));
				$actionLink.children('img').attr('alt', t('files_trashbin', 'Delete permanently'));
				context.$file.find('td:last').append($actionLink);
				return $actionLink;
			},
			actionHandler: function(filename, context) {
				var fileList = context.fileList;
				$('.tipsy').remove();
				var tr = fileList.findFileEl(filename);
				var deleteAction = tr.children("td.date").children(".action.delete");
				deleteAction.removeClass('icon-delete').addClass('icon-loading-small');
				fileList.disableActions();
				$.post(OC.filePath('files_trashbin', 'ajax', 'delete.php'), {
						files: JSON.stringify([filename]),
						dir: fileList.getCurrentDirectory()
					},
					_.bind(fileList._removeCallback, fileList)
				);
			}
		});
		*/
		return fileActions;
	}
};

$(document).ready(function() {
	$('#app-content-trashbin').one('show', function() {
		var App = OCA.Trashbin.App;
		App.initialize($('#app-content-trashbin'));
		
		// force breadcrumb init
		// App.fileList.changeDirectory(App.fileList.getCurrentDirectory(), false, true);
	});
});

