var express = require('express'),
app = express(),
cookieParser = require('cookie-parser'),
session = require('express-session'),
RedisStore = require('connect-redis')(session);

// https://github.com/expressjs/session#cookie-options
//app.set('trust proxy', 1) // trust first proxy
 
app.use(express.static(__dirname + '/static'));
app.use(function(req, res, next) {
    if (req.url.indexOf('favicon') > -1)
    return res.send(404);
    next();
});
app.use(cookieParser());
app.use(session({
    store: new RedisStore({
        prefix: 'session:'
    }),
    name: 'session',
    secret: 'qSFgQ4PIA90uodyDA9DUhXaqK4gH2kEc', 
    saveUninitialized: true,
    resave: true,
    //cookie: {
    //    //path: '/', // default, allow all dirs
    //    httpOnly: false, // needed for Angular, etc.
    //    secure: true, // we're using HTTPS
    //    //maxAge: null // expires on browser close
    //}
    //"cookie": {
    //    "originalMaxAge": null,
    //    "expires": null,
    //    "secure": true,
    //    "httpOnly": false,
    //    "path": "/"
    //}
}));
app.use(function(req, res, next) {
    req.session.nodejs = 'Hello from node.js!';
    req.session.js_count = (req.session.js_count)?req.session.js_count+1:1;
    res.send(JSON.stringify(req.session, null, ' '));
});
 
app.listen(8080);
