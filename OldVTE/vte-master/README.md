https://cnics.cirg.washington.edu/vte

SRC:

	git clone git@gitlab.cirg.washington.edu:cnics/vte.git

Target:

	/srv/www/$FQDN/htdocs/vte

Deploy Notes:

	chown -R cnics:www-data ~/vte
	chmod 2770 ~/vte/app/chartUploads
	chmod 2775 ~/vte/app/tmp/[cache|logs|sessions|test]

Puppet Hooks:

	app/config/database.php
