var express = require('express'),
app = express(),
cookieParser = require('cookie-parser'),
session = require('express-session'),
RedisStore = require('connect-redis')(session);
 
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
    resave: true
}));
app.use(function(req, res, next) {
req.session.nodejs = 'Hello from node.js!';
res.send(JSON.stringify(req.session, null, ' '));
});
 
app.listen(8080);
