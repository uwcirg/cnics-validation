https://cnics.cirg.washington.edu/mci

SRC:

        git clone git@gitlab.cirg.washington.edu:cnics/mci.git

Target:

        /srv/www/$FQDN/htdocs/mci
        (legacy)

    For container-based deployments see `docs/docker-compose.md` which describes
    how to run the application with Docker Compose and mount persistent volumes.

Deploy Notes:

        chown -R cnics:www-data ~/mci
        chmod 2770 ~/mci/app/chartUploads
        chmod 2775 ~/mci/app/tmp/[cache|logs|sessions|test]

Puppet Hooks:

        app/config/database.php

TODO:

        Verify that .htaccess has no explicitly allowed users ( what is the default ? )

TESTING:

CakePHP CLI

$ mysql cnics-mci_testing < app/tests/cnics-mci_test.test_event_derived_datas.schema.sql
$ su - cnics
$ cd /srv/www/cnics.cirg.washington.edu/htdocs/mci/app

# Core ( Updated for schema changes )

app/tests/fixtures
        criteria_fixture
        event_fixture
        log_fixture
        patient_fixture
        review_fixture
        solicitation_fixture
        user_fixture

# Broken
$ ../cake/console/cake testsuite app all

# Unknown
../cake/console/cake testsuite app case controllers/
        criterias_controller
        events_controller_3rd_assign
        events_controller_addMany
        events_controller_assign
        events_controller_csv
        events_controller_edit
        events_controller_no_packet
        events_controller_review
        events_controller_screen
        events_controller_send
        events_controller
        events_controller_upload_download
        solicitations_controller
        users_controller

# Passing
../cake/console/cake testsuite app case models/criteria
        Running app case models/criteria
        Individual test case: models/criteria.test.php
        2/2 test cases complete: 9 passes.

../cake/console/cake testsuite app case models/event_assign
        Running app case models/event_assign
        Individual test case: models/event_assign.test.php
        2/2 test cases complete: 24 passes.

../cake/console/cake testsuite app case models/event_mark_no_packet
        Running app case models/event_mark_no_packet
        Individual test case: models/event_mark_no_packet.test.php
        2/2 test cases complete: 66 passes.

../cake/console/cake testsuite app case models/event_screen
        Running app case models/event_screen
        Individual test case: models/event_screen.test.php
        2/2 test cases complete: 12 passes.

../cake/console/cake testsuite app case models/user
        Running app case models/user
        Individual test case: models/user.test.php
        2/2 test cases complete: 32 passes.

# Fails
../cake/console/cake testsuite app case models/event_3rd_assign
        Running app case models/event_3rd_assign
        Individual test case: models/event_3rd_assign.test.php
        sendmail: fatal: cnics(15054): No recipient addresses found in message header
        sendmail: fatal: cnics(15054): No recipient addresses found in message header
        2/2 test cases complete: 24 passes.

../cake/console/cake testsuite app case models/event_send
        Running app case models/event_send
        Individual test case: models/event_send.test.php
        1) mismatched return value for bad events Array
        (
            [error] =>
            [sent] => 1
            [notFound] => 2
            [badEmail] => 0
            [cannotSend] => 3
            [notFoundList] =>  100 99
            [cannotSendList] =>  15 16 20
            [badEmailList] =>
        )
        Array
        (
            [error] =>
            [sent] => 0
            [notFound] => 2
            [cannotSend] => 3
            [badEmail] => 1
            [notFoundList] =>  100 99
            [cannotSendList] =>  15 16 20
            [badEmailList] =>  24
        )
         at [/srv/www/cnics.cirg.washington.edu/htdocs/mci/app/tests/cases/models/my_test_case.php line 56]
                in testSendAllErrors
                in EventSendTestCase
                in /srv/www/cnics.cirg.washington.edu/htdocs/mci/app/tests/cases/models/event_send.test.php
        FAIL->/srv/www/cnics.cirg.washington.edu/htdocs/mci/app/tests/cases/models/event_send.test.php->EventSendTestCase->testSendAllErrors->mismatched return value for bad events Array
        2/2 test cases complete: 31 passes, 10 fails, 4 exceptions.

../cake/console/cake testsuite app case models/event
        Running app case models/event
        Individual test case: models/event.test.php
        E_USER_WARNING: <span style = "color:Red;text-align:left"><b>SQL Error:</b> 1364: Field 'cardiac_cath' doesn't have a default value</span> in /srv/www/cnics.cirg.washington.edu/htdocs/mci/cake/libs/model/datasources/dbo_source.php on line 525
        2/2 test cases complete: 2469 passes, 45 fails, 227 exceptions.

../cake/console/cake testsuite app case models/patient
        Running app case models/patient
        Individual test case: models/patient.test.php
        E_NOTICE: Undefined index: CWRU in /srv/www/cnics.cirg.washington.edu/htdocs/mci/app/tests/cases/models/patient.test.php on line 20
        1) Missing CWRU site at [/srv/www/cnics.cirg.washington.edu/htdocs/mci/app/tests/cases/models/patient.test.php line 20]
                in testGetSiteArray
                in PatientTestCase
                in /srv/www/cnics.cirg.washington.edu/htdocs/mci/app/tests/cases/models/patient.test.php
        FAIL->/srv/www/cnics.cirg.washington.edu/htdocs/mci/app/tests/cases/models/patient.test.php->PatientTestCase->testGetSiteArray->Missing CWRU site at [/srv/www/cnics.cirg.washington.edu/htdocs/mci/app/tests/cases/models/patient.test.php line 20]
        1/1 test cases complete: 5 passes, 6 fails, 9 exceptions.

