define(['jquery-1.9', 'policy-service'], function($, policyService) {
    var Provider, Embed;

    /*
     * Provider class
     *
     * @param  {String}    name             The name of the provider
     * @param  {String}    type             The type of the provider can be "file", "photo", "video", "rich"
     * @param  {String}    urlshemesarray   Array of url of the provider
     * @param  {String}    apiendpoint      The endpoint of the provider
     */
    Provider = function(name, type, urlschemesarray, apiendpoint, extraSettings) {
        this.name = name;
        this.type = type; // "photo", "video", "link", "rich", null
        this.urlschemes = urlschemesarray;
        this.apiendpoint = apiendpoint;
        this.maxWidth = 500;
        this.maxHeight = 400;
        extraSettings = extraSettings ||{};

        for (var property in extraSettings) {
            this[property] = extraSettings[property];
        }

        this.format = this.format || 'json';
        this.callbackparameter = this.callbackparameter || "callback";
        this.embedtag = this.embedtag || {tag:""};
    };

    Embed = function(options) {
        this.container = options.container;
        this.url = options.url;

        this.start();
    };
    Embed.prototype = {
        doEmbed : function(code, provider) {
            var wrap = $('<div class="third-party__embed"></div>');
            wrap.html(code);
            this.container.addClass('third-party--' + provider.name).html(wrap);
        },
        start : function() {
            var matched_provider = this.getOEmbedProvider(this.url);
            if (!matched_provider) return null;

            this.generateCode(matched_provider, this.url);
        },
        getOEmbedProvider : function (url) {
            var providers_length = this.providers.length,
                schemes_length = 0;
            for (var i = 0; i < providers_length; i++) {
                schemes_length = this.providers[i].urlschemes.length;
                for (var j = 0; j < schemes_length; j++) {
                    var regExp = new RegExp(this.providers[i].urlschemes[j], "i");
                    if (url.match(regExp) !== null) return this.providers[i];
                }
            }
            return null;
        },
        getRequestUrl : function (provider, externalUrl) {
            var url = provider.apiendpoint,
                qs = "",
                i;
            url += (url.indexOf("?") <= 0) ? "?" : "&";
            url = url.replace('#','%23');

            url += "format=" + provider.format + "&url=" + escape(externalUrl) + qs;
            if(provider.dataType!='json') url += "&" + provider.callbackparameter + "=?";

            return url;
        },
        /** @async */
        generateCode : function (embedProvider, externalUrl) {
            var self = this;
            policyService.readPolicy('personalisation')
                .then(function(enabled) {
                    // always render unless the user is logged in and has disabled it explicitly
                    if (enabled === false) return;

                    return (embedProvider.templateRegex) ?
                        self.generateCodeFromTemplate(embedProvider, externalUrl)
                        :
                        self.generateCodeFromRequest(embedProvider, externalUrl)
                        ;
                })
        },

        generateCodeFromRequest : function(embedProvider, externalUrl) {
            var _this = this,
                requestUrl = this.getRequestUrl(embedProvider, externalUrl),
                ajaxopts = {
                    url: requestUrl,
                    dataType: embedProvider.dataType || 'jsonp',
                    success: function(data) {
                        var code;
                        switch (data.type) {
                            case "photo":
                                code = _this.getPhotoCode(externalUrl, data);
                                break;
                            case "video":
                            case "rich":
                                code = _this.getRichCode(externalUrl, data);
                                break;
                            default:
                                code = _this.getGenericCode(externalUrl, data);
                                break;
                        }
                        if (code) {
                            _this.doEmbed(code, embedProvider);
                        }
                    }
                };
            $.ajax(ajaxopts);
        },
        generateCodeFromTemplate : function(embedProvider, externalUrl) {
            var _this = this,
                tag, src, ajaxopts;
            if (embedProvider.embedtag.tag!==''){
                tag = embedProvider.embedtag.tag || 'embed';
                src = externalUrl.replace(embedProvider.templateRegex,embedProvider.apiendpoint);

                var code = $('<'+tag+'/>')
                    .attr('src',src)
                    .attr('allowfullscreen', embedProvider.embedtag.allowfullscreen || 'true')
                    .attr('allowscriptaccess', embedProvider.embedtag.allowfullscreen || 'always')
                    .css('max-height', 'auto' )
                    .css('max-width', 'auto' );
                if (tag=='iframe') {
                    code
                        .attr('scrolling',embedProvider.embedtag.scrolling || "no")
                        .attr('frameborder',embedProvider.embedtag.frameborder || "0");
                }
                this.doEmbed(code, embedProvider);
            } else if (embedProvider.apiendpoint) {
                ajaxopts = {
                    url: externalUrl.replace(embedProvider.templateRegex, embedProvider.apiendpoint),
                    dataType: 'jsonp',
                    success: function(data) {
                        _this.doEmbed(embedProvider.templateData(data), embedProvider);
                    }
                };
                $.ajax(ajaxopts);
            } else {
                this.doEmbed(externalUrl.replace(embedProvider.templateRegex,embedProvider.template), embedProvider);
            }
        },
        getPhotoCode : function(url, oembedData) {
            var code, alt = oembedData.title ? oembedData.title : '';
            alt += oembedData.author_name ? ' - ' + oembedData.author_name : '';
            alt += oembedData.provider_name ? ' - ' + oembedData.provider_name : '';
            if (oembedData.url){
                code = '<div><a href="' + url + '" target=\'_blank\'><img src="' + oembedData.url + '" alt="' + alt + '"/></a></div>';
            } else if (oembedData.thumbnail_url){
                var newURL = oembedData.thumbnail_url.replace('_s','_b');
                code = '<div><a href="' + url + '" target=\'_blank\'><img src="' + newURL + '" alt="' + alt + '"/></a></div>';
            } else {
                return null;
            }
            if (oembedData.html) code += "<div>" + oembedData.html + "</div>";
            return code;
        },

        getRichCode : function(url, oembedData) {
            var code = oembedData.html;
            return code;
        },

        getGenericCode : function(url, oembedData) {
            var title = (oembedData.title !== null) ? oembedData.title : url,
                code = '<a href="' + url + '">' + title + '</a>';
            if (oembedData.html) code += "<div>" + oembedData.html + "</div>";
            return code;
        },
        providers : [
            /* Flickr have currently broken their oEmbed API in Chrome and IE */
            /*new Provider("flickr", "photo", ["flickr\\.com/photos/.+","flic\\.kr/p/.+"], "http://flickr.com/services/oembed",{callbackparameter:'jsoncallback'}),*/
            new Provider("instagram", "rich", ["instagr\\.?am(\\.com)?/.+"], "https://graph.facebook.com/v8.0/instagram_oembed?access_token=3693890007336150%7C25561916b38fe91a65c09d23dc81a858", {
                format: 'json',
                dataType: 'json'
            }),
            new Provider("soundcloud", "rich", ["soundcloud.com/.+","snd.sc/.+"], "https://soundcloud.com/oembed?maxheight=166", {
                format: 'js'
            }),
            new Provider("twitter", "rich", ["twitter.com/.+"], "https://api.twitter.com/1/statuses/oembed.json"),
            new Provider("vimeo", "video", ["www\.vimeo\.com\/groups\/.*\/videos\/.*", "www\.vimeo\.com\/.*", "vimeo\.com\/groups\/.*\/videos\/.*", "vimeo\.com\/.*"], "//vimeo.com/api/oembed.json"),
            new Provider("youtube", "video", ["youtube\\.com/watch.+v=[\\w-]+&?", "youtu\\.be/[\\w-]+","youtube.com/embed"], 'https://www.youtube.com/embed/$1?wmode=transparent&rel=0&iv_load_policy=3', {
                templateRegex: /.*(?:v\=|be\/|embed\/)([\w\-]+)&?.*/,
                embedtag: {
                    tag: 'iframe'
                }
            }),
            new Provider("vine", "video", ["vine\\.co\/v\/.+"], 'https://vine.co/v/$1/embed/simple', {
                templateRegex: /.*(?:\/v\/)([\w\-]+)&?.*/,
                embedtag: {
                    tag: 'iframe'
                }
            })
        ]
    };

    return Embed;
});
