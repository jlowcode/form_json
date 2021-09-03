define(['jquery', 'fab/fabrik'], function (jQuery, Fabrik) {
    'use strict';
    var FbFormJson = new Class({

        Implements: [Events],

        initialize: function (options) {
            this.options = jQuery.extend(this.options, options);

            if (this.options.view === 'form') {
                this.addWatchButton();
            }
        },
        addWatchButton: function () {
            var button = document.getElementById('plugin_selected');
            var plugin_params = document.getElementById('plugin_params');

            button.onclick = () => {
                var pluginSelected = document.getElementById('select_plugin');
                jQuery.ajax ({
                    url: Fabrik.liveSite + 'index.php',
                    method: "POST",
                    data: {
                        'option': 'com_fabrik',
                        'format': 'raw',
                        'task': 'plugin.pluginAjax',
                        'plugin': 'form_json',
                        'method': 'createHtmlPluginFromXml',
                        'g': 'element',
                        'element_id': this.options.element_id,
                        'plugin_selected': pluginSelected.value
                    }
                }).done ((data) => {
                    var html = document.createElement('div');
                    html.innerHTML = JSON.parse(data);

                    while (plugin_params.firstChild) {
                        plugin_params.removeChild(plugin_params.lastChild);
                    }

                    plugin_params.appendChild(html.firstChild);
                });
            };
        }
    });

    return FbFormJson;
});