../cake/console/cake testsuite app case models/review
        Running app case models/review
        Individual test case: models/review.test.php
        1) Secondary cause arrays don't match Array
        (
            [Anaphlaxis] => Anaphlaxis
            [Arrhythmia] => Arrhythmia
            [Cocaine or other illicit drug induced vasospasm] => Cocaine or other illicit drug induced vasospasm
            [GI Bleed] => GI Bleed
            [MVA] => MVA
            [Overdose] => Overdose
            [Procedure related] => Procedure related
            [Sepsis/bacteremia] => Sepsis/bacteremia
            [Hypertensive urgency/emergency] => Hypertensive urgency/emergency
            [Hypoxia] => Hypoxia
            [Hypotension] => Hypotension
            [Other] => Other
        )
        Array
        (
            [Anaphlaxis] => Anaphlaxis
            [Arrhythmia] => Arrhythmia
            [Cocaine or other illicit drug induced vasospasm] => Cocaine or other illicit drug induced vasospasm
            [GI Bleed] => GI Bleed
            [MVA] => MVA
            [Overdose] => Overdose
            [Procedure related] => Procedure related
            [Sepsis/bacteremia] => Sepsis/bacteremia
            [Other] => Other
        )
         at [/srv/www/cnics.cirg.washington.edu/htdocs/mci/app/tests/cases/models/my_test_case.php line 56]
                in testSecondaryCauses
                in ReviewTestCase
                in /srv/www/cnics.cirg.washington.edu/htdocs/mci/app/tests/cases/models/review.test.php
        FAIL->/srv/www/cnics.cirg.washington.edu/htdocs/mci/app/tests/cases/models/review.test.php->ReviewTestCase->testSecondaryCauses->Secondary cause arrays don't match Array
        (
            [Anaphlaxis] => Anaphlaxis
            [Arrhythmia] => Arrhythmia
            [Cocaine or other illicit drug induced vasospasm] => Cocaine or other illicit drug induced vasospasm
            [GI Bleed] => GI Bleed
            [MVA] => MVA
            [Overdose] => Overdose
            [Procedure related] => Procedure related
            [Sepsis/bacteremia] => Sepsis/bacteremia
            [Hypertensive urgency/emergency] => Hypertensive urgency/emergency
            [Hypoxia] => Hypoxia
            [Hypotension] => Hypotension
            [Other] => Other
        )
        Array
        (
            [Anaphlaxis] => Anaphlaxis
            [Arrhythmia] => Arrhythmia
            [Cocaine or other illicit drug induced vasospasm] => Cocaine or other illicit drug induced vasospasm
            [GI Bleed] => GI Bleed
            [MVA] => MVA
            [Overdose] => Overdose
            [Procedure related] => Procedure related
            [Sepsis/bacteremia] => Sepsis/bacteremia
            [Other] => Other
        )
         at [/srv/www/cnics.cirg.washington.edu/htdocs/mci/app/tests/cases/models/my_test_case.php line 56]
        2/2 test cases complete: 4 passes, 1 fails.

DOCKER MARIADB:

        cp .env.example .env
        docker-compose up -d mariadb

DATABASE INITIALIZATION:

        # Core schema
        mysql -u$DB_USER -p$DB_PASSWORD $DB_NAME < app/config/sql/sessions.sql
        # Additional schemas are under app/config/sql/
        # Test dataset
        mysql $DB_NAME < app/tests/cnics-mci_test.test_event_derived_datas.schema.sql
## Container Setup

This repository includes a lightweight Docker configuration based on the setup used in the `asbi-screening-app` project. A basic PHP/Apache image is provided along with a docker-compose file for local development.

### Build and Run

```bash
# build the container
docker-compose build

# start the container on http://localhost:8080
docker-compose up
```

### Environment Variables

Runtime configuration is provided via `default.env` which is loaded by `docker-compose`. The following variables are available:

- `FHIR_SERVER` – URL of the FHIR server used by the application.

You may override these values by creating your own `.env` or editing `default.env`.

## Local Development

See [docs/development.md](docs/development.md) for instructions on running the application with Docker.

## Generating PDF copies of instruction files

Some pages link to `.pdf` versions of the documents under `app/webroot/files/`.
These PDFs are not stored in the repository. Run `python generate_pdfs.py` from
the project root to create them locally. The script converts modern `.docx`
files to PDF and generates placeholder PDFs for older `.doc` files.
The script requires the `python-docx` and `reportlab` packages which can be
installed with `pip install python-docx reportlab`.



## Backend API

The Flask backend under `flask_backend/` exposes REST endpoints that the React frontend fetches. Docker Compose runs a `backend` service alongside the `web` frontend service. The frontend reads the API base URL from the `VITE_API_URL` environment variable.

See [docs/separation_of_duties.md](docs/separation_of_duties.md) for details on the responsibilities of each component.
