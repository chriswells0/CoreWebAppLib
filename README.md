# Core Web Application Libraries

Provides lightweight core web application libraries including a logger, database layer, and MVC framework.

To start quickly, begin with either the [Core Web Application Site](https://github.com/chriswells0/cwa-site) or [Core Web Application Blog](https://github.com/chriswells0/cwa-blog) project. Both use these libraries to build a fully functional web site.

## Features Included

* Lightweight and flexible base classes make it easy to master and extend the code.
* Uses the MVC design pattern and other web application best practices.
* Many built-in protections against common web application vulnerabilities/exploits:
  * Primarily uses prepared statements to deter SQL injection attacks.
  * Clickjacking defenses encompass multiple headers as well as JavaScript.
  * Automatic sanitization of simple variables passed to views and easy sanitization of other content to defend against cross-site scripting (XSS).
  * Cross-site request forgery (CSRF) prevention using the synchronizer token pattern for all POST requests.
  * Role-based method access is straightforward to configure and a cinch to validate with the QA Assistant.
  * User passwords are stored strongly hashed and salted.
  * Full session teardown and recreation upon login to inhibit session fixation.
  * Sessions are pinned to the user's IP and user agent string to thwart hijacking.
