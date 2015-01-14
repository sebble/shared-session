# Example Redis Shared Session Servers (JS, Python, PHP)

Sample servers include:

- Python, Flask, UWSGI
- NodeJS, Express, ~~PM2~~
- PHP, ~~PHP-FPM~~

> **Note**:
> Express requires cookies to be securely signed.
> A 'secret' key is used across all PHP, Node.js, and Python servers.
> When deploying you will want to provide this key to all running servers.
