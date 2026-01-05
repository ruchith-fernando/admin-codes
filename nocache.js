// nocache.js – include this once in your main layout
// Ensures every AJAX request skips cache

// Global jQuery setting
$.ajaxSetup({
    cache: false
});

// Optional: if you ever use fetch() instead of $.ajax
(function(open) {
    XMLHttpRequest.prototype.open = function(method, url, async, user, pass) {
        if (url.indexOf("?") === -1) {
            url += "?_ts=" + new Date().getTime();
        } else {
            url += "&_ts=" + new Date().getTime();
        }
        arguments[1] = url;
        open.call(this, method, url, async, user, pass);
    };
})(XMLHttpRequest.prototype.open);
