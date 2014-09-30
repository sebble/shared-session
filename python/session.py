from flask import Flask, session
import pickle
from datetime import timedelta
from uuid import uuid4
from redis import Redis
from werkzeug.datastructures import CallbackDict
from flask.sessions import SessionInterface, SessionMixin
import json

## signed sessions to match express.js
#re.sub('=+$','',base64.b64encode(hmac.new(key,msg,hashlib.sha256).digest()))
import re, base64, hmac, hashlib

def gen_sig(sid, secret):
    return re.sub('=+$','',base64.b64encode(hmac.new(secret,sid,hashlib.sha256).digest()))

def gen_sid(sid, secret):
    return 's:%s.%s'%(sid, gen_sig(sid, secret))

def check_sid(sid, secret):
    sid,sig = re.match('s:([^\.]+)\.(.+)+',sid).groups()
    return sid if gen_sig(sid, secret) == sig else False

class RedisSession(CallbackDict, SessionMixin):

    def __init__(self, initial=None, sid=None, new=False):
        def on_update(self):
            self.modified = True
        CallbackDict.__init__(self, initial, on_update)
        self.sid = sid
        self.new = new
        self.modified = False


class RedisSessionInterface(SessionInterface):
    serializer = json
    session_class = RedisSession

    def __init__(self, redis=None, prefix='session:', secret='qSFgQ4PIA90uodyDA9DUhXaqK4gH2kEc'):
        if redis is None:
            redis = Redis()
        self.redis = redis
        self.prefix = prefix
        self.secret = secret

    def generate_sid(self):
        return str(uuid4())

    def get_redis_expiration_time(self, app, session):
        if session.permanent:
            return app.permanent_session_lifetime
        return timedelta(days=1)

    def open_session(self, app, request):
        sid = request.cookies.get(app.session_cookie_name)
        if not sid:
            sid = self.generate_sid()
            sid = check_sid(sid, self.secret)
            return self.session_class(sid=sid, new=True)
        val = self.redis.get(self.prefix + sid)
        if val is not None:
            data = self.serializer.loads(val)
            return self.session_class(data, sid=sid)
        return self.session_class(sid=sid, new=True)

    def save_session(self, app, session, response):
        domain = self.get_cookie_domain(app)
        if not session:
            self.redis.delete(self.prefix + session.sid)
            if session.modified:
                response.delete_cookie(app.session_cookie_name,
                                       domain=domain)
            return
        redis_exp = self.get_redis_expiration_time(app, session)
        cookie_exp = self.get_expiration_time(app, session)
        val = self.serializer.dumps(dict(session))
        self.redis.setex(self.prefix + session.sid, val,
                         int(redis_exp.total_seconds()))
        response.set_cookie(app.session_cookie_name, gen_sid(session.sid),
                            expires=cookie_exp, httponly=False,
                            domain=domain)

app = Flask(__name__)
app.session_interface = RedisSessionInterface()
app.debug = True


@app.route('/session/python/hello')
def index():
    return json.dumps({'msg':'Hello, world!'});
    
@app.route('/session/python/session')
def sessionx():
    session['python'] = "Message from Python"
    if 'py_count' in session:
        session['py_count'] += 1
    else:
        session['py_count'] = 1
    return json.dumps(dict([(k,v) for k,v in session.iteritems()]));

@app.errorhandler(404)
def page_not_found(e):
    return json.dumps({'error':404, 'msg':"Invalid endpoint."});


if __name__ == "__main__":
    app.run()
