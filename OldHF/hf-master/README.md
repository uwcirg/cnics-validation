Heart Failure Adjudication App

https://{TBD}.cirg.washington.edu/hf

SRC:

	git clone git@gitlab.cirg.washington.edu:cnics/hf.git

Target:

	/srv/www/$FQDN/htdocs/hf

Deploy Notes:

        chown -R cnics:www-data ~/hf
        chmod 2770 ~/hf/app/chartUploads
        chmod 2775 ~/hf/app/tmp/[cache|logs|sessions|test]

Puppet Hooks:

        app/config/database.php

