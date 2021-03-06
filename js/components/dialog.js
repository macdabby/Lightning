(function(){
    var self;
    lightning.dialog = {
        originalScroll: 0,
        newPosition: 0,
        dialogBox: false,
        dialogBoxLoader: undefined,
        dialogBoxInner: undefined,

        init: function() {
            if (!self.dialogBox) {
                $('<div class="reveal" id="dialog_box" data-reveal aria-hidden="true" role="dialog">'+
                    '<a class="close-button" data-close><i class="fa fa-times-circle"></i></a>' +
                    '<div class="table_data clear border_top">'+
                    '<div class="inner" id="dialog_box_inner">'+
                    '<div class="messenger error"><ul></ul></div>'+
                    '<div class="messenger warning"><ul></ul></div>'+
                    '<div class="messenger message"><ul></ul></div>'+
                    '<div class="content"><ul></ul></div>'+
                    '</div>'+
                    '<div class="inner" id="dialog_box_loader"></div>'+
                    '</div>'+
                    '</div>').prependTo('body');
                self.dialogBox = $('#dialog_box');
                $(self.dialogBox).foundation();
                self.dialogBoxLoader = $('#dialog_box_loader');
                self.dialogBoxInner = $('#dialog_box_inner');
                self.clear();
            }
        },

        /**
         * Fade out the dialog and veil.
         */
        hide: function() {
            self.init();
            self.dialogBox.foundation('close');
        },

        show: function() {
            self.init();
            self.dialogBox.foundation('open');
        },

        showLoader: function(message) {
            self.init();
            self.clear();
            self.setContent('<p align="center"><img src="/images/lightning/cog-spinner.gif" class="loader_image"></p>');
            if (typeof message !== 'undefined') {
                self.addContent('<p align="center">' + message + '</p>');
            }
            self.show();
        },

        /**
         * Load a URL into the dialog.
         * @param url
         */
        showURL: function(url) {
            self.init();
            var iframe = $('<iframe></iframe>');
            iframe.addClass('content-loader');
            iframe.on('load', function(){
                // Make the iframe resize to the content size.
                iframe.css('height', iframe[0].contentWindow.document.body.scrollHeight + 'px');
            });
            iframe.prop('src', url);
            self.setContent(iframe);
        },

        showContent: function(content) {
            self.init();
            self.setContent(content);
            self.show();
        },

        /**
         * Clear the modal contents.
         */
        clear: function() {
            self.init();
            self.dialogBoxInner.find('.content').empty().hide();
            self.dialogBoxInner.find('.error ul').empty();
            self.dialogBoxInner.find('.error').hide();
            self.dialogBoxInner.find('.warning ul').empty();
            self.dialogBoxInner.find('.warning').hide();
            self.dialogBoxInner.find('.message ul').empty();
            self.dialogBoxInner.find('.message').hide();
        },

        /**
         * Adds new content to a dialog even if it's visible without changing anything else.
         * @param {string} content
         *   This must be wrapped in at least an HTML tag or the hide() function will erase it.
         */
        addContent: function(content) {
            self.init();
            content = $(content).hide();
            self.dialogBoxInner.find('.content').append(content);
            content.fadeIn('fast');
            self.dialogBoxInner.fadeIn('fast');
            self.dialogBoxInner.find('.content').fadeIn('fast');
        },

        /**
         * Add a success/error message to an existing dialog.
         *
         * @param {string} message
         *   The message to display.
         * @param {string} message_type
         *   (Optional) self can be 'error' or 'message'. Default is 'message'.
         */
        add: function(message, message_type) {
            self.init();
            message = $('<li>' + message + '</li>');
            var container = (message_type == 'message') ? '.message' : (message_type == 'warning' ? '.warning' : '.error');
            self.dialogBoxLoader.fadeOut('fast', function() {
                if (self.dialogBoxInner.find(container).is(':visible')) {
                    message.hide();
                    self.dialogBoxInner.find(container + ' ul').append(message);
                    message.fadeIn("fast");
                } else {
                    self.dialogBoxInner.find(container + ' ul').append(message);
                    if (self.dialogBoxInner.is(':visible')) {
                        self.dialogBoxInner.find(container).fadeIn('fast');
                    } else {
                        self.dialogBoxInner.find(container).show();
                    }
                }
                self.dialogBoxInner.fadeIn('fast');
            });
        },

        /**
         * The following methods should only be called internally.
         */

        /**
         * Resets a dialog with new content. (fades out if required).
         * @param {string} content
         * @param {boolean} clear_messages
         *   Whether to clear errors and messages before placing the content.
         */
        setContent: function(content, clear_messages) {
            self.init();
            self.dialogBoxInner.hide();
            if (typeof clear_messages === 'undefined' || clear_messages) {
                self.clear();
            }
            if (typeof content === 'object') {
                self.dialogBoxInner.find('.content').append(content).show();
            } else {
                self.dialogBoxInner.find('.content').html(content).show();
            }
            self.dialogBox.foundation('open');
            self.dialogBoxInner.fadeIn('fast');
        }
    };
    self = lightning.dialog;
})();
