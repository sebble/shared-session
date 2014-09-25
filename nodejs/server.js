var express = require('express'),
app = express(),
cookieParser = require('cookie-parser'),
session = require('express-session'),
RedisStore = require('connect-redis')(session);
 
app.use(express.static(__dirname + '/public'));
app.use(function(req, res, next) {
if (req.url.indexOf('favicon') > -1)
return res.send(404);
next();
});
app.use(cookieParser());
app.use(session({
store: new RedisStore({
// this is the default prefix used by redis-session-php
//prefix: 'session:php:'
prefix: 'session:'
}),
// use the default PHP session cookie name
name: 'PHPSESSID',
secret: 'node.js rules'
}));
app.use(function(req, res, next) {
req.session.nodejs = 'Hello from node.js!';
res.send(JSON.stringify(req.session, null, ' '));
});
 
app.listen(8080);
