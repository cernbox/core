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
	if(!OC.Share) {
		OC.Share = {};
	}

	var TEMPLATE_BASE =
		'<div class="resharerInfoView subView"></div>' +
		'{{#if isSharingAllowed}}' +
		'{{#if canShareWithUsers}}' +
		'<label for="shareWith-{{cid}}" class="hidden-visually">{{shareLabel}}</label>' +
		'<div class="oneline">' +
		'    <input id="shareWith-{{cid}}" class="shareWithField" type="text" placeholder="{{sharePlaceholder}}" />' +
		'    <span class="shareWithLoading icon-loading-small hidden"></span>'+
		'{{{remoteShareInfo}}}' +
		'</div>' +
		'<div id="recipentList" class="shareRecipentListView hidden"><p>Share item to the following users/groups:</p><ul></ul>' +
		'<input id="shareListButton" class="emailButton" type="submit" value="Confirm">' +
		'</div>' +
		'{{/if}}' +
		'{{/if}}' +
		'<div class="shareeListView subView"></div>' +
		'<div class="linkShareView subView"></div>' +
		'<div class="expirationView subView"></div>' +
		'<div class="loading hidden" style="height: 50px"></div>';

	var TEMPLATE_REMOTE_SHARE_INFO =
		'<a target="_blank" class="icon-info svg shareWithRemoteInfo hasTooltip" href="{{docLink}}" ' +
		'title="{{tooltip}}"></a>';

	/**
	 * @class OCA.Share.ShareDialogView
	 * @member {OC.Share.ShareItemModel} model
	 * @member {jQuery} $el
	 * @memberof OCA.Sharing
	 * @classdesc
	 *
	 * Represents the GUI of the share dialogue
	 *
	 */
	var ShareDialogView = OC.Backbone.View.extend({
		/** @type {Object} **/
		_templates: {},

		/** @type {boolean} **/
		_showLink: true,

		/** @type {string} **/
		tagName: 'div',

		/** @type {OC.Share.ShareConfigModel} **/
		configModel: undefined,

		/** @type {object} **/
		resharerInfoView: undefined,

		/** @type {object} **/
		linkShareView: undefined,

		/** @type {object} **/
		expirationView: undefined,

		/** @type {object} **/
		shareeListView: undefined,
		
		/** CERNBOX SHARE USER LIST PR PATCH */
		/** @type {array} **/
		shareRecipientList: [],
		
		/** CERNBOX SHOW SHARE INFO PR PATCH */
		/** @type {object} **/
		 eospathView: undefined, 		
		 /** @type {object} **/
		 projectnameView: undefined,

		initialize: function(options) {
			var view = this;

			this.model.on('fetchError', function() {
				OC.Notification.showTemporary(t('core', 'Share details could not be loaded for this item.'));
			});

			if(!_.isUndefined(options.configModel)) {
				this.configModel = options.configModel;
			} else {
				throw 'missing OC.Share.ShareConfigModel';
			}

			this.configModel.on('change:isRemoteShareAllowed', function() {
				view.render();
			});
			this.model.on('change:permissions', function() {
				view.render();
			});

			this.model.on('request', this._onRequest, this);
			this.model.on('sync', this._onEndRequest, this);

			var subViewOptions = {
				model: this.model,
				configModel: this.configModel
			};

			var subViews = {
				resharerInfoView: 'ShareDialogResharerInfoView',
				linkShareView: 'ShareDialogLinkShareView',
				expirationView: 'ShareDialogExpirationView',
				shareeListView: 'ShareDialogShareeListView'
			};

			for(var name in subViews) {
				var className = subViews[name];
				this[name] = _.isUndefined(options[name])
					? new OC.Share[className](subViewOptions)
					: options[name];
			}

			_.bindAll(this, 'autocompleteHandler', '_onSelectRecipient');
		},

		autocompleteHandler: function (search, response) {
			var view = this;
			var $loading = this.$el.find('.shareWithLoading');
			$loading.removeClass('hidden');
			$loading.addClass('inlineblock');
			$.get(OC.filePath('core', 'ajax', 'share.php'), {
				fetch: 'getShareWith',
				search: search.term.trim(),
				limit: 200,
				itemShares: OC.Share.itemShares,
				itemType: view.model.get('itemType')
			}, function (result) {
				$loading.addClass('hidden');
				$loading.removeClass('inlineblock');
				if (result.status == 'success' && result.data.length > 0) {
					$('.shareWithField').autocomplete("option", "autoFocus", true);
					response(result.data);
				} else {
					response();
				}
			}).fail(function () {
				$loading.addClass('hidden');
				$loading.removeClass('inlineblock');
				OC.Notification.show(t('core', 'An error occured. Please try again'));
				window.setTimeout(OC.Notification.hide, 5000);
			});
		},

		autocompleteRenderItem: function(ul, item) {
			var insert = $("<a>");
			var text = item.label;
			if (item.value.shareType === OC.Share.SHARE_TYPE_GROUP) {
				text = text +  ' ('+t('core', 'group')+')';
			} else if (item.value.shareType === OC.Share.SHARE_TYPE_REMOTE) {
				text = text +  ' ('+t('core', 'remote')+')';
			}
			insert.text(text);
			if(item.value.shareType === OC.Share.SHARE_TYPE_GROUP) {
				insert = insert.wrapInner('<strong></strong>');
			}
			return $("<li>")
				.addClass((item.value.shareType === OC.Share.SHARE_TYPE_GROUP) ? 'group' : 'user')
				.append(insert)
				.appendTo(ul);
		},
		
		/** CERNBOX SHARE USER LIST PR PATCH */
		_getRecipentIndex: function(recipentUid)
		{
			for(var i = 0; i < this.shareRecipientList.length; i++)
			{
				if(this.shareRecipientList[i].uid == recipentUid) return i;
			}
			return -1;
		},

		_onSelectRecipient: function(e, s) {
			e.preventDefault();
			$(e.target).val('');
			/** CERNBOX SHARE USER LIST PR PATCH */
			//this.model.addShare(s.item.value);
			var recipent = s.item.value.shareWith;
			
			if(this._getRecipentIndex(recipent) != -1)
			{
				return;
			}
			
			var recipientData = {uid: recipent, displayName: s.item.label, type: s.item.value.shareType };
			this.shareRecipientList.push(recipientData);
			
			var recipentDiv = this.$el.find('#recipentList');
			
			var recipentList = recipentDiv.find('ul');
			if(recipentList && this.shareRecipientList.length > 0)
			{
				recipentDiv.removeClass('hidden');
				recipentList.empty();
				var _self = this;
				for(var i = 0; i < this.shareRecipientList.length; i++)
				{
					curRecipient = this.shareRecipientList[i];
					var li = $('<li recipent="'+ curRecipient.uid + '"><img class="recipentDeleter" src="'+ OC.imagePath('core', 'actions/close.svg') +'"><span class="username">' +curRecipient.displayName + '</span></li>');
					var img = li.find('img');
					img.click(function()
						{
							var delRecipient = $(this).parent().attr('recipent');
							if(delRecipient && delRecipient != 'undefined')
							{
								var index = _self._getRecipentIndex(delRecipient);
								if(index != -1)
								{
									_self.shareRecipientList.splice(index, 1);
									if(_self.shareRecipientList.length <= 0)
									{
										_self.$el.find('#recipentList').addClass('hidden');
									}
								}
							}
							
							$(this).parent().remove();
						});
					recipentList.append(li);
				}
			}
			//this.model.addShare(s.item.value);
		},

		_toggleLoading: function(state) {
			this._loading = state;
			this.$el.find('.subView').toggleClass('hidden', state);
			this.$el.find('.loading').toggleClass('hidden', !state);
		},

		_onRequest: function() {
			// only show the loading spinner for the first request (for now)
			if (!this._loadingOnce) {
				this._toggleLoading(true);
			}
		},

		_onEndRequest: function() {
			var self = this;
			this._toggleLoading(false);
			if (!this._loadingOnce) {
				this._loadingOnce = true;
				// the first time, focus on the share field after the spinner disappeared
				_.defer(function() {
					self.$('.shareWithField').focus();
				});
			}
		},

		render: function() {
			var baseTemplate = this._getTemplate('base', TEMPLATE_BASE);

			this.$el.html(baseTemplate({
				cid: this.cid,
				shareLabel: t('core', 'Share'),
				sharePlaceholder: this._renderSharePlaceholderPart(),
				remoteShareInfo: this._renderRemoteShareInfoPart(),
				isSharingAllowed: this.model.sharePermissionPossible(),
				canShareWithUsers: this.model.isFolder() && this.model.fileInfoModel.get('path') == '/'
			}));

			var $shareField = this.$el.find('.shareWithField');
			if ($shareField.length) {
				$shareField.autocomplete({
					minLength: 2,
					delay: 750,
					focus: function(event) {
						event.preventDefault();
					},
					source: this.autocompleteHandler,
					select: this._onSelectRecipient
				}).data('ui-autocomplete')._renderItem = this.autocompleteRenderItem;
			}

			this.resharerInfoView.$el = this.$el.find('.resharerInfoView');
			this.resharerInfoView.render();

			this.linkShareView.$el = this.$el.find('.linkShareView');
			this.linkShareView.render();

			this.expirationView.$el = this.$el.find('.expirationView');
			this.expirationView.render();

			this.shareeListView.$el = this.$el.find('.shareeListView');
			this.shareeListView.render();
			
			this.$el.find('.hasTooltip').tooltip();
			
			/** CERNBOX SHARE USER LIST PR PATCH */
			var shareButton = this.$el.find('#recipentList').find('#shareListButton');
			var _self = this;
			shareButton.click(function(event)
			{
				event.preventDefault();
				
				var shareRequestData = [];
				for(var i = 0; i < _self.shareRecipientList.length; i++)
				{
					var token = {uid: _self.shareRecipientList[i].uid, type: _self.shareRecipientList[i].type };
					shareRequestData.push(token);
				}
				
				_self.model.addShareList(shareRequestData);
				
				_self.shareRecipientList.length = 0;
				this.$el.find('#recipentList').addClass('hidden');
				_self._toggleLoading(true);
			});

			return this;
		},

		/**
		 * sets whether share by link should be displayed or not. Default is
		 * true.
		 *
		 * @param {bool} showLink
		 */
		setShowLink: function(showLink) {
			this._showLink = (typeof showLink === 'boolean') ? showLink : true;
			this.linkShareView.showLink = this._showLink;
		},

		_renderRemoteShareInfoPart: function() {
			var remoteShareInfo = '';
			if(this.configModel.get('isRemoteShareAllowed')) {
				var infoTemplate = this._getRemoteShareInfoTemplate();
				remoteShareInfo = infoTemplate({
					docLink: this.configModel.getFederatedShareDocLink(),
					tooltip: t('core', 'Share with people on other ownClouds using the syntax username@example.com/owncloud')
				});
			}

			return remoteShareInfo;
		},

		_renderSharePlaceholderPart: function () {
			var sharePlaceholder = t('core', 'Share with users or groups …');
			if (this.configModel.get('isRemoteShareAllowed')) {
				sharePlaceholder = t('core', 'Share with users, groups or remote users …');
			}
			return sharePlaceholder;
		},

		/**
		 *
		 * @param {string} key - an identifier for the template
		 * @param {string} template - the HTML to be compiled by Handlebars
		 * @returns {Function} from Handlebars
		 * @private
		 */
		_getTemplate: function (key, template) {
			if (!this._templates[key]) {
				this._templates[key] = Handlebars.compile(template);
			}
			return this._templates[key];
		},

		/**
		 * returns the info template for remote sharing
		 *
		 * @returns {Function}
		 * @private
		 */
		_getRemoteShareInfoTemplate: function() {
			return this._getTemplate('remoteShareInfo', TEMPLATE_REMOTE_SHARE_INFO);
		}
	});

	OC.Share.ShareDialogView = ShareDialogView;

})();
