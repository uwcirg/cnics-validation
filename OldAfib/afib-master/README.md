https://cnics.cirg.washington.edu/afib

SRC:

	git clone git@gitlab.cirg.washington.edu:cnics/afib.git

Target:

	/srv/www/$FQDN/htdocs/afib

Deploy Notes:

        chown -R cnics:www-data ~/afib
        chmod 2770 ~/afib/app/chartUploads
        chmod 2775 ~/afib/app/tmp/[cache|logs|sessions|test]

Puppet Hooks:

        app/config/database.php

