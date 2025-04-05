This is a micro-service API to feed, followers, posts etc. With PHP, Docker, SLIM 3, Composer, JWT security, you can use Postman for test this API, but first, run the project https://github.com/hedreiandrade/api-doeobem

---------------------------------------------------------------------------------------------

Run by Docker : 

Clone the project :

```
git clone https://github.com/hedreiandrade/api-doeobem-feed.git
```

Inside your project api-doeobem-feed, run :

```
docker-compose up -d --build
```

After run the attached sql file(dblLoginAPI.sql) in api-doeobem inside mysql in container(mysqlslim).

Run ```composer update``` at the root of the project, inside the container(api-doeobem-feed)

Create files and configure \api-doeobem\app\Config
- db.php
- jwt.php

Configure in app/Config/db.php :

	const DRIVER = 'mysql';
	const HOST = 'mysqlslim';
	const DATA_BASE = 'api-doeobem';
	const USER_NAME = 'root';
	const PASSWORD = '123';
	const CHARSET = 'utf8';
	const COLLATION = 'utf8_general_ci';

Test by Postman import the configuration(api-login.postman_collection.json) in attached

---------------------------------------------------------------------------------------------

Run by Wamp64/Xampp/Linux :

Clone the project, run :

```
git clone https://github.com/hedreiandrade/api-doeobem-feed.git 
```

Run ```composer update``` at the root of the project in your directory

Run the attached sql file(dblLoginAPI.sql) in api-doeobem.

Create files and configure \api-doeobem\app\Config
- db.php
- jwt.php

Test by Postman import the configuration(api-feed.postman_collection.json) in attached

---------------------------------------------------------------------------------------------

Now, run the project https://github.com/hedreiandrade/login_react_apidoeobem

---------------------------------------------------------------------------------------------

Enjoy.
